<?php

declare(strict_types=1);

namespace Yinghua\Exceptions;

/** 資料庫唯一約束衝突轉成的內容衝突，讓 Service 可以處理。 */
class ContentConflict extends \RuntimeException {}
