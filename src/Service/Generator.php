<?php

declare(strict_types=1);

namespace Minvws\Zammad\Service;

use ZammadAPIClient\Resource\Ticket;

interface Generator
{
    public function generateIndex(string $path, array $data): void;
    public function generateGroupIndex(string $path, array $data): void;
    public function generateTicket(string $path, Ticket $ticket, array $tags, array $history): void;
}
