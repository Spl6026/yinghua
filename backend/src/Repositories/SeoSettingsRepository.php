<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use UnexpectedValueException;
use Yinghua\DTO\SeoDefaults;
use Yinghua\DTO\SeoSettingsDocument;
use Yinghua\Support\Json;

/** 目前只開放 seo 這個設定 key，不接受外部傳入任意 key。 */
class SeoSettingsRepository
{
    public function __construct(private readonly Database $database) {}

    public function read(bool $lock = false): SeoSettingsDocument
    {
        // 儲存時一律寫入 published=1，因此讀取不再另外篩選發布狀態。
        $values = $this->database->row(
            "SELECT value_json,version,updated_at FROM site_settings WHERE `key`='seo'" . ($lock ? ' FOR UPDATE' : ''),
        );
        if ($values === null) {
            return new SeoSettingsDocument(0, new SeoDefaults());
        }
        $row = new Row($values);
        $decoded = json_decode($row->string('value_json'), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new UnexpectedValueException('Invalid SEO defaults.');
        }
        $fields = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                throw new UnexpectedValueException('Invalid SEO key.');
            }
            $fields[$key] = $value;
        }

        return new SeoSettingsDocument($row->integer('version'), SeoDefaults::fromArray($fields), [], $row->string('updated_at'));
    }

    public function save(SeoDefaults $defaults, int $version, string $actor, bool $creating): void
    {
        $fields = [
            'json' => Json::encode($defaults->toArray()),
            'version' => $version,
            'actor' => $actor,
        ];
        $sql = $creating
            ? "INSERT INTO site_settings(`key`,value_json,published,version,updated_by) VALUES('seo',:json,1,:version,:actor)"
            : "UPDATE site_settings SET value_json=:json,published=1,version=:version,updated_by=:actor,updated_at=UTC_TIMESTAMP() WHERE `key`='seo'";
        $this->database->execute($sql, $fields);
    }
}
