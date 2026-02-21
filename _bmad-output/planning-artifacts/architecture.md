---
stepsCompleted: [1, 2, 3, 4, 5, 6, 7, 8]
workflow_completed: true
lastStep: 8
status: 'complete'
completedAt: '2026-02-19'
inputDocuments: ['prd.md', 'product-brief-budget-app-2026-02-18.md', 'ux-design-specification.md']
workflowType: 'architecture'
project_name: 'budget-app'
user_name: 'Lambert'
date: '2026-02-19'
---

# Architecture Decision Document

_This document builds collaboratively through step-by-step discovery. Sections are appended as we work through each architectural decision together._

## Project Context Analysis

### Requirements Overview

**Functional Requirements:**

33 functional requirements across 7 domains, plus 2 additional capabilities discovered during UX design:

| Domain | FRs | Architectural Impact |
|--------|-----|---------------------|
| Data Ingestion | FR1-FR7 | CSV parsing pipeline with format detection, European locale handling, encoding normalization, deduplication engine |
| Transaction Intelligence | FR8-FR11 | AI categorization service with dual strategy (pattern matching + LLM), correction persistence, learning feedback loop |
| Financial Overview | FR12-FR17 | Hierarchical data model (category → subcategory → transaction), monthly aggregation queries, default-to-current-month logic |
| Account Management | FR18-FR20 | Multi-account data model with personal/professional separation, account-scoped filtering |
| Data Correction | FR21-FR23 | Single-tap correction interface, correction persistence store, AI retraining pipeline on correction events |
| External Access (MCP) | FR24-FR29 | MCP server conforming to protocol spec, read-only endpoints, filtering/aggregation layer, LLM-optimized response format |
| Security & Access Control | FR30-FR33 | Authentication on all endpoints (web + MCP), HTTPS enforcement, session/token management |
| UX-Discovered | FR34-FR35 | Import confirmation dialog with summary, import deletion with cascading transaction removal |

**Non-Functional Requirements:**

15 NFRs that will drive architectural decisions:

| Category | Key NFRs | Architecture Driver |
|----------|----------|-------------------|
| Performance | NFR1-NFR4 | Sub-300ms perceived latency for navigation, non-blocking CSV import, instant month navigation, MCP responses under 5s |
| Security | NFR5-NFR9 | HTTPS everywhere, auth before any data access, separate MCP auth, minimal attack surface |
| Data Integrity | NFR10-NFR13 | PostgreSQL + pgvector, French character preservation end-to-end, deterministic deduplication, correction persistence across restarts |
| Integration | NFR14-NFR15 | MCP protocol conformance, LLM-optimized response structure |

**Scale & Complexity:**

- Primary domain: Full-stack web application (SPA + REST API + MCP server)
- Complexity level: Medium
- Estimated architectural components: ~8 major components (frontend SPA, API server, CSV parser, AI categorization engine, deduplication engine, correction store, MCP server, database layer)

### Technical Constraints & Dependencies

- **PostgreSQL with pgvector** -- explicitly required. Relational for transactional data, vector extension for potential semantic search.
- **Tailwind CSS + shadcn/ui** -- frontend design system decided in UX spec. Custom components + selective shadcn/ui adoption.
- **Inter typeface** -- sole typeface, variable font.
- **Chrome-only browser target** -- no polyfills, latest CSS/JS features available.
- **Single-user deployment** -- no multi-tenancy, no user management beyond single auth.
- **CSV-only data ingestion** -- no bank API integration, no real-time sync.
- **Solo developer** -- architecture must be implementable and maintainable by one person.
- **Publicly accessible server** -- security is not optional despite single-user nature.

### Cross-Cutting Concerns Identified

- **Authentication** -- Unified auth strategy must protect the web UI, API endpoints, and MCP server. Three access points, one identity.
- **Character encoding** -- French accented characters (é, è, ê, à, ç) must survive the full pipeline: CSV file → parser → database → API response → frontend display → MCP response. A single encoding failure anywhere corrupts merchant names.
- **Category data model** -- Categories and subcategories are referenced by the overview UI, drill-down navigation, correction interface, AI engine, deduplication logic, and MCP queries. This is the most connected data structure in the system.
- **Amount handling** -- Storage format (cents vs. decimals), calculation precision, and display format (French locale: 1 234,56 €) must be consistent. Rounding errors in aggregation would erode trust.
- **AI correction propagation** -- When a user corrects a category, the correction must: update the transaction, persist the merchant→category mapping, and influence future AI decisions. This spans the correction UI, the API, the AI engine, and the database.

## Starter Template Evaluation

### Primary Technology Domain

Full-stack Laravel monolith with Inertia.js bridge -- server-side routing with React frontend. Not a traditional SPA + API architecture.

### Starter Options Considered

| Option | Starter | Verdict |
|--------|---------|---------|
| A | Official Laravel React Starter Kit (`laravel/react-starter-kit`) | **Selected** -- first-party, ships with exact stack (React 19, TS, Tailwind 4, shadcn/ui, Inertia 2, Laravel auth) |
| B | Nuno Maduro's Ultra-Strict Variant (`nunomaduro/laravel-starter-kit-inertia-react`) | Considered -- stricter type safety, but adds opinions beyond what's needed for a solo project |
| C | Custom setup from scratch | Rejected -- no benefit over the official kit |

### Selected Starter: Official Laravel React Starter Kit

**Rationale:**
- First-party, maintained by Laravel team -- guaranteed compatibility with Laravel 12 and Laravel Cloud
- Ships with React 19, TypeScript, Tailwind 4, shadcn/ui, Inertia 2 -- the exact frontend stack from the UX spec
- Built-in authentication (login, register, password reset, email verification) satisfies FR30-FR33
- Minimal configuration to start building domain features

