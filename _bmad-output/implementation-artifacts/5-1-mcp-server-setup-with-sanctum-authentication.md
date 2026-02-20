# Story 5.1: MCP Server Setup with Sanctum Authentication

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want the MCP server configured with token-based authentication so that only my authorized Claude sessions can access budget data,
so that my financial data is protected while being accessible conversationally.

## Acceptance Criteria

1. **Given** the laravel/mcp package is installed, **When** the MCP server is configured, **Then** it conforms to the Model Context Protocol specification for tool discovery and invocation (NFR14).

2. **Given** I need to generate an API token for Claude, **When** I create a Sanctum personal access token, **Then** the token is stored in the database and can be used as a Bearer token in MCP requests (FR32).

3. **Given** an MCP request arrives without a valid Bearer token, **When** Sanctum middleware processes the request, **Then** the request is rejected with an authentication error (FR32, NFR7).

4. **Given** an MCP request arrives with a valid Bearer token, **When** Sanctum middleware processes the request, **Then** the request is authenticated and routed to the appropriate MCP tool.

5. **Given** MCP responses are returned, **When** any tool responds, **Then** the response uses structured JSON with snake_case keys, integer cents for amounts, ISO 8601 for dates, and descriptive field names (e.g. `merchant_name` not `desc`) with metadata wrapper: `{ data: [...], meta: { total_count, period, account } }` (FR29, NFR15).

## Tasks / Subtasks

- [x] Task 1: Install and configure Laravel Sanctum (AC: 2, 3, 4)
  - [x] 1.1: Run `sail composer require laravel/sanctum` to install Sanctum
  - [x] 1.2: Run `sail artisan install:api` to publish the Sanctum migration and create `routes/api.php`
  - [x] 1.3: Run `sail artisan migrate` to create the `personal_access_tokens` table
  - [x] 1.4: Add `HasApiTokens` trait to `app/Models/User.php`

- [x] Task 2: Create artisan command for API token generation (AC: 2)
  - [x] 2.1: Create `app/Console/Commands/GenerateMcpToken.php` artisan command
  - [x] 2.2: Command signature: `mcp:token {--name=claude : Token name}` -- creates a Sanctum personal access token for the first user
  - [x] 2.3: Output the plain text token to console for the user to copy into Claude's MCP config
  - [x] 2.4: Include `mcp:read` ability on the token (read-only access to budget data)

- [x] Task 3: Create BudgetMcpServer class (AC: 1, 5)
  - [x] 3.1: Run `sail artisan make:mcp-server BudgetServer` to scaffold the MCP server class
  - [x] 3.2: Configure `$name = 'Budget App'`, `$version = '1.0.0'`, and `$instructions` describing the server's purpose (financial data access for conversational queries)
  - [x] 3.3: Leave `$tools = []` empty -- tools will be added in Stories 5.2 and 5.3

- [x] Task 4: Publish and configure MCP routes with Sanctum auth (AC: 1, 3, 4)
  - [x] 4.1: Run `sail artisan vendor:publish --tag=ai-routes` to create `routes/ai.php`
  - [x] 4.2: Register the MCP server route: `Mcp::web('/mcp', BudgetServer::class)->middleware('auth:sanctum')`
  - [x] 4.3: Verify the route is accessible via `sail artisan route:list | grep mcp`

- [x] Task 5: Establish MCP response format conventions (AC: 5)
  - [x] 5.1: Create `app/MCP/Concerns/FormatsResponse.php` trait with a `formatResponse(array $data, array $meta): array` method
  - [x] 5.2: The trait standardizes: snake_case keys, integer cents for amounts, ISO 8601 for dates, metadata wrapper `{ data: [...], meta: { ... } }`
  - [x] 5.3: Document the convention in the trait's docblock for Stories 5.2 and 5.3 to follow

