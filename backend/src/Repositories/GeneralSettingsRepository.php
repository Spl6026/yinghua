<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\DTO\GeneralSettings;
use Yinghua\DTO\GeneralSettingsDocument;
use Yinghua\DTO\SeoSettingsInput;

/** [S：G28/I28；E-36] 固定key白名單，不提供任意設定寫入。 */
class GeneralSettingsRepository
{
    public function __construct(private readonly Database $database) {}

    public function read(bool $lock = false): GeneralSettingsDocument
    {
        $values = $this->database->row("SELECT value_json,version,updated_at FROM site_settings WHERE `key`='general' AND published=1" . ($lock ? ' FOR UPDATE' : ''));
        if ($values === null) {
            return new GeneralSettingsDocument();
        }
        $row = new Row($values);
        $data = SeoSettingsInput::object(json_decode($row->string('value_json'), true, 32, JSON_THROW_ON_ERROR), 'values');
        return new GeneralSettingsDocument($row->integer('version'), GeneralSettings::fromArray($data), $row->string('updated_at'));
    }

    public function save(GeneralSettings $values, int $version, string $actor, bool $creating): void
    {
        $sql = $creating ? "INSERT INTO site_settings(`key`,value_json,published,version,updated_by) VALUES('general',:json,1,:version,:actor)"
            : "UPDATE site_settings SET value_json=:json,published=1,version=:version,updated_by=:actor,updated_at=UTC_TIMESTAMP() WHERE `key`='general'";
        $this->database->execute($sql, ['json' => json_encode($values->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'version' => $version, 'actor' => $actor]);
    }
}
