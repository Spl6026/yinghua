<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\DTO\ScreeningWindow;

/** [S：功能規格 5／7；E-26] 首頁與電影 API 共用週篩選及第一場排序。 */
final class WeeklyFilmSelection
{
    /** @param list<array<string,mixed>> $films
     * @return list<array<string,mixed>>
     */
    public static function select(array $films, ScreeningWindow $window): array
    {
        $selected = [];
        foreach ($films as $film) {
            $rows = $film['screenings'] ?? [];
            $matches = [];
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (is_array($row) && is_string($row['startsAt'] ?? null) && $window->contains($row['startsAt'])) {
                        $matches[] = $row;
                    }
                }
            }
            usort($matches, static fn(array $a, array $b): int => [$a['startsAt'], $a['id'] ?? ''] <=> [$b['startsAt'], $b['id'] ?? '']);
            if ($matches !== []) {
                $selected[] = ['film' => [...$film,'screenings' => $matches],'first' => $matches[0]['startsAt'],'id' => $film['id'] ?? ''];
            }
        }
        usort($selected, static fn(array $a, array $b): int => [$a['first'], $a['id']] <=> [$b['first'], $b['id']]);
        return array_map(static fn(array $item): array => $item['film'], $selected);
    }
}
