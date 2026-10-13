<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use SensitiveParameter;

readonly class PasswordInput
{
    public function __construct(
        #[SensitiveParameter]
        public string $password,
        #[SensitiveParameter]
        public string $credential,
    ) {}

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values, bool $reset): self
    {
        $input = new Input($values);
        $credential = $reset ? 'token' : 'currentPassword';
        $input->only(['password', 'passwordConfirmation', $credential]);
        $password = $input->confirmedPassword();

        return new self($password, $reset ? $input->text('token', 64, 64) : $input->text('currentPassword', 1, 128, false));
    }
}
