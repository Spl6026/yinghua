<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use PDOException;
use Yinghua\DTO\Offer;
use Yinghua\DTO\OfferInput;
use Yinghua\Exceptions\ContentConflict;

/** [D：資料表建議 16；E-26] 主題查詢／型別映射／歷史網址集中管理。 */
class OfferRepository
{
    private const string SELECT = "SELECT n.*,m.storage_key,m.alt AS media_alt,c.status AS merchant_status,c.deleted_at AS merchant_deleted_at,c.name AS merchant_name,c.district AS merchant_district,c.address AS merchant_address,c.phone AS merchant_phone,c.website AS merchant_website FROM offers n LEFT JOIN media_assets m ON m.id=n.cover_media_id JOIN merchants c ON c.id=n.merchant_id";

    public function __construct(private readonly Database $database) {}

    /** [S G22／G23；E-31] 管理查詢保留非公開內容；不與公開投影共用篩選。 */
    public function listing(\Yinghua\DTO\AdminContentQuery $query, string $today): \Yinghua\DTO\ContentPage
    {
        $where = 'n.deleted_at IS NULL';
        $params = [];
        if ($query->q !== '') {
            $where .= " AND CONCAT(n.title,' ',n.discount_text,' ',c.name) LIKE :q ESCAPE '!'";
            $params['q'] = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $query->q) . '%';
        }
        if ($query->status !== '') {
            $where .= ' AND n.status=:status';
            $params['status'] = $query->status;
        }
        if ($query->merchantId !== '') {
            $where .= ' AND n.merchant_id=:merchant';
            $params['merchant'] = $query->merchantId;
        }
        if ($query->active !== '') {
            $active = "(n.status='published' AND n.publish_at<=UTC_TIMESTAMP() AND c.deleted_at IS NULL AND c.status='active' AND (n.valid_from IS NULL OR n.valid_from<=:startDate) AND (n.valid_to IS NULL OR n.valid_to>=:endDate))";
            $where .= ' AND ' . ($query->active === 'true' ? $active : 'NOT ' . $active);
            $params['startDate'] = $today;
            $params['endDate'] = $today;
        }
        $count = new Row($this->database->row('SELECT COUNT(*) AS total FROM offers n JOIN merchants c ON c.id=n.merchant_id WHERE ' . $where, $params) ?? []);
        $rows = $this->database->rows(self::SELECT . ' WHERE ' . $where . ' ORDER BY n.updated_at DESC,n.id DESC LIMIT :size OFFSET :offset', [...$params,'size' => $query->pageSize,'offset' => ($query->page - 1) * $query->pageSize]);
        return new \Yinghua\DTO\ContentPage(array_map($this->map(...), $rows), $count->integer('total'), $query->page, $query->pageSize);
    }

    public function delete(string $id): void
    {
        $this->database->execute("UPDATE offers SET status='archived',deleted_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP(),version=version+1 WHERE id=:id", ['id' => $id]);
    }

    public function find(string $id, bool $lock = false): ?Offer
    {
        $row = $this->database->row(self::SELECT . ' WHERE n.id=:id' . ($lock ? ' FOR UPDATE' : ''), ['id' => $id]);
        return $row === null ? null : $this->map($row);
    }

    /** @return list<Offer> */
    public function published(int $now): array
    {
        return array_map($this->map(...), $this->database->rows(self::SELECT . " WHERE n.deleted_at IS NULL AND n.status='published' AND n.publish_at<=:now AND c.deleted_at IS NULL AND c.status='active' ORDER BY n.publish_at DESC,n.id", ['now' => gmdate('Y-m-d H:i:s', $now)]));
    }

    public function save(string $id, OfferInput $input, bool $creating, string $actor): void
    {
        $fields = [...$input->fields,'updated_by' => $actor];
        if ($creating) {
            $fields['created_by'] = $actor;
        }
        try {
            if ($creating) {
                $columns = implode(',', array_keys($fields));
                $parameters = implode(',', array_map(static fn(string $key): string => ':' . $key, array_keys($fields)));
                $this->database->execute('INSERT INTO offers (id,' . $columns . ') VALUES (:id,' . $parameters . ')', ['id' => $id,...$fields]);
            } else {
                $set = implode(',', array_map(static fn(string $key): string => $key . '=:' . $key, array_keys($fields)));
                $this->database->execute('UPDATE offers SET ' . $set . ',version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=:id', ['id' => $id,...$fields]);
            }
            $this->alias('/offers/' . (string) $input->fields['slug'] . '.html', $id);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000' && ($error->errorInfo[1] ?? null) === 1062) {
                throw new ContentConflict('Content identity already reserved.', previous: $error);
            }
            throw $error;
        }
    }

    public function alias(string $path, string $id): void
    {
        $existing = $this->database->row('SELECT offer_id FROM offer_aliases WHERE path=:path', ['path' => $path]);
        if ($existing !== null && (new Row($existing))->string('offer_id') !== $id) {
            throw new ContentConflict('Historical URL already reserved.');
        }
        $this->database->execute('INSERT IGNORE INTO offer_aliases (path,offer_id) VALUES (:path,:id)', ['path' => $path,'id' => $id]);
    }

    public function assertSlugAvailable(string $slug, string $id): void
    {
        $alias = $this->database->row('SELECT offer_id FROM offer_aliases WHERE path=:path', ['path' => '/offers/' . $slug . '.html']);
        $other = $this->database->row('SELECT id FROM offers WHERE slug=:slug AND id<>:id', ['slug' => $slug,'id' => $id]);
        if (($alias !== null && (new Row($alias))->string('offer_id') !== $id) || $other !== null) {
            throw new \Yinghua\Exceptions\ApplicationError('CONFLICT', '此優惠網址已被使用或保留。');
        }
    }

    public function demoIdentity(string $id, int $legacy): void
    {
        $this->database->execute('UPDATE offers SET legacy_id=:legacy WHERE id=:id AND legacy_id IS NULL', ['legacy' => $legacy,'id' => $id]);
    }

    /** @return array<string,string> */
    public function aliases(int $now): array
    {
        $result = [];
        foreach ($this->database->rows("SELECT a.path,n.slug FROM offer_aliases a JOIN offers n ON n.id=a.offer_id JOIN merchants c ON c.id=n.merchant_id WHERE n.deleted_at IS NULL AND n.status='published' AND n.publish_at<=:now AND c.deleted_at IS NULL AND c.status='active'", ['now' => gmdate('Y-m-d H:i:s', $now)]) as $values) {
            $row = new Row($values);
            $result[$row->string('path')] = '/offers/' . $row->string('slug') . '.html';
        }
        // [E-29] Alias 是映射，不讓 SQL 掃描順序改變發布 generation。
        ksort($result);
        return $result;
    }

    /** @param array<string,mixed> $values */
    private function map(array $values): Offer
    {
        $row = new Row($values);
        $fields = ContentMapping::fields($values, [...ContentMapping::sharedKeys(),'merchant_id','discount_text','description','terms','valid_from','valid_to']);
        $public = [...ContentMapping::common($values, 'offers'),
            'merchant' => ['id' => $row->string('merchant_id'),'name' => $row->string('merchant_name'),
                'district' => $row->string('merchant_district'),'address' => $row->string('merchant_address'),
                'phone' => $row->string('merchant_phone'),'website' => $row->string('merchant_website')],
            'discount' => $row->string('discount_text'),'description' => $row->string('description'),'terms' => $row->string('terms'),
            'validFrom' => $fields['valid_from'],'validTo' => $fields['valid_to']];
        return new Offer($row->string('id'), $row->integer('version'), $fields, $public, $row->string('merchant_status') === 'active' && ($values['merchant_deleted_at'] ?? null) === null);
    }
}
