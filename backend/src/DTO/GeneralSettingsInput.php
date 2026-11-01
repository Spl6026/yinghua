<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

readonly class GeneralSettingsInput
{
    public function __construct(public int $version, public int $seoVersion, public string $siteName, public string $description, public GeneralSettings $values) {}

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values): self
    {
        $input = new Input($values);
        $input->only(['version', 'seoVersion', 'siteName', 'description', 'values']);
        foreach (['version', 'seoVersion'] as $key) {
            if (!is_int($values[$key] ?? null) || $values[$key] < 0 || $values[$key] >= 4294967295) {
                throw ApplicationError::invalid($key, '請重新讀取設定版本。');
            }
        }
        return new self($values['version'], $values['seoVersion'], $input->text('siteName', 0, 160), $input->text('description', 0, 500), GeneralSettings::fromArray(SeoSettingsInput::object($values['values'] ?? null, 'values')));
    }
}
