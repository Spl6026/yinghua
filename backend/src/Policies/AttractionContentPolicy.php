<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\DTO\AttractionContent;

/** 決定景點摘要與介紹要顯示什麼：摘要空白時從介紹截取，只影響顯示，不改寫儲存值；Google 文字不在這裡處理。 */
class AttractionContentPolicy
{
    public static function select(string $summary, string $description, bool $linked): AttractionContent
    {
        $hasSummary = preg_match('/[^\s\p{Z}]/u', $summary) === 1;
        $hasDescription = preg_match('/[^\s\p{Z}]/u', $description) === 1;
        $excerpt = '';
        if (!$hasSummary && $hasDescription) {
            $excerpt = trim(preg_replace('/[\s\p{Z}]+/u', ' ', $description) ?? '');
            if (mb_strlen($excerpt) > 120) {
                $excerpt = mb_substr($excerpt, 0, 119) . '…';
            }
        }
        return new AttractionContent(
            $hasSummary ? $summary : $excerpt,
            $hasDescription ? $description : '',
            $hasSummary ? 'authored' : ($hasDescription ? 'description-excerpt' : 'empty'),
            $hasDescription ? 'authored' : ($linked ? 'google-pending' : 'empty'),
        );
    }
}
