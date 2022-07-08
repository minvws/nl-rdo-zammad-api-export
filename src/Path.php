<?php

namespace Minvws\Zammad;

use Minvws\Zammad\Service\Sanitize;

class Path
{
    protected string $parent;

    /**
     * Constructs a new path based on a previous path (if not the root path). The name will be automatically sanitized
     */
    public function __construct(?Path $parent, string $name)
    {
        $this->parent = $parent;
        $this->name = Sanitize::path($name);
    }

    /**
     * Returns a Path (with parent paths) from a given string  (ie: /foo/bar/baz)
     */
    public static function fromString(string $fullPath): Path
    {
        $parts = explode(DIRECTORY_SEPARATOR, $fullPath);

        $path = null;
        foreach ($parts as $part) {
            $path = new Path($path, $part);
        }

        return $path;
    }

    /**
     * Returns a new path based on the new name with this path as the parent.
     *
     *      $path = (new Path(null, 'foo'))->add('bar')->add('baz')->getPath() => '/foo/bar/baz'
     */
    public function add(string $name): Path
    {
        return new Path($this, $name);
    }

    /**
     * returns the complete path from this path object
     */
    public function getPath(): string
    {
        $path = $this->parent ? $this->parent->getPath() : "";
        $path .= DIRECTORY_SEPARATOR . $this->name;

        return $path;
    }

    public function __toString(): string
    {
        return $this->getPath();
    }


}
