<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use PDOException;
use Yinghua\DTO\NewsPost;
use Yinghua\DTO\NewsInput;
use Yinghua\Exceptions\ContentConflict;

/** 最新消息的查詢、型別映射與歷史網址集中在這裡。 */
class NewsRepository
{
    private const string SELECT = "SELECT n.*,m.storage_key,m.alt AS media_alt FROM news_posts n LEFT JOIN media_assets m ON m.id=n.cover_media_id";

    public function __construct(private readonly Database $database) {}

    /** 後台查詢會包含未公開的內容，所以不與公開頁面共用篩選條件。 */
    public function listing(\Yinghua\DTO\AdminContentQuery $query, string $today): \Yinghua\DTO\ContentPage
    {
        $where = 'n.deleted_at IS NULL';
        $params = [];
        if ($query->q !== '') {
            $where .= " AND n.title LIKE :q ESCAPE '!'";
            $params['q'] = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $query->q) . '%';
        }
        if ($query->status !== '') {
            $where .= ' AND n.status=:status';
            $params['status'] = $query->status;
        }
        if ($query->type !== '') {
            $where .= ' AND n.type=:type';
            $params['type'] = $query->type;
        }
        $count = new Row($this->database->row('SELECT COUNT(*) AS total FROM news_posts n  WHERE ' . $where, $params) ?? []);
        $rows = $this->database->rows(
            self::SELECT . ' WHERE ' . $where . ' ORDER BY n.updated_at DESC,n.id DESC LIMIT :size OFFSET :offset',
            [...$params,'size' => $query->pageSize,'offset' => ($query->page - 1) * $query->pageSize],
        );
        return new \Yinghua\DTO\ContentPage(array_map($this->map(...), $rows), $count->integer('total'), $query->page, $query->pageSize);
    }

    public function delete(string $id): void
    {
        $this->database->execute(
            "UPDATE news_posts SET status='archived',deleted_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP(),version=version+1 WHERE id=:id",
            ['id' => $id],
        );
    }

    public function find(string $id, bool $lock = false): ?NewsPost
    {
        $row = $this->database->row(self::SELECT . ' WHERE n.id=:id' . ($lock ? ' FOR UPDATE' : ''), ['id' => $id]);
        return $row === null ? null : $this->map($row);
    }

    /** @return list<NewsPost> */
    public function published(int $now): array
    {
        return array_map($this->map(...), $this->database->rows(
            self::SELECT . " WHERE n.deleted_at IS NULL AND n.status='published' AND n.publish_at<=:now ORDER BY n.publish_at DESC,n.id",
            ['now' => gmdate('Y-m-d H:i:s', $now)],
        ));
    }

    public function save(string $id, NewsInput $input, bool $creating, string $actor): void
    {
        $fields = [...$input->fields,'updated_by' => $actor];
        if ($creating) {
            $fields['created_by'] = $actor;
        }
        try {
            if ($creating) {
                $columns = implode(',', array_keys($fields));
                $parameters = implode(',', array_map(static fn(string $key): string => ':' . $key, array_keys($fields)));
                $this->database->execute(
                    'INSERT INTO news_posts (id,' . $columns . ') VALUES (:id,' . $parameters . ')',
                    ['id' => $id,...$fields],
                );
            } else {
                $set = implode(',', array_map(static fn(string $key): string => $key . '=:' . $key, array_keys($fields)));
                $this->database->execute(
                    'UPDATE news_posts SET ' . $set . ',version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=:id',
                    ['id' => $id,...$fields],
                );
            }
            $this->alias('/news/' . (string) $input->fields['slug'] . '.html', $id);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000' && ($error->errorInfo[1] ?? null) === 1062) {
                throw new ContentConflict('Content identity already reserved.', previous: $error);
            }
            throw $error;
        }
    }

    public function alias(string $path, string $id): void
    {
        $existing = $this->database->row('SELECT news_post_id FROM news_aliases WHERE path=:path', ['path' => $path]);
        if ($existing !== null && (new Row($existing))->string('news_post_id') !== $id) {
            throw new ContentConflict('Historical URL already reserved.');
        }
        $this->database->execute('INSERT IGNORE INTO news_aliases (path,news_post_id) VALUES (:path,:id)', ['path' => $path,'id' => $id]);
    }

    public function assertSlugAvailable(string $slug, string $id): void
    {
        $path = '/news/' . $slug . '.html';
        $alias = $this->database->row('SELECT news_post_id FROM news_aliases WHERE path=:path', ['path' => $path]);
        $other = $this->database->row('SELECT id FROM news_posts WHERE slug=:slug AND id<>:id', ['slug' => $slug,'id' => $id]);
        if ($slug === 'coffee-walk' || ($alias !== null && (new Row($alias))->string('news_post_id') !== $id) || $other !== null) {
            throw new \Yinghua\Exceptions\ApplicationError('CONFLICT', '此消息網址已被使用或保留。');
        }
    }

    public function demoIdentity(string $id, int $legacy): void
    {
        $this->database->execute(
            'UPDATE news_posts SET legacy_id=:legacy WHERE id=:id AND legacy_id IS NULL',
            ['legacy' => $legacy,'id' => $id],
        );
    }

    /** @return array<string,string> */
    public function aliases(int $now): array
    {
        $result = [];
        foreach ($this->database->rows(
            "SELECT a.path,n.slug FROM news_aliases a JOIN news_posts n ON n.id=a.news_post_id WHERE n.deleted_at IS NULL AND n.status='published' AND n.publish_at<=:now",
            ['now' => gmdate('Y-m-d H:i:s', $now)],
        ) as $values) {
            $row = new Row($values);
            $result[$row->string('path')] = '/news/' . $row->string('slug') . '.html';
        }
        // 舊網址只是對照表；排序後輸出，避免 SQL 掃描順序不同就產生不同的發布版本。
        ksort($result);
        return $result;
    }

    /** @param array<string,mixed> $values */
    private function map(array $values): NewsPost
    {
        $row = new Row($values);
        $fields = ContentMapping::fields($values, [
            ...ContentMapping::sharedKeys(),
            'type',
            'summary',
            'body',
            'event_starts_at',
            'event_ends_at',
            'event_label',
            'event_location',
        ]);
        $public = [
            ...ContentMapping::common($values, 'news'),
            'type' => $row->string('type'),
            'summary' => $row->string('summary'),
            'body' => $row->string('body'),
            'bodyImages' => \Yinghua\Policies\BodyImages::paths($row->string('body')),
            'eventStartAt' => ContentMapping::iso(is_string($values['event_starts_at'] ?? null) ? $values['event_starts_at'] : null),
            'eventEndAt' => ContentMapping::iso(is_string($values['event_ends_at'] ?? null) ? $values['event_ends_at'] : null),
            'eventLabel' => $row->string('event_label'),
            'eventLocation' => $row->string('event_location'),
        ];
        return new NewsPost(
            $row->string('id'),
            $row->integer('version'),
            $fields,
            $public,
            ContentMapping::iso($row->string('updated_at')),
        );
    }
}
