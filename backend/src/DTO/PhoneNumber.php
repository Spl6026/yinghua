<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

/** 電話號碼格式檢查與 tel: 連結產生；只檢查格式，不代表電話必填。 */
readonly class PhoneNumber
{
    private function __construct(public string $text, public string $uri) {}

    public static function fromText(string $text): self
    {
        if ($text === '') {
            return new self('', '');
        }
        // 支援國碼、括號、常用分隔與分機；不限定台灣市話或要求固定長度。
        if (mb_strlen($text) > 80
            || !preg_match(
                '/^(?<number>\+?[0-9(). -]*[0-9][0-9(). -]*?)(?:\s*(?:ext\.?|x|#|分機)\s*(?<extension>[0-9]+))?$/iD',
                $text,
                $matches,
            )
            || !preg_match('/^[+0-9 .-]+$/D', preg_replace('/\([0-9]+\)/', '0', $matches['number']) ?? '')) {
            throw ApplicationError::invalid('phone', '請輸入有效電話，可使用國碼、空格、括號、連字號及分機。');
        }
        $number = str_replace([' ', '(', ')', '.', '-'], '', $matches['number']);
        $extension = $matches['extension'] ?? '';

        return new self($text, 'tel:' . $number . ($extension === '' ? '' : ';ext=' . $extension));
    }
}
