<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** SEO 設定中的三種搜尋引擎索引選項。 */
enum SeoRobots: string
{
    case Index = 'index,follow';
    case Noindex = 'noindex,follow';
    case Private = 'noindex,nofollow';
}
