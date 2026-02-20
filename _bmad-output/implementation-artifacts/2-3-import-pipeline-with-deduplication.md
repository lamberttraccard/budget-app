# Story 2.3: Import Pipeline with Deduplication

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want imported transactions to be deduplicated automatically so that re-importing the same file produces no duplicates,
so that I can import freely without worrying about duplicate data.

## Acceptance Criteria

1. **Given** a parsed CSV produces TransactionData DTOs, **When** CsvImportService processes the import, **Then** an Import record is created tracking file_name, bank source, transaction_count, and date range (FR5).

2. **Given** a transaction with a specific date + amount_cents + description + account_id, **When** it is persisted, **Then** a SHA-256 dedup_hash is generated from those fields and stored on the transaction (FR5).

3. **Given** the same CSV file is imported twice, **When** DeduplicationService checks the hash, **Then** previously imported transactions are silently skipped with no errors or warnings (FR5, NFR12).

4. **Given** a large CSV is being processed, **When** the import runs, **Then** it completes without blocking — transactions are persisted in a batch operation (NFR2).

5. **Given** the import completes successfully, **When** the ImportCompleted event fires, **Then** the Import record is updated with final transaction count and status.

## Tasks / Subtasks

- [x] Task 1: Add `bankName()` to CsvParserInterface and implement in existing parsers (AC: 1)
  - [x] 1.1: Add `public function bankName(): string;` to `app/Parsers/CsvParserInterface.php`
  - [x] 1.2: Implement `bankName()` in `app/Parsers/BanquePopulaireParser.php` — return `'Banque Populaire'`
  - [x] 1.3: Implement `bankName()` in `app/Parsers/RevolutParser.php` — return `'Revolut'`

- [x] Task 2: Add date range columns to imports table (AC: 1)
  - [x] 2.1: Create migration `database/migrations/xxxx_add_date_range_to_imports_table.php` — add nullable `date` columns `date_from` and `date_to` to the `imports` table
  - [x] 2.2: Update `app/Models/Import.php` — add `date_from` and `date_to` to `$fillable` array and `casts()` method (cast as `date`)

- [x] Task 3: Create DeduplicationService (AC: 2, 3)
  - [x] 3.1: Create `app/Services/DeduplicationService.php`
  - [x] 3.2: Implement `generateHash(string $date, int $amountCents, string $description, int $accountId): string` — returns SHA-256 hash of the concatenated fields: `hash('sha256', "{$date}|{$amountCents}|{$description}|{$accountId}")`
  - [x] 3.3: Implement `filterDuplicates(Collection $transactions, int $accountId): array` — accepts `Collection<TransactionData>`, generates hashes for all DTOs, queries existing `dedup_hash` values from the `transactions` table for the given account, returns an array of `['transaction' => TransactionData, 'hash' => string]` for non-duplicate entries only
  - [x] 3.4: Use a single `whereIn` query to batch-check all hashes against the database — never query one-by-one

- [x] Task 4: Create ImportCompleted Event (AC: 5)
  - [x] 4.1: Create `app/Events/ImportCompleted.php` implementing `ShouldBroadcast` is NOT needed (no real-time UI in this story) — simple event class
  - [x] 4.2: Constructor accepts `public Import $import` — the completed Import model
  - [x] 4.3: Event does NOT need listeners yet — Story 2.4 will add the categorization listener

- [x] Task 5: Create CsvImportService (AC: 1, 2, 3, 4, 5)
  - [x] 5.1: Create `app/Services/CsvImportService.php`
  - [x] 5.2: Inject `CsvParserFactory` and `DeduplicationService` via constructor
  - [x] 5.3: Implement `import(string $content, string $filename, int $accountId): Import` with the following flow:
    1. Detect parser via `CsvParserFactory::detect($content, $filename)`
    2. Parse content via `$parser->parse($content)` → `Collection<TransactionData>`
    3. Create Import record with status `'pending'`, `file_name`, `bank` from `$parser->bankName()`
    4. Call `DeduplicationService::filterDuplicates($transactions, $accountId)` to get unique entries
    5. Batch insert unique transactions using `Transaction::insert()` with arrays containing: `amount_cents`, `date`, `description`, `dedup_hash`, `account_id`, `import_id`, `created_at`, `updated_at`
    6. Compute `date_from` (min date) and `date_to` (max date) from imported transactions
    7. Update Import record: `status` → `'completed'`, `transaction_count` → count of inserted, `date_from`, `date_to`, `imported_at` → `now()`
    8. Dispatch `ImportCompleted` event with the Import model
    9. Return the Import model
  - [x] 5.4: Handle edge case: if ALL transactions are duplicates, update Import with `transaction_count: 0`, `status: 'completed'`, no date_from/date_to, still fire ImportCompleted event
  - [x] 5.5: Handle errors: wrap in try/catch, on failure update Import status to `'failed'`, re-throw the exception
  - [x] 5.6: Use `DB::transaction()` to wrap the batch insert + Import update in a database transaction for atomicity