**Initialization Command:**

```bash
laravel new budget-app --using=laravel/react-starter-kit
```

Post-init Bun setup:
```bash
cd budget-app
rm package-lock.json
./vendor/bin/sail up -d
sail bun install
```

### Architectural Decisions Provided by Starter

**Language & Runtime:**
- PHP 8.5 (backend) + TypeScript (frontend)
- Bun as JavaScript runtime (via Laravel Sail)
- React 19 with JSX/TSX

**Styling Solution:**
- Tailwind CSS 4 with shadcn/ui component primitives

**Build Tooling:**
- Vite (frontend bundling + HMR)
- Composer (PHP dependencies)
- Bun (JS dependency management)

**Authentication:**
- Laravel built-in auth with Inertia-powered pages
- Session-based authentication with CSRF protection

**Code Organization:**
- Laravel standard structure (app/, resources/, routes/, database/)
- Inertia pages in `resources/js/pages/`
- Shared components in `resources/js/components/`
- TypeScript types alongside components

**Development Experience:**
- Laravel Sail (Docker: PHP 8.5, PostgreSQL, Bun)
- Vite HMR for instant frontend updates
- `composer run dev` starts both servers

### Additional Packages Required

| Package | Purpose | Version |
|---------|---------|---------|
| Prism PHP (`prism-php/prism`) | Multi-provider LLM integration (Gemini) for AI categorization | ~v1.0 |
| laravel/mcp | MCP server exposing budget data to Claude | v0.5.2+ |
| pgvector/pgvector | Vector similarity search for PostgreSQL | v0.2.2 |
| tpetry/laravel-postgresql-enhanced | Enhanced PostgreSQL support (vector columns in migrations) | Latest |
| laravel/nightwatch | Production monitoring (errors, queries, jobs, performance) | Latest |
| framer/motion | React animation library for number transitions, page transitions, and staggered cascades | Latest |

### Architecture Note: Inertia Changes the Model

The PRD assumed a traditional SPA + REST API. With Inertia.js:
- **Web UI:** Laravel controllers return Inertia responses -> React renders pages with props. No REST API for the frontend.
- **MCP Server:** Lives within the same Laravel app using `laravel/mcp`. Separate concern, same codebase.
- **Simplification:** No API serialization layer, no CORS, no token-based auth for the frontend. Inertia handles data passing via server-side props.

**Note:** Project initialization using this command should be the first implementation story.

## Core Architectural Decisions

### Decision Priority Analysis

**Critical Decisions (Block Implementation):**
- Amount storage format (integer cents)
- Category data model (predefined, self-referential hierarchy)
- Deduplication strategy (hash + unique constraint)
- MCP authentication (Sanctum API tokens)
- Async processing strategy (hybrid: instant matching + queue for unknowns)

**Important Decisions (Shape Architecture):**
- State management (Inertia props + useState)
- Drill-down navigation (Inertia page visits)
- Queue driver (PostgreSQL database driver)
- Monitoring (Laravel Nightwatch)

**Deferred Decisions (Post-MVP):**
- Caching layer (add Redis only if performance requires it)
- CI pipeline (add GitHub Actions when project stabilizes)

### Data Architecture

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Amount storage | Integer cents | Eliminates floating-point precision issues. All math is integer. Display formatting at presentation layer only. |
| Category model | Predefined seeded categories | UX spec defines 14 categories with specific colors. Seeded on install, AI maps to these. Custom categories can be added later. |
| Category hierarchy | Self-referential `parent_id` | Single `categories` table with nullable `parent_id`. Top-level categories have null parent; subcategories reference their parent. Supports the 3-level drill-down naturally. |
| Deduplication | Composite hash + unique constraint | SHA-256 hash from `date + amount_cents + description + account_id` stored on each transaction for fast import-time lookup. Database unique constraint on the same fields as a safety net. |
| Caching | None for MVP | Single user, modest data volume. PostgreSQL with proper indexes handles aggregation queries. Add caching only if performance becomes an issue. |

### Authentication & Security

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Web auth | Laravel built-in auth (session-based) | Provided by starter kit. Login, register, password reset, email verification. Session + CSRF protection. |
| MCP auth | Laravel Sanctum API tokens | Personal API token for Claude. Bearer token in header. Revocable, database-stored. Laravel-native solution. |
| HTTPS | Laravel Cloud TLS termination | Managed at infrastructure level. App configured with `APP_URL=https://...` and `TrustProxies` middleware. |

### API & Communication Patterns

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Frontend data | Inertia.js (no REST API) | Laravel controllers return Inertia responses. React renders pages with server-side props. No API layer for the web UI. |
| MCP server | Granular tools via `laravel/mcp` | 5-8 focused tools (transactions by category, by date range, category totals, monthly comparison, etc.). LLMs work better with clearly scoped tools. |
| Async processing | Hybrid | Merchant-name matching (instant) for known merchants. Queue unknown transactions for Gemini categorization via Laravel queue jobs. Best UX with manageable complexity. |
| Error handling | Standard Laravel + Inertia | Laravel exception handler + Inertia error bag. Custom exceptions for domain errors (CSV parse failure, unrecognized format). No exotic patterns. |

### Frontend Architecture

