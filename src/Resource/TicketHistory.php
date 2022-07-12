<?php

namespace Minvws\Zammad\Resource;

use ZammadAPIClient\Resource\AbstractResource;

class TicketHistory extends AbstractResource
{
    public const URLS = [
        'get'    => 'ticket_history/{object_id}',
    ];
}
