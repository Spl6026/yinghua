<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use SensitiveParameter;
use Yinghua\Models\IdentityKind;

readonly class LoginInput
{
    public function __construct(public string $identifier, #[SensitiveParameter] public string $password) {}

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values, IdentityKind $kind): self
    {
        $input = new Input($values);
        $field = $kind === IdentityKind::Admin ? 'account' : 'email';
        $input->only([$field, 'password']);

        return new self(
            $kind === IdentityKind::Member ? $input->email() : mb_strtolower($input->text($field, 1, 60)),
            $input->text('password', 1, 128, false),
        );
    }
}
