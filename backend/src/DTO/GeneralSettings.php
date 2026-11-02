<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

/** 一般設定與純文字的條款／隱私政策；字數上限只是防止過大輸入，留空時沿用預設說明。 */
readonly class GeneralSettings
{
    public const array FIELDS = ['contactEmail', 'termsVersion', 'privacyVersion', 'termsText', 'privacyText'];

    public function __construct(
        public string $contactEmail = '',
        public string $termsVersion = '',
        public string $privacyVersion = '',
        public string $termsText = '',
        public string $privacyText = '',
    ) {}

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values): self
    {
        $input = new Input($values);
        $input->only(self::FIELDS);
        $email = $input->optionalText('contactEmail', 254);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw ApplicationError::invalid('contactEmail', '請輸入有效電子郵件或留空。');
        }
        return new self(
            $email,
            $input->optionalText('termsVersion', 80),
            $input->optionalText('privacyVersion', 80),
            $input->optionalText('termsText', 20000),
            $input->optionalText('privacyText', 20000),
        );
    }

    /** @return array<string,string> */
    public function toArray(): array
    {
        return ['contactEmail' => $this->contactEmail, 'termsVersion' => $this->termsVersion, 'privacyVersion' => $this->privacyVersion,
            'termsText' => $this->termsText, 'privacyText' => $this->privacyText];
    }

    public function consentVersion(string $fallback): string
    {
        return $this->termsText === '' && $this->privacyText === ''
            && $this->termsVersion === '' && $this->privacyVersion === ''
            ? $fallback
            : 'policy-' . hash(
                'sha256',
                json_encode([$this->termsVersion, $this->privacyVersion, $this->termsText, $this->privacyText], JSON_THROW_ON_ERROR),
            );
    }
}
