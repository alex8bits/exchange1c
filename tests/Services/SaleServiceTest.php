<?php
/**
 * This file is part of bigperson/exchange1c package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Tests\Services;

use Bigperson\Exchange1C\Config;
use Bigperson\Exchange1C\Interfaces\DocumentInterface;
use Bigperson\Exchange1C\Interfaces\ExportFieldsInterface;
use Bigperson\Exchange1C\Interfaces\ModelBuilderInterface;
use Bigperson\Exchange1C\Interfaces\PartnerInterface;
use Bigperson\Exchange1C\Services\SaleService;
use DOMDocument;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SaleServiceTest extends TestCase
{
    public function testExportKeepsOnlyWindows1251Text(): void
    {
        $dirty = "Ґанок № 5 — «тут» <b> & € Мар\u{02BC}яна👍❤️\u{1F937}\u{200D}\u{2642}\u{FE0F}\x01";
        $clean = "Ґанок № 5 — «тут» &lt;b&gt; &amp; € Мар'яна";

        $partner = $this->createMock(PartnerInterface::class);
        $partner->method('getExportFields1c')->willReturn(['Адрес' => ['Представление' => $dirty]]);

        $offer = $this->createMock(ExportFieldsInterface::class);
        $offer->method('getExportFields1c')->willReturn(['Наименование' => $dirty]);

        $document = $this->createMock(DocumentInterface::class);
        $document->method('getExportFields1c')->willReturn(['Комментарий' => $dirty]);
        $document->method('getPartner1c')->willReturn($partner);
        $document->method('getOffers1c')->willReturn([$offer]);
        $document->method('getRequisites1c')->willReturn([['name' => 'Примітка', 'value' => $dirty]]);

        $service = new SaleService($this->createMock(Config::class), $this->createMock(ModelBuilderInterface::class));
        $xml = (string) (new ReflectionMethod($service, 'buildOrdersXml'))->invoke($service, [$document]);

        $this->assertSame(4, substr_count($xml, $clean));
        $this->assertTrue((new DOMDocument())->loadXML($xml));
        // Nothing left for a text re-encoding to replace or lose.
        $this->assertSame($xml, mb_convert_encoding(mb_convert_encoding($xml, 'Windows-1251', 'UTF-8'), 'UTF-8', 'Windows-1251'));
    }
}
