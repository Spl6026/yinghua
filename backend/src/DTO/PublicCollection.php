<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 從發布成功的快照中取出某類內容清單，並先確認型別正確再交給公開 API 使用。 */
final class PublicCollection
{
    /** @param array<string,mixed> $snapshot
     * @return list<array<string,mixed>>
     */
    public static function items(array $snapshot, string $topic): array
    {
        $rows = $snapshot[$topic] ?? [];
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \UnexpectedValueException('Invalid public collection.');
        }
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \UnexpectedValueException('Invalid public item.');
            }
            $item = [];
            foreach ($row as $key => $value) {
                if (!is_string($key)) {
                    throw new \UnexpectedValueException('Invalid public field.');
                }
                $item[$key] = $value;
            }
            $result[] = $item;
        }
        return $result;
    }

    /**
     * 依欄位值找出快照中的單筆公開內容（例如 slug、id），找不到回 null，由呼叫端決定錯誤訊息。
     *
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed>|null
     */
    public static function find(array $snapshot, string $topic, string $field, mixed $value): ?array
    {
        foreach (self::items($snapshot, $topic) as $item) {
            if (($item[$field] ?? null) === $value) {
                return $item;
            }
        }

        return null;
    }

    /** @param list<array<string,mixed>> $items
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
    public static function page(array $items, int $page, int $size, array $snapshot): array
    {
        return ['items' => array_slice($items, ($page - 1) * $size, $size),'total' => count($items),
            'page' => $page,'pageSize' => $size,'generation' => $snapshot['generation'] ?? ''];
    }
}
