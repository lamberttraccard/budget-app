# Story 5.2: Transaction & Category Query Tools

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want Claude to query my transactions filtered by category, date range, and account, and access individual transaction details,
so that I can ask Claude specific questions about my spending without opening the app.

## Acceptance Criteria

1. **Given** Claude calls the `get_category_totals` tool with a month parameter, **When** BudgetQueryService aggregates the data, **Then** it returns all categories with their total amount_cents, transaction counts, and category names for that month (FR24, FR27).

2. **Given** Claude calls the `query_transactions` tool with a category filter, **When** the tool processes the request, **Then** it returns transactions matching that category with date, merchant_name, amount_cents, and category_name (FR24).

3. **Given** Claude calls `query_transactions` with a date range filter, **When** the tool processes the request, **Then** it returns only transactions within the specified start and end dates (FR25).

4. **Given** Claude calls `query_transactions` with an account filter, **When** the tool processes the request, **Then** it returns only transactions from the specified account context (FR26).

5. **Given** Claude calls the `get_transaction_details` tool with a transaction ID, **When** the tool processes the request, **Then** it returns the full transaction record: date, merchant_name, amount_cents, category_name, subcategory_name, account_name, and import source (FR28).

6. **Given** Claude calls the `get_category_breakdown` tool for a specific category, **When** BudgetQueryService queries subcategories, **Then** it returns subcategory names with their totals and transaction counts (FR27, FR28).

7. **Given** any query tool responds, **When** measuring response time, **Then** the response completes within 5 seconds (NFR4).

## Tasks / Subtasks

- [x] Task 1: Extend BudgetQueryService with MCP query methods (AC: 1-6)
  - [x] 1.1: Add `queryTransactions(array $filters): array` method supporting category_id, date_from, date_to, account_type filters with pagination (limit/offset)
  - [x] 1.2: Add `getTransactionDetail(int $transactionId): ?array` method returning full transaction with category, subcategory, account, and import source
  - [x] 1.3: Ensure existing `getMonthlyData()` and `getCategoryDetail()` methods are reusable for GetCategoryTotals and GetCategoryBreakdown tools

- [x] Task 2: Create GetCategoryTotals MCP tool (AC: 1)
  - [x] 2.1: Create `app/Mcp/Tools/GetCategoryTotals.php` extending `Laravel\Mcp\Server\Tool`
  - [x] 2.2: Define schema: `year` (integer, required), `month` (integer, required, 1-12), `account_type` (string, nullable, enum: personal/professional)
  - [x] 2.3: Use `#[Name('get_category_totals')]` attribute for snake_case naming
  - [x] 2.4: Implement `handle()` calling `BudgetQueryService::getMonthlyData()` and formatting via `FormatsResponse` trait
  - [x] 2.5: Return `Response::structured()` with category totals array + meta (period, account, total_count)

- [x] Task 3: Create QueryTransactions MCP tool (AC: 2, 3, 4)
  - [x] 3.1: Create `app/Mcp/Tools/QueryTransactions.php` extending `Laravel\Mcp\Server\Tool`
  - [x] 3.2: Define schema: `category_id` (integer, nullable), `date_from` (string, nullable, format: date), `date_to` (string, nullable, format: date), `account_type` (string, nullable), `limit` (integer, default: 50, max: 200), `offset` (integer, default: 0)
  - [x] 3.3: Use `#[Name('query_transactions')]` attribute
  - [x] 3.4: Implement `handle()` calling `BudgetQueryService::queryTransactions()` and formatting via `FormatsResponse` trait
  - [x] 3.5: Return `Response::structured()` with transactions array + meta (total_count, filters applied, pagination)

- [x] Task 4: Create GetTransactionDetails MCP tool (AC: 5)
  - [x] 4.1: Create `app/Mcp/Tools/GetTransactionDetails.php` extending `Laravel\Mcp\Server\Tool`
  - [x] 4.2: Define schema: `transaction_id` (integer, required)
  - [x] 4.3: Use `#[Name('get_transaction_details')]` attribute
  - [x] 4.4: Implement `handle()` calling `BudgetQueryService::getTransactionDetail()` with full relationship loading
  - [x] 4.5: Return `Response::structured()` with full transaction detail or `Response::error()` if not found

