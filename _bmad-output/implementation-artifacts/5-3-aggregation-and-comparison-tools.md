# Story 5.3: Aggregation & Comparison Tools

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want Claude to compare my spending across months and summarize accounts,
so that I can get trend insights and financial overviews through conversation.

## Acceptance Criteria

1. **Given** Claude calls the `get_monthly_comparison` tool with two months, **When** BudgetQueryService aggregates across periods, **Then** it returns category-by-category totals for each month with calculated differences (absolute and percentage) (FR27).

2. **Given** Claude asks "How did my restaurant spending change?", **When** `get_monthly_comparison` is called with the Restaurants category, **Then** the response includes month-over-month amounts and the percentage change (e.g. `{ "base_amount_cents": 52000, "compare_amount_cents": 38000, "change_pct": -26.92 }`) (FR27).

3. **Given** Claude calls the `get_account_summary` tool, **When** the tool processes the request, **Then** it returns per-account totals for income and expenses, with account names and types (personal/professional) (FR26, FR27).

4. **Given** Claude combines multiple tool calls in a conversation, **When** tools are called sequentially, **Then** each tool returns consistent data from the same underlying BudgetQueryService (FR29).

5. **Given** any aggregation tool returns large result sets, **When** the response is formatted, **Then** amounts remain as integer cents (never pre-formatted), dates as ISO 8601, and metadata includes total_count and period context for Claude to format in natural language (FR29, NFR15).

## Tasks / Subtasks

- [x] Task 1: Add `getMonthlyComparison()` to BudgetQueryService (AC: 1, 2)
  - [x] 1.1: Implement `getMonthlyComparison(int $baseYear, int $baseMonth, int $compareYear, int $compareMonth, ?int $categoryId = null, ?string $accountType = null): array` method
  - [x] 1.2: Reuse existing `getMonthlyData()` to fetch category totals for both periods
  - [x] 1.3: Compute per-category deltas: `change_cents` (absolute) and `change_pct` (percentage, rounded to 2 decimals)
  - [x] 1.4: Handle categories that appear in one month but not the other (zero-fill the missing month)
  - [x] 1.5: If `$categoryId` is provided, filter the comparison to that single category

- [x] Task 2: Add `getAccountSummary()` to BudgetQueryService (AC: 3)
  - [x] 2.1: Implement `getAccountSummary(?int $year = null, ?int $month = null): array` method
  - [x] 2.2: Query accounts with aggregated income_cents (positive amounts or `slug='income'`) and expenses_cents (negative non-income amounts)
  - [x] 2.3: Return per-account data: `id, name, bank, type, incomeCents, expensesCents, netCents, transactionCount`

- [x] Task 3: Create GetMonthlyComparison MCP tool (AC: 1, 2, 4, 5)
  - [x] 3.1: Create `app/Mcp/Tools/GetMonthlyComparison.php` extending `Tool`
  - [x] 3.2: Define schema: `base_year` (int, required), `base_month` (int, required, 1-12), `compare_year` (int, required), `compare_month` (int, required, 1-12), `category_id` (int, nullable), `account_type` (string, nullable, enum)
  - [x] 3.3: Use `#[Name('get_monthly_comparison')]` attribute
  - [x] 3.4: Implement `handle()` calling `BudgetQueryService::getMonthlyComparison()` and formatting via `FormatsResponse` trait
  - [x] 3.5: Return `Response::structured()` with comparison data + meta (base_period, compare_period, account, total_count)

- [x] Task 4: Create GetAccountSummary MCP tool (AC: 3, 4, 5)
  - [x] 4.1: Create `app/Mcp/Tools/GetAccountSummary.php` extending `Tool`
  - [x] 4.2: Define schema: `year` (int, nullable), `month` (int, nullable, 1-12), `account_type` (string, nullable, enum)
  - [x] 4.3: Use `#[Name('get_account_summary')]` attribute
  - [x] 4.4: Implement `handle()` calling `BudgetQueryService::getAccountSummary()` and formatting via `FormatsResponse` trait
  - [x] 4.5: Return `Response::structured()` with account summaries + meta (period, total_count, total_income_cents, total_expenses_cents)

