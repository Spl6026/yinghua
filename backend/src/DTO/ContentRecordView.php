<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 管理頁使用的內容輸出，只給已授權的管理員；公開頁仍走原本的欄位白名單。 */
readonly class ContentRecordView
{
    public function __construct(private NewsPost|Offer|Merchant $record) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $data = $this->record instanceof Merchant ? [] : $this->record->publicArray();
        foreach ($this->record->fields as $key => $value) {
            $name = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
            if (in_array($key, ['publish_at','event_starts_at','event_ends_at'], true)) {
                $value = is_string($value) ? str_replace(' ', 'T', $value) . '+00:00' : null;
            }
            $data[$name] = $value;
        }
        if ($this->record instanceof NewsPost) {
            $data['eventStartAt'] = $data['eventStartsAt'] ?? null;
            $data['eventEndAt'] = $data['eventEndsAt'] ?? null;
            unset($data['eventStartsAt'], $data['eventEndsAt']);
        }
        if ($this->record instanceof Offer) {
            $data['merchantAvailable'] = $this->record->merchantAvailable;
            $data['discount'] = $data['discountText'] ?? '';
            unset($data['discountText']);
        }
        return [...$data, 'id' => $this->record->id, 'version' => $this->record->version];
    }
}
