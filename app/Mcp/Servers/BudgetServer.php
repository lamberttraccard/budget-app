<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetAccountSummary;
use App\Mcp\Tools\GetCategoryBreakdown;
use App\Mcp\Tools\GetCategoryTotals;
use App\Mcp\Tools\GetMonthlyComparison;
use App\Mcp\Tools\GetTransactionDetails;
use App\Mcp\Tools\QueryTransactions;
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
- By month-over-month comparison (two months side by side with deltas)
- By account summary (per-account income/expenses/net)
MARKDOWN)]
class BudgetServer extends Server
{
    protected array $tools = [
        GetCategoryTotals::class,
        QueryTransactions::class,
        GetTransactionDetails::class,
        GetCategoryBreakdown::class,
        GetMonthlyComparison::class,
        GetAccountSummary::class,
    ];
}
