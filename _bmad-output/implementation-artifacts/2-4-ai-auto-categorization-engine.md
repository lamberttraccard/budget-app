# Story 2.4: AI Auto-Categorization Engine

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want imported transactions to be automatically categorized by AI so that I don't have to manually sort hundreds of transactions,
so that I see a meaningful spending breakdown immediately after import.

## Acceptance Criteria

1. **Given** a new transaction is imported with a merchant description, **When** MerchantMatchingService checks the merchant_rules table, **Then** if an exact match exists, the transaction is instantly assigned to the matching category (FR8).

2. **Given** a new transaction has no matching merchant rule, **When** CategorizationService processes it, **Then** a CategorizeTransactions job is dispatched to the queue for Gemini categorization via Laravel AI SDK (FR8).

3. **Given** the Gemini categorization returns a category suggestion, **When** the job completes, **Then** the transaction is updated with the suggested category_id and a TransactionCategorized event fires.

4. **Given** the AI has low confidence or no meaningful suggestion, **When** categorization is attempted, **Then** the transaction is assigned to the "Other" category (FR10).

5. **Given** the Laravel AI SDK / Gemini API is configured, **When** a batch of uncategorized transactions is processed, **Then** the AI receives transaction descriptions and returns category mappings from the 14 predefined categories.

## Tasks / Subtasks

- [x] Task 1: Install Laravel AI SDK and configure (AC: 2, 4, 5)
  - [x] 1.1: Install: `sail composer require laravel/ai`
  - [x] 1.2: Publish config and run migrations: `sail artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"` then `sail artisan migrate`
  - [x] 1.3: Create `config/categorization.php` with app-specific settings: `fallback_category_slug` (default `'other'`), `confidence_threshold` (default `0.5`), `batch_size` (default `50`)
  - [x] 1.4: Update `.env.example` — rename `PRISM_GEMINI_API_KEY` to `GEMINI_API_KEY` (the standard env var name expected by Laravel AI SDK / Prism). Add actual key to `.env` for local development.

- [x] Task 2: Create MerchantMatchingService (AC: 1)
  - [x] 2.1: Create `app/Services/MerchantMatchingService.php`
  - [x] 2.2: Implement `match(string $description): ?Category` — query `merchant_rules` table for exact match on `merchant_pattern` against the transaction description, return the associated Category or null
  - [x] 2.3: Implement `matchBatch(Collection $transactions): array` — accepts `Collection<Transaction>`, returns associative array `[transactionId => categoryId]` for all matched transactions. Use a single query: load all merchant_rules, check each transaction description against rules. Return only matches.
  - [x] 2.4: Matching strategy: case-insensitive exact match of `merchant_pattern` against transaction `description` using `ILIKE` or PHP `mb_strtolower()` comparison

- [x] Task 3: Create TransactionCategorized event (AC: 3)
  - [x] 3.1: Create `app/Events/TransactionCategorized.php`
  - [x] 3.2: Constructor accepts `public Transaction $transaction` — the categorized transaction
  - [x] 3.3: Simple Dispatchable event (no broadcasting needed)

- [x] Task 4: Create TransactionCategorizer AI Agent (AC: 2, 3, 4, 5)
  - [x] 4.1: Generate agent scaffold: `sail artisan make:agent TransactionCategorizer --structured`
  - [x] 4.2: Place in `app/Ai/Agents/TransactionCategorizer.php` — implement `Agent, HasStructuredOutput` interfaces with `Promptable` trait
  - [x] 4.3: Add `#[Provider(Lab::Gemini)]`, `#[Model('gemini-2.5-flash')]`, `#[Temperature(0.1)]`, `#[MaxTokens(2048)]`, `#[Timeout(30)]` attributes
  - [x] 4.4: Implement `instructions(): string` — return the system prompt for financial transaction categorization (French merchants, 14 category slugs, confidence rules)
  - [x] 4.5: Implement `schema(JsonSchema $schema): array` — return structured output schema using correct `$schema->object([...])` API (not `.properties()`)

