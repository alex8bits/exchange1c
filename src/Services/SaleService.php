<?php
/**
 * This file is part of bigperson/exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Bigperson\Exchange1C\Services;

use Bigperson\Exchange1C\Config;
use Bigperson\Exchange1C\Exceptions\Exchange1CException;
use Bigperson\Exchange1C\Interfaces\DocumentInterface;
use Bigperson\Exchange1C\Interfaces\ModelBuilderInterface;
use DOMDocument;
use DOMElement;
use Symfony\Component\HttpFoundation\Request;
use Zenwalker\CommerceML\CommerceML;
use Zenwalker\CommerceML\Model\Document as CommerceMlDocument;

/**
 * Class SaleService
 *
 * Implements the "type=sale" side of the CommerceML exchange: 1C pulls new
 * website orders (mode=query) and then acknowledges receipt (mode=success).
 * http://v8.1c.ru/edi/edi_stnd/90/92.htm
 */
class SaleService
{
    private const SESSION_PENDING_KEY = 'exchange1c_pending_documents';

    private Config $config;

    private ModelBuilderInterface $modelBuilder;

    public function __construct(Config $config, ModelBuilderInterface $modelBuilder)
    {
        $this->config = $config;
        $this->modelBuilder = $modelBuilder;
    }

    /**
     * Builds orders.xml for every not-yet-exported order and remembers
     * which ones were just sent (by primary key — the documents themselves
     * aren't kept in the session, since round-tripping arbitrary host-app
     * models through session serialization isn't reliable), so success()
     * knows exactly what to mark as exported.
     */
    public function query(Request $request, int $fromId = 0): string
    {
        $documentClass = $this->getDocumentClass();
        $documents = $documentClass::findDocuments1c($fromId);

        if ($request->hasSession()) {
            $ids = array_map(
                static fn (DocumentInterface $document) => $document->getPrimaryKey(),
                $documents
            );
            $request->getSession()->set(self::SESSION_PENDING_KEY, $ids);
        }

        return $this->buildOrdersXml($documents);
    }

    /**
     * Marks the documents returned by the last query() in this session as
     * exported. If success() is never called (e.g. 1C lost connection),
     * nothing is marked — the same orders are simply offered again on the
     * next query(), which 1C can safely ignore since it already knows them.
     */
    public function success(Request $request): string
    {
        if ($request->hasSession()) {
            $session = $request->getSession();
            $pendingIds = $session->get(self::SESSION_PENDING_KEY, []);

            if (!empty($pendingIds)) {
                $documentClass = $this->getDocumentClass();
                foreach ($documentClass::findDocuments1c() as $document) {
                    if (in_array($document->getPrimaryKey(), $pendingIds, true)) {
                        $document->markExported1c();
                    }
                }
            }

            $session->remove(self::SESSION_PENDING_KEY);
        }

        return "success\n";
    }

