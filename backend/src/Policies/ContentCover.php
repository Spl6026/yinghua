<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\DTO\MediaAsset;
use Yinghua\Exceptions\ApplicationError;

class ContentCover
{
    /** @param array<string,string|int|null> $fields
     * @return array<string,string|int|null>
     */
    public function apply(array $fields, ?MediaAsset $media): array
    {
        if (($fields['cover_media_id'] ?? null) !== null && $media === null) {
            throw ApplicationError::invalid('coverMediaId', '找不到此模組可使用的封面。');
        }
        $alt = $fields['cover_alt'] ?? '';
        if ($alt === '' && $media !== null) {
            $alt = $media->alt;
        }
        if (($media !== null || ($fields['cover_url'] ?? '') !== '') && (!is_string($alt) || trim($alt) === '')) {
            throw ApplicationError::invalid('coverAlt', '有封面時替代文字必填。');
        }
        return [...$fields,'cover_alt' => $alt];
    }
}
