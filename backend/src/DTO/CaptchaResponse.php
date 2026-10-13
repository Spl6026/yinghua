<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [E-01／E-06／S-SEC-10] 不保存驗證答案；圖片與 Email 模式保留原有的可選欄位。 */
readonly class CaptchaResponse
{
    public function __construct(public string $challengeToken, public string $expiresAt, public ?string $imageDataUrl = null, public ?string $message = null) {}

    /** @return array{challengeToken:string,expiresAt:string,imageDataUrl?:string,message?:string} */
    public function toArray(): array
    {
        $data = ['challengeToken' => $this->challengeToken, 'expiresAt' => $this->expiresAt];
        if ($this->imageDataUrl !== null) {
            $data['imageDataUrl'] = $this->imageDataUrl;
        }
        if ($this->message !== null) {
            $data['message'] = $this->message;
        }

        return $data;
    }
}
