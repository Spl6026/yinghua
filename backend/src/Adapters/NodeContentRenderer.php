<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use Closure;
use RuntimeException;
use Yinghua\Config;
use Yinghua\Contracts\ContentRenderer;

/** PHP 只負責把資料交給前端的 Node 發布工具，畫面模板仍留在 frontend。 */
class NodeContentRenderer implements ContentRenderer
{
    private readonly string $directory;

    public function __construct(private readonly Config $config)
    {
        $this->directory = $config->string('PUBLICATION_PATH', '/var/www/publication');
    }

    /** 正式映像建置時預先算好的版本；映像內的原始檔不會再變，不必每次請求重新雜湊。 */
    public const PRECOMPUTED_VERSION_FILE = '.renderer-version';

    public function version(): string
    {
        $source = $this->config->string('FRONTEND_SOURCE_PATH', '/var/www/frontend-source');
        $precomputed = @file_get_contents($source . '/' . self::PRECOMPUTED_VERSION_FILE);
        if (is_string($precomputed) && preg_match('/^[a-f0-9]{64}$/', trim($precomputed)) === 1) {
            return trim($precomputed);
        }

        return $this->computeVersion($source);
    }

    /** 依發布相關檔案的內容計算版本；開發環境每次重新計算，改檔後立即生效。 */
    public function computeVersion(string $source): string
    {
        // 發布結果由這些目錄的檔案決定：發布腳本、頁面與共用模板、SEO 目錄，以及前端資產。
        // 發布頁引用的 CSS／JS 網址帶有依 assets/ 內容算出的版本號，所以任何資產改動都要重新發布；
        // 字型檔名本身就是版本，不納入。逐一掃描整個目錄，新增檔案時不必再回來維護清單。
        $files = [];
        foreach (['scripts', 'pages', 'templates', 'resources', 'assets'] as $directory) {
            array_push($files, ...self::filesUnder($source . '/' . $directory));
        }
        $files = array_values(array_filter(
            $files,
            static fn(string $path): bool => !str_contains($path, '/assets/fonts/'),
        ));
        $hash = hash_init('sha256');
        // 這兩個檔案決定搜尋索引的內容；納入版本雜湊後，規則一改就會重建同一版本的 HTML 與 MariaDB 搜尋索引。
        $files[] = dirname(__DIR__) . '/DTO/SearchDocument.php';
        $files[] = dirname(__DIR__) . '/Policies/AttractionContentPolicy.php';
        foreach ($files as $path) {
            if (!hash_update_file($hash, $path)) {
                throw new RuntimeException('Cannot read publication templates.');
            }
        }

        return hash_final($hash);
    }

    /**
     * 只記錄 Node 錯誤的類型；錯誤訊息可能夾帶頁面內容，一律不記，
     * 唯一例外是發布腳本自己丟出的「模板缺少標記」，訊息只有標記本身，最需要讓開發者看到。
     */
    private static function renderError(string $stderr): string
    {
        if (preg_match('/^(\w*Error): (Publication template is missing the expected markup: .{0,100})$/mu', $stderr, $match) === 1) {
            return 'error=' . $match[1] . ' detail="' . addcslashes($match[2], "\"\\") . '"';
        }

        return preg_match('/^(\w*Error)\b/mu', $stderr, $match) === 1 ? 'error=' . $match[1] : 'error=unknown';
    }

    /** @return list<string> 依路徑排序，確保雜湊結果與檔案系統的列舉順序無關。 */
    private static function filesUnder(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new RuntimeException('Cannot read publication templates.');
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /** @param array<string,mixed> $snapshot */
    public function render(array $snapshot): void
    {
        $pipes = [];
        $process = proc_open(
            [
                '/usr/local/bin/node',
                $this->config->string('FRONTEND_SOURCE_PATH', '/var/www/frontend-source') . '/scripts/publish.mjs',
                $this->directory,
            ],
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
        $errors = '';
        do {
            // 讀完子程序的輸出管道以免卡住，但不把內容或秘密寫進日誌。
            stream_get_contents($pipes[1]);
            $errors = substr($errors . (string) stream_get_contents($pipes[2]), 0, 65536);
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
            error_log('publication_render ' . ($state['running'] ? 'timeout' : self::renderError($errors)));
            throw new RuntimeException('Publication rendering failed.');
        }
    }

    /** @return array<string,mixed>|null */
    public function current(): ?array
    {
        // Node 在另一個程序裡原子地切換 symlink，所以這裡要清掉 FPM 的 realpath cache。
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
