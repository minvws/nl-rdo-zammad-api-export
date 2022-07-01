<?php

declare(strict_types=1);

namespace Minvws\Zammad\Service;

use IndieHD\FilenameSanitizer\FilenameSanitizer;

class Sanitize {

    static function path(...$items) {
        $path = [];

        array_walk_recursive($items, function ($item) use (&$path) {
            $sanitizer = new FilenameSanitizer(strval($item));
            $sanitizer->stripIllegalFilesystemCharacters();
            $sanitizer->stripRiskyCharacters();
            $path[] = $sanitizer->getFilename();
        });

        return join(DIRECTORY_SEPARATOR, $path);
    }
}
