<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [U-10／E-21] 已校驗的 Google 候選；不直接將提供者 JSON 當成本站輸入。 */
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
            'latitude' => $this->latitude,'longitude' => $this->longitude,'hours' => $this->openingHours->summary(),'openingHours' => $this->openingHours->toArray(),
            'websiteUrl' => $this->websiteUrl,'mapUrl' => $this->mapUrl,'editorialSummary' => $this->editorialSummary,'attributions' => $this->attributions];
    }
}
