<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Budget App')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
This MCP server provides read-only access to Lambert's personal budget data.
Use the available tools to query transactions, categories, and spending summaries.

**Data conventions:**
- All monetary amounts are in integer cents (e.g., 52049 = 520.49 EUR)
- All dates are in ISO 8601 format (YYYY-MM-DD)
- Response keys use snake_case
- Each response includes a `data` array and a `meta` object with context

**Available query dimensions:**
- By category (14 predefined categories)
- By date range (month or custom range)
- By account type (personal or professional)
- By individual transaction
MARKDOWN)]
class BudgetServer extends Server
{
    protected array $tools = [
        // Tools will be added in Stories 5.2 and 5.3
    ];
}
