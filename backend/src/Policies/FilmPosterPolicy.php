<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\DTO\Film;
use Yinghua\DTO\FilmInput;
use Yinghua\DTO\MediaAsset;
use Yinghua\Exceptions\ApplicationError;

/** [S：功能規格 G7／G21] 無海報可留空；有圖時須有有效替代文字。 */
class FilmPosterPolicy
{
    public function assertValid(?Film $before, FilmInput $input, ?MediaAsset $media): void
    {
        $fields = $input->fields;
        $id = array_key_exists('poster_media_id', $fields) ? $fields['poster_media_id'] : ($before?->values['posterMediaId'] ?? null);
        $url = $fields['poster_url'] ?? $before?->values['posterUrl'] ?? '';
        if ($id !== null && $url !== '') {
            throw ApplicationError::invalid('posterUrl', '請選擇圖片網址或上傳海報其中一種。');
        }
        if ($id !== null && $media === null) {
            throw ApplicationError::invalid('posterMediaId', '找不到可用的電影海報。');
        }
        if ($id === null && $url === '') {
            return;
        }
        $baseAlt = $media === null ? '' : $media->alt;
        $alt = array_key_exists('poster_alt', $fields) ? ($fields['poster_alt'] ?? $baseAlt) : ($before?->values['posterAlt'] ?? $baseAlt);
        if (!is_string($alt) || trim($alt) === '') {
            throw ApplicationError::invalid('posterAlt', '有海報時請填寫替代文字。');
        }
    }
}
