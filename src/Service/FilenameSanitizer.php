<?php

namespace Minvws\Zammad\Service;

class FilenameSanitizer extends \IndieHD\FilenameSanitizer\FilenameSanitizer
{
    public function stripQuotes(): self
    {
        $this->setFilename(str_replace(["'", '"'], '', $this->getFilename()));

        return $this;
    }
}