- [x] Task 5: Create CategorizeTransactions queue job (AC: 2, 3, 4, 5)
  - [x] 5.1: Create `app/Jobs/CategorizeTransactions.php` implementing `ShouldQueue`
  - [x] 5.2: Constructor accepts `public Import $import` — the import whose uncategorized transactions need processing
  - [x] 5.3: Implement `handle()` method with the following flow:
    1. Load uncategorized transactions for this import: `Transaction::where('import_id', $import->id)->whereNull('category_id')->get()`
    2. If no uncategorized transactions, return early
    3. Load all 14 categories from DB: `Category::whereNull('parent_id')->get()` — build slug→id lookup map
    4. Chunk transactions by `config('categorization.batch_size')` (default 50)
    5. For each chunk, build a prompt listing transaction descriptions with amounts
    6. Call `TransactionCategorizer` agent: `(new TransactionCategorizer)->prompt($batchPrompt)`
    7. Parse structured response: for each result, if confidence >= threshold assign category_id via slug lookup; else assign "Other" category
    8. Batch update transactions with assigned category_ids
    9. Dispatch TransactionCategorized event for each categorized transaction
  - [x] 5.4: Configure job: `$tries = 3`, `$backoff = [30, 60, 120]` (exponential backoff for API failures)
  - [x] 5.5: Handle exceptions gracefully: on any `Throwable`, log the error and assign "Other" category to ALL remaining uncategorized transactions for this import (never leave transactions without a category after the job completes)

- [x] Task 6: Create CategorizationService (AC: 1, 2, 3, 4)
  - [x] 6.1: Create `app/Services/CategorizationService.php`
  - [x] 6.2: Inject `MerchantMatchingService` via constructor
  - [x] 6.3: Implement `categorize(Import $import): void` with the following flow:
    1. Load uncategorized transactions for this import
    2. Call `MerchantMatchingService::matchBatch()` for instant matches
    3. Update matched transactions with their category_ids in a batch operation
    4. Dispatch `TransactionCategorized` event for each instantly-matched transaction
    5. If there are remaining unmatched transactions, dispatch `CategorizeTransactions` job to the queue
  - [x] 6.4: This method is called from a listener on `ImportCompleted` event — it orchestrates the hybrid categorization flow

- [x] Task 7: Wire ImportCompleted event to CategorizationService (AC: 1, 2)
  - [x] 7.1: Create `app/Listeners/CategorizeImportedTransactions.php`
  - [x] 7.2: Implement `handle(ImportCompleted $event)`: call `CategorizationService::categorize($event->import)`
  - [x] 7.3: Register the listener in `app/Providers/AppServiceProvider.php` boot method using `Event::listen(ImportCompleted::class, CategorizeImportedTransactions::class)`

