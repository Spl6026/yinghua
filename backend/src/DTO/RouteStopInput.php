<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

readonly class RouteStopInput
{
    public function __construct(public string $attractionId, public ?string $suggestedTime, public string $note) {}

    /** @param array<string,mixed> $values */
    public static function from(array $values): self
    {
        $input = new Input($values);
        $input->only(['attractionId','suggestedTime','note']);
        $id = ContentFields::uuid($input->text('attractionId', 36, 36), 'stops');
        $time = $input->optionalText('suggestedTime', 8);
        if ($time !== '' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time)) {
            throw ApplicationError::invalid('stops', '建議時間須為 HH:mm。');
        }
        return new self($id, $time === '' ? null : $time . ':00', $input->optionalText('note', 2000));
    }
}
