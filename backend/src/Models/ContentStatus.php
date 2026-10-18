<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** [E-18] 共用內容狀態；排程由 published + publishAt 表達。 */
enum ContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
