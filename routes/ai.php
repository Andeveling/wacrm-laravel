<?php

use App\Mcp\Servers\WacrmServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/wacrm', WacrmServer::class)
    ->middleware(['auth.mcp', 'throttle:mcp']);

Mcp::local('wacrm', WacrmServer::class);