- [x] Task 5: Create GetCategoryBreakdown MCP tool (AC: 6)
  - [x] 5.1: Create `app/Mcp/Tools/GetCategoryBreakdown.php` extending `Laravel\Mcp\Server\Tool`
  - [x] 5.2: Define schema: `category_id` (integer, required), `year` (integer, required), `month` (integer, required, 1-12), `account_type` (string, nullable)
  - [x] 5.3: Use `#[Name('get_category_breakdown')]` attribute
  - [x] 5.4: Implement `handle()` calling `BudgetQueryService::getCategoryDetail()` and formatting via `FormatsResponse` trait
  - [x] 5.5: Return `Response::structured()` with subcategories, transactions, and totals + meta

- [x] Task 6: Register tools in BudgetServer (AC: 1-6)
  - [x] 6.1: Add all 4 tool class references to `BudgetServer::$tools` array
  - [x] 6.2: Verify tools appear in MCP tool discovery via `tools/list` response

- [x] Task 7: Write Feature Tests (AC: 1-7)
  - [x] 7.1: Create `tests/Feature/McpToolTest.php` for MCP tool integration tests
  - [x] 7.2: Test `get_category_totals` returns correct monthly aggregation with category data
  - [x] 7.3: Test `query_transactions` with category filter returns only matching transactions
  - [x] 7.4: Test `query_transactions` with date range filter returns only transactions in range
  - [x] 7.5: Test `query_transactions` with account filter returns only matching account transactions
  - [x] 7.6: Test `query_transactions` pagination (limit/offset) works correctly
  - [x] 7.7: Test `get_transaction_details` returns full transaction with relationships
  - [x] 7.8: Test `get_transaction_details` with invalid ID returns error response
  - [x] 7.9: Test `get_category_breakdown` returns subcategories and transactions for a category
  - [x] 7.10: Test all tools require authentication (unauthenticated requests rejected)
  - [x] 7.11: Test response format matches convention (snake_case keys, integer cents, ISO dates, data/meta wrapper)
  - [x] 7.12: Run `sail artisan test` -- all existing + new tests pass with zero regressions
  - [x] 7.13: Verify TypeScript compiles cleanly: `sail bun run build`

## Dev Notes

### Architecture Overview for This Story

This story implements 4 of the 6 MCP query tools for Epic 5 (Conversational Finance). These tools enable Claude to query Lambert's budget data through the MCP protocol. Each tool delegates data access to `BudgetQueryService` and formats responses via the `FormatsResponse` trait established in Story 5.1.

The 4 tools in this story handle transaction and category queries:
- **GetCategoryTotals** -- Monthly category aggregation (reuses `getMonthlyData()`)
- **QueryTransactions** -- Flexible transaction search with filters (new service method)
- **GetTransactionDetails** -- Individual transaction lookup (new service method)
- **GetCategoryBreakdown** -- Category drill-down with subcategories (reuses `getCategoryDetail()`)

The remaining 2 tools (GetMonthlyComparison, GetAccountSummary) are in Story 5.3.

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
  ├─ get_category_totals   → GetCategoryTotals::handle()
  ├─ query_transactions     → QueryTransactions::handle()
  ├─ get_transaction_details → GetTransactionDetails::handle()
  └─ get_category_breakdown → GetCategoryBreakdown::handle()
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

**Data flow for a typical tool call:**

```
Claude sends: tools/call { name: "get_category_totals", arguments: { year: 2026, month: 2 } }
  → BudgetServer dispatches to GetCategoryTotals
  → Tool calls BudgetQueryService::getMonthlyData(2026, 2)
  → Service returns camelCase array { categoryTotals: [...], incomeCents: ..., expensesCents: ... }
  → Tool maps via FormatsResponse::formatCategoryItem() to snake_case
  → Tool wraps in FormatsResponse::formatResponse(data, meta)
  → Returns Response::structured({ data: [...], meta: { period: "2026-02", total_count: 12 } })
```

