<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use PDOException;
use Yinghua\DTO\Attraction;
use Yinghua\DTO\AttractionInput;
use Yinghua\DTO\AttractionPage;
use Yinghua\DTO\OpeningHours;
use Yinghua\DTO\PhoneNumber;
use Yinghua\Exceptions\ContentConflict;
use Yinghua\Support\UtcDateTime;

/** 景點的 SQL 都集中在這一層；公開頁面與後台查詢共用同一套映射。 */
class AttractionRepository
{
    private const string SELECT = 'SELECT a.*,m.storage_key,m.alt AS media_alt,m.source AS media_source,m.license AS media_license FROM attractions a LEFT JOIN media_assets m ON m.id=a.media_id';

    public function __construct(private readonly Database $database) {}

    public function find(string $id, bool $lock = false): ?Attraction
    {
        $row = $this->database->row(self::SELECT . ' WHERE a.id=:id' . ($lock ? ' FOR UPDATE' : ''), ['id' => $id]);

        return $row === null ? null : $this->map($row);
    }

    public function listing(string $query, string $status, int $page, int $size): AttractionPage
    {
        $where = 'a.deleted_at IS NULL';
        $parameters = [];
        if ($query !== '') {
            $where .= ' AND (a.name LIKE :name OR a.address LIKE :address)';
            $parameters['name'] = $parameters['address'] = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $query) . '%';
            $where = str_replace('LIKE :name', "LIKE :name ESCAPE '!'", $where);
            $where = str_replace('LIKE :address', "LIKE :address ESCAPE '!'", $where);
        }
        if ($status !== '') {
            $where .= ' AND a.status=:status';
            $parameters['status'] = $status;
        }
        $total = new Row($this->database->row('SELECT COUNT(*) AS total FROM attractions a WHERE ' . $where, $parameters) ?? []);
        $rows = $this->database->rows(
            self::SELECT . ' WHERE ' . $where . ' ORDER BY a.updated_at DESC,a.id DESC LIMIT :size OFFSET :offset',
            [...$parameters,'size' => $size,'offset' => ($page - 1) * $size],
        );

