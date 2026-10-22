<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use HTMLPurifier;
use HTMLPurifier_Config;
use Yinghua\Contracts\RichTextSanitizer;

class HtmlPurifierSanitizer implements RichTextSanitizer
{
    private readonly HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Allowed', 'p,br,h2,h3,h4,ul,ol,li,strong,em,b,i,blockquote,a[href|title],img[src|alt|width|height]');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);
        $config->set('Cache.DefinitionImpl', null);
        $this->purifier = new HTMLPurifier($config);
    }

    public function clean(string $html): string
    {
        return $this->purifier->purify($html);
    }
}