- [x] Task 6: Write Feature Tests (AC: 1-5)
  - [x] 6.1: Create `tests/Feature/McpServerTest.php` with the MCP testing utilities from laravel/mcp
  - [x] 6.2: Test unauthenticated POST to `/mcp` returns 401/redirect (AC3)
  - [x] 6.3: Test authenticated request with valid Sanctum token reaches the MCP server (AC4)
  - [x] 6.4: Test MCP server initialization (tool discovery handshake) works (AC1)
  - [x] 6.5: Test token generation artisan command creates a valid token (AC2)
  - [x] 6.6: Run `sail artisan test` -- all existing + new tests pass with zero regressions
  - [x] 6.7: Verify TypeScript compiles cleanly: `sail bun run build`

## Dev Notes

### Architecture Overview for This Story

This story sets up the MCP server infrastructure for Epic 5 (Conversational Finance). It's the foundational story that establishes the authenticated MCP endpoint -- Stories 5.2 and 5.3 will add the actual query tools.

The implementation connects three components: Laravel Sanctum for API token authentication, the `laravel/mcp` package for MCP protocol compliance, and the existing application infrastructure. The MCP server is a separate access layer alongside the Inertia web UI, both sharing the `BudgetQueryService` for data access.

```
Claude (MCP Client)
  │
  ├─ Authorization: Bearer {sanctum-token}
  │
  ▼
POST /mcp
  │
  ├─ auth:sanctum middleware (Sanctum)
  │     │
  │     ├─ Invalid/missing token → 401 Unauthorized
  │     └─ Valid token → continue
  │
  ▼
BudgetServer (Laravel\Mcp\Server)
  │
  ├─ MCP Protocol Handshake (initialize)
  │     └─ Returns server name, version, instructions, tool list
  │
  ├─ tools/list → Returns registered tool definitions
  │
  └─ tools/call → Dispatches to specific Tool class
        │
        └─ Tool::handle(Request) → BudgetQueryService → Response::structured()
```

**Data flow for MCP authentication:**

```
Claude sends MCP request
  → HTTP POST /mcp with Authorization: Bearer {token}
  → Laravel routes to ai.php
  → auth:sanctum middleware validates token
    → Token found in personal_access_tokens table? ✓
    → Token not expired? ✓
    → Update last_used_at timestamp
  → Request reaches BudgetServer
  → MCP protocol handles tool discovery/invocation
  → Response returned as Streamable HTTP (MCP transport)
```

**Key design decisions:**

- **Sanctum over Passport:** Sanctum is the right choice for a single-user API token scenario. Passport is overkill -- it's designed for OAuth2 with multiple clients, scopes, and grants. Sanctum's personal access tokens are simpler, lighter, and Laravel-native. The architecture doc explicitly specifies Sanctum.

- **`auth:sanctum` on the MCP route, not globally:** The MCP route gets Sanctum middleware via `Mcp::web('/mcp', BudgetServer::class)->middleware('auth:sanctum')`. This keeps the web UI using session-based auth (starter kit) and the MCP endpoint using token auth -- two authentication strategies, one app.

- **`routes/ai.php` not `routes/api.php`:** The `laravel/mcp` package uses its own route file (`routes/ai.php`) which it auto-discovers. While `sail artisan install:api` creates `routes/api.php`, the MCP server route goes in `routes/ai.php`. The `api.php` file is created by Sanctum's install but we don't need custom API routes in it.

- **Artisan command for token generation:** Rather than building a UI for token management (overkill for single-user), an artisan command `mcp:token` generates a Sanctum token and outputs it to the console. Lambert copies this token into Claude's MCP configuration. Simple, secure, and appropriate for the use case.

- **No tools in this story:** The BudgetServer starts with an empty `$tools` array. This is deliberate -- this story focuses on infrastructure (auth + server + routing). Stories 5.2 and 5.3 add the 6 query tools. This separation ensures the auth foundation is solid before adding business logic.