- [x] Task 5: Add `formatAccountItem()` to FormatsResponse trait (AC: 5)
  - [x] 5.1: Add `formatAccountItem(array $account): array` converting camelCase service output to snake_case MCP format
  - [x] 5.2: Add `formatComparisonItem(array $comparison): array` for monthly comparison rows

- [x] Task 6: Register tools in BudgetServer (AC: 1-5)
  - [x] 6.1: Add `GetMonthlyComparison::class` and `GetAccountSummary::class` to `BudgetServer::$tools` array
  - [x] 6.2: Update server `#[Instructions]` to document the two new query dimensions

- [x] Task 7: Write Feature Tests (AC: 1-5)
  - [x] 7.1: Add tests to `tests/Feature/McpToolTest.php` (extend existing file)
  - [x] 7.2: Test `BudgetQueryService::getMonthlyComparison()` returns category-by-category comparison with deltas
  - [x] 7.3: Test `getMonthlyComparison()` with `categoryId` returns single-category comparison
  - [x] 7.4: Test `getMonthlyComparison()` handles categories present in one month but not the other
  - [x] 7.5: Test `BudgetQueryService::getAccountSummary()` returns per-account income/expenses
  - [x] 7.6: Test `getAccountSummary()` with year/month filter restricts to that period
  - [x] 7.7: Test `get_monthly_comparison` MCP tool with two months returns correct comparison
  - [x] 7.8: Test `get_monthly_comparison` MCP tool with category_id filter
  - [x] 7.9: Test `get_monthly_comparison` MCP tool with account_type filter
  - [x] 7.10: Test `get_account_summary` MCP tool returns all accounts with totals
  - [x] 7.11: Test `get_account_summary` MCP tool with period filter
  - [x] 7.12: Test both new tools require authentication (extend existing unauthenticated test)
  - [x] 7.13: Test response format matches convention (snake_case keys, integer cents, data/meta wrapper)
  - [x] 7.14: Run `sail artisan test` -- all existing + new tests pass with zero regressions
  - [x] 7.15: Verify TypeScript compiles cleanly: `sail bun run build`

## Dev Notes

### Architecture Overview for This Story

This story implements the final 2 MCP tools for Epic 5 (Conversational Finance), completing the 6-tool MCP suite. These tools enable Claude to compare spending across months and get account-level summaries.

The 2 tools in this story:
- **GetMonthlyComparison** -- Compare category spending between two months with absolute and percentage changes
- **GetAccountSummary** -- Per-account income and expense totals

After this story, the complete MCP tool suite is:
1. `get_category_totals` (Story 5.2)
2. `query_transactions` (Story 5.2)
3. `get_transaction_details` (Story 5.2)
4. `get_category_breakdown` (Story 5.2)
5. `get_monthly_comparison` (this story)
6. `get_account_summary` (this story)

```
Claude (MCP Client)
  |
  ├─ Authorization: Bearer {sanctum-token}
  |
  ▼
POST /mcp (tools/call)
  |
  ├─ auth:sanctum middleware ✓ (established in 5.1)
  |
  ▼
BudgetServer → routes to Tool by name
  |
  ├─ get_category_totals       → GetCategoryTotals::handle()      (5.2)
  ├─ query_transactions         → QueryTransactions::handle()       (5.2)
  ├─ get_transaction_details    → GetTransactionDetails::handle()   (5.2)
  ├─ get_category_breakdown     → GetCategoryBreakdown::handle()    (5.2)
  ├─ get_monthly_comparison     → GetMonthlyComparison::handle()    (THIS STORY)
  └─ get_account_summary        → GetAccountSummary::handle()       (THIS STORY)
        |
        ▼
  BudgetQueryService (shared data layer)
        |
        ▼
  FormatsResponse trait (camelCase → snake_case)
        |
        ▼
  Response::structured({ data: [...], meta: {...} })
```

### BudgetQueryService Extensions

Two new methods are needed:

**`getMonthlyComparison(int $baseYear, int $baseMonth, int $compareYear, int $compareMonth, ?int $categoryId = null, ?string $accountType = null): array`**

This method compares category spending between two months. Implementation approach:

