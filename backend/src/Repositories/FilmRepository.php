<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use PDOException;
use Yinghua\DTO\Film;
use Yinghua\DTO\FilmInput;
use Yinghua\DTO\FilmPage;
use Yinghua\Exceptions\ContentConflict;
use Yinghua\Support\UtcDateTime;

/** 電影查詢共用同一套映射；場次改為批次載入，避免 N+1 查詢。 */
class FilmRepository
{
    private const string SELECT = 'SELECT f.*,m.storage_key,m.alt AS media_alt,m.source AS media_source,m.license AS media_license FROM films f LEFT JOIN media_assets m ON m.id=f.poster_media_id';

    public function __construct(private readonly Database $database, private readonly FilmScreeningRepository $screenings) {}

    public function find(string $id, bool $lock = false): ?Film
    {
        $row = $this->database->row(self::SELECT . ' WHERE f.id=:id' . ($lock ? ' FOR UPDATE' : ''), ['id' => $id]);

        return $row === null ? null : $this->map([$row])[0];
    }

    public function listing(string $q, string $status, int $page, int $size): FilmPage
    {
        $where = 'f.deleted_at IS NULL';
        $parameters = [];
        if ($q !== '') {
            $where .= " AND f.title LIKE :q ESCAPE '!'";
            $parameters['q'] = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $q) . '%';
        }
        if ($status !== '') {
            $where .= ' AND f.status=:status';
            $parameters['status'] = $status;
        }
        $total = new Row($this->database->row('SELECT COUNT(*) AS total FROM films f WHERE ' . $where, $parameters) ?? []);
        $rows = $this->database->rows(
            self::SELECT . ' WHERE ' . $where . ' ORDER BY f.updated_at DESC,f.id DESC LIMIT :size OFFSET :offset',
            [...$parameters,'size' => $size,'offset' => ($page - 1) * $size],
        );

        return new FilmPage($this->map($rows), $total->integer('total'), $page, $size);
    }

    /** @return list<Film> */
    public function published(?int $now = null): array
    {
        return $this->map($this->database->rows(
            self::SELECT . " WHERE f.deleted_at IS NULL AND f.status='published' AND f.publish_at<=:now ORDER BY f.publish_at DESC,f.id",
            ['now' => gmdate('Y-m-d H:i:s', $now ?? time())],
        ));
    }

    public function save(string $id, FilmInput $input, bool $creating, ?string $actorId): void
    {
        $fields = [...$input->fields,'updated_by' => $actorId];
        if ($creating) {
            $fields['created_by'] = $actorId;
        }
        try {
            if ($creating) {
                $columns = implode(',', array_keys($fields));
                $placeholders = implode(',', array_map(static fn(string $key): string => ':' . $key, array_keys($fields)));
                $this->database->execute(
                    'INSERT INTO films (id,' . $columns . ') VALUES (:id,' . $placeholders . ')',
                    ['id' => $id,...$fields],
                );
            } else {
                $columns = implode(',', array_map(static fn(string $key): string => $key . '=:' . $key, array_keys($fields)));
                $this->database->execute(
                    'UPDATE films SET ' . $columns . ',version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=:id',
                    ['id' => $id,...$fields],
                );
            }
            $this->alias('/films/' . (string) $input->fields['slug'] . '.html', $id);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000' && ($error->errorInfo[1] ?? null) === 1062) {
                throw new ContentConflict('Film identity already reserved.', previous: $error);
            }
            throw $error;
        }
    }

    public function alias(string $path, string $id): void
    {
        $existing = $this->database->row('SELECT film_id FROM film_aliases WHERE path=:path', ['path' => $path]);
        if ($existing !== null && (new Row($existing))->string('film_id') !== $id) {
            throw new ContentConflict('Film URL already reserved.');
        }
        $this->database->execute('INSERT IGNORE INTO film_aliases (path,film_id) VALUES (:path,:id)', ['path' => $path,'id' => $id]);
    }

    public function delete(string $id): void
    {
        $this->database->execute(
            "UPDATE films SET status='archived',deleted_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP(),version=version+1 WHERE id=:id",
            ['id' => $id],
        );
    }

    /** @return array<string,string> */
    public function aliases(?int $now = null): array
    {
        $result = [];
        foreach ($this->database->rows(
            "SELECT a.path,f.slug FROM film_aliases a JOIN films f ON f.id=a.film_id WHERE f.deleted_at IS NULL AND f.status='published' AND f.publish_at<=:now",
            ['now' => gmdate('Y-m-d H:i:s', $now ?? time())],
        ) as $values) {
            $row = new Row($values);
            $result[$row->string('path')] = '/films/' . $row->string('slug') . '.html';
        }
        ksort($result);

        return $result;
    }

    /** @param list<array<string,mixed>> $rows
     * @return list<Film>
     */
    private function map(array $rows): array
    {
        $ids = array_map(static fn(array $row): string => (new Row($row))->string('id'), $rows);
        $screenings = $this->screenings->forFilms($ids);
        return array_map(static function (array $values) use ($screenings): Film {
            $row = new Row($values);
            $data = [];
            foreach ([
                'id' => 'id',
                'slug' => 'slug',
                'title' => 'title',
                'genre' => 'genre',
                'synopsis' => 'synopsis',
                'posterUrl' => 'poster_url',
                'ticketUrl' => 'ticket_url',
                'status' => 'status',
                'seoTitle' => 'seo_title',
                'seoDescription' => 'seo_description',
            ] as $key => $column) {
                $data[$key] = $row->string($column);
            }
            $storage = $row->nullableString('storage_key');
            $data['image'] = $storage === null ? $row->string('poster_url') : '/media/' . $storage;
            $data['posterMediaId'] = $row->nullableString('poster_media_id');
            $data['imageAlt'] = $row->nullableString('poster_alt') ?? $row->nullableString('media_alt') ?? '';
            $data['posterAlt'] = $data['imageAlt'];
            $data['posterSource'] = $row->nullableString('poster_source') ?? $row->nullableString('media_source') ?? '';
            $data['posterLicense'] = $row->nullableString('poster_license') ?? $row->nullableString('media_license') ?? '';
            $data['version'] = $row->integer('version');
            $data['prototypeId'] = ($values['prototype_id'] ?? null) === null ? null : $row->integer('prototype_id');
            $data['publishAt'] = UtcDateTime::iso($row->string('publish_at'));
            $data['updatedAt'] = UtcDateTime::iso($row->string('updated_at'));
            $data['deletedAt'] = $row->nullableString('deleted_at');
            $data['url'] = '/films/' . $row->string('slug') . '.html';

            return new Film(
                $row->string('id'),
                $row->string('slug'),
                $row->integer('version'),
                $row->string('status'),
                $row->nullableString('deleted_at'),
                $data,
                $screenings[$row->string('id')] ?? [],
            );
        }, $rows);
    }
}