| Decision | Choice | Rationale |
|----------|--------|-----------|
| State management | Inertia props + React useState | Server data via Inertia props. Local UI state (sheet visibility, drill-down position) via useState. No global state library needed for a single-screen app. |
| Drill-down navigation | Inertia page visits | Each level is a separate Inertia page (`/`, `/categories/{id}`, `/categories/{id}/subcategories/{id}`). Back button works natively. Laravel scopes data per page. Color takeover transition via page transition effects. |
| Category correction | `router.patch` | Single-field update: `router.patch('/transactions/{id}', { category_id })`. Lighter than `useForm`. Automatic page prop refresh via Inertia. |
| Component library | shadcn/ui (selective) | 4 components adopted: DropdownMenu, Sheet, Dialog, Toast. Themed via CSS variables. Custom components for domain-specific UI (CategoryCard, MonthStrip, etc.). |
| Animation | Framer Motion | Number transitions (AnimatedNumber component), page transitions (AnimatePresence on Inertia navigation), staggered cascades (variants with staggerChildren). Respect prefers-reduced-motion via useReducedMotion hook. |

### Infrastructure & Deployment

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Deployment | Laravel Cloud auto-deploy | Auto-deploy on push to main. No separate CI pipeline for MVP. |
| Queue driver | PostgreSQL (database) | Avoids paying for a Redis instance on Laravel Cloud. Sufficient for a single-user app with occasional queue jobs. |
| Monitoring | Laravel Nightwatch | First-party Laravel monitoring. Error tracking, slow queries, N+1 detection, job monitoring. Also provides an MCP server for browsing issues from Claude. |
| Environment config | Laravel Cloud (prod) + Sail `.env` (local) | Secrets managed in Laravel Cloud dashboard. Local development uses `.env` via Sail. |
| Dev environment | Laravel Sail | Docker-based: PHP 8.5, PostgreSQL, Bun. No Redis needed (database queue driver). `composer run dev` starts everything. |

### Decision Impact Analysis

**Implementation Sequence:**
1. Project initialization (starter kit + Bun setup + Sail config)
2. Database schema (categories seed, transactions table with hash column, account model)
3. Authentication (starter kit provides this, add Sanctum token generation for MCP)
4. CSV import pipeline (parser + deduplication + hybrid categorization)
5. Inertia pages (overview, category drill-down, transaction drill-down)
6. Category correction (router.patch + AI learning persistence)
7. MCP server (granular tools via laravel/mcp)
8. Laravel Cloud deployment + Nightwatch setup

**Cross-Component Dependencies:**
- Category seed must exist before AI categorization can map to categories
- Sanctum must be configured before MCP server can accept authenticated requests
- Queue worker must be running (database driver) before hybrid categorization can process unknowns
- Prism PHP + Gemini config must be set up before any AI categorization works
- Inertia page structure must match Laravel route definitions

## Implementation Patterns & Consistency Rules

### Critical Conflict Points Identified

12 areas where AI agents could make different choices, now resolved into explicit rules.

### Naming Patterns

**Database Naming (Laravel Convention):**
- Tables: `snake_case`, plural (`transactions`, `categories`, `accounts`, `imports`)
- Columns: `snake_case` (`amount_cents`, `parent_id`, `category_id`, `dedup_hash`)
- Foreign keys: `{model}_id` (`category_id`, `account_id`, `import_id`)
- Timestamps: `created_at`, `updated_at` (Laravel default)
- Indexes: Laravel default naming (`{table}_{column}_index`)

**PHP Code (Laravel Convention):**
- Models: PascalCase singular (`Transaction`, `Category`, `Account`, `Import`)
- Controllers: PascalCase with suffix (`CategoryController`, `TransactionController`, `ImportController`)
- Services: PascalCase with suffix (`CsvImportService`, `CategorizationService`, `DeduplicationService`)
- Jobs: PascalCase imperative (`CategorizeTransactions`, `ProcessCsvImport`)
- Events: PascalCase past tense (`TransactionCategorized`, `ImportCompleted`, `CategoryCorrected`)
- Methods: camelCase (`getMonthlyTotals()`, `categorizeTransaction()`)
- Config keys: snake_case (`categorization.default_category`, `csv.supported_formats`)

**TypeScript/React:**
- Components: PascalCase files and exports (`CategoryCard.tsx`, `MonthStrip.tsx`, `TransactionRow.tsx`)
- Utilities: camelCase files and exports (`formatAmount.ts`, `categoryColors.ts`)
- Types/Interfaces: PascalCase (`Transaction`, `Category`, `CategoryProps`, `MonthData`)
- Variables/functions: camelCase (`categoryId`, `formatAmount()`, `handleCorrection()`)
- Hooks: camelCase with `use` prefix (`useMonthNavigation`, `useImport`)

**Routes:**
- URLs: kebab-case, resource-style (`/categories/{category}`, `/categories/{category}/transactions`)
- Route names: dot-notation (`categories.show`, `transactions.update`, `imports.store`)

**Inertia Props:** camelCase (`categoryTotals`, `currentMonth`, `transactions`). Laravel transforms snake_case to camelCase before passing to React.

**MCP Tools:** snake_case (`get_category_totals`, `query_transactions`, `get_monthly_comparison`).

### Structure Patterns

**Project Organization (Laravel Standard + Budget-App Specifics):**

