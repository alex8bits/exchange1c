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
use LogicException;
use RuntimeException;
use ZipArchive;

/**
 * Class FileLoaderService.
 */
class FileLoaderService
{
    private Config $config;

    /**
     * FileLoaderService constructor.
     *
     * @param Config  $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $filename
     * @return string
     */
    public function load(string $filename): string
    {
        $filename = basename($filename);

        $filePath = $this->config->getFullPath($filename);
        if (str_contains($filename, 'orders')) {
            throw new LogicException('This method is not released');
        }
        if (file_exists($filePath)) {
            return "success\n";
        }
        $directory = dirname($filePath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }
        $f = fopen($filePath, 'ab+');
        fwrite($f, file_get_contents('php://input'));
        fclose($f);
        if ($this->config->isUseZip()) {
            if (!extension_loaded('zip')) {
                throw new RuntimeException('Zip extension is required for file loading');
            }
            $zip = new ZipArchive();
            $zip->open($filePath);
            $zip->extractTo($this->config->getImportDir());
            $zip->close();
            unlink($filePath);
        }

        return "success\n";
    }

    /**
     * Delete all files from tmp directory.
     */
    public function clearImportDirectory(): void
    {
        /*$tmp_files = glob($this->config->getImportDir().DIRECTORY_SEPARATOR.'*.*');
        if (is_array($tmp_files)) {
            foreach ($tmp_files as $v) {
                unlink($v);
            }
        }*/
    }
}
