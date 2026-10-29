<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use DirectoryIterator;
use RuntimeException;
use Yinghua\Config;

/** [E-29] 只處理既有平面 UUID 圖檔儲存；拒絕遺失、連結、未知檔案及暫存殘留。 */
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
