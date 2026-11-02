<?php

declare(strict_types=1);

namespace Yinghua\Exceptions;

/**
 * API 回應的錯誤代碼與對應的 HTTP 狀態。
 * 集中在這裡，拼錯代碼會在靜態檢查時被抓到，不會悄悄變成 500。
 */
enum ErrorCode: string
{
    case BadRequest = 'BAD_REQUEST';
    case Unauthenticated = 'UNAUTHENTICATED';
    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case Forbidden = 'FORBIDDEN';
    case CsrfInvalid = 'CSRF_INVALID';
    case PreviewReadOnly = 'PREVIEW_READ_ONLY';
    case NotFound = 'NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case Conflict = 'CONFLICT';
    case PayloadTooLarge = 'PAYLOAD_TOO_LARGE';
    case UnsupportedMediaType = 'UNSUPPORTED_MEDIA_TYPE';
    case ValidationFailed = 'VALIDATION_FAILED';
    case RateLimited = 'RATE_LIMITED';
    case InternalError = 'INTERNAL_ERROR';
    case ServiceUnavailable = 'SERVICE_UNAVAILABLE';
    case MailUnavailable = 'MAIL_UNAVAILABLE';
    case PublicationPending = 'PUBLICATION_PENDING';
    case MapUnavailable = 'MAP_UNAVAILABLE';

    public function status(): int
    {
        return match ($this) {
            self::BadRequest => 400,
            self::Unauthenticated, self::InvalidCredentials => 401,
            self::Forbidden, self::CsrfInvalid, self::PreviewReadOnly => 403,
            self::NotFound => 404,
            self::MethodNotAllowed => 405,
            self::Conflict => 409,
            self::PayloadTooLarge => 413,
            self::UnsupportedMediaType => 415,
            self::ValidationFailed => 422,
            self::RateLimited => 429,
            self::InternalError => 500,
            self::ServiceUnavailable, self::MailUnavailable, self::PublicationPending, self::MapUnavailable => 503,
        };
    }
}