```
app/
├── Http/Controllers/       # Inertia controllers
├── Models/                 # Eloquent models
├── Services/               # Domain services (flat)
│   ├── CsvImportService.php
│   ├── CategorizationService.php
│   ├── DeduplicationService.php
│   └── MerchantMatchingService.php
├── Jobs/                   # Queue jobs
│   └── CategorizeTransactions.php
├── Events/                 # Domain events
├── Parsers/                # CSV parser strategy
│   ├── CsvParserInterface.php
│   ├── CsvParserFactory.php
│   ├── BanquePopulaireParser.php
│   └── RevolutParser.php
├── MCP/                    # MCP tool definitions
│   └── Tools/
└── Exceptions/             # Custom exceptions
    ├── UnrecognizedCsvFormatException.php
    └── CsvParseException.php

resources/js/
├── pages/                  # Inertia pages
│   ├── Dashboard.tsx       # Overview (main screen)
│   ├── Categories/
│   │   └── Show.tsx        # Category drill-down
│   └── Auth/               # Starter kit auth pages
├── components/             # Shared components
│   ├── ui/                 # shadcn/ui components
│   ├── CategoryCard.tsx
│   ├── MonthStrip.tsx
│   ├── SummaryWidget.tsx
│   ├── TransactionRow.tsx
│   ├── SubcategoryRow.tsx
│   ├── ColorHeader.tsx
│   ├── ImportDropZone.tsx
│   └── EmptyState.tsx
├── lib/                    # Utilities
│   ├── formatAmount.ts
│   └── types.ts
└── hooks/                  # Custom React hooks

database/
├── migrations/
└── seeders/
    └── CategorySeeder.php  # Predefined categories with colors

tests/
├── Feature/                # Laravel feature tests
└── Unit/                   # Unit tests
```

**Test Organization:** Laravel default -- `tests/Feature/` for HTTP/integration tests, `tests/Unit/` for isolated unit tests. Test files mirror the class they test (`CsvImportServiceTest.php`).

### Format Patterns

**Amount Handling (Critical -- All Agents Must Follow):**

| Layer | Format | Example |
|-------|--------|---------|
| Database | Integer cents (`amount_cents INTEGER`) | `52049` |
| PHP | Integer cents, no conversion until display | `$transaction->amount_cents` |
| Inertia prop | `amountCents` (integer, camelCase) | `{ amountCents: 52049 }` |
| TypeScript display | `formatAmount(cents)` → French locale | `"520,49 €"` |
| MCP response | `amount_cents` (integer, snake_case) | `{ "amount_cents": 52049 }` |

**Never** pre-format amounts on the server. **Never** use floating-point for money. **One** formatting function in TypeScript (`formatAmount`), **one** in PHP (for logs/debugging only).

**Animated Amounts (Phase 2):**
- `AnimatedNumber` component receives integer cents, animates between old and new values using Framer Motion
- `formatAmount.ts` applies at each animation frame to maintain French locale formatting
- `tabular-nums` preserved during animation for stable layout
- On `prefers-reduced-motion`: skip animation, render final value directly

**Date Handling:**

| Layer | Format | Example |
|-------|--------|---------|
| Database | `DATE` or `TIMESTAMP` (PostgreSQL native) | `2026-02-19` |
| PHP | Carbon instances | `$transaction->date` |
| Inertia prop | ISO 8601 string | `"2026-02-19"` |
| TypeScript display | Short format (from UX spec) | `"Feb 19"` |
| MCP response | ISO 8601 string | `"2026-02-19"` |

**Category Color Mapping:**
- Colors stored in the `categories` table (`color VARCHAR` column, hex value)
- Seeded with UX spec colors (e.g., `#A3E635` for Food, `#FBBF24` for Restaurants)
- Frontend receives color as a prop on each category -- no frontend color constants
- Single source of truth: the database

### Communication Patterns

**Laravel Events:**
- Naming: PascalCase past tense (`TransactionCategorized`, `ImportCompleted`, `CategoryCorrected`)
- Payload: The relevant Eloquent model(s) as constructor arguments
- Usage: Dispatched from services, listened to by jobs or other services

**Inertia Data Flow:**
- Server → Client: Inertia props (camelCase), passed by controllers via `Inertia::render()`
- Client → Server: `router.patch()`, `router.post()`, `router.delete()` -- never raw fetch/axios
- Flash messages: Inertia's built-in session flash, consumed by Toast component

**MCP Response Structure:**
- Always return structured JSON with snake_case keys
- Include metadata: `{ data: [...], meta: { total_count, period, account } }`
- Amounts as integer cents, dates as ISO 8601
- Descriptive field names for LLM consumption (`merchant_name` not `desc`)

### Process Patterns

**Error Handling:**
- **PHP:** Custom exception classes for domain errors (`UnrecognizedCsvFormatException`, `CsvParseException`). Laravel's exception handler converts to appropriate Inertia error responses.
- **TypeScript:** Inertia's `onError` callback for server errors. No try/catch around `router.patch` -- Inertia handles error flow.
- **User-facing errors:** Always human language, never codes or stack traces. Format: what happened + what to do next.
- **Logging:** Laravel's `Log` facade. `Log::error()` for failures, `Log::info()` for import summaries, `Log::debug()` for AI categorization decisions.

**Loading States:**
- Inertia's `processing` state on form submissions (import, correction)
- No global loading state -- each interaction manages its own
- No skeleton loaders for MVP -- overview renders with server data from Inertia (no client-side fetch)

**CSV Parser Strategy Pattern:**

```php
interface CsvParserInterface {
    public function canParse(string $content, string $filename): bool;
    public function parse(string $content): Collection;
}
```

- `CsvParserFactory::detect($content, $filename)` iterates registered parsers, returns the first that `canParse()` returns true
- Each parser normalizes to a common `TransactionData` DTO: `date`, `description`, `amountCents`, `rawLine`
- Adding a new bank = one new class implementing `CsvParserInterface`, registered in the factory

**Hybrid Categorization Flow:**

