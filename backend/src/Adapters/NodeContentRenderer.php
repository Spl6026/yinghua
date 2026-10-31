<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use Closure;
use RuntimeException;
use Yinghua\Config;
use Yinghua\Contracts\ContentRenderer;

/** [E-12／E-18] PHP 只交付資料給前端 Node 工具，畫面模板留在 frontend。 */
class NodeContentRenderer implements ContentRenderer
{
    private readonly string $directory;

    public function __construct(private readonly Config $config)
    {
        $this->directory = $config->string('PUBLICATION_PATH', '/var/www/publication');
    }

    public function version(): string
    {
        $source = $this->config->string('FRONTEND_SOURCE_PATH', '/var/www/frontend-source');
        $files = [$source . '/scripts/publish.mjs', $source . '/assets/js/core/opening-hours.js', ...glob($source . '/templates/*.html') ?: [],
            $source . '/pages/index.html',$source . '/assets/js/core/home-view.js',$source . '/pages/explore.html',$source . '/pages/food.html',$source . '/pages/museum.html',
            $source . '/assets/js/core/film-view.js',$source . '/pages/cinema.html',...glob($source . '/pages/films/*.html') ?: [],
            $source . '/scripts/public-news.mjs',$source . '/assets/js/core/content-view.js',$source . '/assets/js/core/news-view.js',
            $source . '/pages/events.html',$source . '/pages/activities.html',$source . '/pages/city-news.html',
            ...glob($source . '/pages/news/*.html') ?: [],
            $source . '/scripts/public-offers.mjs',$source . '/assets/js/core/offer-view.js',
            $source . '/assets/js/core/route-view.js',
            $source . '/pages/offers.html',...glob($source . '/pages/offers/*.html') ?: [],
            $source . '/scripts/seo-head.mjs',$source . '/scripts/structured-data.mjs',$source . '/assets/js/core/structured-data.js',$source . '/scripts/crawler-index.mjs',$source . '/assets/js/core/seo-resolver.js',$source . '/resources/seo-pages.json',
            $source . '/scripts/policy-pages.mjs',
            ...glob($source . '/pages/*.html') ?: []];
        $hash = hash_init('sha256');
        // [E-35] 搜尋投影規則更新亦重建同版HTML與MariaDB索引。
        $files[] = dirname(__DIR__) . '/DTO/SearchDocument.php';
        foreach ($files as $path) {
            if (!hash_update_file($hash, $path)) {
                throw new RuntimeException('Cannot read publication templates.');
            }
        }

        return hash_final($hash);
    }

    /** @param array<string,mixed> $snapshot */
    public function render(array $snapshot): void
    {
        $pipes = [];
        $process = proc_open(
            ['/usr/local/bin/node', $this->config->string('FRONTEND_SOURCE_PATH', '/var/www/frontend-source') . '/scripts/publish.mjs', $this->directory],
            [0 => ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Renderer unavailable.');
        }
        fwrite($pipes[0], json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $deadline = microtime(true) + 10;
        do {
            // 排空子程序管道但不將內容／秘密寫入日誌。
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            $state = proc_get_status($process);
            if (!$state['running']) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        if ($state['running']) {
            proc_terminate($process, 9);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($state['running'] || ($state['exitcode'] !== 0 && $exit !== 0)) {
            throw new RuntimeException('Publication rendering failed.');
        }
    }

    /** @return array<string,mixed>|null */
    public function current(): ?array
    {
        // Node 在另一程序原子切換 symlink；FPM 的 realpath cache 必須同步失效。
        clearstatcache(true);
        $path = $this->directory . '/current/.snapshot.json';
        if (!is_file($path)) {
            return null;
        }
        $data = file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException('Cannot read publication.');
        }
        $value = json_decode($data, true, 128, JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new RuntimeException('Invalid publication.');
        }
        $result = [];
        foreach ($value as $key => $entry) {
            if (is_string($key)) {
                $result[$key] = $entry;
            }
        }

        return $result;
    }

    /** @template T
     * @param Closure():T $callback
     * @return T
     */
    public function exclusive(Closure $callback): mixed
    {
        $lock = fopen($this->directory . '/.publish.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot acquire publication lock.');
        }
        try {
            $deadline = microtime(true) + 5;
            do {
                if (flock($lock, LOCK_EX | LOCK_NB)) {
                    return $callback();
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            throw new RuntimeException('Publication is busy.');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
