<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class FilmScreening
{
    public function __construct(public string $id, public string $startsAt, public string $ticketUrl, public string $effectiveTicketUrl) {}

    /** @return array<string,string> */
    public function toArray(): array
    {
        return ['id' => $this->id,'startsAt' => $this->startsAt,'ticketUrl' => $this->ticketUrl,
            'effectiveTicketUrl' => $this->effectiveTicketUrl];
    }
}
