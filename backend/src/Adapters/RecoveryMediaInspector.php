<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use DirectoryIterator;
use RuntimeException;
use Yinghua\Config;

/** 復原時檢查圖檔目錄：只接受平面的 UUID 檔名，遇到遺失、符號連結、未知檔案或暫存殘留都拒絕。 */
readonly class RecoveryMediaInspector
{
    public function __construct(private Config $config) {}

    /** @param list<string> $required
     * @return array<string,string>
     */
    public function inspect(array $required): array
    {
        $directory = $this->config->string('MEDIA_PATH', '/var/www/media');
        $files = [];
        foreach (new DirectoryIterator($directory) as $file) {
            if ($file->isDot()) {
                continue;
            }
            if (!$file->isFile() || $file->isLink() || !preg_match('/^[a-f0-9-]{36}\.(jpg|png|webp)$/D', $file->getFilename())) {
                throw new RuntimeException('Unexpected media storage entry.');
            }
            $hash = hash_file('sha256', $file->getPathname());
            if ($hash === false) {
                throw new RuntimeException('Unreadable media.');
            }
            $files[$file->getFilename()] = $hash;
        }
        foreach ($required as $key) {
            if (!isset($files[$key])) {
                throw new RuntimeException('Referenced media missing.');
            }
        }
        ksort($files);

        return $files;
    }
}