- [x] Task 8: Write Unit Tests for MerchantMatchingService (AC: 1)
  - [x] 8.1: Create `tests/Unit/MerchantMatchingServiceTest.php` with `RefreshDatabase` trait
  - [x] 8.2: Test `match()` returns Category when exact match exists in merchant_rules
  - [x] 8.3: Test `match()` returns null when no match exists
  - [x] 8.4: Test `match()` is case-insensitive (e.g., "CARREFOUR" matches rule "carrefour")
  - [x] 8.5: Test `matchBatch()` returns correct category_ids for matched transactions
  - [x] 8.6: Test `matchBatch()` returns empty array when no transactions match any rules
  - [x] 8.7: Test `matchBatch()` handles mixed batch (some match, some don't)

- [x] Task 9: Write Feature Tests for CategorizeTransactions Job (AC: 2, 3, 4, 5)
  - [x] 9.1: Create `tests/Feature/CategorizeTransactionsTest.php` with `RefreshDatabase` trait
  - [x] 9.2: Test job processes uncategorized transactions and assigns categories — use `TransactionCategorizer::fake([...structured response...])` to mock AI responses
  - [x] 9.3: Test job assigns "Other" category when AI confidence is below threshold
  - [x] 9.4: Test job assigns "Other" category to ALL remaining uncategorized transactions when AI agent throws an exception (graceful degradation)
  - [x] 9.5: Test job dispatches TransactionCategorized event for each categorized transaction (use `Event::fake()`)
  - [x] 9.6: Test job handles empty import (no uncategorized transactions) without errors
  - [x] 9.7: Test job only processes transactions belonging to the given import (doesn't touch other imports)
  - [x] 9.8: Test job retry configuration: `$tries = 3`, `$backoff = [30, 60, 120]`
  - [x] 9.9: Use `TransactionCategorizer::assertPrompted()` to verify the agent was called with expected transaction descriptions

- [x] Task 10: Write Feature Tests for CategorizationService (AC: 1, 2, 3, 4)
  - [x] 10.1: Create `tests/Feature/CategorizationServiceTest.php` with `RefreshDatabase` trait
  - [x] 10.2: Test merchant-matched transactions get instant category assignment (no job dispatched for those)
  - [x] 10.3: Test unmatched transactions trigger CategorizeTransactions job dispatch (use `Queue::fake()`)
  - [x] 10.4: Test hybrid flow: some transactions matched instantly, remaining dispatched to queue
  - [x] 10.5: Test when ALL transactions are matched by merchant rules, no queue job is dispatched
  - [x] 10.6: Test TransactionCategorized event fires for each instantly-matched transaction

- [x] Task 11: Write Feature Test for ImportCompleted → Categorization Pipeline (AC: all)
  - [x] 11.1: Add test to `tests/Feature/CsvImportServiceTest.php` or create `tests/Feature/CategorizationPipelineTest.php`
  - [x] 11.2: Test end-to-end: CSV import → ImportCompleted event → CategorizationService → merchant matching + job dispatch
  - [x] 11.3: Test with merchant rules pre-populated: known merchants get instant categorization, unknown merchants get queued

- [x] Task 12: Run Full Test Suite (AC: all)
  - [x] 12.1: Run `sail artisan test` — all 149 existing + 22 new = 171 tests pass with zero regressions
  - [x] 12.2: All new tests pass (22 new tests, 509 assertions total)
  - [x] 12.3: TypeScript compiles cleanly (`sail bun run build`)

## Dev Notes

### Hybrid Categorization Architecture

The categorization system uses a two-tier approach [Source: architecture.md#Process Patterns — Hybrid Categorization Flow]:

```
ImportCompleted event fires
  ↓
CategorizeImportedTransactions listener
  ↓
CategorizationService::categorize(Import)
  ├─ Load uncategorized transactions for this import
  ├─ MerchantMatchingService::matchBatch() → instant matches
  │   ├─ Update matched transactions with category_id
  │   └─ Dispatch TransactionCategorized for each
  └─ Remaining unmatched → dispatch CategorizeTransactions job
      ↓
CategorizeTransactions job (async, queue)
  ├─ Load uncategorized transactions
  ├─ Build prompt with descriptions + 14 category slugs
  ├─ Call TransactionCategorizer agent → Gemini (via Laravel AI SDK)
  ├─ Parse response: assign category_id per transaction
  │   ├─ confidence >= threshold → suggested category
  │   └─ confidence < threshold → "Other" category
  ├─ Batch update transactions
  └─ Dispatch TransactionCategorized for each
```

**Key design decisions:**
- Merchant matching happens synchronously in the listener (fast, no queue needed)
- AI categorization happens asynchronously via queue job (may take 2-10 seconds for Gemini)
- Transactions are never left without a category after the job completes — fallback to "Other"
- The job is per-import, not per-transaction — processes all uncategorized transactions for one import in a single batch

### Laravel AI SDK Integration Details

**Package:** `laravel/ai` (first-party Laravel package, wraps Prism PHP under the hood)

**Installation (Task 1):**
```bash
sail composer require laravel/ai
sail artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
sail artisan migrate  # Creates conversation tables (not used in this story but needed by the SDK)
```

**TransactionCategorizer Agent:**
```php
<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Gemini)]
#[Model('gemini-2.5-flash')]
#[Temperature(0.1)]
#[MaxTokens(2048)]
#[Timeout(30)]
class TransactionCategorizer implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
You are a financial transaction categorizer for a French user's personal budget app.
Categorize each transaction into exactly one of the following categories based on the merchant description.

Categories:
- food: Groceries, supermarkets (Carrefour, Leclerc, Lidl, Auchan, etc.)
- restaurants: Restaurants, cafés, bars, fast food, bakeries
- transport: Public transport, fuel, parking, tolls, car maintenance
- housing: Rent, mortgage, home maintenance, furniture
- entertainment: Cinema, concerts, streaming, games, hobbies
- shopping: Clothing, electronics, Amazon, online shopping
- health: Pharmacy, doctor, dentist, gym, medical
- subscriptions: Recurring subscriptions (Netflix, Spotify, phone, internet)
- education: Books, courses, training, school supplies
- travel: Hotels, flights, vacation expenses
- insurance: Insurance premiums, banking fees
- income: Salary, freelance income, reimbursements, refunds
- professional: Business expenses, coworking, professional tools
- other: Cannot confidently categorize

Rules:
- Use 'other' when confidence is below 0.5
- French merchant names are common (e.g., "BOULANGERIE" = restaurants, "PHARMACIE" = health)
- Amounts are in cents (e.g., 5042 = 50.42 EUR)
- Return confidence 0.0-1.0 reflecting how certain you are
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'results' => $schema->array()->items(
                $schema->object()->properties([
                    'description' => $schema->string()->required(),
                    'category_slug' => $schema->enum([
                        'food', 'restaurants', 'transport', 'housing', 'entertainment',
                        'shopping', 'health', 'subscriptions', 'education', 'travel',
                        'insurance', 'income', 'professional', 'other',
                    ])->required(),
                    'confidence' => $schema->number()->min(0)->max(1)->required(),
                ])
            )->required(),
        ];
    }
}
```

**Calling the Agent (from CategorizeTransactions job):**
```php
use App\Ai\Agents\TransactionCategorizer;

$prompt = "Categorize these bank transactions:\n\n";
foreach ($transactions as $i => $t) {
    $prompt .= ($i + 1) . ". \"{$t->description}\" ({$t->amount_cents} cents, {$t->date})\n";
}

$response = (new TransactionCategorizer)->prompt($prompt);

// $response is an array matching the schema:
// ['results' => [['description' => '...', 'category_slug' => 'food', 'confidence' => 0.95], ...]]
foreach ($response['results'] as $result) {
    // Map category_slug to category_id and update transaction
}
```

**Env Configuration:**
```env
# Laravel AI SDK / Prism expects GEMINI_API_KEY
GEMINI_API_KEY=your-api-key-from-google-ai-studio
```

### Error Handling & Exceptions

**In the CategorizeTransactions job**, wrap the agent call in a try/catch:

```php
try {
    $response = (new TransactionCategorizer)->prompt($prompt);
    // Process results...
} catch (\Throwable $e) {
    Log::error('AI categorization failed', [
        'import_id' => $this->import->id,
        'error' => $e->getMessage(),
    ]);
    // Assign "Other" to all remaining uncategorized transactions
    $this->assignFallbackCategory($uncategorized);
}
```

**Key principle: Never leave transactions uncategorized.** The catch-all handler must assign the "Other" category to all uncategorized transactions for this import. The user can correct later (Story 4.1-4.2).

**Job retry:** Set `$tries = 3` and `$backoff = [30, 60, 120]` on the job class. Laravel's queue system will retry on exceptions that are thrown (not caught). For rate limiting or transient API errors, let the exception propagate so the job retries. For permanent errors (e.g., malformed response), catch, assign "Other", and don't retry.

### MerchantMatchingService Design

**Matching strategy: case-insensitive exact match.**

```php
class MerchantMatchingService
{
    public function match(string $description): ?Category
    {
        $rule = MerchantRule::whereRaw('LOWER(merchant_pattern) = LOWER(?)', [$description])
            ->first();

        return $rule?->category;
    }

    public function matchBatch(Collection $transactions): array
    {
        $rules = MerchantRule::with('category')->get();
        $ruleMap = $rules->keyBy(fn ($rule) => mb_strtolower($rule->merchant_pattern));

        $matches = [];
        foreach ($transactions as $transaction) {
            $key = mb_strtolower($transaction->description);
            if ($ruleMap->has($key)) {
                $matches[$transaction->id] = $ruleMap[$key]->category_id;
            }
        }

        return $matches;
    }
}
```

**Why load all rules for batch?** The merchant_rules table is expected to be small (hundreds of entries at most for a single user). Loading all rules once and checking in PHP is faster than N queries or even a single `whereIn` with potentially messy case-insensitive matching.

**Note:** The `merchant_pattern` column stores the full transaction description as seen in the CSV (e.g., "CARREFOUR CITY PARIS 15"). This is an exact match pattern, not a regex. When the user corrects a transaction in Story 4.2, the `UpdateMerchantRule` listener will store the transaction's description as the `merchant_pattern`. This means the same merchant at different locations may not match (e.g., "CARREFOUR CITY PARIS 15" won't match "CARREFOUR CITY LYON 03"). This is intentional — better to send unknowns to AI than to miscategorize.

### CategorizeTransactions Job — Batch Prompt Strategy

**Process transactions in batches** to minimize API calls while keeping prompts manageable:

1. Load uncategorized transactions for the import (may be 0 to hundreds)
2. Chunk into batches of `config('categorization.batch_size')` (default 50)
3. For each batch, build a prompt listing all transaction descriptions
4. Call Gemini once per batch
5. Parse structured response and map back to transactions

**Prompt for a batch:**
```
Categorize these bank transactions:

1. "CARREFOUR CITY PARIS 15" (5042 cents, 2026-02-15)
2. "NETFLIX.COM" (1399 cents, 2026-02-16)
3. "SNCF VOYAGES" (8500 cents, 2026-02-17)
...

Return a categorization result for each transaction.
```

**Mapping response back to transactions:** Match by the `description` field in the response to the transaction's description. This is reliable because the prompt includes the exact description.

### Event Wiring

**Register listener in AppServiceProvider::boot():**
```php
use App\Events\ImportCompleted;
use App\Listeners\CategorizeImportedTransactions;
use Illuminate\Support\Facades\Event;

public function boot(): void
{
    $this->configureDefaults();

    Event::listen(ImportCompleted::class, CategorizeImportedTransactions::class);
}
```

**Why AppServiceProvider and not EventServiceProvider?** Laravel 12 removed the standalone EventServiceProvider. Listeners are registered in AppServiceProvider or via attribute-based discovery.

### What This Story Does NOT Include

- **User-facing correction UI** — Story 4.1 (single-tap correction)
- **AI learning from corrections** — Story 4.2 (CategoryCorrected event + UpdateMerchantRule listener)
- **Import UI (drag-and-drop, file picker)** — Story 2.6
- **Dashboard display** — Story 2.5 (BudgetQueryService + Inertia page)
- **BudgetQueryService** — Story 2.5 (shared data access for web + MCP)
- **CategoryCorrected event** — Story 4.2 (user correction triggers learning)
- **UpdateMerchantRule listener** — Story 4.2 (persists learned merchant→category mappings)

**Note on CategoryCorrected & UpdateMerchantRule:** These are mentioned in the architecture as part of the AI learning loop, but they belong to Epic 4 (Category Correction & AI Learning), not this story. This story sets up the initial categorization pipeline. Story 4.2 closes the learning loop by populating the merchant_rules table from user corrections, which then feeds back into this story's MerchantMatchingService on future imports.

### Project Structure Notes

**New files to create:**
```
app/
├── Ai/
│   └── Agents/
│       └── TransactionCategorizer.php (NEW — Laravel AI SDK agent)
├── Services/
│   ├── MerchantMatchingService.php    (NEW)
│   └── CategorizationService.php      (NEW)
├── Jobs/
│   └── CategorizeTransactions.php     (NEW — create Jobs/ directory)
├── Events/
│   └── TransactionCategorized.php     (NEW)
├── Listeners/
│   └── CategorizeImportedTransactions.php (NEW — create Listeners/ directory)
config/
└── categorization.php                  (NEW — app-specific AI settings)
tests/
├── Unit/
│   └── MerchantMatchingServiceTest.php (NEW)
└── Feature/
    ├── CategorizeTransactionsTest.php  (NEW)
    ├── CategorizationServiceTest.php   (NEW)
    └── CategorizationPipelineTest.php  (NEW)
```

**Modified files:**
```
app/Providers/AppServiceProvider.php    (add Event::listen for ImportCompleted)
.env.example                           (rename PRISM_GEMINI_API_KEY to GEMINI_API_KEY)
composer.json                           (add laravel/ai dependency)
```

**Aligns with architecture directory structure:**
- Services in `app/Services/` (flat) [Source: architecture.md#Structure Patterns]
- Jobs in `app/Jobs/` [Source: architecture.md#Structure Patterns]
- Events in `app/Events/` [Source: architecture.md#Structure Patterns]
- Listeners in `app/Listeners/` [Source: architecture.md#Structure Patterns]

### Previous Story Intelligence

**From Story 2.3 (direct predecessor — Import Pipeline):**
- CsvImportService dispatches ImportCompleted event after successful import — this is the trigger for categorization
- Transactions are created with `category_id: null` — ready for this story to populate
- Import model has `status`, `transaction_count`, `date_from`, `date_to`
- DB::transaction() used for atomicity — follow same pattern for batch category updates
- 149 tests, 469 assertions, zero regressions — maintain this standard
- Within-batch dedup and chunked inserts (500 rows) patterns established

**From Story 2.2:**
- Dynamic header mapping via array_flip($headers) in parsers
- Defensive coding practices from code review feedback
- str_getcsv() on PHP 8.5 requires explicit '' escape parameter

**From Story 2.1:**
- Test CSV content embedded as PHP strings (no external fixtures)
- User-friendly error messages in exceptions
- Code review found 6 issues — build defensively

**From Story 1.2:**
- All models and migrations complete with proper relationships
- CategorySeeder creates 14 categories: food, restaurants, transport, housing, entertainment, shopping, health, subscriptions, education, travel, insurance, income, professional, other
- MerchantRule model: `merchant_pattern` (unique), `category_id` (FK with cascadeOnDelete)
- QUEUE_CONNECTION=database configured
- Factory states for Account: `personal()`, `professional()`

**From Story 1.4:**
- All commands run inside Sail: `sail artisan test`, `sail bun run build`
- One commit per story convention

### Git Intelligence

Recent commits (one per story):
- `a37277f` Story 2.3: Import pipeline with SHA-256 deduplication
- `01f6da1` Story 2.2: Revolut CSV parser (Personal + Business formats)
- `5e990ad` Story 2.1: CSV parser framework and Banque Populaire support
- `520aee7` Story 1.4: Production deployment with HTTPS and monitoring

**Patterns established:**
- One commit per story
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Tests must pass with zero regressions before committing
- Unit tests: no DB for pure logic, RefreshDatabase for service/integration tests
- Feature tests: full DB with RefreshDatabase trait

### Testing Strategy

**MerchantMatchingService — Unit tests with DB (RefreshDatabase):**
- Seed merchant_rules with test data
- Test exact match, case-insensitive match, no match
- Test batch matching with mixed results

**CategorizeTransactions Job — Feature tests with Laravel AI SDK fake:**
- **Use `TransactionCategorizer::fake()`** — the Laravel AI SDK provides first-class testing support
- Test category assignment with faked AI responses
- Test "Other" fallback for low confidence
- Test "Other" fallback when agent throws exceptions
- Test event dispatch for each categorized transaction
- Test only processes transactions for the given import
- Use `TransactionCategorizer::assertPrompted()` to verify the agent was called correctly

**CategorizationService — Feature tests with Queue::fake():**
- Test merchant matching happens synchronously
- Test queue job dispatched for unmatched transactions
- Test hybrid flow: some instant, some queued

**Integration test:**
- Test full pipeline: import CSV → ImportCompleted → categorization → merchant match + job dispatch

**Mocking with Laravel AI SDK:**
```php
use App\Ai\Agents\TransactionCategorizer;
use Laravel\Ai\Prompts\AgentPrompt;

// Fake the agent with a structured response
TransactionCategorizer::fake([
    [
        'results' => [
            ['description' => 'CARREFOUR CITY', 'category_slug' => 'food', 'confidence' => 0.95],
            ['description' => 'NETFLIX.COM', 'category_slug' => 'subscriptions', 'confidence' => 0.99],
        ],
    ],
]);

// Run the job...
(new CategorizeTransactions($import))->handle();

// Assert the agent was called
TransactionCategorizer::assertPrompted(function (AgentPrompt $prompt) {
    return str_contains($prompt->prompt, 'CARREFOUR CITY');
});

// Fake with dynamic response
TransactionCategorizer::fake(function (AgentPrompt $prompt) {
    return [
        'results' => [
            ['description' => 'ANY MERCHANT', 'category_slug' => 'other', 'confidence' => 0.3],
        ],
    ];
});

// Prevent stray API calls in tests
TransactionCategorizer::fake()->preventStrayPrompts();
```

**Key advantage of Laravel AI SDK testing:** No need to mock Prism internals. `Agent::fake()` intercepts at the agent level, returns structured data directly, and provides assertion helpers.

### Architecture Compliance

| Requirement | Implementation |
|-------------|---------------|
| Hybrid categorization [arch#Process Patterns] | MerchantMatchingService (instant) + CategorizeTransactions job (queue) |
| Service pattern [arch#Service Boundary] | Stateless services, constructor injection |
| Event pattern [arch#Communication Patterns] | PascalCase past tense events, dispatched from services |
| Queue driver [arch#Infrastructure] | Database driver (no Redis), already configured |
| Amount handling [arch#Format Patterns] | amount_cents stays as integer, passed to AI prompt |
| Naming conventions [arch#Naming Patterns] | Services: PascalCase+Suffix, Jobs: PascalCase imperative, Events: PascalCase past tense |
| Category mapping | AI maps to 14 predefined category slugs, looked up in DB |
| Laravel AI SDK | laravel/ai (wraps prism-php/prism), first-party Laravel AI integration |

### References

- Epic 2 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 2.4]
- Hybrid categorization flow: [Source: _bmad-output/planning-artifacts/architecture.md#Process Patterns]
- Service boundary pattern: [Source: _bmad-output/planning-artifacts/architecture.md#Service Boundary]
- Event communication pattern: [Source: _bmad-output/planning-artifacts/architecture.md#Communication Patterns]
- Category seeder (14 categories): [Source: database/seeders/CategorySeeder.php]
- MerchantRule model: [Source: app/Models/MerchantRule.php]
- Laravel AI SDK docs: [Source: laravel.com/docs/12.x/ai-sdk]
- Laravel AI SDK structured output: [Source: laravel.com/docs/12.x/ai-sdk#structured-output]
- Laravel AI SDK testing: [Source: laravel.com/docs/12.x/ai-sdk#testing]
- Previous story 2.3: [Source: _bmad-output/implementation-artifacts/2-3-import-pipeline-with-deduplication.md]
- Queue configuration: [Source: config/queue.php]
- File structure: [Source: _bmad-output/planning-artifacts/architecture.md#Structure Patterns]
- Naming conventions: [Source: _bmad-output/planning-artifacts/architecture.md#Naming Patterns]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

- Schema API mismatch: Dev Notes specified `$schema->object()->properties([...])` but actual Laravel AI SDK uses `$schema->object([...])` (properties as constructor arg). Also `$schema->enum([...])` doesn't exist — must use `$schema->string()->enum([...])`. Fixed by reading actual `JsonSchemaTypeFactory` and `ObjectType` source.
- Existing test regression: `CsvImportServiceTest` tests dispatch `ImportCompleted` events without faking. Our new listener triggered `CategorizeTransactions` job (sync queue in tests), which failed because no categories were seeded. Fixed by adding defensive early return when fallback category is not found.

### Completion Notes List

- Installed `laravel/ai` v0.2.1 with config, stubs, and migration published
- Created `config/categorization.php` with fallback_category_slug, confidence_threshold, batch_size
- Renamed `PRISM_GEMINI_API_KEY` → `GEMINI_API_KEY` in `.env.example`
- Implemented hybrid categorization: MerchantMatchingService (sync, instant) + CategorizeTransactions job (async, queue)
- TransactionCategorizer AI Agent with structured output schema for Gemini 2.5 Flash
- CategorizationService orchestrates the two-tier flow: merchant match first, then queue AI job for unmatched
- CategorizeImportedTransactions listener wired to ImportCompleted event in AppServiceProvider
- Defensive error handling: fallback to "Other" on AI failure, early return when no categories seeded
- 22 new tests (6 unit + 16 feature), all passing with zero regressions on 149 existing tests

### Change Log

- 2026-02-20: Story 2.4 implemented — AI auto-categorization engine with hybrid merchant matching + Gemini AI pipeline
- 2026-02-20: Code review — fixed 6 issues (2 HIGH, 4 MEDIUM): duplicate description bug in AI response matching, dead retry mechanism, N+1 batch writes, unnecessary eager load, missing test assertions, incomplete file list

### File List

New files:
- app/Ai/Agents/TransactionCategorizer.php
- app/Services/MerchantMatchingService.php
- app/Services/CategorizationService.php
- app/Jobs/CategorizeTransactions.php
- app/Events/TransactionCategorized.php
- app/Listeners/CategorizeImportedTransactions.php
- config/categorization.php
- config/ai.php (published from laravel/ai)
- stubs/agent.stub (published from laravel/ai)
- stubs/structured-agent.stub (published from laravel/ai)
- stubs/tool.stub (published from laravel/ai)
- stubs/middleware.stub (published from laravel/ai)
- database/migrations/2026_02_20_135409_create_agent_conversations_table.php (published from laravel/ai)
- tests/Unit/MerchantMatchingServiceTest.php
- tests/Feature/CategorizeTransactionsTest.php
- tests/Feature/CategorizationServiceTest.php
- tests/Feature/CategorizationPipelineTest.php

Modified files:
- app/Providers/AppServiceProvider.php (added Event::listen for ImportCompleted → CategorizeImportedTransactions)
- .env.example (renamed PRISM_GEMINI_API_KEY to GEMINI_API_KEY)
- composer.json (added laravel/ai dependency)
- composer.lock (updated)
- _bmad-output/implementation-artifacts/sprint-status.yaml (story status set to review)