- **`FormatsResponse` trait for consistency:** A shared trait establishes the MCP response format convention (snake_case, integer cents, ISO 8601, metadata wrapper). All tools in 5.2 and 5.3 will use this trait, ensuring consistent output format without each tool reinventing formatting.

- **Read-only token ability:** The token is created with `mcp:read` ability. While not strictly enforced yet (no ability middleware on individual tools), this sets the convention for future expansion. If write tools are ever added, they'd require a separate `mcp:write` ability.

### Sanctum Installation

```bash
# Install Sanctum
sail composer require laravel/sanctum

# This publishes config/sanctum.php, creates personal_access_tokens migration, and routes/api.php
sail artisan install:api

# Run the migration
sail artisan migrate
```

**Post-install User model update:**

```php
// app/Models/User.php -- add HasApiTokens trait
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;
    // ... rest unchanged
}
```

**Why `install:api`:** This is Sanctum's recommended installation method for Laravel 12. It publishes the migration and creates `routes/api.php` with the `auth:sanctum` middleware group. We don't use `api.php` for MCP routes (those go in `ai.php`), but Sanctum's installation creates it as part of its setup.

### Artisan Command: GenerateMcpToken

```php
// app/Console/Commands/GenerateMcpToken.php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GenerateMcpToken extends Command
{
    protected $signature = 'mcp:token {--name=claude : Token name}';
    protected $description = 'Generate a Sanctum API token for MCP access';

    public function handle(): int
    {
        $user = User::first();

        if (! $user) {
            $this->error('No user found. Register a user first.');
            return self::FAILURE;
        }

        $token = $user->createToken(
            $this->option('name'),
            ['mcp:read'],
        );

        $this->info('MCP API Token generated successfully!');
        $this->newLine();
        $this->line('Token: ' . $token->plainTextToken);
        $this->newLine();
        $this->info('Add this token to your Claude MCP configuration as a Bearer token.');
        $this->warn('This token will only be shown once. Store it safely.');

        return self::SUCCESS;
    }
}
```

**Usage:**
```bash
sail artisan mcp:token
# Output:
# MCP API Token generated successfully!
#
# Token: 1|5Rg3FGHjKlMnOpQrStUvWxYzAbCdEfG...
#
# Add this token to your Claude MCP configuration as a Bearer token.
# This token will only be shown once. Store it safely.
```

### BudgetServer MCP Server Class

```php
// app/Mcp/Servers/BudgetServer.php (scaffolded by make:mcp-server)
namespace App\Mcp\Servers;

use Laravel\Mcp\Server;

class BudgetServer extends Server
{
    protected string $name = 'Budget App';
    protected string $version = '1.0.0';
    protected string $instructions = <<<'MARKDOWN'
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
    MARKDOWN;

    protected array $tools = [
        // Tools will be added in Stories 5.2 and 5.3:
        // \App\Mcp\Tools\GetCategoryTotals::class,
        // \App\Mcp\Tools\QueryTransactions::class,
        // \App\Mcp\Tools\GetMonthlyComparison::class,
        // \App\Mcp\Tools\GetTransactionDetails::class,
        // \App\Mcp\Tools\GetAccountSummary::class,
        // \App\Mcp\Tools\GetCategoryBreakdown::class,
    ];
}
```

### MCP Route Configuration

```php
// routes/ai.php (published via artisan vendor:publish --tag=ai-routes)
<?php

use App\Mcp\Servers\BudgetServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', BudgetServer::class)->middleware('auth:sanctum');
```

**How `Mcp::web()` works:**
- Registers a GET route (returns 405 -- MCP uses POST only)
- Registers a POST route for the MCP Streamable HTTP transport
- Adds `ReorderJsonAccept` and `AddWwwAuthenticateHeader` middleware automatically
- Returns a `Route` instance, so `->middleware('auth:sanctum')` chains on the POST route
- The MCP package auto-discovers `routes/ai.php` and loads it

### FormatsResponse Trait

