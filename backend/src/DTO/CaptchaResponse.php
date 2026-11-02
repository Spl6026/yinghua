<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 驗證碼回應不含答案；圖片模式帶 imageDataUrl，Email 模式帶 message。 */
readonly class CaptchaResponse
{
    public function __construct(
        public string $challengeToken,
        public string $expiresAt,
        public ?string $imageDataUrl = null,
        public ?string $message = null,
    ) {}

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
