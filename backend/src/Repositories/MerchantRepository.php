<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use PDOException;
use Yinghua\DTO\Merchant;
use Yinghua\DTO\MerchantInput;
use Yinghua\Exceptions\ContentConflict;

class MerchantRepository
{
    public function __construct(private readonly Database $database) {}

    public function listing(\Yinghua\DTO\AdminContentQuery $query): \Yinghua\DTO\ContentPage
    {
        $where = 'deleted_at IS NULL';
        $params = [];
        if ($query->q !== '') {
            $where .= " AND name LIKE :q ESCAPE '!'";
            $params['q'] = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $query->q) . '%';
        }
        if ($query->status !== '') {
            $where .= ' AND status=:status';
            $params['status'] = $query->status;
        }
        if ($query->merchantId !== '') {
            $where .= ' AND id=:merchantId';
            $params['merchantId'] = $query->merchantId;
        }
        $count = new Row($this->database->row('SELECT COUNT(*) AS total FROM merchants WHERE ' . $where, $params) ?? []);
        $rows = $this->database->rows(
            'SELECT * FROM merchants WHERE ' . $where . ' ORDER BY name,id LIMIT :size OFFSET :offset',
            [...$params,'size' => $query->pageSize,'offset' => ($query->page - 1) * $query->pageSize],
        );
        return new \Yinghua\DTO\ContentPage(array_map(static function (array $values): Merchant {
            $row = new Row($values);
            return new Merchant(
                $row->string('id'),
                $row->integer('version'),
                ContentMapping::fields($values, ['name','district','address','phone','website','status','attraction_id','deleted_at']),
            );
        }, $rows), $count->integer('total'), $query->page, $query->pageSize);
    }

    public function find(string $id, bool $lock = false): ?Merchant
    {
        $values = $this->database->row('SELECT * FROM merchants WHERE id=:id' . ($lock ? ' FOR UPDATE' : ''), ['id' => $id]);
        if ($values === null) {
            return null;
        }
        $row = new Row($values);
        return new Merchant(
            $row->string('id'),
            $row->integer('version'),
            ContentMapping::fields($values, ['name','district','address','phone','website','status','attraction_id','deleted_at']),
        );
    }

    public function attractionExists(string $id): bool
    {
        return $this->database->row('SELECT id FROM attractions WHERE id=:id AND deleted_at IS NULL', ['id' => $id]) !== null;
    }

    public function forAttractionPrototypeId(int $prototypeId): ?\Yinghua\DTO\Merchant
    {
        $value = $this->database->row(
            'SELECT m.id FROM merchants m JOIN attractions a ON a.id=m.attraction_id WHERE a.prototype_id=:prototypeId',
            ['prototypeId' => $prototypeId],
        );
        return $value === null ? null : $this->find((new Row($value))->string('id'), true);
    }

    public function assertLinkAvailable(string $attraction, string $merchant): void
    {
        if ($this->database->row(
            'SELECT id FROM merchants WHERE attraction_id=:attraction AND id<>:id',
            ['attraction' => $attraction,'id' => $merchant],
        ) !== null) {
            throw new \Yinghua\Exceptions\ApplicationError(\Yinghua\Exceptions\ErrorCode::Conflict, '此景點已連結其他店家 profile。');
        }
    }

    public function save(string $id, MerchantInput $input, bool $creating): void
    {
        $fields = $input->fields;
        try {
            if ($creating) {
                $this->database->insert('merchants', $id, $fields);
            } else {
                $this->database->updateVersioned('merchants', $id, $fields);
            }
        } catch (PDOException $error) {
            if (Database::isDuplicateKey($error)) {
                throw new ContentConflict('Merchant identity already reserved.', previous: $error);
            }
            throw $error;
        }
    }
}
