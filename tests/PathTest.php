<?php

namespace MinVWS\Zammad\Tests;

use Minvws\Zammad\Path;
use PHPUnit\Framework\TestCase;

class PathTest extends TestCase
{
    public function testRootWithNonRoot()
    {
        $this->expectException(\Exception::class);
        new Path(new Path(null, "foo"), "bar", true);
    }

    public function testFromString()
    {
        $this->assertEquals("/foo/bar/baz", Path::fromString("/foo/bar/baz"));
    }

    public function testRoot()
    {
        $p = new Path(null, "foo", true);
        $p = new Path($p, "bar");
        $p = new Path($p, "/baz");
        $this->assertEquals("/foo/bar/baz", $p);

        $p = new Path(null, "foo");
        $p = new Path($p, "bar");
        $p = new Path($p, "/baz");
        $this->assertEquals("foo/bar/baz", $p);
    }

    public function testAdd()
    {
        $p = (new Path(null, 'foo'))->add('bar')->add('baz');

        $this->assertEquals('foo/bar/baz', $p->getPath());
        $this->assertEquals('foo/bar/baz', $p);
        $this->assertEquals('foo/bar/baz', (string)$p);
    }

    public function testSanitize()
    {
        $p = (new Path(null, 'fo/o!!#"$oéo'))->add("  /:b<<a\n\tr\\")->add('%*?@(/:baz)');

        $this->assertEquals('foo!!$oéo/  bar/%@(baz)', $p->getPath());
    }
}