1. Transaction arrives from parser
2. `MerchantMatchingService` checks `merchant_rules` table for exact match → if found, assign category (instant)
3. If no match, dispatch `CategorizeTransactions` job to queue
4. Job calls Prism PHP → Gemini with transaction description → receives category suggestion
5. Transaction updated with category, user reviews on next page load

### Enforcement Guidelines

**All AI Agents MUST:**
- Store amounts as integer cents -- never floats, never pre-formatted strings
- Use the CSV parser strategy pattern when adding bank format support
- Pass Inertia props in camelCase, MCP responses in snake_case
- Place services in `app/Services/` (flat), parsers in `app/Parsers/`
- Use `router.patch`/`router.post` for client→server communication, never raw HTTP calls
- Follow Laravel naming conventions for models, controllers, migrations, events
- Read category colors from the database, never hardcode in TypeScript

## Project Structure & Boundaries

### Complete Project Directory Structure

```
budget-app/
├── .env                            # Local environment (Sail)
├── .env.example                    # Environment template
├── .gitignore
├── artisan
├── composer.json
├── composer.lock
├── package.json                    # Bun-managed
├── bun.lock
├── vite.config.ts
├── tsconfig.json
├── tailwind.config.ts              # Tailwind 4 config (category colors, dark theme, spacing tokens)
├── phpunit.xml
├── docker-compose.yml              # Laravel Sail (PHP 8.5, PostgreSQL, Bun)
│
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── DashboardController.php       # Overview page (FR12-FR17)
│   │   │   ├── CategoryController.php        # Category drill-down (FR14-FR15)
│   │   │   ├── TransactionController.php     # Transaction correction (FR21-FR23)
│   │   │   ├── ImportController.php          # CSV import flow (FR1-FR7, FR34-FR35)
│   │   │   └── AccountController.php         # Account switching (FR18-FR20)
│   │   └── Middleware/
│   │       └── ForceHttps.php                # HTTPS enforcement (FR31)
│   │
│   ├── Models/
│   │   ├── User.php                          # Auth user (starter kit)
│   │   ├── Transaction.php                   # Core: amount_cents, date, description, dedup_hash
│   │   ├── Category.php                      # Self-referential: parent_id, name, color, icon
│   │   ├── Account.php                       # Bank account: name, bank, type (personal/pro)
│   │   ├── Import.php                        # Import batch: file_name, bank, transaction_count, date_range
│   │   └── MerchantRule.php                  # AI learning: merchant_pattern → category_id
│   │
│   ├── Services/
│   │   ├── CsvImportService.php              # Orchestrates import flow (FR1-FR7)
│   │   ├── CategorizationService.php         # Hybrid categorization orchestration (FR8-FR11)
│   │   ├── MerchantMatchingService.php       # Fast merchant → category lookup (FR9, FR11)
│   │   ├── DeduplicationService.php          # Hash-based dedup (FR5)
│   │   ├── BudgetQueryService.php            # Shared query logic for controllers + MCP (extended: trends, search)
│   │   └── RecurringDetectionService.php     # Merchant grouping + frequency analysis (FR41-FR42)
│   │
│   ├── Parsers/
│   │   ├── CsvParserInterface.php            # Strategy interface
│   │   ├── CsvParserFactory.php              # Auto-detection factory
│   │   ├── BanquePopulaireParser.php         # BP format (FR1, FR3-FR4)
│   │   ├── RevolutParser.php                 # Revolut format (FR2, FR3-FR4)
│   │   └── TransactionData.php               # Normalized DTO
│   │
│   ├── Jobs/
│   │   └── CategorizeTransactions.php        # Queue job: Gemini categorization for unknowns
│   │
│   ├── Events/
│   │   ├── ImportCompleted.php               # After successful import
│   │   ├── TransactionCategorized.php        # After AI categorization
│   │   └── CategoryCorrected.php             # After user correction (triggers learning)
│   │
│   ├── Listeners/
│   │   └── UpdateMerchantRule.php            # On CategoryCorrected → persist rule
│   │
│   ├── MCP/
│   │   └── Tools/
│   │       ├── GetCategoryTotals.php         # Monthly category sums (FR24, FR27)
│   │       ├── QueryTransactions.php         # Filter by category/date/account (FR24-FR26)
│   │       ├── GetMonthlyComparison.php      # Month-over-month (FR27)
│   │       ├── GetTransactionDetails.php     # Individual transaction access (FR28)
│   │       ├── GetAccountSummary.php         # Income/expenses by account (FR26-FR27)
│   │       ├── GetCategoryBreakdown.php      # Subcategory drill-down (FR27-FR28)
│   │       ├── SearchTransactions.php        # Full-text search via MCP (FR43-FR44)
│   │       └── GetRecurringTransactions.php  # Recurring patterns via MCP (FR41-FR42)
│   │
│   ├── Exceptions/
│   │   ├── UnrecognizedCsvFormatException.php  # Unknown CSV format (FR7)
│   │   └── CsvParseException.php               # Parse failure
│   │
│   └── Providers/
│       └── AppServiceProvider.php            # Register parsers, bind interfaces
│
├── config/
│   ├── categorization.php                    # AI config: default provider, fallback category, confidence threshold
│   └── csv.php                               # Supported formats, encoding settings
│
├── database/
│   ├── migrations/
│   │   ├── xxxx_create_accounts_table.php
│   │   ├── xxxx_create_categories_table.php  # name, slug, color, icon, parent_id
│   │   ├── xxxx_create_transactions_table.php # amount_cents, date, description, dedup_hash, category_id, account_id, import_id
│   │   ├── xxxx_create_imports_table.php     # file_name, bank, status, transaction_count
│   │   ├── xxxx_create_merchant_rules_table.php # merchant_pattern, category_id
│   │   └── xxxx_create_jobs_table.php        # Database queue driver
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── CategorySeeder.php                # 14 predefined categories + subcategories with colors
│
├── resources/
│   └── js/
│       ├── app.tsx                           # Inertia app entry
│       ├── pages/
│       │   ├── Dashboard.tsx                 # Overview: MonthStrip + SummaryWidget + CategoryCards (FR12-FR13, FR16-FR17, FR36-FR40)
│       │   ├── Categories/
│       │   │   └── Show.tsx                  # Category drill-down: ColorHeader + SubcategoryRows + TransactionRows (FR14-FR15)
│       │   ├── Import/
│       │   │   └── Confirm.tsx               # Import confirmation dialog page (FR34)
│       │   ├── Recurring/
│       │   │   └── Index.tsx                 # Recurring transactions page (FR41-FR42)
│       │   └── Auth/                         # Starter kit auth pages (FR30)
│       │       ├── Login.tsx
│       │       ├── Register.tsx
│       │       └── ...
│       ├── components/
│       │   ├── ui/                           # shadcn/ui components
│       │   │   ├── dropdown-menu.tsx         # Account picker
│       │   │   ├── sheet.tsx                 # Category correction picker (mobile)
│       │   │   ├── dialog.tsx                # Import confirmation, import deletion
│       │   │   └── toast.tsx                 # AI learning acknowledgment
│       │   ├── CategoryCard.tsx              # Category card for overview (extended: trend indicator)
│       │   ├── MonthStrip.tsx                # Horizontal month navigation
│       │   ├── SummaryWidget.tsx             # Income/Expenses cards
│       │   ├── TransactionRow.tsx            # Transaction in drill-down
│       │   ├── SubcategoryRow.tsx            # Subcategory in drill-down
│       │   ├── ColorHeader.tsx               # Category drill-down header
│       │   ├── ImportDropZone.tsx            # Drag-and-drop / file picker
│       │   ├── EmptyState.tsx                # First-time experience
│       │   ├── CategoryPicker.tsx            # Correction picker (wraps Sheet/DropdownMenu)
│       │   ├── AnimatedNumber.tsx            # Counting transition for amounts (FR38)
│       │   ├── SearchBar.tsx                 # Transaction search input + results (FR43-FR44)
│       │   └── RecurringList.tsx             # Recurring transaction view (FR41-FR42)
│       ├── lib/
│       │   ├── formatAmount.ts               # Integer cents → "1 234,56 €"
│       │   ├── formatDate.ts                 # ISO string → "Feb 19"
│       │   └── types.ts                      # Shared TypeScript types
│       ├── hooks/
│       │   ├── useViewport.ts                # Mobile/desktop detection for Sheet vs Dropdown
│       │   └── useReducedMotion.ts           # Respects prefers-reduced-motion (FR38-FR40)
│       └── layouts/
│           └── AppLayout.tsx                 # Main layout (header with account picker)
│
├── routes/
│   ├── web.php                               # Inertia routes
│   └── console.php                           # Artisan commands
│
├── tests/
│   ├── Feature/
│   │   ├── ImportTest.php                    # CSV import + dedup + categorization flow
│   │   ├── CategoryDrilldownTest.php         # Navigation + data scoping
│   │   ├── TransactionCorrectionTest.php     # Correction + merchant rule creation
│   │   ├── AccountSwitchingTest.php          # Personal/professional filtering
│   │   └── McpToolTest.php                   # MCP tool responses
│   └── Unit/
│       ├── BanquePopulaireParserTest.php     # BP CSV parsing
│       ├── RevolutParserTest.php             # Revolut CSV parsing
│       ├── DeduplicationServiceTest.php      # Hash generation + dedup logic
│       ├── MerchantMatchingServiceTest.php   # Pattern matching
│       └── FormatAmountTest.php              # Amount formatting (PHP side)
│
└── public/
    └── build/                                # Vite output
```

