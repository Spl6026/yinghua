<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use Throwable;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\ImportEnvelope;
use Yinghua\DTO\UploadedImage;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Exceptions\ContentConflict;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Models\MediaModule;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Services\ContentImporter;
use Yinghua\Services\MediaManagement;
use Yinghua\Services\NewsManagement;
use Yinghua\Support\Secrets;

/** [E-17／26] 受信 Docker 維運 CLI；不建立可公開呼叫的 HTTP 匯入入口。 */
class PublicContentCommands
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly ContentImporter $importer,
        private readonly NewsManagement $news,
        private readonly MediaManagement $media,
        private readonly Secrets $secrets,
    ) {}

    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        $requestId = bin2hex(random_bytes(16));
        $client = new ClientContext('cli', $this->secrets->keyed('ip', 'cli'), $this->secrets->keyed('agent', 'public-content-cli'), $requestId);
        try {
            $actor = $this->root();
            $command = $arguments[1] ?? '';
            $options = $this->options(array_slice($arguments, 2), $command === 'media:import'
                ? ['module','file','alt','source','license'] : ['module','file','apply','dry-run']);
            $module = $options['module'] ?? '';
            if ($module !== 'news') {
                throw ApplicationError::invalid('module', '目前此入口支援 news。');
            }
            $path = $this->localFile($options['file'] ?? '');
            if ($command === 'media:import') {
                $size = filesize($path);
                if ($size === false) {
                    throw ApplicationError::invalid('file', '無法讀取檔案。');
                }
                $asset = $this->media->upload(
                    $actor,
                    new UploadedImage($path, UPLOAD_ERR_OK, $size),
                    'create',
                    $options['alt'] ?? '',
                    $options['source'] ?? '',
                    $options['license'] ?? '',
                    $client,
                    MediaModule::News,
                );
                $result = ['status' => 'stored','requestId' => $requestId,'media' => $asset->toArray()];
            } else {
                if (isset($options['apply'], $options['dry-run'])) {
                    throw ApplicationError::invalid('apply', 'apply 與 dry-run 不可同時提供。');
                }
                $text = file_get_contents($path, false, null, 0, 2 * 1024 * 1024 + 1);
                if ($text === false) {
                    throw ApplicationError::invalid('file', '無法讀取 JSON。');
                }
                $text = str_starts_with($text, "\xEF\xBB\xBF") ? substr($text, 3) : $text;
                $result = $this->importer->run($actor, ImportEnvelope::fromJson($text), $this->news, isset($options['apply']), $client);
            }
            fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n");
            return ($result['status'] ?? '') === 'saved_publication_pending' ? 4 : 0;
        } catch (ApplicationError|ContentConflict $error) {
            $conflict = $error instanceof ContentConflict || $error->errorCode === 'CONFLICT';
            fwrite(STDOUT, json_encode(['status' => $conflict ? 'conflict' : 'validation_failed',
                'message' => $error instanceof ApplicationError ? $error->getMessage() : '內容識別或網址衝突。',
                'fieldErrors' => $error instanceof ApplicationError ? $error->fieldErrors : [],'requestId' => $requestId], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n");
            return $conflict ? 3 : 2;
        } catch (Throwable $error) {
            error_log('content-cli request=' . $requestId . ' exception=' . $error::class);
            fwrite(STDOUT, json_encode(['status' => 'error','message' => '操作失敗，請以 requestId 查閱系統紀錄。','requestId' => $requestId], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n");
            return 1;
        }
    }

    private function root(): Account
    {
        $actor = $this->accounts->byLogin(IdentityKind::Admin, 'admin');
        if ($actor === null || !$actor->isRoot || !$actor->isActive()) {
            throw ApplicationError::forbidden();
        }
        return $actor;
    }

    /** @param list<string> $arguments
     * @param list<string> $allowed
     * @return array<string,string>
     */
    private function options(array $arguments, array $allowed): array
    {
        $options = [];
        foreach ($arguments as $argument) {
            if (!preg_match('/^--([a-z-]+)(?:=(.*))?$/sD', $argument, $match)) {
                throw ApplicationError::invalid('options', '參數須為 --name=value。');
            }
            $key = $match[1];
            if (!in_array($key, $allowed, true) || array_key_exists($key, $options)
                || (in_array($key, ['apply','dry-run'], true) ? isset($match[2]) : !isset($match[2]))) {
                throw ApplicationError::invalid('options', '參數不支援、重複或缺少值。');
            }
            $options[$key] = $match[2] ?? '';
        }
        return $options;
    }

    private function localFile(string $path): string
    {
        $real = realpath($path);
        if ($real === false || !str_starts_with($real, '/tmp/') || !is_file($real) || !is_readable($real)) {
            throw ApplicationError::invalid('file', '請先將可讀取的輸入檔複製到容器 /tmp/。');
        }
        return $real;
    }
}
