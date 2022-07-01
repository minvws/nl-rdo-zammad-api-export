<?php

declare(strict_types=1);

namespace Minvws\Zammad\Service;

use ZammadAPIClient\Resource\Ticket;

interface Generator
{
    public function generateIndex(string $basePath, array $data): void;
    public function generateGroupIndex(string $basePath, array $data): void;
    public function generateTicket(string $basePath, Ticket $ticket, array $tags, array $history): void;
}
