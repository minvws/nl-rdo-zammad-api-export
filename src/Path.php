<?php

namespace Minvws\Zammad;

use Minvws\Zammad\Service\FilenameSanitizer;

class Path
{
    protected ?Path $parent;
    protected bool $isRoot = false;

    /**
     * Constructs a new path based on a previous path (if not the root path). The name will be automatically sanitized
     * When isRoot is true, this will be the root path (can't have a parent on a root node)
     */
    public function __construct(?Path $parent, string $name, $isRoot = false)
    {
        if ($isRoot && $parent) {
            throw new \Exception("Cannot be root when you have a parent directory");
        }

        $this->isRoot = $isRoot;
        $this->parent = $parent;
        $this->name = $this->sanitize($name);
    }

    /**
     * Returns a Path (with parent paths) from a given string  (ie: /foo/bar/baz)
     */
    public static function fromString(string $fullPath): Path
    {
        // Make sure we start with a root node when our string starts with /
        $isRoot = false;
        if (str_starts_with('/', $fullPath)) {
            $isRoot = true;
            $fullPath = substr($fullPath, 1);
        }

        $parts = explode(DIRECTORY_SEPARATOR, $fullPath);

        $path = null;
        foreach ($parts as $part) {
            $path = new Path($path, $part, $isRoot);
            $isRoot = false;
        }

        return $path;
    }

    /**
     * Returns a new path based on the new name with this path as the parent.
     *
     *      $path = (new Path(null, 'foo'))->add('bar')->add('baz')->getPath() => '/foo/bar/baz'
     */
    public function add(mixed $name): Path
    {
        return new Path($this, strval($name));
    }

    /**
     * returns the complete path from this path object
     */
    public function getPath(): string
    {
        $path = $this->parent ? $this->parent->getPath() : "";

        // Only add the first / when this is the root
        if ($this->parent == null && ! $this->isRoot) {
            $path .= $this->name;
        } else {
            $path .= DIRECTORY_SEPARATOR . $this->name;
        }

        return $path;
    }

    public function __toString(): string
    {
        return $this->getPath();
    }

    protected function sanitize(string $name)
    {
        $sanitizer = new FilenameSanitizer($name);
        $sanitizer->stripAdditionalCharacters();
        $sanitizer->stripIllegalFilesystemCharacters();
        $sanitizer->stripRiskyCharacters();

        return $sanitizer->getFilename();
    }


}