1. Call existing `getMonthlyData($baseYear, $baseMonth, $accountType)` to get base month totals
2. Call existing `getMonthlyData($compareYear, $compareMonth, $accountType)` to get comparison month totals
3. Merge categories from both months into a unified list (zero-fill any category missing from either month)
4. For each category, compute:
   - `baseAmountCents`: amount from base month (0 if not present)
   - `compareAmountCents`: amount from compare month (0 if not present)
   - `changeCents`: `compareAmountCents - baseAmountCents`
   - `changePct`: percentage change, rounded to 2 decimals. If `baseAmountCents == 0` and `compareAmountCents != 0`, return `null` (cannot compute percentage from zero base)
5. If `$categoryId` is provided, filter the result to only that category

Returns:
```php
[
    'categories' => [
        [
            'id' => 1,
            'name' => 'Food',
            'slug' => 'food',
            'color' => '#A3E635',
            'baseAmountCents' => 52000,
            'compareAmountCents' => 38000,
            'changeCents' => -14000,
            'changePct' => -26.92,
        ],
        // ...
    ],
    'baseIncomeCents' => 300000,
    'baseExpensesCents' => 250000,
    'compareIncomeCents' => 310000,
    'compareExpensesCents' => 230000,
]
```

**`getAccountSummary(?int $year = null, ?int $month = null): array`**

This method returns per-account income/expense totals. Implementation approach:

1. Query all accounts with their transaction aggregates
2. Join `transactions` and `categories` to distinguish income (category slug = 'income') from expenses
3. Group by account, computing `incomeCents` and `expensesCents` separately
4. If year/month provided, filter transactions to that period

Returns:
```php
[
    'accounts' => [
        [
            'id' => 1,
            'name' => 'Banque Populaire',
            'bank' => 'Banque Populaire',
            'type' => 'personal',
            'incomeCents' => 300000,
            'expensesCents' => 250000,
            'netCents' => 50000,
            'transactionCount' => 45,
        ],
        // ...
    ],
    'totalIncomeCents' => 300000,
    'totalExpensesCents' => 280000,
]
```

### MCP Tool Implementation Details

**GetMonthlyComparison tool response structure:**

```json
{
  "data": [
    {
      "category_id": 1,
      "category_name": "Food",
      "category_slug": "food",
      "color": "#A3E635",
      "base_amount_cents": 52000,
      "compare_amount_cents": 38000,
      "change_cents": -14000,
      "change_pct": -26.92
    }
  ],
  "meta": {
    "base_period": "2026-01",
    "compare_period": "2026-02",
    "account": "all",
    "category_filter": null,
    "total_count": 12,
    "base_income_cents": 300000,
    "base_expenses_cents": 250000,
    "compare_income_cents": 310000,
    "compare_expenses_cents": 230000
  }
}
```

**GetAccountSummary tool response structure:**

```json
{
  "data": [
    {
      "account_id": 1,
      "account_name": "Banque Populaire",
      "account_bank": "Banque Populaire",
      "account_type": "personal",
      "income_cents": 300000,
      "expenses_cents": 250000,
      "net_cents": 50000,
      "transaction_count": 45
    }
  ],
  "meta": {
    "period": "2026-02",
    "total_count": 2,
    "total_income_cents": 300000,
    "total_expenses_cents": 280000
  }
}
```

### Previous Story Intelligence

**From Story 5.2 (Transaction & Category Query Tools -- immediately preceding):**
- 289 tests, 1241 assertions -- maintain this standard
- 4 MCP tools registered in `BudgetServer::$tools` -- add 2 more
- `FormatsResponse` trait at `app/Mcp/Concerns/FormatsResponse.php` with `formatResponse()`, `formatCategoryItem()`, `formatTransactionItem()` methods
- Testing pattern: `BudgetServer::actingAs($user)->tool(ToolClass::class, $args)` for protocol-level testing
- `$this->postJson('/mcp', $payload)` pattern for unauthenticated tests (verifying auth middleware)
- Tool naming via `#[Name('snake_case')]` attribute -- required because laravel/mcp defaults to kebab-case
- `#[IsReadOnly]` annotation on all read-only tools
- `Response::structured()` for success, `Response::error()` for errors
- Schema uses `Illuminate\Contracts\JsonSchema\JsonSchema` (interface, not concrete class)
- [Debug fix from 5.2] `PendingTestResponse::tool()` requires class references (`GetCategoryTotals::class`), not tool name strings

