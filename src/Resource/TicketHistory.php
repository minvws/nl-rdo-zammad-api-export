<?php

namespace Minvws\Zammad\Resource;

use ZammadAPIClient\Resource\AbstractResource;

class TicketHistory extends AbstractResource
{
    const URLS = [
        'get'    => 'ticket_history/{object_id}',
    ];
}
