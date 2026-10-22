<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [E-26] 確認成功快照中的主題清單型別，再交給公開使用案例。 */
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
