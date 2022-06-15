<?php

/**
 * @package Zammad API Client
 * @author  Jens Pfeifer <jens.pfeifer@znuny.com>
 */

namespace Minvws\Zammad\Resource;

use ZammadAPIClient\Resource\AbstractResource;

class TicketHistory extends AbstractResource
{
    const URLS = [
        'get'    => 'ticket_history/{object_id}',
    ];
}