        return new AttractionPage(array_map($this->map(...), $rows), $total->integer('total'), $page, $size);
    }

    /**
     * 只取原始六筆中已發布且未刪除的資料庫內容，不另外重建本機模擬資料。
     *
     * @return list<Attraction>
     */
    public function samples(): array
    {
        $rows = $this->database->rows(
            self::SELECT . " WHERE a.legacy_id BETWEEN 1 AND 6 AND a.status='published' AND a.deleted_at IS NULL AND a.publish_at<=UTC_TIMESTAMP() ORDER BY a.legacy_id",
        );

        return array_map($this->map(...), $rows);
    }

    public function save(string $id, AttractionInput $input, bool $creating, ?string $actorId = null): void
    {
        // 操作者由服務層傳入，不能透過 HTTP 送來的欄位冒用他人身分。
        $fields = $input->fields;
        if ($actorId !== null) {
            $fields['updated_by'] = $actorId;
            if ($creating) {
                $fields['created_by'] = $actorId;
            }
        }
        try {
            if ($creating) {
                $columns = implode(',', array_keys($fields));
                $placeholders = implode(',', array_map(static fn(string $key): string => ':' . $key, array_keys($fields)));
                $this->database->execute(
                    'INSERT INTO attractions (id,' . $columns . ') VALUES (:id,' . $placeholders . ')',
                    ['id' => $id,...$fields],
                );
            } else {
                // 舊版客戶端沒有送週表時，不能用舊格式的摘要蓋掉已存在的結構化營業時間。
                $structured = array_key_exists('opening_hours', $input->fields);
                $columns = implode(',', array_map(static fn(string $key): string => $key === 'hours' && !$structured
                    ? 'hours=CASE WHEN opening_hours IS NULL THEN :hours ELSE hours END' : $key . '=:' . $key, array_keys($fields)));
                $this->database->execute(
                    'UPDATE attractions SET ' . $columns . ',version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=:id',
                    ['id' => $id,...$fields],
                );
            }
            if ($input->fields['category'] === '景點') {
                // 分類可以修改，但已被優惠引用的店家資料要保留，不能硬刪而破壞歷史紀錄。
                $this->database->execute(
                    'DELETE m FROM merchants m WHERE m.attraction_id=:id AND NOT EXISTS (SELECT 1 FROM offers o WHERE o.merchant_id=m.id)',
                    ['id' => $id],
                );
                $this->database->execute(
                    'UPDATE merchants SET attraction_id=NULL,version=version+1,updated_at=UTC_TIMESTAMP() WHERE attraction_id=:id',
                    ['id' => $id],
                );
            } else {
                // 店家資料只在第一次建立時回填，之後編輯景點不會覆寫優惠用的聯絡資料。
                $this->database->execute(
                    "INSERT IGNORE INTO merchants (id,attraction_id,name,district,address,phone,website,status)
                    SELECT UUID(),id,name,COALESCE(district,''),address,phone,website_url,
                    IF(status='published' AND publish_at<=UTC_TIMESTAMP(),'active','inactive') FROM attractions WHERE id=:id",
                    ['id' => $id],
                );
            }
            $this->alias('/places/' . (string) $input->fields['slug'] . '.html', $id);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000' && ($error->errorInfo[1] ?? null) === 1062) {
                throw new ContentConflict('Content identity already reserved.', previous: $error);
            }
            throw $error;
        }
    }

    public function alias(string $path, string $id): void
    {
        $existing = $this->database->row('SELECT attraction_id FROM attraction_aliases WHERE path=:path', ['path' => $path]);
        if ($existing !== null && (new Row($existing))->string('attraction_id') !== $id) {
            throw new ContentConflict('Historical URL already reserved.');
        }
        $this->database->execute(
            'INSERT IGNORE INTO attraction_aliases (path,attraction_id) VALUES (:path,:id)',
            ['path' => $path,'id' => $id],
        );
    }

    public function delete(string $id): void
    {
        $this->database->execute(
            "UPDATE attractions SET status='archived',deleted_at=UTC_TIMESTAMP(),version=version+1 WHERE id=:id",
            ['id' => $id],
        );
    }

    public function referenced(string $id): bool
    {
        return $this->database->row(
            'SELECT id FROM attractions WHERE id=:id AND
            (EXISTS (SELECT 1 FROM route_attractions r WHERE r.attraction_id=attractions.id)
            OR EXISTS (SELECT 1 FROM merchants m JOIN offers o ON o.merchant_id=m.id WHERE m.attraction_id=attractions.id AND o.deleted_at IS NULL))',
            ['id' => $id],
        ) !== null;
    }

    public function hasMuseum(string $exceptId): bool
    {
        return $this->database->row(
            'SELECT id FROM attractions WHERE is_museum=1 AND deleted_at IS NULL AND id<>:id LIMIT 1',
            ['id' => $exceptId],
        ) !== null;
    }

    public function mediaExists(string $id): bool
    {
        return $this->database->row("SELECT id FROM media_assets WHERE id=:id AND module='attractions'", ['id' => $id]) !== null;
    }

    /** @return list<Attraction> */
    public function published(?int $now = null): array
    {
        // 先依發布時間排序；同時發布的示範資料依原型順序排列，其他資料也有穩定的次要排序。
        return array_map($this->map(...), $this->database->rows(
            self::SELECT . " WHERE a.deleted_at IS NULL AND a.status='published' AND a.publish_at<=:now ORDER BY a.publish_at DESC,a.id ASC",
            ['now' => gmdate('Y-m-d H:i:s', $now ?? time())],
        ));
    }

    /** @return array<string,string> */
    public function aliases(?int $now = null): array
    {
        $result = [];
        foreach ($this->database->rows(
            "SELECT l.path,a.slug,a.is_museum FROM attraction_aliases l JOIN attractions a ON a.id=l.attraction_id WHERE a.deleted_at IS NULL AND a.status='published' AND a.publish_at<=:now",
            ['now' => gmdate('Y-m-d H:i:s', $now ?? time())],
        ) as $values) {
            $row = new Row($values);
            $result[$row->string('path')] = $row->integer('is_museum') === 1
                ? '/museum.html'
                : '/places/' . $row->string('slug') . '.html';
        }
        ksort($result);

        return $result;
    }

    /** @param array<string,mixed> $values */
    private function map(array $values): Attraction
    {
        $r = new Row($values);
        $data = [];
        foreach ([
            'id' => 'id',
            'slug' => 'slug',
            'name' => 'name',
            'category' => 'category',
            'district' => 'district',
            'typeLabel' => 'type_label',
            'phone' => 'phone',
            'summary' => 'summary',
            'description' => 'description',
            'hours' => 'hours',
            'address' => 'address',
            'transport' => 'transport',
            'websiteUrl' => 'website_url',
            'mapUrl' => 'map_url',
            'seoTitle' => 'seo_title',
            'seoDescription' => 'seo_description',
            'status' => 'status',
        ] as $key => $column) {
            $data[$key] = $r->string($column);
        }
        foreach (['latitude','longitude'] as $key) {
            $value = $r->nullableString($key);
            $data[$key] = $value === null ? null : (float) $value;
        }
        $data['phoneUrl'] = PhoneNumber::fromText($r->string('phone'))->uri;
        $data['mediaId'] = $r->nullableString('media_id');
        $key = $r->nullableString('storage_key');
        $data['image'] = $key === null ? $r->string('external_image_url') : '/media/' . $key;
        $data['imageAlt'] = $r->nullableString('image_alt') ?? ($key === null ? $r->string('external_image_alt') : $r->string('media_alt'));
        $hasExternalImage = $r->string('external_image_url') !== '';
        $data['imageSource'] = $r->nullableString('image_source') ?? $r->nullableString('media_source') ?? ($hasExternalImage ? '原始交付展示素材' : '');
        $data['imageLicense'] = $r->nullableString('image_license') ?? $r->nullableString('media_license') ?? ($hasExternalImage ? '正式上線前須確認授權' : '');
        $data['recommendationLabel'] = $r->string('recommendation_label');
        $opening = $r->nullableString('opening_hours');
        $data['openingHours'] = $opening === null
            ? null
            : OpeningHours::fromValue(json_decode($opening, true, 32, JSON_THROW_ON_ERROR))->toArray();
        $data['googlePlaceId'] = $r->nullableString('google_place_id');
        $data['version'] = $r->integer('version');
        $data['featured'] = $r->integer('featured') === 1;
        $data['isMuseum'] = $r->integer('is_museum') === 1;
        $data['url'] = $data['isMuseum'] ? '/museum.html' : '/places/' . $r->string('slug') . '.html';
        $data['publishAt'] = UtcDateTime::iso($r->string('publish_at'));
        $data['updatedAt'] = UtcDateTime::iso($r->string('updated_at'));
        $data['deletedAt'] = $r->nullableString('deleted_at');
        $data['legacyId'] = ($values['legacy_id'] ?? null) === null ? null : $r->integer('legacy_id');

        return new Attraction(
            $r->string('id'),
            $r->string('slug'),
            $r->integer('version'),
            $r->string('status'),
            $r->nullableString('deleted_at'),
            $data,
        );
    }
}