### Architectural Boundaries

**Web Boundary (Inertia):**
- Controllers return `Inertia::render()` -- never JSON, never Blade views
- All data flows through Inertia props (camelCase)
- Authentication via Laravel session middleware
- Routes in `web.php`, protected by `auth` middleware

**MCP Boundary:**
- Tools in `app/MCP/Tools/` -- isolated from web controllers
- Authentication via Sanctum API tokens (Bearer header)
- Response format: structured JSON with snake_case keys + metadata
- Tools call `BudgetQueryService` for data access -- same service the web controllers use

**Service Boundary:**
- Services are stateless -- no direct DB queries in controllers or MCP tools
- `BudgetQueryService` is the shared data access layer for both web and MCP
- `CsvImportService` orchestrates the full import pipeline: parse → dedup → categorize
- `CategorizationService` orchestrates hybrid flow: merchant match → queue fallback

**Data Boundary:**
- All database access through Eloquent models
- No raw SQL except in migrations
- `BudgetQueryService` encapsulates complex queries (aggregations, filtering, joins)
- Category hierarchy traversed via Eloquent relationships (`parent`, `children`)

### Requirements to Structure Mapping

| FR Domain | Controllers | Services | Models | Pages | Components |
|-----------|------------|----------|--------|-------|------------|
| Data Ingestion (FR1-FR7) | `ImportController` | `CsvImportService`, `DeduplicationService` | `Transaction`, `Import` | `Import/Confirm` | `ImportDropZone` |
| Transaction Intelligence (FR8-FR11) | -- | `CategorizationService`, `MerchantMatchingService` | `MerchantRule` | -- | -- |
| Financial Overview (FR12-FR17) | `DashboardController`, `CategoryController` | `BudgetQueryService` | `Transaction`, `Category` | `Dashboard`, `Categories/Show` | `CategoryCard`, `MonthStrip`, `SummaryWidget`, `ColorHeader`, `SubcategoryRow`, `TransactionRow` |
| Account Management (FR18-FR20) | `AccountController` | `BudgetQueryService` | `Account` | `Dashboard` | `DropdownMenu` (account picker) |
| Data Correction (FR21-FR23) | `TransactionController` | `MerchantMatchingService` | `Transaction`, `MerchantRule` | `Categories/Show` | `CategoryPicker`, `Sheet`, `Toast` |
| MCP Access (FR24-FR29) | -- | `BudgetQueryService` | All | -- | -- |
| Security (FR30-FR33) | Starter kit auth | -- | `User` | `Auth/*` | -- |
| Import Management (FR34-FR35) | `ImportController` | `CsvImportService` | `Import`, `Transaction` | `Import/Confirm` | `Dialog` |
| Visual Polish (FR36-FR40) | `DashboardController` | `BudgetQueryService` | `Transaction`, `Category` | `Dashboard` | `CategoryCard` (trend), `AnimatedNumber`, `MonthStrip` |
| Transaction Discovery (FR41-FR44) | `SearchController`, `RecurringController` | `BudgetQueryService`, `RecurringDetectionService` | `Transaction` | `Dashboard` (search inline), `Recurring/Index` | `SearchBar`, `RecurringList` |

