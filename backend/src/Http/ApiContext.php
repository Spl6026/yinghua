<?php

declare(strict_types=1);

namespace Yinghua\Http;

use Yinghua\DTO\ClientContext;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;

readonly class ApiContext
{
    /** @param array<string,string> $parameters */
    public function __construct(
        public Request $request,
        public IdentityKind $kind,
        public ClientContext $client,
        public CsrfContext $csrf,
        public ?Account $account,
        public string $sessionToken,
        public array $parameters,
    ) {}

    public function actor(): Account
    {
        return $this->account ?? throw new ApplicationError('UNAUTHENTICATED', '請先登入。');
    }

    public function id(): string
    {
        return $this->parameters['id'] ?? throw new ApplicationError('NOT_FOUND', '找不到此資料。');
    }
}