**Key design decisions:**

- **Reuse existing BudgetQueryService methods where possible:** `getMonthlyData()` already returns exactly what GetCategoryTotals needs. `getCategoryDetail()` already returns what GetCategoryBreakdown needs. Only QueryTransactions and GetTransactionDetails need new service methods.

- **New service methods for MCP-specific queries:** `queryTransactions()` needs flexible filtering (category, date range, account) that doesn't map to any existing method. `getTransactionDetail()` needs full relationship loading (category name, account name, import source) which is a new query.

- **Pagination on QueryTransactions:** Transaction lists can be large. Adding `limit`/`offset` parameters prevents overwhelming Claude's context window. Default limit of 50, max of 200.

- **`Response::structured()` for all tools:** This is the correct MCP response type for structured data. It returns both a text representation (JSON string) and structured content that MCP clients can parse. Using `Response::json()` would only return text.

- **Tool naming via `#[Name()]` attribute:** The architecture specifies snake_case tool names (`get_category_totals`), but laravel/mcp defaults to kebab-case from class name. The `#[Name()]` attribute overrides this.

- **`#[IsReadOnly]` annotation on all tools:** All 4 tools are read-only queries. The `IsReadOnly` annotation informs MCP clients that these tools don't modify data, which helps Claude make better decisions about tool usage.

### BudgetQueryService Extensions

Two new methods are needed:

**`queryTransactions(array $filters): array`**

```php
// Filters: category_id, date_from, date_to, account_type, limit (default 50), offset (default 0)
// Returns: ['transactions' => [...], 'totalCount' => int]
// Each transaction: id, date, description, amountCents, categoryId, categoryName, accountName
```

This method builds a query with optional WHERE clauses based on provided filters. It joins categories and accounts to include names in the result. Pagination is applied via LIMIT/OFFSET.

**`getTransactionDetail(int $transactionId): ?array`**

```php
// Returns full transaction with eager-loaded relationships
// Fields: id, date, description, amountCents, categoryId, categoryName, subcategoryName (if applicable),
//         accountId, accountName, accountType, importId, importFileName, importBank
// Returns null if transaction not found
```

This method loads a single transaction with its category (and parent category for subcategory name), account, and import relationships.

### MCP Tool Implementation Pattern

Each tool follows an identical pattern:

```php
namespace App\Mcp\Tools;

use App\Mcp\Concerns\FormatsResponse;
use App\Services\BudgetQueryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('tool_name')]
#[Description('Tool description for Claude')]
#[IsReadOnly]
class ToolName extends Tool
{
    use FormatsResponse;

    public function __construct(
        protected BudgetQueryService $queryService,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'param' => $schema->integer()->required()->description('...'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $this->queryService->someMethod(...);
        $formatted = array_map([$this, 'formatCategoryItem'], $data);
        return Response::structured($this->formatResponse($formatted, $meta));
    }
}
```

**Key conventions:**
- Constructor injection of `BudgetQueryService` (Laravel container auto-resolves)
- `FormatsResponse` trait for consistent output formatting
- `#[IsReadOnly]` annotation on all read-only query tools
- `Response::structured()` wraps the formatted response with both text and structured content
- Schema uses Laravel's `JsonSchema` fluent API for parameter validation

### Previous Story Intelligence

**From Story 5.1 (MCP Server Setup -- immediately preceding):**
- 264 tests, 1161 assertions -- maintain this standard
- MCP server at `app/Mcp/Servers/BudgetServer.php` with empty `$tools = []` array
- `FormatsResponse` trait at `app/Mcp/Concerns/FormatsResponse.php` with `formatResponse()`, `formatCategoryItem()`, `formatTransactionItem()` methods
- MCP route: `Mcp::web('/mcp', BudgetServer::class)->middleware('auth:sanctum')` in `routes/ai.php`
- Testing pattern: Sanctum token via `$user->createToken('test-mcp', ['mcp:read'])`, JSON-RPC payload format
- `$this->mcp(BudgetServer::class)->actingAs($user)` helper available from laravel/mcp for protocol-level testing
- Namespace/directory: `App\Mcp\*` (lowercase 'cp' in directory -- fixed in 5.1 review)
- `GenerateMcpToken` artisan command creates tokens with `mcp:read` ability
- [Review fix] Namespace casing aligned to `App\Mcp\Concerns` (not `App\MCP\Concerns`)

