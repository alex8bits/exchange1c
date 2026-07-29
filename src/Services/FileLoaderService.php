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
use Symfony\Component\HttpFoundation\Request;
use ZipArchive;

/**
 * Class FileLoaderService.
 */
class FileLoaderService
{
    private const SESSION_STARTED_KEY = 'exchange1c_upload_started';

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
     * A single logical file (e.g. offers0_1.xml) can arrive as several
     * sequential POSTs to the same filename once it's bigger than
     * `file_limit` — 1C keeps calling mode=file until it has sent
     * everything, then calls mode=import once. So: truncate on the first
     * chunk of a given filename in this session, append on every chunk
     * after that. Without session-scoping this, a stale file left over
     * from an earlier aborted session would silently get appended to
     * instead of replaced.
     *
     * @param string $filename
     * @return string
     */
    public function load(string $filename, Request $request): string
    {
        $filename = basename($filename);

        $filePath = $this->config->getFullPath($filename);
        if (str_contains($filename, 'orders')) {
            throw new LogicException('This method is not released');
        }
        $directory = dirname($filePath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        $sessionKey = self::SESSION_STARTED_KEY . ':' . $filename;
        $isFirstChunk = !$session || !$session->has($sessionKey);

        $f = fopen($filePath, $isFirstChunk ? 'wb' : 'ab');
        fwrite($f, $request->getContent());
        fclose($f);
        $session?->set($sessionKey, true);

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
     * Intentionally a no-op: import_dir holds classifier.xml written by a
     * *previous* session's category import, which a later offers-only
     * session still needs to read (see CategoryService::import()). Wiping
     * the whole directory on every mode=init would delete it. Per-file
     * truncation is instead handled in load() via the session.
     */
    public function clearImportDirectory(): void
    {
    }
}
