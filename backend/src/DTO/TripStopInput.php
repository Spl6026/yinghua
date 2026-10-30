<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

/** [S F15/G15；E-33] startTime映射既有planned_time；順序取陣列位置。 */
readonly class TripStopInput
{
    public function __construct(public string $attractionId, public ?string $startTime, public string $note) {}

    /** @param array<string,mixed> $values */
    public static function from(array $values): self
    {
        $input = new Input($values);
        $input->only(['attractionId','startTime','note']);
        $id = ContentFields::uuid($input->text('attractionId', 36, 36), 'items');
        $time = ($values['startTime'] ?? null) === null ? '' : $input->optionalText('startTime', 5);
        if ($time !== '' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time)) {
            throw ApplicationError::invalid('items', '站點時間須為HH:mm，或留白。');
        }
        return new self($id, $time === '' ? null : $time . ':00', $input->optionalText('note', 2000));
    }
}
