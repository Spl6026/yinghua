<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 模擬檢視的狀態；只提供操作者的摘要，不含 session、token 或完整帳號資料。 */
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
