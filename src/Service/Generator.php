<?php

declare(strict_types=1);

namespace Minvws\Zammad\Service;

use ZammadAPIClient\Resource\Ticket;

interface Generator
{
    public function generateIndex(string $path, string $basepath, array $tickets): void;
    public function generateTicket(string $path, Ticket $tickets): void;
}
