<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [U-06／E-01] 對外只提供操作者摘要；不持有 session、token 或完整帳號。 */
readonly class PreviewStatus
{
    public function __construct(
        public bool $active,
        public bool $targetAvailable,
        public ?string $targetId,
        public string $operatorId,
        public ?string $operatorAccount,
        public string $operatorDisplayName,
    ) {}

    /** @return array{active:bool,readOnly:bool,targetAvailable:bool,targetId:?string,operator:array{id:string,account:?string,displayName:string}} */
    public function toArray(): array
    {
        return ['active' => $this->active, 'readOnly' => true, 'targetAvailable' => $this->targetAvailable, 'targetId' => $this->targetId,
            'operator' => ['id' => $this->operatorId, 'account' => $this->operatorAccount, 'displayName' => $this->operatorDisplayName]];
    }
}
