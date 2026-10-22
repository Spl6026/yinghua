<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class PreparedImport
{
    /** @param list<string> $warnings */
    public function __construct(
        public ImportItem $item,
        public NewsInput|OfferInput|MerchantInput $input,
        public bool $creating,
        public bool $unchanged,
        public int $version,
        public array $warnings = [],
    ) {}

    /** @return array<string,mixed> */
    public function summary(): array
    {
        return ['id' => $this->item->id,'action' => $this->unchanged ? 'unchanged' : ($this->creating ? 'create' : 'update'),
            'version' => $this->unchanged ? $this->version : $this->version + 1,'warnings' => $this->warnings];
    }

    /** @return array<string,mixed> */
    public function manifestItem(): array
    {
        $values = ['id' => $this->item->id,...$this->item->values];
        $publish = $this->input->fields['publish_at'] ?? null;
        if ($this->creating && is_string($publish)) {
            $values['publishAt'] = str_replace(' ', 'T', $publish) . '+00:00';
        }
        return $values;
    }
}
