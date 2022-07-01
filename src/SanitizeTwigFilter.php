<?php

declare(strict_types=1);

namespace Minvws\Zammad;

use Minvws\Zammad\Service\Sanitize;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class SanitizeTwigFilter extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('sanitize', [$this, 'sanitize']),
        ];
    }

    public function sanitize($items) {
        return Sanitize::path($items);
    }
}
