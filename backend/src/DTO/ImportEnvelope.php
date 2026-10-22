<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use JsonException;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Support\Secrets;

readonly class ImportEnvelope
{
    /** @param list<ImportItem> $items */
    public function __construct(public array $items) {}

    public static function fromJson(string $json): self
    {
        if (strlen($json) > 2 * 1024 * 1024) {
            throw ApplicationError::invalid('file', 'JSON 不可超過 2 MiB，請拆批匯入。');
        }
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ApplicationError::invalid('file', 'JSON 格式無效。');
        }
        if (!is_array($data) || array_is_list($data) || array_diff(array_keys($data), ['schemaVersion','items']) !== [] || ($data['schemaVersion'] ?? null) !== 1) {
            throw ApplicationError::invalid('schemaVersion', '請提供 schemaVersion=1 的匯入物件。');
        }
        $rows = $data['items'] ?? null;
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 500) {
            throw ApplicationError::invalid('items', '請提供最多 500 筆的清單。');
        }
        $items = [];
        $ids = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row) || array_is_list($row)) {
                throw ApplicationError::invalid('items.' . $index, '每筆內容須為物件。');
            }
            $values = [];
            foreach ($row as $key => $value) {
                if (!is_string($key)) {
                    throw ApplicationError::invalid('items.' . $index, '欄位名稱無效。');
                }
                $values[$key] = $value;
            }
            $id = isset($values['id']) ? (new Input($values))->text('id', 36, 36) : Secrets::uuid();
            ContentFields::uuid($id, 'items.' . $index . '.id');
            if (isset($ids[$id])) {
                throw ApplicationError::invalid('items.' . $index . '.id', '同批不能重複同一 ID。');
            }
            $ids[$id] = true;
            unset($values['id']);
            $items[] = new ImportItem($id, $values);
        }
        return new self($items);
    }
}
