<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\Config;
use Yinghua\DTO\AccountSummary;
use Yinghua\DTO\IdentityResponse;
use Yinghua\DTO\Input;
use Yinghua\DTO\LoginInput;
use Yinghua\DTO\RegisterInput;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Csrf;
use Yinghua\Http\Response;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Services\Authentication;
use Yinghua\Services\Authorization;
use Yinghua\Services\Captcha;

class AuthController
{
    public function __construct(
        private readonly Authentication $authentication,
        private readonly Authorization $authorization,
        private readonly Captcha $captcha,
        private readonly Csrf $csrf,
        private readonly Config $config,
    ) {}

    public function csrf(ApiContext $context): Response
    {
        return new Response(200, ['token' => $this->csrf->token($context->csrf, $context->kind),
            'expiresIn' => $this->config->integer('CSRF_SECONDS', 3600),
            'termsVersion' => $this->config->string('TERMS_VERSION', 'local-development-v1')]);
    }

    public function captcha(ApiContext $context): Response
    {
        return new Response(200, $this->captcha->create($context->client)->toArray());
    }

    public function emailCaptcha(ApiContext $context): Response
    {
        $input = new Input($context->request->json());
        $input->only(['email']);

        return new Response(200, $this->captcha->create($context->client, $input->email())->toArray());
    }

    public function register(ApiContext $context): Response
    {
        $grant = $this->authentication->register(RegisterInput::fromArray($context->request->json()), $context->client, $context->sessionToken);

        return $this->signedIn($grant->account, $grant->token, $context, 201);
    }

    public function login(ApiContext $context): Response
    {
        $grant = $this->authentication->login($context->kind, LoginInput::fromArray($context->request->json(), $context->kind), $context->client, $context->sessionToken);

        return $this->signedIn($grant->account, $grant->token, $context);
    }

    public function me(ApiContext $context): Response
    {
        return new Response(200, $this->identity($context->actor())->toArray());
    }

    public function logout(ApiContext $context): Response
    {
        $this->authentication->logout($context->actor(), $context->sessionToken, $context->client);

        return $this->signedOut($context);
    }




    private function signedIn(Account $account, string $token, ApiContext $context, int $status = 200): Response
    {
        $anonymous = $this->csrf->rotate($context->kind);

        return new Response(
            $status,
            [...$this->identity($account)->toArray(), 'csrfToken' => $this->csrf->token($anonymous, $context->kind)],
            [$this->csrf->authCookie($context->kind, $token), $this->csrf->cookie($anonymous, $context->kind)],
        );
    }

    private function signedOut(ApiContext $context): Response
    {
        $anonymous = $this->csrf->rotate($context->kind);

        return new Response(204, [], [$this->csrf->authCookie($context->kind, '', true), $this->csrf->cookie($anonymous, $context->kind)]);
    }

    private function identity(Account $account): IdentityResponse
    {
        $permissions = $account->kind === IdentityKind::Admin ? $this->authorization->permissions($account) : [];

        return new IdentityResponse(AccountSummary::fromAccount($account), $permissions);
    }
}
