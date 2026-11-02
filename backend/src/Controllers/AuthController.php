<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\Config;
use Yinghua\DTO\AccountSummary;
use Yinghua\DTO\IdentityResponse;
use Yinghua\DTO\PreviewStatus;
use Yinghua\DTO\Input;
use Yinghua\DTO\LoginInput;
use Yinghua\DTO\PasswordInput;
use Yinghua\DTO\RegisterInput;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Csrf;
use Yinghua\Http\Response;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Services\Authentication;
use Yinghua\Services\AdminPreview;
use Yinghua\Services\Authorization;
use Yinghua\Services\Captcha;
use Yinghua\Services\PasswordReset;
use Yinghua\Services\PublishedPolicies;
use Yinghua\Support\Uuid;

class AuthController
{
    public function __construct(
        private readonly Authentication $authentication,
        private readonly Authorization $authorization,
        private readonly Captcha $captcha,
        private readonly PasswordReset $passwordReset,
        private readonly Csrf $csrf,
        private readonly Config $config,
        private readonly AdminPreview $preview,
        private readonly PublishedPolicies $policies,
    ) {}

    public function csrf(ApiContext $context): Response
    {
        return new Response(200, ['token' => $this->csrf->token($context->csrf, $context->kind),
            'expiresIn' => $this->config->csrfSeconds(),
            'termsVersion' => $this->policies->configurationVersion()]);
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
        $grant = $this->authentication->register(
            RegisterInput::fromArray($context->request->json()),
            $context->client,
            $context->sessionToken,
        );

        return $this->signedIn($grant->account, $grant->token, $context, 201);
    }

    public function login(ApiContext $context): Response
    {
        $grant = $this->authentication->login(
            $context->kind,
            LoginInput::fromArray($context->request->json(), $context->kind),
            $context->client,
            $context->sessionToken,
        );

        return $this->signedIn($grant->account, $grant->token, $context);
    }

    public function me(ApiContext $context): Response
    {
        $identity = $context->identity;
        $preview = null;
        if ($context->kind === IdentityKind::Admin) {
            $owner = $context->owner();
            $preview = new PreviewStatus(
                $identity?->isPreview() ?? false,
                $identity?->previewAvailable() ?? true,
                $identity?->previewAdminId,
                $owner->id,
                $owner->account,
                $owner->displayName,
            );
        }

        return new Response(200, $this->identity($context->actor(), $identity?->previewAvailable() ?? true, $preview)->toArray());
    }

    public function logout(ApiContext $context): Response
    {
        $this->authentication->logout($context->owner(), $context->sessionToken, $context->client);

        return $this->signedOut($context);
    }

    public function forgotPassword(ApiContext $context): Response
    {
        $input = new Input($context->request->json());
        $input->only(['email']);
        $pending = $this->passwordReset->prepare($context->kind, $input->email(), $context->client);
        $callbacks = $pending === null ? [] : [fn() => $this->passwordReset->deliver($pending, $context->client)];

        return new Response(200, ['message' => '若帳號有效，將寄送密碼重設信。'], [], [], $callbacks);
    }

    public function resetPassword(ApiContext $context): Response
    {
        $this->passwordReset->reset($context->kind, PasswordInput::fromArray($context->request->json(), true), $context->client);

        return $this->signedOut($context);
    }

    public function changePassword(ApiContext $context): Response
    {
        $this->passwordReset->change($context->actor(), PasswordInput::fromArray($context->request->json(), false), $context->client);

        return $this->signedOut($context);
    }

    public function previewTargets(ApiContext $context): Response
    {
        return new Response(200, ['targets' => $this->preview->targets($context->owner())]);
    }

    public function startPreview(ApiContext $context): Response
    {
        $input = new Input($context->request->json());
        $input->only(['adminId']);
        $id = $input->text('adminId', 36, 36);
        if (!Uuid::isValid($id)) {
            throw ApplicationError::invalid('adminId', '請提供有效管理員 ID。');
        }

        return $this->switchPreview($context, $id);
    }

    public function stopPreview(ApiContext $context): Response
    {
        (new Input($context->request->json()))->only([]);

        return $this->switchPreview($context, null);
    }

    private function switchPreview(ApiContext $context, ?string $targetId): Response
    {
        $grant = $this->preview->switch($context->owner(), $context->sessionToken, $targetId, $context->client);
        $anonymous = $this->csrf->rotate($context->kind);

        return new Response(
            200,
            ['csrfToken' => $this->csrf->token($anonymous, $context->kind)],
            [$this->csrf->authCookie($context->kind, $grant->token), $this->csrf->cookie($anonymous, $context->kind)],
        );
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

    private function identity(Account $account, bool $available = true, ?PreviewStatus $preview = null): IdentityResponse
    {
        $permissions = $account->kind === IdentityKind::Admin && $available ? $this->authorization->permissions($account) : [];

        return new IdentityResponse(AccountSummary::fromAccount($account), $permissions, $preview);
    }
}