```php
// app/MCP/Concerns/FormatsResponse.php
namespace App\MCP\Concerns;

/**
 * Standardizes MCP tool response format.
 *
 * Convention for all budget-app MCP tools:
 * - Keys: snake_case (never camelCase -- that's for Inertia props)
 * - Amounts: integer cents (never floats, never formatted strings)
 * - Dates: ISO 8601 (YYYY-MM-DD)
 * - Wrapper: { data: [...], meta: { total_count, period, account, ... } }
 */
trait FormatsResponse
{
    protected function formatResponse(array $data, array $meta = []): array
    {
        return [
            'data' => $data,
            'meta' => $meta,
        ];
    }

    protected function formatCategoryItem(array $category): array
    {
        return [
            'category_id' => $category['id'],
            'category_name' => $category['name'],
            'category_slug' => $category['slug'],
            'color' => $category['color'] ?? null,
            'amount_cents' => $category['amountCents'],
            'transaction_count' => $category['transactionCount'],
        ];
    }

    protected function formatTransactionItem(array $transaction): array
    {
        return [
            'transaction_id' => $transaction['id'],
            'date' => $transaction['date'],
            'merchant_name' => $transaction['description'],
            'amount_cents' => $transaction['amountCents'],
            'category_id' => $transaction['categoryId'] ?? null,
        ];
    }
}
```

**Why a trait:** Tools in Stories 5.2 and 5.3 will `use FormatsResponse` to ensure consistent output without duplicating formatting logic. The trait converts from BudgetQueryService's camelCase output to the MCP snake_case convention.

### Testing Strategy

**New test file: `tests/Feature/McpServerTest.php`**

```php
namespace Tests\Feature;

use App\Models\User;
use App\Mcp\Servers\BudgetServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_mcp_request_is_rejected(): void
    {
        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'id' => 1,
            'params' => [
                'protocolVersion' => '2024-11-05',
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
                'capabilities' => [],
            ],
        ])->assertUnauthorized();
    }

    public function test_authenticated_mcp_request_succeeds(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-mcp', ['mcp:read']);

        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'method' => 'initialize',
                'id' => 1,
                'params' => [
                    'protocolVersion' => '2024-11-05',
                    'clientInfo' => ['name' => 'test', 'version' => '1.0'],
                    'capabilities' => [],
                ],
            ])->assertOk();
    }

    public function test_mcp_server_initialization(): void
    {
        $user = User::factory()->create();

        $this->mcp(BudgetServer::class)
            ->actingAs($user)
            ->assertName('budget-server')
            ->assertOk();
    }

    public function test_mcp_token_artisan_command(): void
    {
        $user = User::factory()->create();

        $this->artisan('mcp:token')
            ->expectsOutput('MCP API Token generated successfully!')
            ->assertSuccessful();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_mcp_token_command_fails_without_user(): void
    {
        $this->artisan('mcp:token')
            ->expectsOutput('No user found. Register a user first.')
            ->assertFailed();
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('expired', ['mcp:read'], now()->subDay());

        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'method' => 'initialize',
                'id' => 1,
                'params' => [
                    'protocolVersion' => '2024-11-05',
                    'clientInfo' => ['name' => 'test', 'version' => '1.0'],
                    'capabilities' => [],
                ],
            ])->assertUnauthorized();
    }
}
```

**Testing approach:**
- Use both raw HTTP tests (for auth verification) and the `$this->mcp()` helper (for MCP protocol testing)
- The `$this->mcp()` helper is provided by `laravel/mcp` and handles MCP protocol handshake internally
- Test both positive (valid token) and negative (no token, expired token) auth scenarios
- Verify the artisan command works end-to-end

### Previous Story Intelligence

**From Story 4.3 (Import Deletion -- most recent):**
- 249 tests, 1126 assertions, zero regressions -- maintain this standard
- All commands via Sail: `sail artisan test`, `sail bun run build`
- One commit per story with descriptive message
- Feature tests use `RefreshDatabase` trait
- Loading state patterns, Dialog patterns, toast patterns all established
- `sonner` toast is installed and configured

