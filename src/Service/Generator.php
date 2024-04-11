<?php

declare(strict_types=1);

namespace Minvws\Zammad\Service;

use Minvws\Zammad\Path;
use ZammadAPIClient\Resource\Ticket;

interface Generator
{
    public function generateIndex(Path $path, array $data): void;
    public function generateFullIndex(Path $path, array $data): void;
    public function generateGroupIndex(Path $path, array $data): void;
    public function generateTicket(Path $path, Ticket $ticket, array $articles, array $tags, array $history): void;
}
