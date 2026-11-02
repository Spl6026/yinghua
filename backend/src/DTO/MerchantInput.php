<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

/** 店家表單輸入；店家不必對應任何景點。 */
readonly class MerchantInput
{
    /** @param array<string,string|int|null> $fields */
    public function __construct(public array $fields, public ?int $version) {}

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values, bool $creating): self
    {
        $input = new Input($values);
        $input->only(['name','district','address','phone','website','status','attractionId','version']);
        $district = $input->optionalText('district', 20);
        if (!in_array($district, ['', '北區','東區','香山區','新竹市'], true)) {
            throw ApplicationError::invalid('district', '行政區無效。');
        }
        $status = $input->optionalText('status') ?: ($creating ? 'active' : '');
        if ($status !== '' && !in_array($status, ['active','inactive'], true)) {
            throw ApplicationError::invalid('status', '店家狀態無效。');
        }
        $link = ($values['attractionId'] ?? null) === null ? '' : $input->optionalText('attractionId', 36);
        $fields = ['name' => $input->text('name'), 'district' => $district, 'address' => $input->optionalText('address'),
            'phone' => PhoneNumber::fromText($input->optionalText('phone', 80))->text,
            'website' => ContentUrl::validate($input->optionalText('website', 2000), 'website'),
            'attraction_id' => $link === '' ? null : ContentFields::uuid($link, 'attractionId')];
        if ($status !== '') {
            $fields['status'] = $status;
        }
        return new self($fields, $creating || !array_key_exists('version', $values) ? null : $input->positiveInt('version'));
    }
}
