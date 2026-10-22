<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\ContentStatus;

/** [E-26] 消息／優惠共用格式邊界；不增加業務必填。 */
final class ContentFields
{
    public static function uuid(string $value, string $field): string
    {
        if (!preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D', $value)) {
            throw ApplicationError::invalid($field, '請提供有效 UUID。');
        }
        return $value;
    }

    public static function date(?string $value, string $field, bool $withTime = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $format = $withTime ? 'Y-m-d\TH:i:sP' : 'Y-m-d';
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date === false || $date->format($format) !== $value) {
            throw ApplicationError::invalid($field, '請提供有效日期' . ($withTime ? '與時區。' : '。'));
        }
        $utc = $date->setTimezone(new DateTimeZone('UTC'));
        if ((int) $utc->format('Y') < 1000 || (int) $utc->format('Y') > 9999) {
            throw ApplicationError::invalid($field, '日期超出支援範圍。');
        }
        return $withTime ? $utc->format('Y-m-d H:i:s') : $date->format('Y-m-d');
    }

    /** @param array<string,mixed> $values
     * @return array<string,string|int|null>
     */
    public static function shared(array $values, bool $creating, int $now): array
    {
        $input = new Input($values);
        $slug = $input->optionalText('slug', 160);
        if ($slug !== '' && (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || $slug === 'resolve-url')) {
            throw ApplicationError::invalid('slug', '請使用未保留的小寫英文、數字與連字號。');
        }
        $fields = ['title' => $input->text('title'), 'slug' => $slug];
        foreach (['coverUrl' => ['cover_url',2000], 'coverAlt' => ['cover_alt',255], 'coverSource' => ['cover_source',255],
            'coverLicense' => ['cover_license',255], 'seoTitle' => ['seo_title',160], 'seoDescription' => ['seo_description',500]] as $key => [$column, $max]) {
            $fields[$column] = $input->optionalText($key, $max);
        }
        $fields['cover_url'] = ContentUrl::validate($input->optionalText('coverUrl', 2000), 'coverUrl');
        $media = ($values['coverMediaId'] ?? null) === null ? '' : $input->optionalText('coverMediaId', 36);
        $fields['cover_media_id'] = $media === '' ? null : self::uuid($media, 'coverMediaId');
        if ($media !== '' && $fields['cover_url'] !== '') {
            throw ApplicationError::invalid('coverUrl', '請選擇媒體或圖片網址其中一種。');
        }
        if ($creating || array_key_exists('status', $values)) {
            $status = ContentStatus::tryFrom($input->optionalText('status') ?: 'draft');
            if ($status === null) {
                throw ApplicationError::invalid('status', '請選擇有效發布狀態。');
            }
            $fields['status'] = $status->value;
        }
        if ($creating || array_key_exists('publishAt', $values)) {
            $fields['publish_at'] = self::date($input->optionalText('publishAt', 30), 'publishAt', true) ?? gmdate('Y-m-d H:i:s', $now);
        }
        return $fields;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return ['title','slug','coverMediaId','coverUrl','coverAlt','coverSource','coverLicense','status','publishAt','seoTitle','seoDescription','version'];
    }
}
