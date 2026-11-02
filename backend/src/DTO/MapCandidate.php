<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 已經過檢查的 Google 地點候選；不直接把 Google 回傳的 JSON 當成本站資料。 */
readonly class MapCandidate
{
    /** @param list<array{provider:string,providerUri:string}> $attributions */
    public function __construct(
        public string $googlePlaceId,
        public string $name,
        public string $address,
        public string $district,
        public ?float $latitude,
        public ?float $longitude,
        public OpeningHours $openingHours,
        public string $websiteUrl,
        public string $mapUrl,
        public string $editorialSummary,
        public array $attributions,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['googlePlaceId' => $this->googlePlaceId,'name' => $this->name,'address' => $this->address,'district' => $this->district,
            'latitude' => $this->latitude,'longitude' => $this->longitude,'hours' => $this->openingHours->summary(),
            'openingHours' => $this->openingHours->toArray(),
            'websiteUrl' => $this->websiteUrl,'mapUrl' => $this->mapUrl,'editorialSummary' => $this->editorialSummary,
            'attributions' => $this->attributions];
    }
}
