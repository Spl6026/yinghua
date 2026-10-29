<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class ContentNeighbors
{
    public function __construct(public ?ContentNeighbor $previous, public ?ContentNeighbor $next) {}

    /** @return array{previous:array{slug:string,url:string,title:string,image:string,alt:string}|null,next:array{slug:string,url:string,title:string,image:string,alt:string}|null} */
    public function toArray(): array
    {
        return ['previous' => $this->previous?->toArray(), 'next' => $this->next?->toArray()];
    }
}
