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
use Bigperson\Exchange1C\Services\FileLoaderService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\TestCase;

class FileLoaderServiceTest extends TestCase
{
    public function testLoadAppendsChunksWithinSameSession(): void
    {
        $configValues = [
            'import_dir' => __DIR__.DIRECTORY_SEPARATOR.'1c_exchangetest',
        ];
        $config = new Config($configValues);
        if (is_dir($config->getImportDir())) {
            $this->recurseRmdir($config->getImportDir());
        }

        $session = new Session(new MockArraySessionStorage());
        $fileLoader = new FileLoaderService($config);

        $firstChunkRequest = Request::create('/?filename=test.xml', 'POST', [], [], [], [], 'first-');
        $firstChunkRequest->query->set('filename', 'test.xml');
        $firstChunkRequest->setSession($session);
        $fileLoader->load($firstChunkRequest->query->get('filename'), $firstChunkRequest);

        $secondChunkRequest = Request::create('/?filename=test.xml', 'POST', [], [], [], [], 'second');
        $secondChunkRequest->query->set('filename', 'test.xml');
        $secondChunkRequest->setSession($session);
        $fileLoader->load($secondChunkRequest->query->get('filename'), $secondChunkRequest);

        $this->assertSame('first-second', file_get_contents($config->getFullPath('test.xml')));
        $this->recurseRmdir($config->getImportDir());
    }

    public function testLoadTruncatesOnFirstChunkOfNewSession(): void
    {
        $configValues = [
            'import_dir' => __DIR__.DIRECTORY_SEPARATOR.'1c_exchangetest',
        ];
        $config = new Config($configValues);
        if (is_dir($config->getImportDir())) {
            $this->recurseRmdir($config->getImportDir());
        }

        $fileLoader = new FileLoaderService($config);

        $staleRequest = Request::create('/?filename=test.xml', 'POST', [], [], [], [], 'stale-leftover-bytes');
        $staleRequest->query->set('filename', 'test.xml');
        $staleRequest->setSession(new Session(new MockArraySessionStorage()));
        $fileLoader->load($staleRequest->query->get('filename'), $staleRequest);

        $newSessionRequest = Request::create('/?filename=test.xml', 'POST', [], [], [], [], 'fresh');
        $newSessionRequest->query->set('filename', 'test.xml');
        $newSessionRequest->setSession(new Session(new MockArraySessionStorage()));
        $fileLoader->load($newSessionRequest->query->get('filename'), $newSessionRequest);

        $this->assertSame('fresh', file_get_contents($config->getFullPath('test.xml')));
        $this->recurseRmdir($config->getImportDir());
    }

    public function testLoad(): void
    {
        $configValues = [
            'import_dir' => __DIR__.DIRECTORY_SEPARATOR.'1c_exchangetest',
        ];
        $config = new Config($configValues);

        $request = new Request();
        $request->query->set('filename', 'test.xml');
        $fileLoader = new FileLoaderService($config);
        if (is_dir($config->getImportDir())) {
            $this->recurseRmdir($config->getImportDir());
        }

        $fileLoader->load($request->query->get('filename'), $request);
        $this->assertFileExists($config->getFullPath('test.xml'));
        $this->recurseRmdir($config->getImportDir());
    }

    public function testLoadZip(): void
    {
        $configValues = [
            'import_dir' => __DIR__.DIRECTORY_SEPARATOR.'1c_exchangetest',
            'use_zip'    => true,
        ];
        $config = new Config($configValues);

        $request = new Request();
        $request->query->set('filename', 'test.zip');
        $fileLoader = new FileLoaderService($config);
        if (is_dir($config->getImportDir())) {
            $this->recurseRmdir($config->getImportDir());
        }

        $fileLoader->load($request->query->get('filename'), $request);
        $this->assertFileDoesNotExist($config->getFullPath('test.zip'));
        $this->recurseRmdir($config->getImportDir());
    }

    public function testClearImportDirectory(): void
    {
        $configValues = [
            'import_dir' => __DIR__.DIRECTORY_SEPARATOR.'1c_exchangetest',
        ];
        $config = new Config($configValues);
        $request = new Request();
        $request->query->set('filename', 'test.xml');
        $fileLoader = new FileLoaderService($config);
        $fileLoader->load($request->query->get('filename'), $request);
        $fileLoader->clearImportDirectory();
        $this->assertFileExists($config->getFullPath('test.xml'));
        $this->recurseRmdir($config->getImportDir());
    }

    public function testException(): void
    {
        $configValues = [];
        $config = new Config($configValues);
        $this->expectException(\LogicException::class);

        $request = new Request();
        $request->query->set('filename', 'orders.xml');
        $fileLoader = new FileLoaderService($config);
        $fileLoader->load($request->query->get('filename'), $request);
    }

    /**
     * @param $dir
     *
     * @return void
     */
    private function recurseRmdir($dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recurseRmdir("$dir/$file") : unlink("$dir/$file");
        }

        rmdir($dir);
    }
}
