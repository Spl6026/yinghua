<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class AttractionContent
{
    public function __construct(public string $summary, public string $description, public string $summarySource, public string $descriptionSource) {}

    /** @return array{summary:string,description:string} */
    public function sources(): array
    {
        return ['summary' => $this->summarySource, 'description' => $this->descriptionSource];
    }
}
