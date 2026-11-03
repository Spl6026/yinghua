<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Clock;
use Yinghua\Contracts\ContentEditor;
use Yinghua\Contracts\RichTextSanitizer;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\ImportItem;
use Yinghua\DTO\NewsInput;
use Yinghua\DTO\PreparedImport;
use Yinghua\DTO\PreparedContentChange;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\MediaModule;
use Yinghua\Policies\BodyImages;
use Yinghua\Policies\ContentCover;
use Yinghua\Policies\ImportVersion;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\MediaRepository;
use Yinghua\Repositories\NewsRepository;
use Yinghua\Exceptions\ErrorCode;

/** 消息的寫入流程，CLI 匯入與後台 HTTP 共用。 */
class NewsManagement implements ContentEditor
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
        $change = $this->prepareValues($actor, $item->id, $item->values, $lock);
        return new PreparedImport($item, $change->input, $change->creating, $change->unchanged, $change->version, $change->warnings);
    }

    /** @param array<string,mixed> $values */
    public function prepareValues(Account $actor, string $id, array $values, bool $lock, ?bool $creating = null): PreparedContentChange
    {
        $before = $this->news->find($id, $lock);
        if ($creating === false && ($before === null || ($before->fields['deleted_at'] ?? null) !== null)) {
            throw new ApplicationError(ErrorCode::NotFound, '找不到此內容。');
        }
        if ($creating === true && $before !== null) {
            throw new ApplicationError(ErrorCode::Conflict, '此識別已存在。');
        }
        $this->authorization->require($actor, 'news', $before === null ? 'create' : 'edit', $lock);
        if (($before?->fields['deleted_at'] ?? null) !== null) {
            throw new ApplicationError(ErrorCode::Conflict, '此識別已刪除，不能以匯入重新使用。');
        }
        $input = NewsInput::fromArray($values, $before === null, $this->clock->now());
        $fields = $input->fields;
        if ($fields['slug'] === '') {
            $fields['slug'] = $before?->fields['slug'] ?? 'news-' . str_replace('-', '', $id);
        }
        $slug = (string) $fields['slug'];
        $this->news->assertSlugAvailable($slug, $id);
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
        return new PreparedContentChange(
            $id,
            new NewsInput($fields, $input->version),
            $before === null,
            $unchanged,
            $version,
            $body === $clean ? [] : ['body_sanitized'],
        );
    }

    public function save(Account $actor, PreparedImport $change, ClientContext $client): void
    {
        $this->saveChange(
            $actor,
            new PreparedContentChange(
                $change->item->id,
                $change->input,
                $change->creating,
                $change->unchanged,
                $change->version,
                $change->warnings,
            ),
            $client,
        );
    }

    public function saveChange(Account $actor, PreparedContentChange $change, ClientContext $client): void
    {
        if (!$change->input instanceof NewsInput) {
            throw new \LogicException('News input required.');
        }
        $fresh = $this->authorization->require($actor, 'news', $change->creating ? 'create' : 'edit', true);
        $this->news->save($change->id, $change->input, $change->creating, $fresh->id);
        $this->events->content(
            $fresh->id,
            $change->creating ? 'news-create' : 'news-update',
            'news',
            $change->id,
            $change->creating ? null : $change->version,
            $change->version + 1,
            (string) ($change->input->fields['status'] ?? 'unchanged'),
            $client,
        );
    }
}
