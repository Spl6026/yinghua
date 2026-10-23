<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [D：C19；E-27] 內部本人行程摘要；不把 member_id 暴露給前端作授權依據。 */
readonly class MemberItinerary
{
    public function __construct(public string $id, public string $memberId, public string $title, public ?string $tripDate, public int $version) {}

    /** @return array{id:string,title:string,tripDate:?string,version:int} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'tripDate' => $this->tripDate, 'version' => $this->version];
    }
}
