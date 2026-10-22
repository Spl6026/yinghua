<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use PDOException;
use Yinghua\DTO\NewsPost;
use Yinghua\DTO\NewsInput;
use Yinghua\Exceptions\ContentConflict;

/** [D：資料表建議 14；E-26] 主題查詢／型別映射／歷史網址集中管理。 */
class NewsRepository
{
    private const string SELECT = "SELECT n.*,m.storage_key,m.alt AS media_alt FROM news_posts n LEFT JOIN media_assets m ON m.id=n.cover_media_id";

    public function __construct(private readonly Database $database) {}

    public function find(string $id, bool $lock = false): ?NewsPost
    {
        $row = $this->database->row(self::SELECT . ' WHERE n.id=:id' . ($lock ? ' FOR UPDATE' : ''), ['id' => $id]);
        return $row === null ? null : $this->map($row);
    }

    /** @return list<NewsPost> */
    public function published(int $now): array
    {
        return array_map($this->map(...), $this->database->rows(self::SELECT . " WHERE n.deleted_at IS NULL AND n.status='published' AND n.publish_at<=:now ORDER BY n.publish_at DESC,n.id", ['now' => gmdate('Y-m-d H:i:s', $now)]));
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
                $this->database->execute('INSERT INTO news_posts (id,' . $columns . ') VALUES (:id,' . $parameters . ')', ['id' => $id,...$fields]);
            } else {
                $set = implode(',', array_map(static fn(string $key): string => $key . '=:' . $key, array_keys($fields)));
                $this->database->execute('UPDATE news_posts SET ' . $set . ',version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=:id', ['id' => $id,...$fields]);
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

    /** @return array<string,string> */
    public function aliases(int $now): array
    {
        $result = [];
        foreach ($this->database->rows("SELECT a.path,n.slug FROM news_aliases a JOIN news_posts n ON n.id=a.news_post_id WHERE n.deleted_at IS NULL AND n.status='published' AND n.publish_at<=:now", ['now' => gmdate('Y-m-d H:i:s', $now)]) as $values) {
            $row = new Row($values);
            $result[$row->string('path')] = '/news/' . $row->string('slug') . '.html';
        }
        return $result;
    }

    /** @param array<string,mixed> $values */
    private function map(array $values): NewsPost
    {
        $row = new Row($values);
        $fields = ContentMapping::fields($values, [...ContentMapping::sharedKeys(),'type','summary','body','event_starts_at','event_ends_at','event_label','event_location']);
        $public = [...ContentMapping::common($values, 'news'),
            'type' => $row->string('type'),'summary' => $row->string('summary'),'body' => $row->string('body'),
            'eventStartAt' => ContentMapping::iso(is_string($values['event_starts_at'] ?? null) ? $values['event_starts_at'] : null),
            'eventEndAt' => ContentMapping::iso(is_string($values['event_ends_at'] ?? null) ? $values['event_ends_at'] : null),
            'eventLabel' => $row->string('event_label'),'eventLocation' => $row->string('event_location')];
        return new NewsPost($row->string('id'), $row->integer('version'), $fields, $public);
    }
}
