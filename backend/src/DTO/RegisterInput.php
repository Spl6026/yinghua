<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use SensitiveParameter;
use Yinghua\Exceptions\ApplicationError;

readonly class RegisterInput
{
    public function __construct(
        public string $email,
        public string $displayName,
        #[SensitiveParameter]
        public string $password,
        public string $challengeToken,
        public string $answer,
        public string $termsVersion,
    ) {}

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        $input = new Input($values);
        $input->only(['email', 'displayName', 'password', 'passwordConfirmation', 'challengeToken', 'answer', 'acceptTerms', 'termsVersion']);
        $password = $input->confirmedPassword();
        if (!$input->boolean('acceptTerms')) {
            throw ApplicationError::invalid('acceptTerms', '請先閱讀並同意條款。');
        }

        return new self(
            $input->email(),
            $input->text('displayName', 1, 80),
            $password,
            $input->text('challengeToken', 64, 64),
            $input->text('answer', 1, 12),
            $input->text('termsVersion', 1, 80),
        );
    }
}
