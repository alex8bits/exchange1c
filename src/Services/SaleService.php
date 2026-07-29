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
use Bigperson\Exchange1C\Interfaces\DocumentInterface;
use Bigperson\Exchange1C\Interfaces\ModelBuilderInterface;
use DOMDocument;
use DOMElement;
use Symfony\Component\HttpFoundation\Request;

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
     * @param array<string, string> $fields
     */
    private function appendFields(DOMDocument $dom, DOMElement $parent, array $fields): void
    {
        foreach ($fields as $tag => $value) {
            $el = $dom->createElement((string) $tag);
            $el->appendChild($dom->createTextNode((string) $value));
            $parent->appendChild($el);
        }
    }
}
