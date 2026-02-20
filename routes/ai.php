<?php

use App\Mcp\Servers\BudgetServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', BudgetServer::class)->middleware('auth:sanctum');
