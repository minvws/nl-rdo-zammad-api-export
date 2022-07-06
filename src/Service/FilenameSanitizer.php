<?php

namespace Minvws\Zammad\Service;

class FilenameSanitizer extends \IndieHD\FilenameSanitizer\FilenameSanitizer
{
    public function __construct(string $filename)
    {
        $this->illegalCharacters['extra'] = [
            "'",
        ];

        parent::__construct($filename);
    }
}