- [x] Task 6: Write Unit Tests for DeduplicationService (AC: 2, 3)
  - [x] 6.1: Create `tests/Unit/DeduplicationServiceTest.php`
  - [x] 6.2: Test `generateHash()` produces a 64-character hex SHA-256 hash
  - [x] 6.3: Test `generateHash()` is deterministic — same inputs always produce the same hash (NFR12)
  - [x] 6.4: Test `generateHash()` produces different hashes for different inputs (different date, different amount, different description, different account)
  - [x] 6.5: Test `filterDuplicates()` returns all transactions when no duplicates exist in DB
  - [x] 6.6: Test `filterDuplicates()` silently skips transactions whose hash already exists in DB
  - [x] 6.7: Test `filterDuplicates()` handles mixed batch (some duplicates, some new) correctly
  - [x] 6.8: Test `filterDuplicates()` returns empty array when all transactions are duplicates
  - [x] 6.9: Test `filterDuplicates()` scopes dedup check to the given account_id — same transaction in different accounts should NOT be considered a duplicate

- [x] Task 7: Write Feature Tests for CsvImportService (AC: 1, 2, 3, 4, 5)
  - [x] 7.1: Create `tests/Feature/CsvImportServiceTest.php` with `RefreshDatabase` trait
  - [x] 7.2: Test importing a valid BP CSV creates an Import record with correct file_name, bank ('Banque Populaire'), status ('completed'), transaction_count, date_from, date_to, and imported_at
  - [x] 7.3: Test importing a valid Revolut CSV creates an Import record with bank 'Revolut'
  - [x] 7.4: Test all imported transactions have a non-null dedup_hash (64 chars, hex)
  - [x] 7.5: Test all imported transactions are linked to the Import record (import_id set)
  - [x] 7.6: Test all imported transactions are linked to the correct account (account_id set)
  - [x] 7.7: Test re-importing the same CSV file produces zero new transactions — Import record has transaction_count: 0 and status: 'completed'
  - [x] 7.8: Test partial re-import — CSV with 5 transactions where 3 already exist: only 2 new transactions are inserted, Import has transaction_count: 2
  - [x] 7.9: Test ImportCompleted event is dispatched after successful import (use `Event::fake()`)
  - [x] 7.10: Test ImportCompleted event is dispatched even when all transactions are duplicates (transaction_count: 0)
  - [x] 7.11: Test exception thrown and no Import created when CSV format is unrecognized
  - [x] 7.12: Test batch insert — import a CSV with 50+ transactions and verify all are persisted correctly with correct data
  - [x] 7.13: Test date_from and date_to are computed correctly from transaction dates (min/max)
  - [x] 7.14: Test database transaction atomicity — if batch insert fails, no partial transactions are persisted and Import status is 'failed'

- [x] Task 8: Run Full Test Suite (AC: all)
  - [x] 8.1: Run `sail artisan test` — all existing 124 tests must pass with zero regressions
  - [x] 8.2: All new DeduplicationService and CsvImportService tests must pass
  - [x] 8.3: TypeScript compiles cleanly (`sail bun run build`)

## Dev Notes

### Import Pipeline Architecture

The import pipeline follows the architecture's prescribed flow [Source: architecture.md#Integration Points]:

```
CSV Content + Filename + Account ID
  → CsvParserFactory::detect()       // Auto-detect bank format
  → Parser::parse()                  // Parse to TransactionData DTOs
  → DeduplicationService::filterDuplicates()  // Remove known transactions
  → Transaction::insert()            // Batch persist new transactions
  → Import record updated            // Status, counts, date range
  → ImportCompleted event dispatched  // For future listeners (Story 2.4)
```

**This story does NOT include:**
- AI categorization — Story 2.4 will add a listener on `ImportCompleted` to dispatch `CategorizeTransactions` queue jobs
- Import UI / file upload — Story 2.6 will add the controller + drag-and-drop interface
- Dashboard display — Story 2.5 will create the category overview page
- Category assignment — transactions are imported with `category_id: null`; categorization is Story 2.4

