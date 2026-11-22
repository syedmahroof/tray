<?php

use App\Mcp\Servers\TrayServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Middleware\CheckToken;

Route::middleware('throttle:30,1')->group(function (): void {
    Mcp::oauthRoutes();
});

Mcp::web('/api/mcp', TrayServer::class)
    ->middleware(['api', 'auth:api', CheckToken::using('mcp:use'), 'throttle:60,1']);
