<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** 各類內容共用的狀態；排程發布用 published 搭配未來的 publishAt 表示。 */
enum ContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
