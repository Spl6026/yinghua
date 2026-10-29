<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class ContentNeighbor
{
    public function __construct(public string $slug, public string $url, public string $title, public string $image, public string $alt) {}

    /** @param array<string,mixed> $item */
    public static function fromArray(array $item): self
    {
        $text = static fn(string $key): string => is_string($item[$key] ?? null) ? $item[$key] : '';
        return new self($text('slug'), $text('url'), $text('title') ?: $text('name'), $text('image'), $text('imageAlt'));
    }

    /** @return array{slug:string,url:string,title:string,image:string,alt:string} */
    public function toArray(): array
    {
        return ['slug' => $this->slug,'url' => $this->url,'title' => $this->title,'image' => $this->image,'alt' => $this->alt];
    }
}