**From Story 1.1 (Project Initialization):**
- laravel/mcp ^0.5.9 already in composer.json (installed during project setup)
- `php artisan mcp:install` was NOT run -- MCP server scaffolding still needed
- `app/MCP/` directory does NOT exist yet

**From Story 2.5 (Financial Overview Dashboard):**
- `BudgetQueryService` is the shared data access layer -- already has `getMonthlyData()`, `getAvailableMonths()`, and `getCategoryDetail()` methods
- These methods return camelCase arrays for Inertia -- MCP tools must transform to snake_case via `FormatsResponse` trait
- Service accepts optional `$accountType` parameter for account filtering

### Git Intelligence

Recent commits (one per story, mono-commit pattern):
- `527ed6a` Story 4.3: Import deletion with cascading transaction removal + review fixes
- `4c016d9` Story 4.2: AI learning from corrections with event-driven merchant rules + review fixes
- `dde98cc` Story 4.1: Single-tap category correction with responsive CategoryPicker + review fixes

**Established patterns:**
- One commit per story with descriptive message
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Tests must pass with zero regressions before committing
- Feature tests use `RefreshDatabase` trait + seed categories where needed
- Test file naming: `{Subject}Test.php` in `tests/Feature/`

### Architecture Compliance

| Requirement | Implementation |
|-------------|----------------|
| MCP server via laravel/mcp [arch#Additional Packages] | `BudgetServer` extends `Laravel\Mcp\Server`, registered in `routes/ai.php` via `Mcp::web()` |
| Sanctum API tokens for MCP auth [arch#Authentication & Security] | `auth:sanctum` middleware on MCP route, `HasApiTokens` trait on User |
| MCP response format [arch#Communication Patterns] | `FormatsResponse` trait: snake_case keys, integer cents, ISO 8601, metadata wrapper |
| MCP tools isolated from web controllers [arch#MCP Boundary] | Tools in `app/MCP/Tools/`, server in `app/Mcp/Servers/` -- separate from `app/Http/Controllers/` |
| BudgetQueryService as shared data layer [arch#Service Boundary] | MCP tools will call `BudgetQueryService` (same as web controllers) |
| Sanctum over Passport [arch#Authentication & Security] | Personal access tokens, no OAuth complexity |
| MCP tool names in snake_case [arch#Naming Patterns] | Tool names auto-generated as kebab-case by laravel/mcp `Str::kebab(class_basename)` -- override to snake_case via `$name` property |

### Library & Framework Requirements

**New packages to install:**

| Package | Purpose | Installation |
|---------|---------|-------------|
| `laravel/sanctum` | API token authentication for MCP endpoint | `sail composer require laravel/sanctum` + `sail artisan install:api` |

**Already installed (no action needed):**

| Package | Version | Status |
|---------|---------|--------|
| `laravel/mcp` | ^0.5.9 | In composer.json, vendor downloaded, not yet configured |

### File Structure Requirements

**New files to create:**
```
app/Mcp/Servers/BudgetServer.php                    (NEW -- scaffolded by make:mcp-server)
app/MCP/Concerns/FormatsResponse.php                 (NEW -- response format trait)
app/Console/Commands/GenerateMcpToken.php            (NEW -- artisan command)
routes/ai.php                                        (NEW -- published by vendor:publish)
tests/Feature/McpServerTest.php                      (NEW -- MCP auth + server tests)
```

**Modified files:**
```
app/Models/User.php                                  (add HasApiTokens trait)
composer.json                                        (add laravel/sanctum dependency)
composer.lock                                        (updated by composer require)
```

**Auto-generated files (by Sanctum install):**
```
config/sanctum.php                                   (AUTO -- published by install:api)
database/migrations/xxxx_create_personal_access_tokens_table.php (AUTO)
routes/api.php                                       (AUTO -- created by install:api, minimal use)
```

### What This Story Does NOT Include

- **MCP tools** -- No query tools are created in this story. The `$tools` array is empty. Stories 5.2 (transaction/category tools) and 5.3 (aggregation/comparison tools) add the 6 tools.
- **OAuth/Passport** -- Sanctum personal access tokens only. No OAuth2 flows, no client registration, no scopes beyond `mcp:read`.
- **Token management UI** -- No web interface for creating/revoking tokens. The artisan command is sufficient for a single-user app.
- **Token rotation** -- No automatic token rotation or expiration policy. Tokens are long-lived by default. Lambert can manually revoke and regenerate if needed.
- **Rate limiting** -- No rate limiting on the MCP endpoint for MVP. Single user, conversational queries -- not a concern yet.
- **MCP Inspector setup** -- The `artisan mcp:inspector` command exists in the package but is a dev tool, not part of the production setup.

### Edge Cases to Handle

1. **No user exists when running `mcp:token`:** The command checks for `User::first()` and fails gracefully with an error message if no user is registered.

2. **Multiple tokens:** Creating multiple tokens is fine -- Sanctum supports it. Each call to `mcp:token` creates a new token without revoking previous ones. All valid tokens work simultaneously.

3. **Token in wrong header format:** Sanctum expects `Authorization: Bearer {token}`. If the header is missing or malformed, the request gets a 401. The MCP client (Claude) handles this standard format.

4. **Sanctum `install:api` creates `routes/api.php`:** This file is separate from `routes/ai.php`. The MCP route goes in `ai.php` (laravel/mcp convention). The `api.php` file can be left with its default content.

5. **MCP route conflict with existing routes:** The `/mcp` path doesn't conflict with any existing routes (`/`, `/dashboard`, `/categories/*`, `/imports/*`, `/transactions/*`).

6. **Sanctum token expiration config:** By default, Sanctum tokens don't expire. This can be configured in `config/sanctum.php` via `'expiration' => null`. For a single-user app, non-expiring tokens are appropriate.

### Project Structure Notes

- BudgetServer in `app/Mcp/Servers/` (laravel/mcp scaffolding convention) [Source: vendor/laravel/mcp/stubs/mcp-server.stub]
- MCP tools in `app/MCP/Tools/` (architecture.md convention for future stories) [Source: _bmad-output/planning-artifacts/architecture.md#Structure Patterns]
- FormatsResponse trait in `app/MCP/Concerns/` (Laravel concern convention) [Source: architecture.md#MCP Boundary]
- Artisan command in `app/Console/Commands/` (Laravel standard) [Source: Laravel convention]
- MCP route in `routes/ai.php` (laravel/mcp convention) [Source: vendor/laravel/mcp/src/Server/McpServiceProvider.php]
- Tests in `tests/Feature/McpServerTest.php` (following existing test organization) [Source: architecture.md#Test Organization]

### References

- Epic 5 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 5.1]
- FR24-FR29, FR32 requirements: [Source: _bmad-output/planning-artifacts/epics.md#Requirements Inventory]
- MCP authentication architecture: [Source: _bmad-output/planning-artifacts/architecture.md#Authentication & Security]
- MCP response format spec: [Source: _bmad-output/planning-artifacts/architecture.md#Communication Patterns]
- MCP boundary definition: [Source: _bmad-output/planning-artifacts/architecture.md#Architectural Boundaries]
- MCP tool naming (snake_case): [Source: _bmad-output/planning-artifacts/architecture.md#Naming Patterns]
- BudgetQueryService (shared data layer): [Source: app/Services/BudgetQueryService.php]
- User model (needs HasApiTokens): [Source: app/Models/User.php]
- AppServiceProvider (event bindings): [Source: app/Providers/AppServiceProvider.php]
- laravel/mcp Server class: [Source: vendor/laravel/mcp/src/Server.php]
- laravel/mcp Tool class: [Source: vendor/laravel/mcp/src/Server/Tool.php]
- laravel/mcp Registrar (Mcp::web): [Source: vendor/laravel/mcp/src/Server/Registrar.php]
- laravel/mcp route file convention: [Source: vendor/laravel/mcp/src/Server/McpServiceProvider.php]
- laravel/mcp testing utilities: [Source: vendor/laravel/mcp/src/Server/Testing/PendingTestResponse.php]
- Previous story 4.3: [Source: _bmad-output/implementation-artifacts/4-3-import-deletion.md]
- Composer require section: [Source: composer.json]
- Existing routes: [Source: routes/web.php]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

None -- clean implementation with no blocking issues.

### Completion Notes List

- Installed laravel/sanctum v4.3.1 via `composer require`, ran `install:api` and migration
- Added `HasApiTokens` trait to User model for Sanctum token support
- Created `GenerateMcpToken` artisan command (`mcp:token`) with `mcp:read` ability, graceful no-user handling
- Scaffolded `BudgetServer` MCP server via `make:mcp-server`, configured with PHP Attributes (`#[Name('Budget App')]`, `#[Version('1.0.0')]`, `#[Instructions(...)]`) following the laravel/mcp package convention
- Published `routes/ai.php`, registered `Mcp::web('/mcp', BudgetServer::class)->middleware('auth:sanctum')`
- Created `FormatsResponse` trait in `app/Mcp/Concerns/` with `formatResponse`, `formatCategoryItem`, and `formatTransactionItem` methods
- Wrote 9 feature tests covering: auth rejection (no token, invalid token, expired token), authenticated access, server initialization, protocol capabilities, artisan command (success, failure, custom name), GET method rejection
- All 258 tests pass (1142 assertions), zero regressions from previous 249 tests
- [Review fix] Fixed namespace/directory casing mismatch: `App\MCP\Concerns` → `App\Mcp\Concerns` (was broken on case-sensitive Linux)
- [Review fix] Added 6 unit tests for FormatsResponse trait (formatResponse, formatCategoryItem, formatTransactionItem + edge cases)
- [Review fix] Updated File List to include `bootstrap/app.php` and `tests/Unit/FormatsResponseTest.php`
- All 264 tests pass (1161 assertions), zero regressions
- TypeScript compiles cleanly, all new files pass Pint lint

### Implementation Plan

Followed the story task order exactly:
1. Sanctum installation and configuration (composer require, install:api, migrate, HasApiTokens)
2. Artisan command for token generation
3. BudgetServer class scaffolding and configuration
4. MCP route publishing and registration with auth:sanctum
5. FormatsResponse trait for MCP response conventions
6. Feature tests and full validation

### File List

**New files:**
- app/Console/Commands/GenerateMcpToken.php
- app/Mcp/Servers/BudgetServer.php
- app/Mcp/Concerns/FormatsResponse.php
- routes/ai.php
- tests/Feature/McpServerTest.php
- tests/Unit/FormatsResponseTest.php

**Modified files:**
- app/Models/User.php (added HasApiTokens trait)
- bootstrap/app.php (added api route registration for Sanctum)
- composer.json (added laravel/sanctum dependency)
- composer.lock (updated by composer require)

**Auto-generated files (by Sanctum install):**
- config/sanctum.php
- database/migrations/2026_02_20_233821_create_personal_access_tokens_table.php
- routes/api.php

### Change Log

| Date | Change |
|------|--------|
| 2026-02-21 | Story 5.1 implemented: MCP server setup with Sanctum authentication. Installed Sanctum, created BudgetServer, artisan token command, FormatsResponse trait, MCP routes with auth middleware, and 9 feature tests. |
| 2026-02-21 | Code review: Fixed namespace casing mismatch (App\MCP\Concerns → App\Mcp\Concerns), added 6 unit tests for FormatsResponse trait, updated File List to include bootstrap/app.php. MCP directory convention aligned to `app/Mcp/` (laravel/mcp scaffold convention). 264 tests, 1161 assertions. |
