<?php

use App\Mcp\Servers\TrayServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/api/mcp', TrayServer::class)->middleware(['api', 'auth:sanctum', 'throttle:60,1']);
