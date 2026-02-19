---
stepsCompleted: ['step-01-validate-prerequisites', 'step-02-design-epics', 'step-03-create-stories', 'step-04-final-validation']
workflow_completed: true
inputDocuments: ['prd.md', 'architecture.md', 'ux-design-specification.md']
---

# budget-app - Epic Breakdown

## Overview

This document provides the complete epic and story breakdown for budget-app, decomposing the requirements from the PRD, UX Design, and Architecture requirements into implementable stories.

## Requirements Inventory

### Functional Requirements

FR1: Lambert can import transaction data from Banque Populaire CSV exports
FR2: Lambert can import transaction data from Revolut CSV exports (personal and professional accounts)
FR3: The system can parse European CSV formats (semicolon delimiters, comma decimal separators, DD/MM/YYYY and YYYY-MM-DD date formats)
FR4: The system can handle French character encoding (ISO-8859-1 and UTF-8) without corrupting merchant names
FR5: The system can silently skip previously imported transactions during re-import (deduplication based on date + amount + description)
FR6: Lambert can import files via drag-and-drop on desktop or file picker on mobile
FR7: The system can detect unrecognized CSV formats and display clear error messages
FR8: The system can automatically assign categories to transactions on import
FR9: The system can learn from user corrections and apply learned patterns to future imports
FR10: The system can assign an "Other/Uncategorized" fallback category when confidence is low
FR11: The system can categorize previously corrected merchants with near-perfect accuracy
FR12: Lambert can view a category overview showing all categories with their total amounts
FR13: Lambert can view income and expenses in a unified category list
FR14: Lambert can drill down from categories to subcategories
FR15: Lambert can drill down from subcategories to individual transactions (amount, date, merchant)
FR16: Lambert can navigate between months to view spending for different time periods
FR17: Lambert can view the current month's spending by default when opening the app
FR18: Lambert can import transactions from multiple bank accounts
FR19: Lambert can separate personal and professional account contexts
FR20: The system excludes professional accounts from the personal view by default
FR21: Lambert can manually re-categorize any transaction
FR22: Lambert can correct a category with a single tap/click interaction
FR23: The system persists all corrections and uses them to improve future categorization
FR24: Claude can query transactions filtered by category via MCP
FR25: Claude can query transactions filtered by date range via MCP
FR26: Claude can query transactions filtered by account via MCP
FR27: Claude can retrieve aggregated data (sums, counts, comparisons) via MCP
FR28: Claude can access individual transaction details via MCP
FR29: The MCP server provides data in a format optimized for LLM consumption
FR30: Lambert can authenticate to access the application
FR31: The system enforces HTTPS for all traffic
FR32: The MCP endpoint requires authentication for access
FR33: Unauthenticated users cannot access any financial data
FR34: The system shows an import confirmation dialog with summary (bank source, transaction count, date range) before committing transactions
FR35: Lambert can delete an import and all its linked transactions (cascading removal)

### NonFunctional Requirements

NFR1: Category overview and drill-down navigation must feel instant (perceived latency under 300ms)
NFR2: CSV import processing must not block the UI -- the user can see progress or continue interacting
NFR3: Month navigation must render without visible loading delay
NFR4: MCP queries must respond within 5 seconds for Claude to provide a fluid conversational experience
NFR5: All traffic between client and server must be encrypted via HTTPS
NFR6: Authentication must be required before any data is accessible (no anonymous read access)
NFR7: MCP endpoint must require separate authentication -- only authorized Claude sessions can query data
NFR8: Session/token management must prevent unauthorized access after authentication
NFR9: The application must expose a minimal attack surface -- no unnecessary ports, endpoints, or services
NFR10: The database must use PostgreSQL to support relational queries and vector extensions (pgvector) for potential semantic search on transaction data
NFR11: French accented characters (e, e, e, a, c, etc.) must be preserved correctly through the entire pipeline (import -> storage -> display -> MCP)
NFR12: Deduplication must be deterministic -- the same import file processed twice must always produce identical results
NFR13: AI correction data must persist across application restarts and updates
NFR14: The MCP server must conform to the Model Context Protocol specification for tool discovery and invocation
NFR15: MCP responses must be structured for LLM consumption (clear field names, consistent formats, no HTML or UI artifacts)

### Additional Requirements

