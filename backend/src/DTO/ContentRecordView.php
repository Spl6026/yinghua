<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [E-31] 只對已授權管理頁輸出選定業務欄位；公開投影保持原白名單。 */
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
