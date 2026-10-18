<?php

declare(strict_types=1);

namespace Yinghua\Exceptions;

/** [E-18] 儲存層唯一約束轉為可由 Service 處理的內容衝突。 */
class ContentConflict extends \RuntimeException {}
