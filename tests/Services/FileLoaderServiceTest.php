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
use Tests\TestCase;

class FileLoaderServiceTest extends TestCase
{
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

        $fileLoader->load($request->query->get('filename'));
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

        $fileLoader->load($request->query->get('filename'));
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
        $fileLoader->load($request->query->get('filename'));
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
        $fileLoader->load($request->query->get('filename'));
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