**From Architecture:**
- Starter template: Official Laravel React Starter Kit (`laravel new budget-app --using=laravel/react-starter-kit`) -- this is the first implementation step
- Laravel Sail development environment (Docker: PHP 8.5, PostgreSQL, Bun)
- Bun as JavaScript runtime (replaces npm/node for JS dependency management)
- Inertia.js architecture: No REST API for frontend; controllers return Inertia responses with React page props
- Amount storage as integer cents throughout the pipeline (never floats, never pre-formatted)
- Predefined seeded categories (14 categories with specific colors from UX spec, stored in database)
- Self-referential category hierarchy (single `categories` table with nullable `parent_id`)
- SHA-256 deduplication hash (date + amount_cents + description + account_id) + database unique constraint
- Sanctum API tokens for MCP authentication (Bearer token in header)
- PostgreSQL database queue driver (no Redis dependency)
- Hybrid categorization: instant merchant-name matching via `merchant_rules` table + queue job for unknown transactions via Prism PHP / Gemini
- CSV parser strategy pattern (`CsvParserInterface` with `canParse()` and `parse()` methods, factory auto-detection)
- Prism PHP (`prism-php/prism`) for multi-provider LLM integration (Gemini) for AI categorization
- laravel/mcp package for MCP server implementation (5-8 granular tools)
- pgvector extension for PostgreSQL (potential semantic search)
- Laravel Nightwatch for production monitoring
- Laravel Cloud for deployment (auto-deploy on push to main)
- `BudgetQueryService` as shared data access layer for both web controllers and MCP tools
- Inertia props in camelCase, MCP responses in snake_case
- Laravel events for domain communication (`ImportCompleted`, `TransactionCategorized`, `CategoryCorrected`)
- `UpdateMerchantRule` listener on `CategoryCorrected` event to persist AI learning

