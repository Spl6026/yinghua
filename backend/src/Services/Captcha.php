<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Support\Secrets;
use RuntimeException;
use Throwable;
use Yinghua\Config;
use Yinghua\Contracts\Clock;
use Yinghua\Contracts\Mailer;
use Yinghua\Contracts\Transactions;
use Yinghua\DTO\CaptchaResponse;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\RegisterInput;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Repositories\ChallengeRepository;

class Captcha
{
    public function __construct(
        private readonly ChallengeRepository $repository,
        private readonly Transactions $transactions,
        private readonly RateLimiter $limiter,
        private readonly Secrets $secrets,
        private readonly Clock $clock,
        private readonly Config $config,
        private readonly Mailer $mailer,
    ) {}

    public function create(ClientContext $client, ?string $email = null): CaptchaResponse
    {
        $this->limiter->check('captcha-ip', $client->ipHash, 30, 60);
        $this->limiter->check('captcha-session', $client->anonymousId, 15, 60);
        if ($email !== null) {
            $this->limiter->check('captcha-email', $email, 3, 900);
        }
        $token = Secrets::token();
        $id = Secrets::uuid();
        $answer = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = $this->clock->now() + $this->config->integer('CAPTCHA_SECONDS', 300);
        $this->transactions->run(fn() => $this->repository->create(
            $id,
            hash('sha256', $token),
            $email === null ? 'image' : 'email',
            $this->secrets->keyed('captcha-answer', $id . ':' . $answer),
            hash('sha256', $client->anonymousId),
            $email === null ? null : $this->secrets->keyed('captcha-email', $email),
            $expires,
            $this->config->integer('CAPTCHA_ATTEMPTS', 3),
        ));
        if ($email === null) {
            return new CaptchaResponse($token, gmdate('c', $expires), imageDataUrl: $this->image($answer));
        }
        try {
            $this->mailer->send($email, '映畫竹塹註冊驗證碼', "您的註冊驗證碼：{$answer}\n有效期限五分鐘。若不是您申請，請忽略此信。");
        } catch (Throwable) {
            $this->repository->revoke($id);
            throw new ApplicationError('MAIL_UNAVAILABLE', '驗證信暫時無法寄送，請稍後再試或使用圖片驗證。');
        }

        return new CaptchaResponse($token, gmdate('c', $expires), message: '驗證碼已寄送，請查看信箱。');
    }

    /** 在註冊交易內呼叫；錯誤回傳而不 throw，使錯誤次數能提交。 */
    public function consume(RegisterInput $input, ClientContext $client): bool
    {
        $challenge = $this->repository->lock($input->challengeToken);
        if ($challenge === null || $challenge->unavailable || $challenge->expiresAt <= $this->clock->now()
            || $challenge->attempts >= $challenge->maxAttempts
            || !hash_equals($challenge->bindingHash, hash('sha256', $client->anonymousId))) {
            return false;
        }
        $recipientMatches = $challenge->recipientHash === null
            || hash_equals($challenge->recipientHash, $this->secrets->keyed('captcha-email', $input->email));
        if (!$recipientMatches || !hash_equals($challenge->answerHmac, $this->secrets->keyed('captcha-answer', $challenge->id . ':' . $input->answer))) {
            $this->repository->failed($challenge->id);

            return false;
        }
        $this->repository->consume($challenge->id);

        return true;
    }

    private function image(string $answer): string
    {
        // [S-016／S-SEC-10] 答案只畫入 PNG，不回傳文字、SVG 或 DOM 屬性。
        $image = imagecreatetruecolor(230, 64);
        if ($image === false) {
            throw new RuntimeException('GD image creation failed.');
        }
        $background = imagecolorallocate($image, 245, 240, 230);
        $ink = imagecolorallocate($image, 30, 38, 40);
        $noise = imagecolorallocate($image, 170, 160, 140);
        if ($background === false || $ink === false || $noise === false) {
            throw new RuntimeException('GD color allocation failed.');
        }
        imagefill($image, 0, 0, $background);
        for ($i = 0; $i < 180; ++$i) {
            imagesetpixel($image, random_int(0, 229), random_int(0, 63), $noise);
        }
        for ($i = 0; $i < 6; ++$i) {
            imagestring($image, 5, 20 + $i * 32, random_int(15, 32), $answer[$i], $ink);
            imageline($image, random_int(0, 229), random_int(0, 63), random_int(0, 229), random_int(0, 63), $noise);
        }
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        if ($png === false) {
            throw new RuntimeException('PNG encoding failed.');
        }

        return 'data:image/png;base64,' . base64_encode($png);
    }
}