**From BudgetQueryService (established in Story 2.5, extended in 5.2):**
- 5 methods: `getMonthlyData()`, `getAvailableMonths()`, `getCategoryDetail()`, `queryTransactions()`, `getTransactionDetail()`
- `getMonthlyData()` already returns `categoryTotals`, `incomeCents`, `expensesCents` -- **directly reusable** for `getMonthlyComparison()`. Call it twice (once per month) and compute deltas.
- Income is identified by category `slug === 'income'`; expenses = abs(sum of non-income)
- All service methods return camelCase arrays -- MCP tools transform via FormatsResponse

**Established code patterns:**
- Constructor injection: `public function __construct(protected BudgetQueryService $queryService) {}`
- Schema fluent API: `$schema->integer()->required()->min(1)->max(12)->description('...')`
- Enum validation: `$schema->string()->nullable()->enum(['personal', 'professional'])`
- Response wrapping: `Response::structured($this->formatResponse($formatted, $meta))`
- Period formatting: `sprintf('%d-%02d', $year, $month)`

### Git Intelligence

Recent commits (one per story, mono-commit pattern):
- `afc1ea6` Story 5.2: Transaction & category query tools with 4 MCP tools + review fixes
- `bf8787d` Story 5.1: MCP server setup with Sanctum authentication + review fixes
- `527ed6a` Story 4.3: Import deletion with cascading transaction removal + review fixes

**Established patterns:**
- One commit per story with descriptive message
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Tests must pass with zero regressions before committing
- Feature tests use `RefreshDatabase` trait + seed categories where needed
- Test file naming: `{Subject}Test.php` in `tests/Feature/`
- MCP tests grouped in existing `tests/Feature/McpToolTest.php` -- extend this file, do NOT create a new one

### Architecture Compliance

