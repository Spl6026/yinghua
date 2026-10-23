<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\SeoRobots;
use Yinghua\Policies\SeoTargetPolicy;
use Yinghua\Policies\SeoUrlPolicy;

/** [E-28] 完整文件／version 為技術更新契約，不要求管理員填滿選填 SEO。 */
readonly class SeoSettingsInput
{
    /** @param list<SeoOverride> $overrides */
    public function __construct(public int $version, public SeoDefaults $defaults, public array $overrides) {}

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values, SeoTargetPolicy $targets): self
    {
        $input = new Input($values);
        $input->only(['version','defaults','overrides']);
        $version = $values['version'] ?? null;
        if (!is_int($version) || $version < 0 || $version >= 4294967295) {
            throw ApplicationError::invalid('version', '請重新讀取有效設定版本。');
        }
        $defaults = self::object($values['defaults'] ?? null, 'defaults');
        $overrides = [];
        $seen = [];
        foreach ($input->objects('overrides') as $index => $row) {
            try {
                (new Input($row))->only(['target','title','description','keywords','canonicalPath','robots','ogImageUrl']);
                [$type, $target] = $targets->parse(self::object($row['target'] ?? null, 'target'));
                $field = static function (string $key, int $max, bool $blankInherits = false) use ($row): ?string {
                    if (!array_key_exists($key, $row) || $row[$key] === null) {
                        return null;
                    }
                    $value = (new Input($row))->text($key, 0, $max);
                    return $blankInherits && $value === '' ? null : $value;
                };
                $robots = $field('robots', 40, true);
                if ($robots !== null && SeoRobots::tryFrom($robots) === null) {
                    throw ApplicationError::invalid('robots', '請選有效索引政策。');
                }
                $image = $field('ogImageUrl', 2000, true);
                $override = new SeoOverride(
                    $type,
                    $target,
                    $field('title', 160, true),
                    $field('description', 500, true),
                    $field('keywords', 1000),
                    $field('canonicalPath', 180, true),
                    $robots,
                    $image === null ? null : SeoUrlPolicy::https($image, 'ogImageUrl'),
                );
                $targets->assertCanonical($override);
                if (isset($seen[$override->key()])) {
                    throw ApplicationError::invalid('target', '同一目標只能有一筆覆寫。');
                }
                $seen[$override->key()] = true;
                $overrides[] = $override;
            } catch (ApplicationError $error) {
                $errors = [];
                foreach ($error->fieldErrors as $field => $message) {
                    $errors['overrides.' . $index . '.' . $field] = $message;
                }
                throw new ApplicationError($error->errorCode, $error->getMessage(), $errors);
            }
        }
        try {
            return new self($version, SeoDefaults::fromArray($defaults), $overrides);
        } catch (ApplicationError $error) {
            $errors = [];
            foreach ($error->fieldErrors as $field => $message) {
                $errors[str_starts_with($field, 'defaults.') ? $field : 'defaults.' . $field] = $message;
            }
            throw new ApplicationError($error->errorCode, $error->getMessage(), $errors);
        }
    }

    /** @return array<string,mixed> */
    public static function object(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw ApplicationError::invalid($field, '請提供有效設定物件。');
        }
        $result = [];
        foreach ($value as $key => $entry) {
            if (!is_string($key)) {
                throw ApplicationError::invalid($field, '請提供有效設定物件。');
            }
            $result[$key] = $entry;
        }
        return $result;
    }
}
