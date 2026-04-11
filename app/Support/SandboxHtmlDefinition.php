<?php

namespace App\Support;

use HTMLPurifier_HTMLDefinition;
use Stevebauman\Purify\Definitions\Html5Definition;

class SandboxHtmlDefinition extends Html5Definition
{
    public static function apply(HTMLPurifier_HTMLDefinition $definition)
    {
        parent::apply($definition);

        // TipTap CommentMark extension uses data-thread-id on spans
        $definition->addAttribute('span', 'data-thread-id', 'Text');

        // TipTap Highlight/Mark extension uses data-color on mark
        $definition->addAttribute('mark', 'data-color', 'Text');
    }
}