    /**
     * 1C also pushes order updates back to the site: type=sale&mode=file
     * uploads a CommerceML orders file (handled generically by
     * CatalogService::file(), same as catalog uploads — it's just a byte
     * sink keyed by filename), then type=sale&mode=import processes it —
     * this is that processing step. Matches each incoming <Документ> to a
     * local order by the Ид it was originally exported with and hands the
     * raw fields/requisites to the host app to interpret (status vocabulary
     * is 1C-instance-specific, so this package doesn't guess at it).
     *
     * @throws Exchange1CException
     */
    public function importUpdates(string $filename): void
    {
        $filename = basename($filename);
        $filePath = $this->config->getFullPath($filename);
        if (!is_file($filePath)) {
            throw new Exchange1CException("File not found for import: {$filePath}");
        }

        $commerce = new CommerceML();
        $commerce->loadOrdersXml($filePath);

        $documentClass = $this->getDocumentClass();
        foreach ($commerce->order->documents as $incoming) {
            $localDocument = $documentClass::findByExternalId1c((string) $incoming->id);
            if ($localDocument === null) {
                continue;
            }

            $localDocument->applyIncomingUpdate1c(
                $this->extractScalarFields($incoming),
                $this->extractRequisites($incoming)
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function extractScalarFields(CommerceMlDocument $document): array
    {
        $fields = [];
        foreach ($document->xml->children() as $tag => $node) {
            // Skip container elements (Контрагенты, Товары, ЗначенияРеквизитов, ...) —
            // only plain leaf tags (Ид, Номер, Дата, Время, Сумма, ...) are meaningful here.
            if ($node->count() === 0) {
                $fields[$tag] = trim((string) $node);
            }
        }

        return $fields;
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    private function extractRequisites(CommerceMlDocument $document): array
    {
        $requisites = [];
        foreach ($document->getRequisites() ?? [] as $requisite) {
            $requisites[] = ['name' => (string) $requisite->name, 'value' => (string) $requisite->value];
        }

        return $requisites;
    }

    private function getDocumentClass(): DocumentInterface
    {
        return $this->modelBuilder->getInterfaceClass($this->config, DocumentInterface::class);
    }

    /**
     * @param DocumentInterface[] $documents
     */
    private function buildOrdersXml(array $documents): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('КоммерческаяИнформация');
        $root->setAttribute('ВерсияСхемы', '2.08');
        $root->setAttribute('ДатаФормирования', date('c'));
        $dom->appendChild($root);

        foreach ($documents as $document) {
            $root->appendChild($this->buildDocumentElement($dom, $document));
        }

        return (string) $dom->saveXML();
    }

    private function buildDocumentElement(DOMDocument $dom, DocumentInterface $document): DOMElement
    {
        $documentEl = $dom->createElement('Документ');
        $this->appendFields($dom, $documentEl, $document->getExportFields1c());

        $partnersEl = $dom->createElement('Контрагенты');
        $partnerEl = $dom->createElement('Контрагент');
        $this->appendFields($dom, $partnerEl, $document->getPartner1c()->getExportFields1c());
        $partnersEl->appendChild($partnerEl);
        $documentEl->appendChild($partnersEl);

        $goodsEl = $dom->createElement('Товары');
        foreach ($document->getOffers1c() as $offer) {
            $offerEl = $dom->createElement('Товар');
            $this->appendFields($dom, $offerEl, $offer->getExportFields1c());
            $goodsEl->appendChild($offerEl);
        }
        $documentEl->appendChild($goodsEl);

        $requisites = $document->getRequisites1c();
        if (!empty($requisites)) {
            $requisitesEl = $dom->createElement('ЗначенияРеквизитов');
            foreach ($requisites as $requisite) {
                $requisitesEl->appendChild($this->buildRequisiteElement($dom, $requisite));
            }
            $documentEl->appendChild($requisitesEl);
        }

        return $documentEl;
    }

    /**
     * @param array{name: string, value: string} $requisite
     */
    private function buildRequisiteElement(DOMDocument $dom, array $requisite): DOMElement
    {
        $requisiteEl = $dom->createElement('ЗначениеРеквизита');

        $nameEl = $dom->createElement('Наименование');
        $nameEl->appendChild($dom->createTextNode((string) $requisite['name']));
        $requisiteEl->appendChild($nameEl);

        $valueEl = $dom->createElement('Значение');
        $valueEl->appendChild($dom->createTextNode((string) $requisite['value']));
        $requisiteEl->appendChild($valueEl);

        return $requisiteEl;
    }

    /**
     * Builds child elements from a tag => value map.
     *
     * $value is normally a scalar and becomes a plain text node (unchanged
     * behaviour). CommerceML also has a handful of tags — Телефон, Email,
     * Адрес, АдресРегистрации — that 1C expects as a container with a
     * nested <Представление> (and, for phones/addresses, further optional
     * sub-fields). To support those without a second code path, $value may
     * also be an array; in that case it is expanded recursively as child
     * elements instead of a text node, e.g.:
     *
     *   ['Телефон' => ['Представление' => '+380...']]
     *   // => <Телефон><Представление>+380...</Представление></Телефон>
     *
     * @param array<string, mixed> $fields
     */
    private function appendFields(DOMDocument $dom, DOMElement $parent, array $fields): void
    {
        foreach ($fields as $tag => $value) {
            $el = $dom->createElement((string) $tag);

            if (is_array($value)) {
                $this->appendFields($dom, $el, $value);
            } else {
                $el->appendChild($dom->createTextNode((string) $value));
            }

            $parent->appendChild($el);
        }
    }
}