### Cross-Cutting Concerns Mapping

| Concern | Location |
|---------|----------|
| Authentication (web) | Laravel middleware (`auth`), starter kit |
| Authentication (MCP) | Sanctum middleware on MCP routes |
| Character encoding | `CsvParserInterface` normalizes to UTF-8; PostgreSQL stores UTF-8; Inertia passes through |
| Amount handling | `amount_cents` column → PHP integer → Inertia prop → `formatAmount.ts` |
| Category colors | `categories.color` column → Inertia prop → component `style` attribute |
| Error handling | Custom exceptions → Laravel handler → Inertia error bag |
| AI learning | `CategoryCorrected` event → `UpdateMerchantRule` listener → `merchant_rules` table |

### Integration Points

**Internal Data Flow:**

```
CSV File → CsvParserFactory → Parser → TransactionData DTO
    → DeduplicationService (hash check)
    → MerchantMatchingService (instant match)
    → [if unknown] CategorizeTransactions job → Prism/Gemini
    → Transaction model (persisted)
    → Inertia props via BudgetQueryService
    → React components (display)
```

**External Integrations:**

| Integration | Package | Purpose | Auth |
|-------------|---------|---------|------|
| Google Gemini | Prism PHP | Transaction categorization | API key in `.env` |
| Claude (MCP) | laravel/mcp | Budget data queries | Sanctum API token |
| Laravel Cloud | Built-in | Deployment, TLS, env management | Cloud dashboard |
| Laravel Nightwatch | laravel/nightwatch | Monitoring | Nightwatch token in `.env` |

## Architecture Validation Results

### Coherence Validation

**Decision Compatibility:** All technology choices verified compatible:
- Laravel 12 + PHP 8.5 + Inertia 2 + React 19 + TypeScript + Tailwind 4 + shadcn/ui
- Prism PHP (~v1.0) requires Laravel 11/12 + PHP 8.2+
- laravel/mcp (v0.5.2+) compatible with Laravel 12
- Sanctum ships with Laravel, supports both session and API token auth
- PostgreSQL database queue driver built into Laravel -- no Redis dependency
- Bun supported natively by Laravel Sail
- No version conflicts detected

**Pattern Consistency:** All naming conventions, structure patterns, and communication patterns are internally consistent. camelCase for Inertia props / snake_case for MCP / Laravel conventions for PHP -- no overlaps or contradictions.

**Structure Alignment:** Project structure supports all decisions. Boundaries (web, MCP, service, data) are respected. Every architectural component has a home in the directory tree.

**Clarification Added:** Month navigation and account switching use Inertia partial reloads on the same Dashboard page (`router.get` with `preserveState: true`), not full page visits. Drill-down navigation uses full Inertia page visits to different pages. This distinction ensures instant month/account switching while maintaining clean URL-based drill-down.

### Requirements Coverage Validation

**Functional Requirements (35/35 covered):**

| FR | Description | Architectural Support |
|----|------------|----------------------|
| FR1-FR2 | CSV import (BP + Revolut) | `BanquePopulaireParser`, `RevolutParser` |
| FR3-FR4 | European CSV + French encoding | Parser implementations normalize to UTF-8 |
| FR5 | Deduplication | `DeduplicationService` + SHA-256 hash + unique constraint |
| FR6 | Drag-and-drop + file picker | `ImportDropZone` component |
| FR7 | Format detection + errors | `CsvParserFactory` + `UnrecognizedCsvFormatException` |
| FR8 | Auto-categorize | `CategorizationService` + hybrid flow |
| FR9 | Learn from corrections | `MerchantMatchingService` + `MerchantRule` model |
| FR10 | Fallback category | Predefined "Other/Uncategorized" in `CategorySeeder` |
| FR11 | Near-perfect for known | `MerchantMatchingService` exact match |
| FR12-FR13 | Category overview + income/expenses | `Dashboard` page + `CategoryCard` + `SummaryWidget` |
| FR14-FR15 | Drill-down (subcategories + transactions) | `Categories/Show` page + `SubcategoryRow` + `TransactionRow` |
| FR16-FR17 | Month navigation + default current | `MonthStrip` + Inertia partial reload with `?month=` param |
| FR18-FR20 | Account management + separation | `Account` model + `BudgetQueryService` filtering + `DropdownMenu` |
| FR21-FR23 | Category correction + persistence | `TransactionController` + `router.patch` + `CategoryCorrected` event → `UpdateMerchantRule` |
| FR24-FR29 | MCP access (filter, aggregate, detail) | 6 granular MCP tools via `laravel/mcp` + `BudgetQueryService` |
| FR30-FR33 | Auth + HTTPS + MCP auth | Laravel auth (starter kit) + Sanctum API tokens + Laravel Cloud TLS |
| FR34-FR35 | Import confirmation + deletion | `Import/Confirm` page + `ImportController` delete action + `Dialog` |

