<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class PermissionMatrixInput
{
    /** @param list<array{module:string,action:string,allowed:bool}> $permissions */
    public function __construct(public int $version, public array $permissions) {}

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        $input = new Input($values);
        $input->only(['version', 'permissions']);
        $permissions = [];
        foreach ($input->objects('permissions') as $values) {
            $entry = new Input($values);
            $entry->only(['module', 'action', 'allowed']);
            $permissions[] = ['module' => $entry->text('module', 1, 40), 'action' => $entry->text('action', 1, 20),
                'allowed' => $entry->boolean('allowed')];
        }

        return new self($input->positiveInt('version'), $permissions);
    }
}
