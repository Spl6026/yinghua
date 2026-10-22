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

    public function find(string $id, bool $lock = false): ?Merchant
    {
        $values = $this->database->row('SELECT * FROM merchants WHERE id=:id' . ($lock ? ' FOR UPDATE' : ''), ['id' => $id]);
        if ($values === null) {
            return null;
        }
        $row = new Row($values);
        return new Merchant($row->string('id'), $row->integer('version'), ContentMapping::fields($values, ['name','district','address','phone','website','status','attraction_id','deleted_at']));
    }

    public function attractionExists(string $id): bool
    {
        return $this->database->row('SELECT id FROM attractions WHERE id=:id AND deleted_at IS NULL', ['id' => $id]) !== null;
    }

    public function save(string $id, MerchantInput $input, bool $creating): void
    {
        $fields = $input->fields;
        try {
            if ($creating) {
                $columns = implode(',', array_keys($fields));
                $parameters = implode(',', array_map(static fn(string $key): string => ':' . $key, array_keys($fields)));
                $this->database->execute('INSERT INTO merchants (id,' . $columns . ') VALUES (:id,' . $parameters . ')', ['id' => $id,...$fields]);
            } else {
                $set = implode(',', array_map(static fn(string $key): string => $key . '=:' . $key, array_keys($fields)));
                $this->database->execute('UPDATE merchants SET ' . $set . ',version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=:id', ['id' => $id,...$fields]);
            }
        } catch (PDOException $error) {
            if ($error->getCode() === '23000' && ($error->errorInfo[1] ?? null) === 1062) {
                throw new ContentConflict('Merchant identity already reserved.', previous: $error);
            }
            throw $error;
        }
    }
}