**Non-Functional Requirements (15/15 covered):**

| NFR | Requirement | Architectural Support |
|-----|------------|----------------------|
| NFR1 | Sub-300ms navigation | Inertia server-rendered pages, no client-side fetch |
| NFR2 | Non-blocking import | Hybrid async: instant merchant match + queue for unknowns |
| NFR3 | Instant month navigation | Inertia partial reload (`preserveState: true, only: [...]`) |
| NFR4 | MCP under 5s | `BudgetQueryService` with PostgreSQL indexes |
| NFR5-NFR9 | Security suite | Laravel Cloud TLS + auth middleware + Sanctum + session management + minimal surface |
| NFR10 | PostgreSQL + pgvector | Database decided, pgvector/pgvector + laravel-postgresql-enhanced packages |
| NFR11 | French character preservation | UTF-8 normalization in parsers → PostgreSQL UTF-8 → Inertia passthrough |
| NFR12 | Deterministic deduplication | SHA-256 hash + database unique constraint |
| NFR13 | Correction persistence | `merchant_rules` table, survives restarts and updates |
| NFR14-NFR15 | MCP protocol + LLM format | laravel/mcp conformance + structured snake_case JSON with metadata |

### Implementation Readiness Validation

**Decision Completeness:** All critical and important decisions documented with specific technology choices, versions, and rationale. No ambiguous decisions remaining.

**Structure Completeness:** Every FR domain mapped to specific controllers, services, models, pages, and components. Full directory tree with file-level specificity.

**Pattern Completeness:** 12 conflict points identified and resolved. Amount handling, date handling, naming conventions, communication patterns, error handling, and process patterns all specified with concrete examples.

### Gap Analysis Results

**Critical Gaps:** None found.

**Important Gaps (documented for implementation):**

1. **Sail docker-compose customization** -- Remove Redis from default Sail services since database queue driver is used. Add pgvector extension to the PostgreSQL service. This is a first-day setup task.
2. **Tailwind 4 configuration** -- Tailwind 4 uses CSS-based configuration, not `tailwind.config.ts`. The project tree lists `tailwind.config.ts` for clarity, but the actual implementation may use `@theme` directives in CSS. Resolve during project initialization.
3. **`BudgetQueryService` output contract** -- The service is shared between web controllers and MCP tools. Controllers transform output to camelCase Inertia props; MCP tools format as snake_case JSON. The service itself should return domain objects (Eloquent models/collections), and transformation happens in the consumer layer.

**Deferred (Post-Phase 2):**
- Testing coverage targets
- CI/CD pipeline (GitHub Actions)
- Caching layer (Redis)
- Performance profiling and optimization
- Phase 3 features (spending ceilings, evolution view, notifications, MCP write access)

### Architecture Completeness Checklist

**Requirements Analysis**
- [x] Project context thoroughly analyzed
- [x] Scale and complexity assessed
- [x] Technical constraints identified
- [x] Cross-cutting concerns mapped

**Architectural Decisions**
- [x] Critical decisions documented with versions
- [x] Technology stack fully specified
- [x] Integration patterns defined
- [x] Performance considerations addressed

**Implementation Patterns**
- [x] Naming conventions established
- [x] Structure patterns defined
- [x] Communication patterns specified
- [x] Process patterns documented

**Project Structure**
- [x] Complete directory structure defined
- [x] Component boundaries established
- [x] Integration points mapped
- [x] Requirements to structure mapping complete

### Architecture Readiness Assessment

**Overall Status:** READY FOR IMPLEMENTATION

**Confidence Level:** High -- all 35 FRs and 15 NFRs have explicit architectural support, all technology choices are verified compatible, and implementation patterns are comprehensive.

**Key Strengths:**
- Clean separation between web (Inertia) and MCP (Sanctum) access layers with shared `BudgetQueryService`
- Hybrid categorization gives instant results for known merchants while handling unknowns asynchronously
- CSV parser strategy pattern makes adding new banks a single-class operation
- Integer cents for money eliminates an entire class of bugs
- No Redis dependency reduces infrastructure cost

**Areas for Future Enhancement:**
- Redis caching layer if aggregation queries become slow at scale
- GitHub Actions CI pipeline for automated testing
- Additional MCP tools as usage patterns emerge from Claude conversations
- CSV parser for additional banks beyond BP and Revolut

### Implementation Handoff

**AI Agent Guidelines:**
- Follow all architectural decisions exactly as documented
- Use implementation patterns consistently across all components
- Respect project structure and boundaries
- Refer to this document for all architectural questions
- When in doubt about a convention, check the Implementation Patterns section

**First Implementation Priority:**

```bash
laravel new budget-app --using=laravel/react-starter-kit
```

Then: Sail setup → Bun swap → database schema → category seed → first Inertia page.

