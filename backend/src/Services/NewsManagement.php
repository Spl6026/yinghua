<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Clock;
use Yinghua\Contracts\ContentImportService;
use Yinghua\Contracts\RichTextSanitizer;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\ImportItem;
use Yinghua\DTO\NewsInput;
use Yinghua\DTO\PreparedImport;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\MediaModule;
use Yinghua\Policies\BodyImages;
use Yinghua\Policies\ContentCover;
use Yinghua\Policies\ImportVersion;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\MediaRepository;
use Yinghua\Repositories\NewsRepository;

/** [S：功能規格 9／G22；E-17／26] 對 CLI 與未來管理 HTTP 共用的消息寫入流程。 */
class NewsManagement implements ContentImportService
{
    public function __construct(
        private readonly NewsRepository $news,
        private readonly MediaRepository $media,
        private readonly Authorization $authorization,
        private readonly EventRepository $events,
        private readonly RichTextSanitizer $sanitizer,
        private readonly ContentCover $cover,
        private readonly Clock $clock,
    ) {}

    public function prepare(Account $actor, ImportItem $item, bool $lock): PreparedImport
    {
        $before = $this->news->find($item->id, $lock);
        $this->authorization->require($actor, 'news', $before === null ? 'create' : 'edit', $lock);
        if (($before?->fields['deleted_at'] ?? null) !== null) {
            throw new ApplicationError('CONFLICT', '此識別已刪除，不能以匯入重新使用。');
        }
        $input = NewsInput::fromArray($item->values, $before === null, $this->clock->now());
        $fields = $input->fields;
        if ($fields['slug'] === '') {
            $fields['slug'] = $before?->fields['slug'] ?? 'news-' . str_replace('-', '', $item->id);
        }
        $slug = (string) $fields['slug'];
        $this->news->assertSlugAvailable($slug, $item->id);
        $coverId = $fields['cover_media_id'] ?? null;
        $fields = $this->cover->apply($fields, is_string($coverId) ? $this->media->find($coverId, MediaModule::News) : null);
        $body = (string) ($fields['body'] ?? '');
        $clean = $this->sanitizer->clean($body);
        foreach (BodyImages::paths($clean) as $path) {
            if (str_starts_with($path, '/media/') && $this->media->moduleForStorage(substr($path, 7)) !== MediaModule::News) {
                throw ApplicationError::invalid('body', '正文引用的本地圖片不屬於消息模組。');
            }
        }
        $fields['body'] = $clean;
        if ($before !== null) {
            foreach (['status','publish_at'] as $key) {
                if (!array_key_exists($key, $fields)) {
                    $fields[$key] = $before->fields[$key] ?? null;
                }
            }
        }
        $unchanged = $before !== null && ImportVersion::unchanged($before->fields, $fields);
        if ($before !== null) {
            ImportVersion::assertCurrent($input->version, $before->version, $unchanged);
        }
        $version = $before === null ? 0 : $before->version;
        return new PreparedImport(
            $item,
            new NewsInput($fields, $input->version),
            $before === null,
            $unchanged,
            $version,
            $body === $clean ? [] : ['body_sanitized'],
        );
    }

    public function save(Account $actor, PreparedImport $change, ClientContext $client): void
    {
        if (!$change->input instanceof NewsInput) {
            throw new \LogicException('News input required.');
        }
        $fresh = $this->authorization->require($actor, 'news', $change->creating ? 'create' : 'edit', true);
        $this->news->save($change->item->id, $change->input, $change->creating, $fresh->id);
        $this->events->content(
            $fresh->id,
            $change->creating ? 'news-create' : 'news-update',
            'news',
            $change->item->id,
            $change->creating ? null : $change->version,
            $change->version + 1,
            (string) ($change->input->fields['status'] ?? 'unchanged'),
            $client,
        );
    }
}
