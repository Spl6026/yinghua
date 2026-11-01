<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class GoogleSummary
{
    /** @param list<array{provider:string,providerUri:string}> $attributions */
    public function __construct(public string $text = '', public string $mapUrl = '', public array $attributions = []) {}

    /** @return array{status:string,text:string,mapUrl:string,attribution:string,attributions:list<array{provider:string,providerUri:string}>} */
    public function toArray(): array
    {
        return ['status' => $this->text === '' ? 'unavailable' : 'available', 'text' => $this->text,
            'mapUrl' => $this->mapUrl, 'attribution' => 'Google Maps', 'attributions' => $this->attributions];
    }
}