| Requirement | Implementation |
|-------------|----------------|
| 6 MCP tools via laravel/mcp [arch#Communication Patterns] | 4 existing (5.2) + 2 new (this story) = 6 total |
| MCP tool names in snake_case [arch#Naming Patterns] | `#[Name('get_monthly_comparison')]`, `#[Name('get_account_summary')]` |
| BudgetQueryService as shared data layer [arch#Service Boundary] | Both new tools call BudgetQueryService -- same service used by web controllers and existing MCP tools |
| MCP response format [arch#Communication Patterns] | `FormatsResponse` trait: snake_case keys, integer cents, ISO 8601, `{ data: [...], meta: {...} }` wrapper |
| MCP tools isolated from web controllers [arch#MCP Boundary] | Tools in `app/Mcp/Tools/`, completely separate from `app/Http/Controllers/` |
| Amounts as integer cents [arch#Format Patterns] | All `amount_cents` values passed through as integers, including deltas and summaries |
| Descriptive field names for LLM [arch#MCP Response Structure] | `base_amount_cents`, `compare_amount_cents`, `change_pct`, `account_name`, `account_type` |

### Library & Framework Requirements

**No new packages needed** -- all dependencies installed in previous stories:

| Package | Version | Status |
|---------|---------|--------|
| `laravel/mcp` | ^0.5.9 | Installed, server configured in Story 5.1 |
| `laravel/sanctum` | v4.x | Installed, auth working in Story 5.1 |

### File Structure Requirements

**New files to create:**
```
app/Mcp/Tools/GetMonthlyComparison.php       (NEW -- MCP tool for month-over-month comparison)
app/Mcp/Tools/GetAccountSummary.php           (NEW -- MCP tool for per-account income/expenses)
```

**Modified files:**
```
app/Services/BudgetQueryService.php           (ADD getMonthlyComparison() and getAccountSummary() methods)
app/Mcp/Servers/BudgetServer.php              (ADD 2 tool class references to $tools array + update Instructions)
app/Mcp/Concerns/FormatsResponse.php          (ADD formatComparisonItem() and formatAccountItem() methods)
tests/Feature/McpToolTest.php                 (ADD tests for both new service methods and MCP tools)
```

### Testing Strategy

**Extend existing test file: `tests/Feature/McpToolTest.php`**

Test approach:
- Use same test infrastructure: `RefreshDatabase`, `CategorySeeder`, personal + professional accounts
- Create transactions across TWO different months (e.g., January 2026 and February 2026) for comparison tests
- Test each new service method independently with various parameter combinations
- Test each new tool via `BudgetServer::actingAs($user)->tool(ToolClass::class, $args)`
- Verify response structure matches `{ data: [...], meta: {...} }` convention
- Verify snake_case keys, integer cents
- Test account filtering and category filtering work correctly
- Add both new tool names to the existing unauthenticated access test

**Key test scenarios:**

```php
// Monthly comparison - two months with different spending
// Jan: Food=5000, Transport=3000, Restaurants=0
// Feb: Food=3500, Transport=3000, Restaurants=2000
// Expected: Food change=-1500 (-30%), Transport change=0 (0%), Restaurants: base=0, compare=2000, pct=null

// Monthly comparison with category filter
// category_id = $food->id
// Expected: single-item data array with Food comparison only

// Account summary with period filter
// Create transactions for personal + professional accounts
// Expected: two items in data, each with income/expenses/net totals

// Account summary without period filter
// Expected: all-time aggregation across all months
```

### What This Story Does NOT Include

- **Write operations** -- All tools are read-only. No transaction modification via MCP.
- **Rate limiting** -- No rate limiting on MCP tools for MVP. Single user, conversational queries.
- **Caching** -- No query result caching. PostgreSQL with proper indexes handles the load.
- **Multi-month arrays** -- `get_monthly_comparison` compares exactly 2 months (base vs compare). For 3+ month trends, Claude can call the tool multiple times.
- **Custom date ranges in GetAccountSummary** -- Only supports year/month filtering, not arbitrary date ranges. This matches the monthly paradigm used throughout the app.

### Edge Cases to Handle

1. **Category exists in one month but not the other:** Zero-fill the missing month. For example, if "Restaurants" has spending in February but not January, `base_amount_cents = 0`, `compare_amount_cents = 2000`, `change_pct = null` (cannot compute from zero base).

2. **Both months have zero spending for a category:** This category should not appear in the comparison results (filtered out during merge).

3. **Percentage change from zero base:** Return `null` for `change_pct` when `base_amount_cents == 0` and `compare_amount_cents != 0`. This avoids division-by-zero and signals to Claude that it should describe this as "new spending" rather than a percentage increase.

4. **No accounts in GetAccountSummary:** Return empty `data` array with zero totals in meta.

5. **GetAccountSummary without year/month:** Returns all-time aggregation. Meta `period` should be `"all"`.

6. **GetMonthlyComparison with same month twice:** Valid edge case -- all change values would be 0 and 0.0%. Handle gracefully, don't error.

7. **Income category in comparison:** Income appears as a positive category in the comparison. Change calculations work the same way (positive change_cents means income increased).

### Project Structure Notes

- MCP tools in `app/Mcp/Tools/` (note lowercase 'cp' aligned in 5.1 review) [Source: architecture.md#Structure Patterns]
- BudgetQueryService in `app/Services/` (flat, no subdirectories) [Source: architecture.md#Structure Patterns]
- Tests extend existing `tests/Feature/McpToolTest.php` (do NOT create a separate file) [Source: architecture.md#Test Organization]
- Tool registration in `BudgetServer::$tools` array [Source: app/Mcp/Servers/BudgetServer.php]
- Response formatting via `App\Mcp\Concerns\FormatsResponse` trait [Source: app/Mcp/Concerns/FormatsResponse.php]

### References

- Epic 5 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 5.3]
- FR24-FR29 requirements: [Source: _bmad-output/planning-artifacts/epics.md#Requirements Inventory]
- MCP tool architecture: [Source: _bmad-output/planning-artifacts/architecture.md#Communication Patterns]
- MCP response format spec: [Source: _bmad-output/planning-artifacts/architecture.md#Communication Patterns]
- MCP tool naming (snake_case): [Source: _bmad-output/planning-artifacts/architecture.md#Naming Patterns]
- BudgetQueryService (shared data layer): [Source: app/Services/BudgetQueryService.php]
- FormatsResponse trait: [Source: app/Mcp/Concerns/FormatsResponse.php]
- BudgetServer class: [Source: app/Mcp/Servers/BudgetServer.php]
- Existing MCP tools (pattern reference): [Source: app/Mcp/Tools/GetCategoryTotals.php]
- Transaction model: [Source: app/Models/Transaction.php]
- Category model: [Source: app/Models/Category.php]
- Account model: [Source: app/Models/Account.php]
- laravel/mcp Tool base class: [Source: vendor/laravel/mcp/src/Server/Tool.php]
- laravel/mcp Response class: [Source: vendor/laravel/mcp/src/Response.php]
- laravel/mcp JsonSchema types: [Source: vendor/laravel/framework/src/Illuminate/JsonSchema/Types/]
- Previous story 5.2: [Source: _bmad-output/implementation-artifacts/5-2-transaction-and-category-query-tools.md]
- Sprint status: [Source: _bmad-output/implementation-artifacts/sprint-status.yaml]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6 (claude-opus-4-6)

### Debug Log References

No issues encountered during implementation. All tests passed on first run.

### Completion Notes List

- Implemented `getMonthlyComparison()` in BudgetQueryService: reuses existing `getMonthlyData()` twice, merges categories from both months, computes deltas with zero-fill for missing categories, returns `null` for `changePct` when base is zero
- Implemented `getAccountSummary()` in BudgetQueryService: uses DB query builder with left joins to aggregate income/expenses per account, income identified by category slug='income', supports year/month filtering
- Created `GetMonthlyComparison` MCP tool with 6-parameter schema (base_year, base_month, compare_year, compare_month, category_id, account_type), formats via `formatComparisonItem()`
- Created `GetAccountSummary` MCP tool with 3-parameter schema (year, month, account_type), filters account_type in PHP after query, formats via `formatAccountItem()`
- Added `formatComparisonItem()` and `formatAccountItem()` to FormatsResponse trait for camelCase-to-snake_case conversion
- Registered both tools in BudgetServer, updated Instructions with 2 new query dimensions
- Added 13 new tests (38 total in McpToolTest): 3 service-level tests for comparison, 2 for account summary, 3 tool-level tests for comparison, 3 for account summary, 1 auth test, 1 response format test
- Full suite: 302 tests, 1304 assertions, zero regressions
- TypeScript builds cleanly

### File List

**New files:**
- `app/Mcp/Tools/GetMonthlyComparison.php` -- MCP tool for month-over-month category comparison
- `app/Mcp/Tools/GetAccountSummary.php` -- MCP tool for per-account income/expense summaries

**Modified files:**
- `app/Services/BudgetQueryService.php` -- Added `getMonthlyComparison()` and `getAccountSummary()` methods
- `app/Mcp/Servers/BudgetServer.php` -- Registered 2 new tools, updated Instructions
- `app/Mcp/Concerns/FormatsResponse.php` -- Added `formatComparisonItem()` and `formatAccountItem()` methods
- `tests/Feature/McpToolTest.php` -- Added 13 new tests for both service methods and MCP tools

### Change Log

- 2026-02-21: Implemented Story 5.3 - Added `get_monthly_comparison` and `get_account_summary` MCP tools with BudgetQueryService extensions, completing the 6-tool MCP suite for Epic 5
- 2026-02-21: Code Review fixes applied (5 issues fixed):
  - [H1] Fixed `getAccountSummary()` LEFT JOIN + WHERE bug: moved date filters into JOIN condition so accounts with zero transactions in filtered period are no longer silently dropped
  - [H2] Added test `test_get_account_summary_includes_accounts_with_zero_transactions_in_period` to validate zero-transaction accounts appear in results
  - [M1] Added validation in `GetAccountSummary::handle()` that `month` requires `year`, returns error otherwise
  - [M1] Added test `test_get_account_summary_tool_month_without_year_returns_error`
  - [M2] Added `min(2000)->max(2100)` constraints to `base_year` and `compare_year` in GetMonthlyComparison schema
  - [M3] Merged new tool auth entries into existing `test_all_tools_require_authentication` and removed duplicate test
  - Post-fix suite: 303 tests, 1311 assertions, zero regressions