**From UX Design:**
- Dark theme as default (background: #0A0A0B, surface: #1C1C1E, surface-elevated: #2C2C2E)
- Inter typeface (variable font, sole typeface)
- 14 category accent colors defined (vibrant on dark background, Tailwind 400-level palette)
- Mobile-first responsive design with single breakpoint at 640px (Tailwind `sm:`)
- Centered single-column layout on desktop (max-width ~480px)
- Bottom sheet (Sheet) for category correction on mobile, dropdown (DropdownMenu) on desktop
- 44px minimum touch target height for all tappable elements
- Category cards sorted by amount descending (highest spend first)
- French locale amount formatting: `1 234,56 EUR` with tabular-nums for alignment
- Motivated-style color takeover on drill-down (category accent color floods the header)
- Empty state as an invitation (clean import prompt, not a void)
- Import summary confirmation before committing (Dialog component)
- "Got it, I'll remember" toast for AI learning acknowledgment (auto-dismiss 2s)
- No confirmation dialog for corrections (instant, tap-to-fix)
- Month strip as horizontally scrollable pills with snap-to-month behavior
- Summary widget (Income + Expenses side-by-side cards) above category list
- Account picker as dropdown in header ("Personal" default)
- 4 shadcn/ui components adopted: DropdownMenu, Sheet, Dialog, Toast

### FR Coverage Map

FR1: Epic 2 - CSV Import & Financial Overview (Banque Populaire CSV import)
FR2: Epic 2 - CSV Import & Financial Overview (Revolut CSV import)
FR3: Epic 2 - CSV Import & Financial Overview (European CSV format parsing)
FR4: Epic 2 - CSV Import & Financial Overview (French character encoding)
FR5: Epic 2 - CSV Import & Financial Overview (Deduplication on re-import)
FR6: Epic 2 - CSV Import & Financial Overview (Drag-and-drop / file picker)
FR7: Epic 2 - CSV Import & Financial Overview (Unrecognized format error handling)
FR8: Epic 2 - CSV Import & Financial Overview (Auto-categorization on import)
FR9: Epic 4 - Category Correction & AI Learning (Learn from user corrections)
FR10: Epic 2 - CSV Import & Financial Overview (Fallback "Other" category)
FR11: Epic 4 - Category Correction & AI Learning (Near-perfect accuracy for known merchants)
FR12: Epic 2 - CSV Import & Financial Overview (Category overview with totals)
FR13: Epic 2 - CSV Import & Financial Overview (Income and expenses in unified list)
FR14: Epic 3 - Spending Exploration (Drill-down to subcategories)
FR15: Epic 3 - Spending Exploration (Drill-down to individual transactions)
FR16: Epic 3 - Spending Exploration (Month navigation)
FR17: Epic 2 - CSV Import & Financial Overview (Default current month view)
FR18: Epic 3 - Spending Exploration (Multiple bank accounts)
FR19: Epic 3 - Spending Exploration (Personal/professional separation)
FR20: Epic 3 - Spending Exploration (Exclude professional from personal view)
FR21: Epic 4 - Category Correction & AI Learning (Manual re-categorization)
FR22: Epic 4 - Category Correction & AI Learning (Single tap/click correction)
FR23: Epic 4 - Category Correction & AI Learning (Persist corrections for future)
FR24: Epic 5 - Conversational Finance / MCP Server (Query by category)
FR25: Epic 5 - Conversational Finance / MCP Server (Query by date range)
FR26: Epic 5 - Conversational Finance / MCP Server (Query by account)
FR27: Epic 5 - Conversational Finance / MCP Server (Aggregated data)
FR28: Epic 5 - Conversational Finance / MCP Server (Transaction details)
FR29: Epic 5 - Conversational Finance / MCP Server (LLM-optimized format)
FR30: Epic 1 - Project Foundation & Secure Access (Authentication)
FR31: Epic 1 - Project Foundation & Secure Access (HTTPS enforcement)
FR32: Epic 5 - Conversational Finance / MCP Server (MCP endpoint authentication)
FR33: Epic 1 - Project Foundation & Secure Access (No anonymous data access)
FR34: Epic 2 - CSV Import & Financial Overview (Import confirmation dialog)
FR35: Epic 4 - Category Correction & AI Learning (Import deletion with cascade)

## Epic List

### Epic 1: Project Foundation & Secure Access
Lambert can log in to a secured, deployed application with the database ready for financial data.
**FRs covered:** FR30, FR31, FR33
**Includes:** Starter kit initialization (`laravel new budget-app --using=laravel/react-starter-kit`), Sail setup (PHP 8.5, PostgreSQL, Bun), database schema + migrations (accounts, categories, transactions, imports, merchant_rules, jobs tables), category seeder (14 categories with colors from UX spec), Laravel Cloud deployment, Nightwatch monitoring setup, HTTPS enforcement, dark theme + Inter typeface foundation.

### Epic 2: CSV Import & Financial Overview
Lambert can import CSV files from his banks and see a categorized spending overview for the current month.
**FRs covered:** FR1, FR2, FR3, FR4, FR5, FR6, FR7, FR8, FR10, FR12, FR13, FR17, FR34
**Includes:** CSV parser strategy pattern (BanquePopulaireParser, RevolutParser, CsvParserFactory), European format handling + French encoding normalization, deduplication engine (SHA-256 hash + unique constraint), hybrid AI categorization (MerchantMatchingService instant + CategorizeTransactions queue job via Prism/Gemini), category overview page (Dashboard with CategoryCards, SummaryWidget), ImportDropZone (drag-and-drop desktop / file picker mobile), import confirmation dialog, EmptyState for first-time experience, BudgetQueryService.

### Epic 3: Spending Exploration
Lambert can drill down into categories and subcategories, navigate between months, and switch between personal and professional account views.
**FRs covered:** FR14, FR15, FR16, FR18, FR19, FR20
**Includes:** Category drill-down page (Categories/Show with ColorHeader, SubcategoryRow, TransactionRow), Motivated-style color takeover transition, MonthStrip (horizontally scrollable month navigation with snap-to-month), account picker (DropdownMenu in header), account-scoped filtering via BudgetQueryService, Inertia partial reloads for month/account switching.

### Epic 4: Category Correction & AI Learning
Lambert can correct AI categorization mistakes with a single tap, and the system learns from corrections for future imports. Import deletion provides a safety net for wrong imports.
**FRs covered:** FR9, FR11, FR21, FR22, FR23, FR35
**Includes:** CategoryPicker component (Sheet on mobile, DropdownMenu on desktop via useViewport hook), single-tap correction via router.patch, CategoryCorrected event + UpdateMerchantRule listener, merchant_rules table learning pipeline, "Got it, I'll remember" Toast (auto-dismiss 2s), import deletion with cascading transaction removal (Dialog confirmation).

### Epic 5: Conversational Finance (MCP Server)
Claude can query Lambert's budget data conversationally, giving financial insights without opening the app.
**FRs covered:** FR24, FR25, FR26, FR27, FR28, FR29, FR32
**Includes:** 6 MCP tools via laravel/mcp (GetCategoryTotals, QueryTransactions, GetMonthlyComparison, GetTransactionDetails, GetAccountSummary, GetCategoryBreakdown), Sanctum API token authentication for MCP endpoint, structured snake_case JSON responses with metadata, LLM-optimized field names and format.

## Epic 1: Project Foundation & Secure Access

Lambert can log in to a secured, deployed application with the database ready for financial data.

### Story 1.1: Project Initialization with Authentication

As Lambert,
I want to set up the budget-app project with the Laravel React starter kit and working authentication,
So that I have a secured application foundation I can log into.

**Acceptance Criteria:**

**Given** a fresh development environment
**When** I run the project initialization commands (`laravel new budget-app --using=laravel/react-starter-kit`)
**Then** a Laravel 12 application is created with React 19, TypeScript, Tailwind 4, shadcn/ui, and Inertia 2

**Given** the project is initialized
**When** I configure and start Laravel Sail
**Then** Docker containers run with PHP 8.5, PostgreSQL, and Bun (no Redis service)

**Given** Sail is running
**When** I navigate to the application URL without being authenticated
**Then** I am redirected to the login page (FR33)

**Given** I am on the login page
**When** I enter valid credentials
**Then** I am authenticated and can access the application (FR30)

**Given** additional packages are needed
**When** I install project dependencies
**Then** Prism PHP, laravel/mcp, pgvector, tpetry/laravel-postgresql-enhanced, and laravel/nightwatch are added to composer.json

### Story 1.2: Domain Data Model & Category Seed

As Lambert,
I want the database to contain all financial data models and 14 predefined spending categories with their UX-specified colors,
So that the application is ready to store and organize my financial data.

**Acceptance Criteria:**

**Given** the project is initialized
**When** I run database migrations
**Then** tables are created for: accounts (name, bank, type), categories (name, slug, color, icon, parent_id), transactions (amount_cents INTEGER, date, description, dedup_hash, category_id, account_id, import_id), imports (file_name, bank, status, transaction_count), merchant_rules (merchant_pattern, category_id), and jobs (database queue driver)

**Given** migrations have run
**When** I run the category seeder
**Then** 14 top-level categories are created with UX-specified colors: Food (#A3E635), Restaurants (#FBBF24), Transport (#60A5FA), Housing (#2DD4BF), Entertainment (#C084FC), Shopping (#F472B6), Health (#34D399), Subscriptions (#FB7185), Education (#38BDF8), Travel (#818CF8), Insurance (#94A3B8), Income (#10B981), Professional (#EAB308), Other (#A1A1AA)

**Given** Eloquent models are created
**When** I access Category relationships
**Then** Category supports self-referential hierarchy (parent/children), Transaction belongs to Category, Account, and Import, and all foreign keys are properly defined

**Given** any amount is stored
**When** a transaction record is created
**Then** the amount is stored as integer cents (amount_cents column, INTEGER type) -- never floats

### Story 1.3: Visual Foundation & App Layout

As Lambert,
I want the application to use the dark theme with Inter typeface and design tokens from the UX specification,
So that every page matches the visual design from the start.

**Acceptance Criteria:**

**Given** I am logged in
**When** I view any page
**Then** the background is near-black (#0A0A0B), cards use dark surface (#1C1C1E), and text is white (#FFFFFF) primary / zinc-400 (#A1A1AA) secondary

**Given** the app is loaded
**When** I inspect the typography
**Then** Inter variable font is the sole typeface with `font-display: swap`

**Given** I am on mobile (< 640px)
**When** I view the layout
**Then** content is full-width with 16px margins

**Given** I am on desktop (>= 640px)
**When** I view the layout
**Then** content is centered with max-width ~480px and 32px margins

**Given** the Tailwind configuration
**When** I reference design tokens in components
**Then** category colors, surface layers, spacing scale (4px base), and border-radius tokens (12px cards, 8px buttons) are available as Tailwind utilities

### Story 1.4: Production Deployment with HTTPS & Monitoring

As Lambert,
I want the application deployed to Laravel Cloud with HTTPS enforcement and monitoring,
So that I can access it securely from anywhere and detect issues early.

**Acceptance Criteria:**

**Given** the application is configured for Laravel Cloud
**When** code is pushed to the main branch
**Then** the application auto-deploys

**Given** the app is deployed
**When** any request arrives via HTTP
**Then** it is redirected to HTTPS (FR31)

**Given** Laravel Nightwatch is configured
**When** errors, slow queries, or job failures occur in production
**Then** they are tracked and visible in the Nightwatch dashboard

**Given** the production environment
**When** environment variables are needed
**Then** secrets are managed via Laravel Cloud dashboard, not committed to code

## Epic 2: CSV Import & Financial Overview

Lambert can import CSV files from his banks and see a categorized spending overview for the current month.

### Story 2.1: CSV Parser Framework & Banque Populaire Support

As Lambert,
I want to parse Banque Populaire CSV exports with correct handling of European formats and French characters,
So that my bank transactions can be ingested into the application.

**Acceptance Criteria:**

**Given** a Banque Populaire CSV file with semicolon delimiters and comma decimal separators
**When** the file is processed by BanquePopulaireParser
**Then** each row is parsed into a TransactionData DTO with date, description, amountCents (integer), and rawLine (FR1, FR3)

**Given** a CSV with DD/MM/YYYY date format
**When** it is parsed
**Then** dates are correctly converted to ISO 8601 format (FR3)

**Given** a CSV encoded in ISO-8859-1 with French accented characters (e.g. "Boulangerie Lefevre")
**When** it is parsed
**Then** merchant names are correctly normalized to UTF-8 without corruption (FR4, NFR11)

**Given** the CsvParserFactory receives a file
**When** it calls `canParse()` on registered parsers
**Then** BanquePopulaireParser correctly identifies its format and the factory returns the matching parser

**Given** a CSV that matches no registered parser
**When** CsvParserFactory attempts detection
**Then** an UnrecognizedCsvFormatException is thrown with a clear message listing expected formats (FR7)

### Story 2.2: Revolut CSV Parser

As Lambert,
I want to parse Revolut CSV exports for both personal and professional accounts,
So that all my bank data can be imported into the application.

**Acceptance Criteria:**

**Given** a Revolut CSV export (comma-delimited, YYYY-MM-DD dates, dot decimal separators)
**When** the file is processed by RevolutParser
**Then** each row is parsed into a TransactionData DTO with correct date, description, and amountCents (FR2, FR3)

**Given** a Revolut CSV with both completed and pending transactions
**When** it is parsed
**Then** only completed transactions are included

**Given** the CsvParserFactory receives a Revolut file
**When** it calls `canParse()` on registered parsers
**Then** RevolutParser correctly identifies its format

**Given** both BanquePopulaireParser and RevolutParser are registered
**When** a CSV is submitted
**Then** the factory correctly distinguishes between the two formats

### Story 2.3: Import Pipeline with Deduplication

As Lambert,
I want imported transactions to be deduplicated automatically so that re-importing the same file produces no duplicates,
So that I can import freely without worrying about duplicate data.

**Acceptance Criteria:**

**Given** a parsed CSV produces TransactionData DTOs
**When** CsvImportService processes the import
**Then** an Import record is created tracking file_name, bank source, transaction_count, and date range

**Given** a transaction with a specific date + amount_cents + description + account_id
**When** it is persisted
**Then** a SHA-256 dedup_hash is generated from those fields and stored on the transaction (FR5)

**Given** the same CSV file is imported twice
**When** DeduplicationService checks the hash
**Then** previously imported transactions are silently skipped with no errors or warnings (FR5, NFR12)

**Given** a large CSV is being processed
**When** the import runs
**Then** it completes without blocking -- transactions are persisted in a batch operation (NFR2)

**Given** the import completes successfully
**When** the ImportCompleted event fires
**Then** the Import record is updated with final transaction count and status

### Story 2.4: AI Auto-Categorization Engine

As Lambert,
I want imported transactions to be automatically categorized by AI so that I don't have to manually sort hundreds of transactions,
So that I see a meaningful spending breakdown immediately after import.

**Acceptance Criteria:**

**Given** a new transaction is imported with a merchant description
**When** MerchantMatchingService checks the merchant_rules table
**Then** if an exact match exists, the transaction is instantly assigned to the matching category (FR8)

**Given** a new transaction has no matching merchant rule
**When** CategorizationService processes it
**Then** a CategorizeTransactions job is dispatched to the queue for Gemini categorization via Prism PHP (FR8)

**Given** the Gemini categorization returns a category suggestion
**When** the job completes
**Then** the transaction is updated with the suggested category_id and a TransactionCategorized event fires

**Given** the AI has low confidence or no meaningful suggestion
**When** categorization is attempted
**Then** the transaction is assigned to the "Other/Uncategorized" category (FR10)

**Given** the Prism PHP / Gemini API is configured
**When** a batch of uncategorized transactions is processed
**Then** the AI receives transaction descriptions and returns category mappings from the 14 predefined categories

### Story 2.5: Financial Overview Dashboard

As Lambert,
I want to see a category overview with total amounts and an income/expenses summary for the current month,
So that I know where my money is going at a glance.

**Acceptance Criteria:**

**Given** I am logged in and transactions exist for the current month
**When** I open the application
**Then** I see the Dashboard page showing category cards with total amounts, sorted by amount descending (FR12, FR17)

**Given** the Dashboard is displayed
**When** I look at the category cards
**Then** each card shows: category color icon (48px rounded square), category name, transaction count, and amount in the category's accent color (FR12)

**Given** the Dashboard is displayed
**When** I look at the summary widget
**Then** I see two side-by-side cards: Income (emerald) and Expenses (coral) with their total amounts for the current month (FR13)

**Given** amounts are displayed
**When** I read any financial figure
**Then** it is formatted in French locale with tabular-nums: `1 234,56 EUR` (integer cents converted by formatAmount.ts)

**Given** BudgetQueryService is implemented
**When** DashboardController requests monthly category totals
**Then** the service returns aggregated data via Eloquent that the controller passes as camelCase Inertia props (NFR1)

### Story 2.6: Import User Interface & Confirmation

As Lambert,
I want to import CSV files via drag-and-drop on desktop or file picker on mobile with a confirmation step,
So that I can easily add new financial data and verify before committing.

**Acceptance Criteria:**

**Given** I am on the Dashboard with no transactions
**When** the page renders
**Then** I see the EmptyState component: a clean invitation to import with a subtle icon and "Import your first CSV" prompt

**Given** I am on desktop and drag a CSV file over the window
**When** the file enters the drop zone
**Then** a dashed border overlay appears with "Drop CSV to import" text (FR6)

**Given** I am on mobile
**When** I tap the import button
**Then** the native file picker opens for CSV selection (FR6)

**Given** I drop or select a valid CSV file
**When** the file is parsed
**Then** an import confirmation dialog appears showing: bank source, transaction count, and date range (FR34)

**Given** the confirmation dialog is showing
**When** I tap "Import"
**Then** transactions are committed, the dialog dismisses, and category amounts update on the overview

**Given** the confirmation dialog is showing
**When** I tap "Cancel"
**Then** the dialog dismisses and no transactions are imported

**Given** I drop an unrecognized file format
**When** parsing fails
**Then** a clear error message appears: "Unrecognized CSV format. Expected: Banque Populaire or Revolut export." (FR7)

## Epic 3: Spending Exploration

Lambert can drill down into categories and subcategories, navigate between months, and switch between personal and professional account views.

### Story 3.1: Category Drill-Down with Color Takeover

As Lambert,
I want to tap a category card and see its subcategories and individual transactions in a color-dominant detail view,
So that I can understand exactly what makes up each spending category.

**Acceptance Criteria:**

**Given** I am on the Dashboard viewing category cards
**When** I tap a category card
**Then** I navigate to the Categories/Show page with a full Inertia page visit (FR14)

**Given** the drill-down page loads
**When** I see the header
**Then** the category's accent color floods the header background (ColorHeader) with: large centered icon (64px), category name, total amount (36px bold), and transaction count (FR14)

**Given** the category has subcategories
**When** the drill-down page renders
**Then** I see SubcategoryRows grouped in a surface-colored card, each showing subcategory name, amount, and a chevron for further drill-down (FR14)

**Given** I tap a subcategory row
**When** the subcategory expands or navigates
**Then** I see individual TransactionRows for that subcategory: date (short format, e.g. "Feb 19"), merchant name, and amount (FR15)

**Given** the drill-down is displayed
**When** I tap the close/back button
**Then** I return to the Dashboard with my previous scroll position preserved

**Given** the drill-down loads
**When** I measure perceived latency
**Then** navigation feels instant, under 300ms (NFR1)

### Story 3.2: Month Navigation

As Lambert,
I want to scroll through months to view spending for different time periods,
So that I can compare my spending across months and track changes over time.

**Acceptance Criteria:**

**Given** I am on the Dashboard
**When** I look at the top of the page below the header
**Then** I see a MonthStrip: horizontally scrollable month pills with the current month highlighted (elevated surface background, white text) (FR16)

**Given** the MonthStrip is visible
**When** I scroll horizontally to a different month
**Then** the selected month becomes active and all Dashboard data updates instantly: category card amounts, SummaryWidget totals, and transaction counts (FR16, NFR3)

**Given** I navigate to a different month
**When** the data refreshes
**Then** it uses an Inertia partial reload (`router.get` with `preserveState: true`) so the update feels instant with no full page load

**Given** I select a month with no imported transactions
**When** the Dashboard renders
**Then** the category list area shows "No transactions for [Month Year]" in tertiary text, and the SummaryWidget shows dashes for both Income and Expenses

**Given** I open the app fresh
**When** the Dashboard loads
**Then** the MonthStrip auto-scrolls to center on the current month (FR17)

### Story 3.3: Account Management & Switching

As Lambert,
I want to switch between personal and professional account views so that my spending contexts stay separate,
So that professional expenses don't mix with my personal financial picture.

**Acceptance Criteria:**

**Given** I am on the Dashboard
**When** I look at the header
**Then** I see an account picker dropdown showing the currently selected account context ("Personal" by default) (FR19, FR20)

**Given** I tap the account picker
**When** the dropdown opens (shadcn/ui DropdownMenu)
**Then** I see available account contexts (e.g. "Personal", "Professional") to choose from (FR18)

**Given** I select "Professional" from the dropdown
**When** the view updates
**Then** all Dashboard data (category amounts, SummaryWidget, category cards) reflects only transactions from professional accounts via Inertia partial reload (FR19)

**Given** I am in "Personal" view (default)
**When** the Dashboard loads data
**Then** professional account transactions are excluded from all totals and category breakdowns (FR20)

**Given** I switch accounts and then navigate months
**When** month data loads
**Then** the account filter persists -- only the selected account context's transactions are shown

**Given** I close and reopen the app
**When** the Dashboard loads
**Then** the account selection defaults to "Personal" (FR20)

## Epic 4: Category Correction & AI Learning

Lambert can correct AI categorization mistakes with a single tap, and the system learns from corrections for future imports. Import deletion provides a safety net.

### Story 4.1: Single-Tap Category Correction

As Lambert,
I want to correct a miscategorized transaction with a single tap to select the right category,
So that my spending data is accurate without tedious editing.

**Acceptance Criteria:**

**Given** I am viewing transactions in the category drill-down
**When** I tap a miscategorized transaction row
**Then** a category picker appears showing all 14 categories with their color icons and names (FR21)

**Given** I am on mobile (< 640px)
**When** the category picker opens
**Then** it renders as a bottom Sheet (shadcn/ui) sliding up from the bottom of the screen (FR22)

**Given** I am on desktop (>= 640px)
**When** the category picker opens
**Then** it renders as a DropdownMenu (shadcn/ui) anchored to the transaction row (FR22)

**Given** the category picker is open
**When** I tap the correct category
**Then** the transaction's category is updated via `router.patch('/transactions/{id}', { category_id })`, the picker dismisses immediately, and amounts update in real time on both the drill-down and overview (FR21, FR22)

**Given** the current (incorrect) category is shown in the picker
**When** I look at the list
**Then** the current category is visually de-emphasized so the correct options stand out

**Given** I made a correction but it was wrong
**When** I tap the same transaction again
**Then** I can re-correct with the same two-tap flow -- no undo mechanism needed

### Story 4.2: AI Learning from Corrections

As Lambert,
I want the system to remember my corrections and apply them automatically to future imports,
So that the AI gets smarter over time and I do less manual work.

**Acceptance Criteria:**

**Given** I correct a transaction's category
**When** the correction is saved
**Then** a CategoryCorrected event fires, and the UpdateMerchantRule listener persists a merchant_pattern → category_id mapping in the merchant_rules table (FR9, FR23)

**Given** a correction is saved
**When** the Toast component renders
**Then** a subtle message appears at the bottom: "Got it, I'll remember" -- auto-dismisses after 2 seconds

**Given** a merchant rule exists for "CARREFOUR CITY"
**When** a future import contains a transaction with that merchant description
**Then** MerchantMatchingService instantly assigns the correct category with no AI/Gemini call needed (FR11)

**Given** multiple corrections have been made over time
**When** correction data is queried
**Then** all merchant rules persist across application restarts and updates (NFR13)

**Given** the merchant_rules table has learned patterns
**When** a new import is processed
**Then** known merchants are categorized instantly, and only unknown merchants go through the Gemini queue (FR9, FR11)

### Story 4.3: Import Deletion

As Lambert,
I want to delete an import and all its linked transactions when I imported the wrong file or wrong account context,
So that I can recover from mistakes without manual cleanup.

**Acceptance Criteria:**

**Given** I want to manage my imports
**When** I access the import history (accessible from the Dashboard)
**Then** I see a list of recent imports with bank source, transaction count, and date range

**Given** I select an import to delete
**When** the deletion dialog appears (shadcn/ui Dialog)
**Then** it shows: "Delete this import? [X] transactions from [Bank] ([Date range]) will be removed." with "Cancel" (secondary) and "Delete" (destructive, error-colored) actions (FR35)

**Given** I tap "Delete" in the confirmation dialog
**When** the deletion executes
**Then** the Import record and all linked transactions are removed (cascading deletion), category amounts update, and a toast confirms "Import deleted" (FR35)

**Given** I tap "Cancel" in the confirmation dialog
**When** the dialog closes
**Then** no data is deleted

**Given** an import is deleted that contained transactions with merchant rules
**When** the deletion completes
**Then** the learned merchant rules are preserved -- they were learned from corrections, not from the import itself

## Epic 5: Conversational Finance (MCP Server)

Claude can query Lambert's budget data conversationally, giving financial insights without opening the app.

### Story 5.1: MCP Server Setup with Sanctum Authentication

As Lambert,
I want the MCP server configured with token-based authentication so that only my authorized Claude sessions can access budget data,
So that my financial data is protected while being accessible conversationally.

**Acceptance Criteria:**

**Given** the laravel/mcp package is installed
**When** the MCP server is configured
**Then** it conforms to the Model Context Protocol specification for tool discovery and invocation (NFR14)

**Given** I need to generate an API token for Claude
**When** I create a Sanctum personal access token
**Then** the token is stored in the database and can be used as a Bearer token in MCP requests (FR32)

**Given** an MCP request arrives without a valid Bearer token
**When** Sanctum middleware processes the request
**Then** the request is rejected with an authentication error (FR32, NFR7)

**Given** an MCP request arrives with a valid Bearer token
**When** Sanctum middleware processes the request
**Then** the request is authenticated and routed to the appropriate MCP tool

**Given** MCP responses are returned
**When** any tool responds
**Then** the response uses structured JSON with snake_case keys, integer cents for amounts, ISO 8601 for dates, and descriptive field names (e.g. `merchant_name` not `desc`) with metadata wrapper: `{ data: [...], meta: { total_count, period, account } }` (FR29, NFR15)

### Story 5.2: Transaction & Category Query Tools

As Lambert,
I want Claude to query my transactions filtered by category, date range, and account, and access individual transaction details,
So that I can ask Claude specific questions about my spending without opening the app.

**Acceptance Criteria:**

**Given** Claude calls the `get_category_totals` tool with a month parameter
**When** BudgetQueryService aggregates the data
**Then** it returns all categories with their total amount_cents, transaction counts, and category names for that month (FR24, FR27)

**Given** Claude calls the `query_transactions` tool with a category filter
**When** the tool processes the request
**Then** it returns transactions matching that category with date, merchant_name, amount_cents, and category_name (FR24)

**Given** Claude calls `query_transactions` with a date range filter
**When** the tool processes the request
**Then** it returns only transactions within the specified start and end dates (FR25)

**Given** Claude calls `query_transactions` with an account filter
**When** the tool processes the request
**Then** it returns only transactions from the specified account context (FR26)

**Given** Claude calls the `get_transaction_details` tool with a transaction ID
**When** the tool processes the request
**Then** it returns the full transaction record: date, merchant_name, amount_cents, category_name, subcategory_name, account_name, and import source (FR28)

**Given** Claude calls the `get_category_breakdown` tool for a specific category
**When** BudgetQueryService queries subcategories
**Then** it returns subcategory names with their totals and transaction counts (FR27, FR28)

**Given** any query tool responds
**When** measuring response time
**Then** the response completes within 5 seconds (NFR4)

### Story 5.3: Aggregation & Comparison Tools

As Lambert,
I want Claude to compare my spending across months and summarize accounts,
So that I can get trend insights and financial overviews through conversation.

**Acceptance Criteria:**

**Given** Claude calls the `get_monthly_comparison` tool with two or more months
**When** BudgetQueryService aggregates across periods
**Then** it returns category-by-category totals for each month with calculated differences (absolute and percentage) (FR27)

**Given** Claude asks "How did my restaurant spending change?"
**When** `get_monthly_comparison` is called with the Restaurants category
**Then** the response includes month-over-month amounts and the percentage change (e.g. `{ "jan": 52000, "feb": 38000, "change_pct": -26.9 }`) (FR27)

**Given** Claude calls the `get_account_summary` tool
**When** the tool processes the request
**Then** it returns per-account totals for income and expenses, with account names and types (personal/professional) (FR26, FR27)

**Given** Claude combines multiple tool calls in a conversation
**When** tools are called sequentially
**Then** each tool returns consistent data from the same underlying BudgetQueryService (FR29)

**Given** any aggregation tool returns large result sets
**When** the response is formatted
**Then** amounts remain as integer cents (never pre-formatted), dates as ISO 8601, and metadata includes total_count and period context for Claude to format in natural language (FR29, NFR15)
