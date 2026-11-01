<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\DTO\AttractionContent;

/** [U-23／E-40] 顯示補位，不改寫儲存值；Google文字不進此純自家文案政策。 */
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
