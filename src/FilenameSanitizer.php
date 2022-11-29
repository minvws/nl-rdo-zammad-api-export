<?php

namespace Minvws\Zammad;

class FilenameSanitizer extends \IndieHD\FilenameSanitizer\FilenameSanitizer
{
    public function stripAdditionalCharacters(): self
    {
        $this->setFilename(str_replace(["'", chr(127), '#'], '', $this->getFilename()));

        return $this;
    }
}
