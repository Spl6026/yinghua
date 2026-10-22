<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

/** [S：功能規格 G11／G23；原型 offer-add；E-26] 條款必填、日期不擅加必填。 */
readonly class OfferInput
{
    /** @param array<string,string|int|null> $fields */
    public function __construct(public array $fields, public ?int $version) {}

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values, bool $creating, int $now): self
    {
        $input = new Input($values);
        $input->only([...ContentFields::keys(),'merchantId','discount','description','terms','validFrom','validTo']);
        $terms = $input->text('terms', 1, 20000);
        if (trim(html_entity_decode(strip_tags($terms), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '') {
            throw ApplicationError::invalid('terms', '請填寫實際使用條款。');
        }
        $fields = [...ContentFields::shared($values, $creating, $now),
            'merchant_id' => ContentFields::uuid($input->text('merchantId', 36, 36), 'merchantId'),
            'discount_text' => $input->text('discount'), 'description' => $input->optionalText('description', 20000), 'terms' => $terms,
            'valid_from' => ContentFields::date($input->optionalText('validFrom', 10), 'validFrom'),
            'valid_to' => ContentFields::date($input->optionalText('validTo', 10), 'validTo')];
        if ($fields['valid_from'] !== null && $fields['valid_to'] !== null && $fields['valid_to'] < $fields['valid_from']) {
            throw ApplicationError::invalid('validTo', '結束日期不可早於開始日期。');
        }
        return new self($fields, $creating ? null : $input->positiveInt('version'));
    }
}
