# Story 1.2: Domain Data Model & Category Seed

Status: done

## Story

As Lambert,
I want the database to contain all financial data models and 14 predefined spending categories with their UX-specified colors,
so that the application is ready to store and organize my financial data.

## Acceptance Criteria

1. **Given** the project is initialized, **When** I run database migrations, **Then** tables are created for: `accounts` (name, bank, type), `categories` (name, slug, color, icon, parent_id), `transactions` (amount_cents INTEGER, date, description, dedup_hash, category_id, account_id, import_id), `imports` (file_name, bank, status, transaction_count), `merchant_rules` (merchant_pattern, category_id). The `jobs` table already exists from the starter kit.

2. **Given** migrations have run, **When** I run the category seeder, **Then** 14 top-level categories are created with UX-specified colors:
   - Food → `#A3E635`
   - Restaurants → `#FBBF24`
   - Transport → `#60A5FA`
   - Housing → `#2DD4BF`
   - Entertainment → `#C084FC`
   - Shopping → `#F472B6`
   - Health → `#34D399`
   - Subscriptions → `#FB7185`
   - Education → `#38BDF8`
   - Travel → `#818CF8`
   - Insurance → `#94A3B8`
   - Income → `#10B981`
   - Professional → `#EAB308`
   - Other → `#A1A1AA`

3. **Given** Eloquent models are created, **When** I access Category relationships, **Then** `Category` supports self-referential hierarchy (`parent()`/`children()` relationships), `Transaction` belongs to `Category`, `Account`, and `Import`, and all foreign keys are properly defined with cascading deletes where appropriate.

4. **Given** any amount is stored, **When** a transaction record is created, **Then** the amount is stored as integer cents (`amount_cents` column, `INTEGER` type) — never floats.

## Tasks / Subtasks

- [x] Task 1: Create database migrations in correct dependency order (AC: 1)
  - [x] 1.1: Create `accounts` migration — `name` (string), `bank` (string), `type` (enum: personal/professional), timestamps
  - [x] 1.2: Create `categories` migration — `name` (string), `slug` (string, unique), `color` (string, 7 chars), `icon` (string, nullable), `parent_id` (foreignId, nullable, references categories.id, nullOnDelete), timestamps
  - [x] 1.3: Create `imports` migration — `file_name` (string), `bank` (string), `status` (string, default: 'pending'), `transaction_count` (integer, default: 0), `imported_at` (timestamp, nullable), timestamps
  - [x] 1.4: Create `transactions` migration — `amount_cents` (integer, not null), `date` (date, not null), `description` (string, not null), `dedup_hash` (string, 64 chars, unique), `category_id` (foreignId, nullable, constrained), `account_id` (foreignId, constrained, cascadeOnDelete), `import_id` (foreignId, nullable, constrained, cascadeOnDelete), timestamps
  - [x] 1.5: Create `merchant_rules` migration — `merchant_pattern` (string, not null), `category_id` (foreignId, constrained, cascadeOnDelete), timestamps; add unique constraint on `merchant_pattern`
  - [x] 1.6: Run `sail artisan migrate` and confirm all tables created without errors
  - [x] 1.7: **CRITICAL CHECK**: Verify `jobs` table is NOT recreated — it already exists from Story 1.1 starter kit migrations (`0001_01_01_000002_create_jobs_table.php`)

- [x] Task 2: Create Eloquent models with relationships (AC: 3)
  - [x] 2.1: Create `app/Models/Account.php` — fillable: `name`, `bank`, `type`; hasMany `transactions`
  - [x] 2.2: Create `app/Models/Category.php` — fillable: `name`, `slug`, `color`, `icon`, `parent_id`; self-referential `parent()` (belongsTo Category), `children()` (hasMany Category); hasMany `transactions`
  - [x] 2.3: Create `app/Models/Import.php` — fillable: `file_name`, `bank`, `status`, `transaction_count`, `imported_at`; hasMany `transactions`
  - [x] 2.4: Create `app/Models/Transaction.php` — fillable: `amount_cents`, `date`, `description`, `dedup_hash`, `category_id`, `account_id`, `import_id`; `$casts = ['date' => 'date', 'amount_cents' => 'integer']`; belongsTo `Category`, `Account`, `Import`
  - [x] 2.5: Create `app/Models/MerchantRule.php` — fillable: `merchant_pattern`, `category_id`; belongsTo `Category`

- [x] Task 3: Create CategorySeeder with all 14 categories (AC: 2)
  - [x] 3.1: Create `database/seeders/CategorySeeder.php` with all 14 categories (name, slug, color, icon=null, parent_id=null) — use `updateOrCreate` on slug to make seeder idempotent
  - [x] 3.2: Register `CategorySeeder` in `DatabaseSeeder::run()` — call `$this->call(CategorySeeder::class)`
  - [x] 3.3: Run `sail artisan db:seed --class=CategorySeeder` and verify 14 rows in categories table
  - [x] 3.4: Verify seeder is idempotent: run twice, still exactly 14 rows (no duplicates)