### CsvImportService Design

**Method signature:**
```php
class CsvImportService
{
    public function __construct(
        private CsvParserFactory $parserFactory,
        private DeduplicationService $deduplicationService,
    ) {}

    public function import(string $content, string $filename, int $accountId): Import
}
```

**Critical: Use `DB::transaction()` for atomicity.** The batch insert and Import record update must be wrapped in a database transaction. If the insert fails partway, no partial data should remain.

**Critical: Use `Transaction::insert()` for batch persistence (AC: 4).** Do NOT use `Transaction::create()` in a loop — that generates N individual INSERT queries. The `insert()` method generates a single multi-row INSERT. For the timestamps (`created_at`, `updated_at`), compute `now()` once and include in every row array since `insert()` bypasses Eloquent's automatic timestamp handling.

**Edge case: All duplicates.** If every transaction in the CSV already exists, the Import record should still be created with `status: 'completed'` and `transaction_count: 0`. The `ImportCompleted` event should still fire. No errors, no warnings — this is the expected behavior for a re-import (FR5).

**Edge case: Parser failure.** If `CsvParserFactory::detect()` throws `UnrecognizedCsvFormatException` or `Parser::parse()` throws `CsvParseException`, the exception should propagate up to the caller (Story 2.6's controller will handle the user-facing error). The Import record should be created with `status: 'failed'` if possible (create it before parsing starts, update to 'completed' or 'failed' after).

Actually, cleaner approach: create the Import record AFTER successful parsing but BEFORE dedup/insert. This way:
1. Parse content (may throw — no Import record created)
2. Create Import record with status 'pending'
3. Deduplicate + batch insert (wrapped in DB::transaction)
4. Update Import to 'completed'

If step 3 fails, catch the exception, update Import to 'failed', re-throw.

### DeduplicationService Design

**Hash generation — deterministic and specific (NFR12):**
```php
public function generateHash(string $date, int $amountCents, string $description, int $accountId): string
{
    return hash('sha256', "{$date}|{$amountCents}|{$description}|{$accountId}");
}
```

- **Separator:** Use `|` (pipe) between fields to prevent accidental collisions where field boundaries shift (e.g., date="2026-02-20", amount=100 vs date="2026-02-2", amount=0100)
- **Account scoping:** The account_id is part of the hash, so the same transaction in different accounts generates different hashes. This is correct — Lambert might import the same transaction from both his personal and professional Revolut accounts.
- **Deterministic:** Same inputs always produce the same hash. No timestamps, no random components.

**Duplicate filtering — batch query:**
```php
public function filterDuplicates(Collection $transactions, int $accountId): array
{
    // 1. Generate hash for each DTO
    $entries = $transactions->map(fn (TransactionData $t) => [
        'transaction' => $t,
        'hash' => $this->generateHash($t->date, $t->amountCents, $t->description, $accountId),
    ]);

    // 2. Batch-query existing hashes (single query)
    $existingHashes = Transaction::whereIn('dedup_hash', $entries->pluck('hash'))
        ->pluck('dedup_hash')
        ->flip();

    // 3. Filter out duplicates
    return $entries->reject(fn ($entry) => $existingHashes->has($entry['hash']))->values()->all();
}
```

**Critical: Single query for all hashes.** Never query hashes one-by-one in a loop. Use `whereIn()` to check all hashes in one query. For very large CSVs (1000+ rows), PostgreSQL handles `IN (...)` clauses efficiently.

**Note:** The `filterDuplicates()` method does NOT scope the query by `account_id` because `account_id` is already baked into the hash. Two transactions with the same date/amount/description but different accounts will have different hashes, so the `whereIn` on `dedup_hash` alone is sufficient.

### ImportCompleted Event Design

```php
class ImportCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Import $import) {}
}
```

- Simple event, no broadcasting needed
- No listeners in this story — Story 2.4 will add a listener that dispatches `CategorizeTransactions` queue jobs
- The event carries the full Import model (with relationships loadable as needed)

### Adding `bankName()` to CsvParserInterface

The Import record requires a `bank` field (e.g., "Banque Populaire", "Revolut"). The parser that successfully detected the format knows which bank it handles. Adding `bankName(): string` to the interface is the cleanest approach:

```php
interface CsvParserInterface
{
    public function canParse(string $content, string $filename): bool;
    public function parse(string $content): Collection;
    public function bankName(): string;
}
```

**Changes to existing parsers:**
- `BanquePopulaireParser::bankName()` → returns `'Banque Populaire'`
- `RevolutParser::bankName()` → returns `'Revolut'`

These are user-friendly bank names matching the `ImportFactory` values already established in Story 1.2.

### Database Schema Changes

**New migration — add date range to imports:**
```php
Schema::table('imports', function (Blueprint $table) {
    $table->date('date_from')->nullable()->after('transaction_count');
    $table->date('date_to')->nullable()->after('date_from');
});
```

Nullable because an import with all duplicates (transaction_count: 0) has no date range.

**Import model update — add to $fillable and casts:**
```php
protected $fillable = ['file_name', 'bank', 'status', 'transaction_count', 'date_from', 'date_to', 'imported_at'];

protected function casts(): array
{
    return [
        'imported_at' => 'datetime',
        'transaction_count' => 'integer',
        'date_from' => 'date',
        'date_to' => 'date',
    ];
}
```

### Batch Insert Implementation Detail

`Transaction::insert()` accepts an array of arrays. Each inner array must include ALL required fields since Eloquent's automatic features (timestamps, casts) are bypassed:

```php
$rows = array_map(fn ($entry) => [
    'amount_cents' => $entry['transaction']->amountCents,
    'date' => $entry['transaction']->date,
    'description' => $entry['transaction']->description,
    'dedup_hash' => $entry['hash'],
    'category_id' => null,  // Categorization is Story 2.4
    'account_id' => $accountId,
    'import_id' => $import->id,
    'created_at' => $now,
    'updated_at' => $now,
], $uniqueEntries);

Transaction::insert($rows);
```

**Critical:** Compute `$now = now()` once before the loop. Do NOT call `now()` inside the map — all transactions in a batch should have identical timestamps.

**Critical:** Set `category_id` to `null`. Categorization happens in Story 2.4 after the `ImportCompleted` event fires.

### Existing Codebase Context

**Files created in Stories 2.1-2.2 that this story builds on:**
- `app/Parsers/CsvParserInterface.php` — the contract to extend with `bankName()`
- `app/Parsers/TransactionData.php` — the DTO: `readonly class TransactionData { date, description, amountCents, rawLine }`
- `app/Parsers/CsvParserFactory.php` — `detect($content, $filename): CsvParserInterface` — auto-detects format
- `app/Parsers/BanquePopulaireParser.php` — BP format parser (semicolon, ISO-8859-15, DD/MM/YYYY, comma-decimal)
- `app/Parsers/RevolutParser.php` — Revolut format parser (comma, UTF-8, YYYY-MM-DD HH:MM:SS, dot-decimal, Personal + Business)
- `app/Exceptions/UnrecognizedCsvFormatException.php` — thrown when no parser matches
- `app/Exceptions/CsvParseException.php` — thrown for parse-time errors

**Models created in Story 1.2:**
- `app/Models/Transaction.php` — `$fillable: [amount_cents, date, description, dedup_hash, category_id, account_id, import_id]`, casts `amount_cents` as integer and `date` as date
- `app/Models/Import.php` — `$fillable: [file_name, bank, status, transaction_count, imported_at]`, casts `imported_at` as datetime
- `app/Models/Account.php` — `$fillable: [name, bank, type]`, type is enum: personal|professional

**Factories (Story 1.2):**
- `TransactionFactory` — generates random transaction data with SHA-256 dedup_hash from UUID
- `ImportFactory` — generates imports with random bank names ('Banque Populaire', 'Revolut'), states: `completed()`, `pending()`
- `AccountFactory` — generates accounts with states: `personal()`, `professional()`

**Database schema:**
- `transactions` table: dedup_hash is `VARCHAR(64)` with `UNIQUE` constraint, `import_id` is nullable FK with `cascadeOnDelete`, `category_id` is nullable FK with `nullOnDelete`
- `imports` table: status defaults to `'pending'`, transaction_count defaults to 0

**Current test state:** 124 tests, 298 assertions, all passing.

### Architecture Patterns to Follow

**Service pattern** [Source: architecture.md#Service Boundary]:
- Services are stateless — inject dependencies via constructor
- `CsvImportService` orchestrates the full import pipeline
- Services go in `app/Services/` (flat directory)

**Event pattern** [Source: architecture.md#Communication Patterns]:
- Events named in PascalCase past tense: `ImportCompleted`
- Payload: relevant Eloquent model(s) as constructor arguments
- Dispatched from services, listened to by jobs/other services

**Amount handling** [Source: architecture.md#Format Patterns]:
- ALL amounts stored as integer cents — the TransactionData DTO already provides `amountCents` as `int`
- Never convert or format amounts in the service layer

**Naming conventions** [Source: architecture.md#Naming Patterns]:
- Services: PascalCase with suffix (`CsvImportService`, `DeduplicationService`)
- Events: PascalCase past tense (`ImportCompleted`)
- Methods: camelCase (`import()`, `generateHash()`, `filterDuplicates()`)
- Test files: mirror the class they test (`CsvImportServiceTest.php`, `DeduplicationServiceTest.php`)

### Testing Strategy

**DeduplicationService tests — Unit (no DB for hash tests, DB for filter tests):**
- `generateHash()` tests are pure unit — no database needed
- `filterDuplicates()` tests need the database — use `RefreshDatabase` trait
- Embed sample TransactionData DTOs directly in test methods

**CsvImportService tests — Feature (full DB):**
- Use `RefreshDatabase` trait
- Create Account via `Account::factory()->personal()->create()`
- Seed categories via `$this->seed(CategorySeeder::class)` if needed (not needed for this story since category_id is null)
- Embed sample CSV content as PHP strings (same pattern as parser tests)
- Use `Event::fake()` to assert ImportCompleted is dispatched
- Verify transaction count in DB: `$this->assertDatabaseCount('transactions', N)`
- Verify Import record attributes: `$this->assertDatabaseHas('imports', [...])`

**Edge case tests are critical:**
- Re-import same file → 0 new transactions
- Partial overlap → only new transactions inserted
- All duplicates → Import created with count 0, event still fires
- Cross-account: same transaction data for different accounts → NOT a duplicate

### Previous Story Intelligence

**From Story 2.2 (direct predecessor):**
- Dynamic header mapping via `array_flip($headers)` in RevolutParser
- Personal format filters by `State === 'COMPLETED'`; Business includes all rows
- 25 new tests added, 124 total, 298 assertions, zero regressions
- Applied defensive coding from Story 2.1 code review: amount validation, date try/catch
- TypeScript compiles cleanly

**From Story 2.1:**
- `str_getcsv()` on PHP 8.5 requires explicit `''` escape parameter (4th arg)
- Code review found 6 issues — build defensively from the start to minimize review findings
- User-friendly error messages in exceptions (bank names, not class names)
- Test CSV content embedded as PHP strings (no external fixtures)

**From Story 1.2:**
- All models and migrations are complete with proper relationships
- CategorySeeder creates 14 categories with UX-specified colors
- `QUEUE_CONNECTION=database` configured
- Factory states defined for all models (including `personal()`, `professional()` for Account)

**From Story 1.4:**
- All commands run inside Sail: `sail artisan test`, `sail bun run build`
- Convention: one commit per story

### Git Intelligence

Recent commits (one per story):
- `01f6da1` Story 2.2: Revolut CSV parser (Personal + Business formats)
- `5e990ad` Story 2.1: CSV parser framework and Banque Populaire support
- `520aee7` Story 1.4: Production deployment with HTTPS and monitoring

**Patterns established:**
- One commit per story with descriptive message
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Tests must pass with zero regressions before committing
- Parser tests: pure unit (no DB), service tests: feature (with DB)

### What This Story Does NOT Include

- **AI categorization** — Story 2.4 (will add listener on ImportCompleted)
- **Dashboard UI** — Story 2.5 (BudgetQueryService + Inertia page)
- **Import UI (drag-and-drop, file picker)** — Story 2.6 (ImportController + ImportDropZone component)
- **Import confirmation dialog** — Story 2.6 (shows bank source, count, date range before commit)
- **File upload/storage** — Story 2.6 (this story works with content strings)
- **MerchantMatchingService** — Story 2.4 (instant categorization for known merchants)
- **BudgetQueryService** — Story 2.5 (shared data access for web + MCP)

### Project Structure Notes

- Creates new `app/Services/` directory (first services in the project)
- Creates new `app/Events/` directory (first event in the project)
- Aligns with architecture directory structure: services in `app/Services/` (flat), events in `app/Events/`
- Modifies existing `app/Parsers/CsvParserInterface.php` — backward-compatible addition of `bankName()` method
- Modifies existing parsers minimally (one new method each)
- Adds migration for `date_from`/`date_to` on imports table (AC requires tracking date range)
- No frontend changes in this story (pure backend)
- No route changes (service is called programmatically; HTTP endpoint comes in Story 2.6)

### References

- Epic 2 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 2.3]
- Import pipeline architecture: [Source: _bmad-output/planning-artifacts/architecture.md#Integration Points]
- Service boundary pattern: [Source: _bmad-output/planning-artifacts/architecture.md#Service Boundary]
- Dedup hash specification: [Source: _bmad-output/planning-artifacts/architecture.md#Data Architecture]
- Event communication pattern: [Source: _bmad-output/planning-artifacts/architecture.md#Communication Patterns]
- Amount handling rules: [Source: _bmad-output/planning-artifacts/architecture.md#Format Patterns]
- File structure: [Source: _bmad-output/planning-artifacts/architecture.md#Structure Patterns]
- Naming conventions: [Source: _bmad-output/planning-artifacts/architecture.md#Naming Patterns]
- Previous story 2.2: [Source: _bmad-output/implementation-artifacts/2-2-revolut-csv-parser.md]
- Previous story 2.1: [Source: _bmad-output/implementation-artifacts/2-1-csv-parser-framework-and-banque-populaire-support.md]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

- Test 7.11 adjusted: original spec expected CsvParseException from empty-body CSV, but BP parser returns empty collection (no exception). Changed to test UnrecognizedCsvFormatException with unrecognized format, verifying no Import record is created when parsing fails.

### Completion Notes List

- Implemented full import pipeline: CsvParserFactory → Parser → DeduplicationService → batch insert → ImportCompleted event
- DeduplicationService uses SHA-256 hash of date|amountCents|description|accountId with pipe separator to prevent field boundary collisions
- CsvImportService wraps batch insert + Import update in DB::transaction() for atomicity
- Import record created AFTER successful parsing but BEFORE dedup/insert (cleaner error handling)
- Edge cases handled: all duplicates (count=0, completed, event still fires), partial re-imports, parser failures
- 25 new tests added (12 unit + 13 feature), 149 total, 469 assertions, zero regressions
- TypeScript builds cleanly

### Implementation Plan

1. Added `bankName(): string` to CsvParserInterface and implemented in both parsers
2. Created migration for `date_from`/`date_to` nullable date columns on imports table
3. Created DeduplicationService with deterministic SHA-256 hashing and batch `whereIn` dedup check
4. Created ImportCompleted event (simple Dispatchable event, no broadcasting)
5. Created CsvImportService orchestrating the full pipeline with DB::transaction atomicity
6. Wrote comprehensive unit tests for DeduplicationService (hash generation + duplicate filtering)
7. Wrote comprehensive feature tests for CsvImportService (import flow, dedup, events, edge cases, batch)

### File List

**New files:**
- app/Services/DeduplicationService.php
- app/Services/CsvImportService.php
- app/Events/ImportCompleted.php
- database/migrations/2026_02_20_130342_add_date_range_to_imports_table.php
- tests/Unit/DeduplicationServiceTest.php
- tests/Feature/CsvImportServiceTest.php

**Modified files:**
- app/Parsers/CsvParserInterface.php (added bankName() method)
- app/Parsers/BanquePopulaireParser.php (implemented bankName())
- app/Parsers/RevolutParser.php (implemented bankName())
- app/Models/Import.php (added date_from, date_to to fillable and casts)

### Change Log

- 2026-02-20: Story 2.3 implemented — Import pipeline with SHA-256 deduplication, CsvImportService orchestration, ImportCompleted event, 24 new tests (148 total, 466 assertions)
- 2026-02-20: Code review — 9 issues found (3 HIGH, 3 MEDIUM, 3 LOW). All HIGH and MEDIUM fixed:
  - H1: Fixed test 7.11 unreachable assertion (replaced expectException with try/catch)
  - H2+H3: Rewrote atomicity test to trigger real failure via mocked dedup service + unique constraint violation; now tests rollback + 'failed' status
  - M1: Added within-batch dedup (->unique('hash')) in DeduplicationService to prevent import failure from duplicate rows in same CSV
  - M2: Added chunked inserts (500 rows) to avoid PostgreSQL parameter limit
  - M3: Moved filterDuplicates inside DB::transaction for correct concurrent behavior
  - L1: Fixed inconsistent now() usage (compute once, reuse)
  - Added $import->refresh() in catch block for clean state after rollback
  - 1 new test added (within-batch dedup), 149 total, 469 assertions, zero regressions
