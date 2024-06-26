<?php

declare(strict_types=1);

namespace Minvws\Zammad\Twig;

use Minvws\Zammad\Path;
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

    public function sanitize(string $path): string
    {
        return Path::fromString($path)->getPath();
    }
}
