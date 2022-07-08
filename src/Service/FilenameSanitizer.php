<?php

namespace Minvws\Zammad\Service;

class FilenameSanitizer extends \IndieHD\FilenameSanitizer\FilenameSanitizer
{
    public function stripAdditionalCharacters(): self
    {
        $this->setFilename(str_replace(["'", '"', '#'], '', $this->getFilename()));

        return $this;
    }
}