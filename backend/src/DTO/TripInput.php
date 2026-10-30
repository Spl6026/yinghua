<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

/** [S E15/G15；原型planner欄位無required；E-33] 空名稱日期與空行程皆可保存。 */
readonly class TripInput
{
    /** @param list<TripStopInput> $items */
    public function __construct(public ?string $id, public string $title, public ?string $tripDate, public array $items, public int $version) {}

    /** @param array<string,mixed> $values */
    public static function from(array $values, bool $creating): self
    {
        $input = new Input($values);
        $input->only($creating ? ['id','title','tripDate','items'] : ['title','tripDate','items','version']);
        $id = $creating && array_key_exists('id', $values) ? ContentFields::uuid($input->text('id', 36, 36), 'id') : null;
        $title = $creating && !array_key_exists('title', $values) ? '我的旅行計畫' : $input->text('title', 0, 200);
        $date = ($values['tripDate'] ?? null) === null ? null : ContentFields::date($input->optionalText('tripDate', 10), 'tripDate');
        $items = [];
        $ids = [];
        foreach ((new Input($creating ? ['items' => [],...$values] : $values))->objects('items', 1000) as $values) {
            $item = TripStopInput::from($values);
            if (in_array($item->attractionId, $ids, true)) {
                throw ApplicationError::invalid('items', '同一景點不能重複加入。');
            }
            $ids[] = $item->attractionId;
            $items[] = $item;
        }
        return new self($id, $title, $date, $items, $creating ? 0 : $input->positiveInt('version'));
    }
}
