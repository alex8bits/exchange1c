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
use Bigperson\Exchange1C\Interfaces\AuthServiceInterface;
use Bigperson\Exchange1C\Services\CatalogService;
use Bigperson\Exchange1C\Services\CategoryService;
use Bigperson\Exchange1C\Services\FileLoaderService;
use Bigperson\Exchange1C\Services\OfferService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Tests\TestCase;

class CatalogServiceTest extends TestCase
{
    public function testCheckAuth(): void
    {
        $config = $this->createMock(Config::class);
        $request = $this->createMock(Request::class);
        $loader = $this->createMock(FileLoaderService::class);
        $auth = $this->createMock(AuthServiceInterface::class);
        $auth->method('checkAuth')
            ->willReturn('success');
        $category = $this->createMock(CategoryService::class);
        $offer = $this->createMock(OfferService::class);
        $service = new CatalogService($config, $auth, $loader, $category, $offer);

        $this->assertEquals('success', $service->checkauth($request));
    }

    public function testInit(): void
    {
        $config = $this->createMock(Config::class);
        $request = $this->createMock(Request::class);
        $loader = $this->createMock(FileLoaderService::class);
        $auth = $this->createMock(AuthServiceInterface::class);
        $auth->method('auth');
        $category = $this->createMock(CategoryService::class);
        $offer = $this->createMock(OfferService::class);
        $service = new CatalogService($config, $auth, $loader, $category, $offer);

        $this->assertIsString($service->init($request));
    }

    public function testFile(): void
    {
        $config = $this->createMock(Config::class);

        $request = new Request();
        $request->query->set('filename', 'import.xml');

        $loader = $this->createMock(FileLoaderService::class);
        $auth = $this->createMock(AuthServiceInterface::class);
        $auth->method('auth');
        $category = $this->createMock(CategoryService::class);
        $offer = $this->createMock(OfferService::class);
        $service = new CatalogService($config, $auth, $loader, $category, $offer);
        $loader->method('load')
            ->willReturn('success');

        $this->assertEquals('success', $service->file($request));
    }

    public function testImportImport(): void
    {
        $this->expectNotToPerformAssertions();
        $config = $this->createMock(Config::class);
        $request = $this->createMock(Request::class);
        $request->method('get')
            ->with('filename')
            ->willReturn('import.xml');
        $session = $this->createMock(SessionInterface::class);
        $session->method('getId')
            ->willReturn('1231243');
        $request->method('getSession')
            ->willReturn($session);
        $loader = $this->createMock(FileLoaderService::class);
        $auth = $this->createMock(AuthServiceInterface::class);
        $auth->method('auth');
        $category = $this->createMock(CategoryService::class);
        $category->method('import');
        $offer = $this->createMock(OfferService::class);
        $service = new CatalogService($config, $auth, $loader, $category, $offer);

        $service->import($request, 'import.xml');
    }

    public function testImportOffers(): void
    {
        $this->expectNotToPerformAssertions();

        $config = $this->createMock(Config::class);
        $request = $this->createMock(Request::class);
        $request->method('get')
            ->with('filename')
            ->willReturn('offers.xml');
        $session = $this->createMock(SessionInterface::class);
        $session->method('getId')
            ->willReturn('1231243');
        $request->method('getSession')
            ->willReturn($session);
        $loader = $this->createMock(FileLoaderService::class);
        $auth = $this->createMock(AuthServiceInterface::class);
        $auth->method('auth');
        $category = $this->createMock(CategoryService::class);
        $offer = $this->createMock(OfferService::class);
        $offer->method('import');
        $service = new CatalogService($config, $auth, $loader, $category, $offer);

        $service->import($request, 'offers.xml');
    }
}