- [x] Task 4: Create model factories for testing (AC: 3, 4)
  - [x] 4.1: Create `AccountFactory` — generates realistic name (e.g. "Personal Checking"), bank (e.g. "Banque Populaire"), type (personal/professional)
  - [x] 4.2: Create `CategoryFactory` — generates name, slug (Str::slug of name), color (hex), parent_id (null by default)
  - [x] 4.3: Create `ImportFactory` — generates file_name, bank, status ('pending'/'completed'), transaction_count
  - [x] 4.4: Create `TransactionFactory` — generates amount_cents (integer, e.g. `fake()->numberBetween(100, 100000)`), date, description, dedup_hash (sha256 of random string); requires account and category

- [x] Task 5: Write tests (AC: 1, 2, 3, 4)
  - [x] 5.1: Feature test `CategorySeederTest` — assert 14 categories exist after seeding, spot-check Food (#A3E635) and Other (#A1A1AA) colors
  - [x] 5.2: Feature test `DomainModelRelationshipsTest` — assert Category→children, Transaction→Category, Transaction→Account, Transaction→Import relationships resolve correctly via factory-created records
  - [x] 5.3: Unit test `TransactionAmountCentsTest` — assert amount_cents is cast to integer on read, and that a float stored in migration would violate constraint (document that floats must never be passed)
  - [x] 5.4: Run full test suite: `sail artisan test` — all tests must pass (target: 45+ tests from Story 1.1 + new tests)

## Dev Notes

### Migration Ordering — CRITICAL

Migrations must be created in dependency order so foreign key constraints resolve correctly:

```
1. accounts       (no FK dependencies)
2. categories     (self-referential: parent_id → categories.id — runs OK, nullOnDelete)
3. imports        (no FK dependencies)
4. transactions   (depends on: categories, accounts, imports)
5. merchant_rules (depends on: categories)
```

**DO NOT create a `jobs` migration** — it already exists from the Laravel starter kit (`0001_01_01_000002_create_jobs_table.php`). Running `php artisan make:migration create_jobs_table` would produce a duplicate that fails on migrate.

### Amount Cents — Non-Negotiable

```php
// Migration column type
$table->integer('amount_cents')->comment('Amount in smallest currency unit (cents)');

// Model cast
protected function casts(): array
{
    return [
        'amount_cents' => 'integer',
        'date' => 'date',
    ];
}

// NEVER do this
$table->decimal('amount', 10, 2); // WRONG
$table->float('amount');           // WRONG
```

All arithmetic stays in integer domain. `520.49 EUR` is stored as `52049`. Display formatting (`formatAmount.ts`) happens in TypeScript only.

### Category Self-Referential Hierarchy

```php
// Category model
public function parent(): BelongsTo
{
    return $this->belongsTo(Category::class, 'parent_id');
}

public function children(): HasMany
{
    return $this->hasMany(Category::class, 'parent_id');
}
```

The top-level 14 categories all have `parent_id = null`. Subcategories (added in future stories) will reference a parent category's id. The migration uses `nullOnDelete()` on the self-referential FK so deleting a parent doesn't cascade-delete children — it orphans them with `parent_id = null`.

### CategorySeeder — Use updateOrCreate

```php
foreach ($categories as $category) {
    Category::updateOrCreate(
        ['slug' => $category['slug']],
        $category
    );
}
```

This makes the seeder safe to run multiple times (e.g., during `sail artisan migrate:fresh --seed`). Never use `insert()` or `create()` without an upsert guard.

### Faker Notes (Learned in Implementation)

- `fake()->hexColor()` already includes the `#` prefix — do NOT prepend another `#`, or the 7-char color column constraint will fail
- `fake()->dateThisYear()` does NOT exist in Faker — use `fake()->dateTimeBetween('first day of January this year', 'now')->format('Y-m-d')` for date columns
- Laravel 12 date cast returns `Carbon\CarbonImmutable`, not `Illuminate\Support\Carbon` — assert against `\Carbon\CarbonInterface::class` in tests

### Slug Generation

Slugs for the 14 categories match the category name lowercased and hyphenated:

| Name | Slug |
|------|------|
| Food | food |
| Restaurants | restaurants |
| Transport | transport |
| Housing | housing |
| Entertainment | entertainment |
| Shopping | shopping |
| Health | health |
| Subscriptions | subscriptions |
| Education | education |
| Travel | travel |
| Insurance | insurance |
| Income | income |
| Professional | professional |
| Other | other |

### Transaction FK Cascade Strategy (As Implemented)

| FK | On Delete |
|----|-----------|
| `category_id → categories.id` | `nullOnDelete()` — category deleted = transaction uncategorized, not lost |
| `account_id → accounts.id` | `cascadeOnDelete()` — account deleted = all transactions deleted |
| `import_id → imports.id` | `cascadeOnDelete()` — FR35: import deletion cascades to transactions (Story 4.3) |

### tpetry/laravel-postgresql-enhanced

This package is already installed (Story 1.1, Task 4). It auto-discovers via Laravel's package discovery — no `ServiceProvider::register()` call needed. For this story, we use standard Laravel column types only. It will be needed in later stories for vector columns.

### All Commands Run Inside Sail

```bash
sail artisan migrate
sail artisan db:seed --class=CategorySeeder
sail artisan test
```

Never use `php artisan`, `composer`, or `bun` directly on the macOS host — always prefix with `sail`.

### References

- Story AC source: [Source: _bmad-output/planning-artifacts/epics.md#Story 1.2]
- Category colors + 14 category names: [Source: _bmad-output/planning-artifacts/epics.md#Story 1.2 AC2]
- Schema column names and types: [Source: _bmad-output/planning-artifacts/epics.md#Story 1.2 AC1]
- Amount cents mandate: [Source: _bmad-output/planning-artifacts/architecture.md#Data Architecture]
- Self-referential category hierarchy: [Source: _bmad-output/planning-artifacts/architecture.md#Data Architecture]
- FK cascade strategy for import deletion: [Source: _bmad-output/planning-artifacts/epics.md#Story 4.3 AC3]
- Jobs table already exists: [Source: _bmad-output/implementation-artifacts/1-1-project-initialization-with-authentication.md#File List]
- Sail command mandate: [Source: _bmad-output/implementation-artifacts/1-1-project-initialization-with-authentication.md#IDE / Environment]

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

- Factory bug: `fake()->hexColor()` already includes `#` prefix; adding another `#` exceeded varchar(7) column constraint → fixed by removing manual `#` prefix
- Factory bug: `fake()->dateThisYear()` is not a valid Faker method → fixed to `fake()->dateTimeBetween('first day of January this year', 'now')`
- Test bug: Laravel 12 date cast returns `CarbonImmutable` not `Illuminate\Support\Carbon` → fixed assertion to `\Carbon\CarbonInterface::class`

### Completion Notes List

- All 5 migrations created and run successfully in correct dependency order
- `jobs` table confirmed NOT recreated (5 new tables only)
- All 5 models created with correct fillable, casts, and relationships
- CategorySeeder creates all 14 categories with exact UX-specified colors using `updateOrCreate` (idempotent)
- CategorySeeder registered in DatabaseSeeder
- 5 factories created (Account, Category, Import, Transaction, MerchantRule) + MerchantRuleFactory as bonus
- 3 test files: CategorySeederTest (7 tests), DomainModelRelationshipsTest (14 tests), TransactionAmountCentsTest (6 tests)
- FK cascade tests verified DB-level behavior: account delete cascades, import delete cascades, category delete nullifies
- Full test suite: **72 tests, 193 assertions — all pass, zero regressions**

### File List

**New files:**
- `database/migrations/2026_02_20_101006_create_accounts_table.php`
- `database/migrations/2026_02_20_101006_create_categories_table.php`
- `database/migrations/2026_02_20_101006_create_imports_table.php`
- `database/migrations/2026_02_20_101007_create_transactions_table.php`
- `database/migrations/2026_02_20_101007_create_merchant_rules_table.php`
- `app/Models/Account.php`
- `app/Models/Category.php`
- `app/Models/Import.php`
- `app/Models/Transaction.php`
- `app/Models/MerchantRule.php`
- `database/seeders/CategorySeeder.php`
- `database/factories/AccountFactory.php`
- `database/factories/CategoryFactory.php`
- `database/factories/ImportFactory.php`
- `database/factories/TransactionFactory.php`
- `database/factories/MerchantRuleFactory.php`
- `tests/Feature/CategorySeederTest.php`
- `tests/Feature/DomainModelRelationshipsTest.php`
- `tests/Feature/TransactionAmountCentsTest.php`

**Modified files:**
- `database/seeders/DatabaseSeeder.php` (added `$this->call(CategorySeeder::class)`)

### Senior Developer Review (AI)

**Reviewer:** Lambert (via claude-opus-4-6)
**Date:** 2026-02-20
**Outcome:** Approved with fixes applied

**Review Summary:**
- 4 ACs verified: all IMPLEMENTED
- All tasks marked [x] verified as actually done
- Git vs Story discrepancies: 0
- Issues found: 1 HIGH, 2 MEDIUM, 3 LOW

**Fixes Applied (3):**
1. **[HIGH] Added database indexes on FK columns and transactions.date** — PostgreSQL does not auto-index FK columns. Added indexes on `transactions.{date, category_id, account_id, import_id}`, `categories.parent_id`, `merchant_rules.category_id` for query performance and cascade delete efficiency.
2. **[MEDIUM] Moved TransactionAmountCentsTest from Unit/ to Feature/** — Test uses RefreshDatabase and hits the database; it's an integration test, not a unit test. Moved to `tests/Feature/` with corrected namespace.
3. **[MEDIUM] TransactionFactory now generates mixed positive/negative amounts** — Changed from always-positive to random sign. Added `expense()` and `income()` factory states for explicit control in future stories.

**Remaining LOW issues (not fixed, acceptable for MVP):**
- CategorySeederTest spot-checks 3/14 colors (sufficient per story spec)
- `accounts.type` uses DB enum (acceptable for 2-value constraint)
- `transactions.description` VARCHAR(255) (adequate; revisit if CSV data exceeds)

**Test Suite:** 72 tests, 193 assertions — all pass, zero regressions
