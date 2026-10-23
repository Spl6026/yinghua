<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** [S：功能規格 G32；原型 SEO 三選項] */
enum SeoRobots: string
{
    case Index = 'index,follow';
    case Noindex = 'noindex,follow';
    case Private = 'noindex,nofollow';
}