**From Story 2.5 (Financial Overview Dashboard):**
- `BudgetQueryService` in `app/Services/BudgetQueryService.php` has 3 methods:
  - `getMonthlyData($year, $month, $accountType)` → category totals (reusable for GetCategoryTotals)
  - `getAvailableMonths($accountType)` → distinct months
  - `getCategoryDetail($categoryId, $year, $month, $accountType)` → subcategories + transactions (reusable for GetCategoryBreakdown)
- Service returns camelCase arrays for Inertia -- MCP tools must transform to snake_case
- Service accepts optional `$accountType` parameter for account filtering

### Git Intelligence

Recent commits (one per story, mono-commit pattern):
- `bf8787d` Story 5.1: MCP server setup with Sanctum authentication + review fixes
- `527ed6a` Story 4.3: Import deletion with cascading transaction removal + review fixes
- `4c016d9` Story 4.2: AI learning from corrections with event-driven merchant rules + review fixes

**Established patterns:**
- One commit per story with descriptive message
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Tests must pass with zero regressions before committing
- Feature tests use `RefreshDatabase` trait + seed categories where needed
- Test file naming: `{Subject}Test.php` in `tests/Feature/`

### Architecture Compliance

| Requirement | Implementation |
|-------------|----------------|
| 6 MCP tools via laravel/mcp [arch#Communication Patterns] | 4 tools in this story (GetCategoryTotals, QueryTransactions, GetTransactionDetails, GetCategoryBreakdown), 2 in Story 5.3 |
| MCP tool names in snake_case [arch#Naming Patterns] | `#[Name('get_category_totals')]`, `#[Name('query_transactions')]`, `#[Name('get_transaction_details')]`, `#[Name('get_category_breakdown')]` |
| BudgetQueryService as shared data layer [arch#Service Boundary] | All tools call BudgetQueryService -- same service used by web controllers |
| MCP response format [arch#Communication Patterns] | `FormatsResponse` trait: snake_case keys, integer cents, ISO 8601, `{ data: [...], meta: {...} }` wrapper |
| MCP tools isolated from web controllers [arch#MCP Boundary] | Tools in `app/Mcp/Tools/`, completely separate from `app/Http/Controllers/` |
| Amounts as integer cents [arch#Format Patterns] | All `amount_cents` values passed through as integers, never converted |
| Dates as ISO 8601 [arch#Format Patterns] | All dates returned as `YYYY-MM-DD` strings |
| Descriptive field names for LLM [arch#MCP Response Structure] | `merchant_name` (not `desc`), `category_name` (not `cat`), `account_name` (not `acct`) |

### Library & Framework Requirements

**No new packages needed** -- all dependencies installed in previous stories:

| Package | Version | Status |
|---------|---------|--------|
| `laravel/mcp` | ^0.5.9 | Installed, server configured in Story 5.1 |
| `laravel/sanctum` | v4.x | Installed, auth working in Story 5.1 |

### File Structure Requirements

**New files to create:**
```
app/Mcp/Tools/GetCategoryTotals.php          (NEW -- MCP tool for monthly category aggregation)
app/Mcp/Tools/QueryTransactions.php           (NEW -- MCP tool for flexible transaction search)
app/Mcp/Tools/GetTransactionDetails.php       (NEW -- MCP tool for individual transaction lookup)
app/Mcp/Tools/GetCategoryBreakdown.php        (NEW -- MCP tool for category drill-down)
tests/Feature/McpToolTest.php                 (NEW -- integration tests for all 4 MCP tools)
```

**Modified files:**
```
app/Services/BudgetQueryService.php           (ADD queryTransactions() and getTransactionDetail() methods)
app/Mcp/Servers/BudgetServer.php              (ADD 4 tool class references to $tools array)
app/Mcp/Concerns/FormatsResponse.php          (EXTEND with additional formatting helpers if needed)
```

### Testing Strategy

**New test file: `tests/Feature/McpToolTest.php`**

Test approach:
- Use Sanctum token authentication for all tool calls
- Create test data: user, accounts (personal + professional), categories (seeded), transactions across multiple months
- Test each tool independently with various parameter combinations
- Verify response structure matches `{ data: [...], meta: {...} }` convention
- Verify snake_case keys, integer cents, ISO 8601 dates
- Test error cases: invalid IDs, missing required params
- Test account filtering works correctly (personal vs professional)
- Use `$this->mcp(BudgetServer::class)->actingAs($user)->callTool('tool_name', $args)` pattern from laravel/mcp testing utilities

**Test data setup:**
```php
// Create accounts
$personal = Account::factory()->create(['type' => 'personal']);
$professional = Account::factory()->create(['type' => 'professional']);

// Seed categories (use existing CategorySeeder)
$this->seed(CategorySeeder::class);
$food = Category::where('slug', 'food')->first();
$transport = Category::where('slug', 'transport')->first();

// Create transactions across months and accounts
Transaction::factory()->create([
    'amount_cents' => -5000, 'date' => '2026-02-15',
    'description' => 'CARREFOUR', 'category_id' => $food->id,
    'account_id' => $personal->id,
]);
// ... more test transactions
```

### What This Story Does NOT Include

- **GetMonthlyComparison tool** -- Compares spending across months. Deferred to Story 5.3.
- **GetAccountSummary tool** -- Per-account income/expense totals. Deferred to Story 5.3.
- **Write operations** -- All tools are read-only. No transaction modification via MCP.
- **Rate limiting** -- No rate limiting on MCP tools for MVP. Single user, conversational queries.
- **Caching** -- No query result caching. PostgreSQL with proper indexes handles the load.
- **Full-text search** -- QueryTransactions filters by exact category/date/account, not fuzzy merchant name search.

### Edge Cases to Handle

1. **Empty results:** Tools must return valid responses with empty `data` arrays and `total_count: 0` meta -- never null or error.

2. **Invalid transaction ID in GetTransactionDetails:** Return `Response::error('Transaction not found')` rather than crashing.

3. **Future month with no data in GetCategoryTotals:** Return empty category array with zero income/expenses in meta.

4. **Category with no subcategories in GetCategoryBreakdown:** Return empty `subcategories` array but still include direct transactions.

5. **QueryTransactions with no filters:** Return recent transactions with default limit (50) -- don't return entire database.

6. **QueryTransactions date range spanning multiple months:** The date_from/date_to filters are independent of month -- they query by exact date range.

7. **Account type filter with non-existent type:** Return empty results, not an error. The tool gracefully handles this.

8. **Large result sets in QueryTransactions:** Pagination via limit/offset prevents memory issues. Max limit of 200 transactions per call.

### Project Structure Notes

- MCP tools in `app/Mcp/Tools/` (consistent with architecture.md, note lowercase 'cp' aligned in 5.1 review) [Source: _bmad-output/planning-artifacts/architecture.md#Structure Patterns]
- BudgetQueryService in `app/Services/` (flat, no subdirectories) [Source: architecture.md#Structure Patterns]
- Test file in `tests/Feature/McpToolTest.php` (matches architecture.md test organization naming) [Source: architecture.md#Test Organization]
- Tool registration in `BudgetServer::$tools` array [Source: vendor/laravel/mcp/src/Server.php]
- Response formatting via `App\Mcp\Concerns\FormatsResponse` trait [Source: app/Mcp/Concerns/FormatsResponse.php]

### References

- Epic 5 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 5.2]
- FR24-FR29 requirements: [Source: _bmad-output/planning-artifacts/epics.md#Requirements Inventory]
- MCP tool architecture: [Source: _bmad-output/planning-artifacts/architecture.md#Communication Patterns]
- MCP response format spec: [Source: _bmad-output/planning-artifacts/architecture.md#Communication Patterns]
- MCP tool naming (snake_case): [Source: _bmad-output/planning-artifacts/architecture.md#Naming Patterns]
- BudgetQueryService (shared data layer): [Source: app/Services/BudgetQueryService.php]
- FormatsResponse trait: [Source: app/Mcp/Concerns/FormatsResponse.php]
- BudgetServer class: [Source: app/Mcp/Servers/BudgetServer.php]
- Transaction model: [Source: app/Models/Transaction.php]
- Category model: [Source: app/Models/Category.php]
- Account model: [Source: app/Models/Account.php]
- Import model: [Source: app/Models/Import.php]
- laravel/mcp Tool base class: [Source: vendor/laravel/mcp/src/Server/Tool.php]
- laravel/mcp Response class: [Source: vendor/laravel/mcp/src/Response.php]
- laravel/mcp JsonSchema types: [Source: vendor/laravel/framework/src/Illuminate/JsonSchema/Types/]
- Previous story 5.1: [Source: _bmad-output/implementation-artifacts/5-1-mcp-server-setup-with-sanctum-authentication.md]
- Sprint status: [Source: _bmad-output/implementation-artifacts/sprint-status.yaml]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

- Fixed `JsonSchema` import: tools required `Illuminate\Contracts\JsonSchema\JsonSchema` (interface), not `Illuminate\JsonSchema\JsonSchema` (concrete class)
- MCP testing helper `PendingTestResponse::tool()` requires class references (`GetCategoryTotals::class`), not tool name strings (`'get_category_totals'`)

### Completion Notes List

- Implemented `queryTransactions()` method on BudgetQueryService with flexible filtering (category_id, date_from, date_to, account_type) and pagination (limit/offset with max 200)
- Implemented `getTransactionDetail()` method with full relationship loading (category with parent for subcategory name, account, import)
- Confirmed existing `getMonthlyData()` and `getCategoryDetail()` methods are directly reusable by GetCategoryTotals and GetCategoryBreakdown tools
- Created 4 MCP tools: GetCategoryTotals, QueryTransactions, GetTransactionDetails, GetCategoryBreakdown
- All tools follow identical pattern: constructor-injected BudgetQueryService, `FormatsResponse` trait, `#[Name]`/`#[Description]`/`#[IsReadOnly]` attributes, `Response::structured()` with data/meta wrapper
- Registered all 4 tools in `BudgetServer::$tools` array
- 24 new tests (75 assertions) covering: service methods, all 4 MCP tools, authentication, response format conventions
- Full test suite: 288 tests, 1236 assertions, zero regressions
- TypeScript compiles cleanly
- [Review fix] Added `category_name` and `account_name` to `formatTransactionItem()` -- AC2 required `category_name` in query_transactions response
- [Review fix] Added `(int)` casts to `$year`/`$month` in `GetCategoryTotals::handle()` to match `GetCategoryBreakdown` pattern
- [Review fix] Added `test_query_transactions_tool_includes_category_and_account_names` test for response format validation
- Post-review test suite: 289 tests, 1241 assertions, zero regressions

### File List

New files:
- app/Mcp/Tools/GetCategoryTotals.php
- app/Mcp/Tools/QueryTransactions.php
- app/Mcp/Tools/GetTransactionDetails.php
- app/Mcp/Tools/GetCategoryBreakdown.php
- tests/Feature/McpToolTest.php

Modified files:
- app/Services/BudgetQueryService.php (added queryTransactions() and getTransactionDetail() methods)
- app/Mcp/Servers/BudgetServer.php (added 4 tool class references to $tools array)
- app/Mcp/Concerns/FormatsResponse.php (added category_name and account_name to formatTransactionItem -- review fix)
- _bmad-output/implementation-artifacts/sprint-status.yaml (story status tracking)

### Change Log

- 2026-02-21: Implemented Story 5.2 - Transaction & Category Query Tools. Added 4 MCP tools (get_category_totals, query_transactions, get_transaction_details, get_category_breakdown) with BudgetQueryService extensions and comprehensive feature tests.
- 2026-02-21: Code review fixes -- Added category_name/account_name to formatTransactionItem() (AC2 compliance), fixed type casting in GetCategoryTotals, added response format regression test. 289 tests, 1241 assertions.
