---
title: 'Inertia Deferred Props for Instant Page Transitions'
slug: 'inertia-deferred-props'
created: '2026-02-22'
status: 'review-complete'
stepsCompleted: [1, 2, 3, 4, 5, 6]
tech_stack: ['Laravel 12', 'Inertia.js v2', 'React 19', 'TypeScript', 'Tailwind CSS 4', 'shadcn/ui', 'motion/react']
files_to_modify:
  - 'app/Http/Middleware/HandleInertiaRequests.php'
  - 'app/Http/Controllers/DashboardController.php'
  - 'app/Http/Controllers/CategoryController.php'
  - 'app/Http/Controllers/RecurringController.php'
  - 'app/Http/Controllers/SearchController.php'
  - 'resources/js/types/budget.ts'
  - 'resources/js/pages/dashboard.tsx'
  - 'resources/js/pages/categories/show.tsx'
  - 'resources/js/pages/recurring.tsx'
  - 'resources/js/pages/search.tsx'
  - 'tests/Feature/CategoryControllerTest.php'
  - 'tests/Feature/RecurringControllerTest.php'
code_patterns:
  - 'Controllers use Inertia::render() with inline prop arrays'
  - 'BudgetQueryService provides aggregation queries via getMonthlyData() and getCategoryDetail()'
  - 'HandleInertiaRequests shares global props in share() method'
  - 'Frontend pages destructure props directly from component parameters'
  - 'shadcn/ui Skeleton component uses animate-pulse with bg-primary/10'
  - 'motion/react used for entry animations (fade-in, stagger) on all pages'
  - 'Partial reloads via router.get() with only: [...] arrays on dashboard'
test_patterns:
  - 'PHPUnit Feature tests with Inertia AssertableInertia assertions'
  - 'Tests use assertInertia(fn (Assert $page) => ...) to verify props'
  - 'RefreshDatabase trait + CategorySeeder for test data'
  - 'Frontend: Vitest + @testing-library/react (component-level tests exist)'
---

# Tech-Spec: Inertia Deferred Props for Instant Page Transitions

**Created:** 2026-02-22

## Overview

### Problem Statement

All pages block rendering on every database query before any UI appears. On mobile especially, this creates a sluggish feel during page transitions — the app waits for all data (including heavy aggregations, full transaction lists, and unbounded table scans) before showing anything to the user.

### Solution

Use Inertia v2 `Inertia::defer()` on heavy/below-the-fold props so pages render instantly with pulse skeleton placeholders, then hydrate data asynchronously via automatic follow-up requests. Move shared reference data (`allCategories`) to a lazy shared prop in middleware to eliminate duplicate queries.

### Scope

**In Scope:**
- **Dashboard** — defer `trendData` (group: `trends`) and `imports` (group: `imports`). Keep `categoryTotals`, `availableMonths`, and core data immediate.
- **Category Detail** — defer `transactions` list (render header + subcategories from aggregates first)
- **Recurring** — defer `recurringMerchants` (heaviest prop in the app)
- **Global** — move `allCategories` to lazy shared prop in HandleInertiaRequests middleware
- **Frontend** — add `<Deferred>` wrappers with shadcn pulse skeleton fallbacks on affected pages

**Out of Scope:**
- Caching of RecurringDetectionService
- Pagination of transaction lists
- SSR considerations
- Search page deferral (results already lazy via partial reloads)

## Context for Development

### Codebase Patterns

- Controllers use `Inertia::render()` with inline prop arrays
- `BudgetQueryService` provides aggregation queries via `getMonthlyData()` and `getCategoryDetail()`
- `HandleInertiaRequests` middleware shares global props in `share()` method — lazy closures supported
- Frontend pages destructure props directly from component parameters
- shadcn/ui `Skeleton` component (`resources/js/components/ui/skeleton.tsx`) uses `animate-pulse` with `bg-primary/10`
- `motion/react` used for entry animations (fade-in, stagger) on all pages
- Partial reloads via `router.get()` with `only: [...]` arrays on dashboard (month/account changes)
- `Deferred` component from `@inertiajs/react` — typed in `types/Deferred.d.ts`
- `Inertia::defer()` — `ResponseFactory.php:194` — returns `DeferProp` implementing `Deferrable, IgnoreFirstLoad, Mergeable, Onceable`

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `app/Http/Middleware/HandleInertiaRequests.php` | Global shared props — will add `allCategories` |
| `app/Http/Controllers/DashboardController.php` | Dashboard — `getMonthlyData()` x2, imports, availableMonths |
| `app/Http/Controllers/CategoryController.php` | Category detail — `getCategoryDetail()` + allCategories |
| `app/Http/Controllers/RecurringController.php` | Recurring — `detectRecurring()` |
| `app/Http/Controllers/SearchController.php` | Search — `queryTransactions()` + allCategories |
| `resources/js/types/budget.ts` | TypeScript types — DashboardProps, CategoryShowProps, RecurringProps, SearchProps |
| `resources/js/pages/dashboard.tsx` | Dashboard page component |
| `resources/js/pages/categories/show.tsx` | Category detail page component |
| `resources/js/pages/recurring.tsx` | Recurring page component |
| `resources/js/pages/search.tsx` | Search page component |
| `resources/js/components/ui/skeleton.tsx` | shadcn Skeleton primitive |
| `resources/js/components/CategoryCard.tsx` | Category card — icon + name/count + amount/trend |
| `resources/js/components/RecurringMerchantCard.tsx` | Merchant card — dot + name/category + amount/frequency |
| `resources/js/components/ImportHistory.tsx` | Import history panel — collapsible, below fold |
| `tests/Feature/CategoryControllerTest.php` | Tests asserting allCategories and transactions props |
| `tests/Feature/RecurringControllerTest.php` | Tests asserting recurringMerchants prop |

### Technical Decisions

- **`Inertia::defer()` is controller-level only** — cannot be used in middleware `share()`
- **Lazy shared prop for `allCategories`** — `fn () => Category::whereNull(...)` in `share()`. Lightweight query (~13 rows), runs on every Inertia response but eliminates duplicate controller queries. Not deferred but always available instantly.
- **Pulse skeletons** — consistent with existing shadcn/ui design system
- **Dashboard grouping** — two deferred groups: `trends` (previous-month trend data) and `imports` (below-fold history). These fire as two parallel follow-up requests after initial render.
- **`availableMonths` stays immediate** — feeds the above-fold MonthStrip. Lightweight `DISTINCT` query.
- **Dashboard trend refactor** — `categoryTotals` rendered immediately with `trendPercentage: null`. New deferred prop `trendData` provides `{ hasPreviousMonthData, categoryTrends: Record<slug, number|null> }`. Frontend merges trends into cards when data arrives (no `<Deferred>` wrapper needed — cards render immediately, trend arrows appear progressively).
- **Skeleton shapes** — match real content layout: Dashboard: 5 category card skeletons. Recurring: 4 merchant card skeletons. Category Detail: 6 transaction row skeletons.
- **TypeScript** — deferred props typed as optional (`?`) since they're `undefined` on initial render. Inside `<Deferred>` children, they're guaranteed defined.
- **Partial reload compatibility** — dashboard `only: [...]` arrays updated to include `trendData` and `imports` so partial reloads resolve deferred closures directly.

## Implementation Plan

### Tasks

- [x] Task 1: Add `allCategories` as lazy shared prop in middleware
  - File: `app/Http/Middleware/HandleInertiaRequests.php`
  - Action: Add `allCategories` lazy closure to the `share()` return array, guarded by `$request->user()`. Returns parent categories with `id`, `name`, `color`, `icon` ordered by name.
  - Notes: Only resolves for authenticated users. Returns `[]` for guests.

- [x] Task 2: Refactor DashboardController to defer trend data and imports
  - File: `app/Http/Controllers/DashboardController.php`
  - Action:
    - Remove the synchronous previous-month `getMonthlyData()` call and all inline trend computation
    - Set `trendPercentage` to `null` on all `categoryTotals` entries (immediate render without trends)
    - Remove `hasPreviousMonthData` as a direct prop
    - Add `trendData` deferred prop (group: `trends`) — closure computes previous month data and returns `{ hasPreviousMonthData: bool, categoryTrends: array<slug, int|null> }`
    - Extract `$imports` query into deferred prop (group: `imports`) — closure returns the mapped import records
    - Extract private helper methods: `computeTrendData(int $year, int $month, string $account, array $currentTotals): array` and `getImportRecords(): array`
  - Notes: The controller re-executes on deferred follow-up requests. Each deferred closure captures the values it needs. Two parallel follow-up requests fire (one per group).

- [x] Task 3: Refactor CategoryController to defer transactions and remove allCategories
  - File: `app/Http/Controllers/CategoryController.php`
  - Action:
    - Keep `getCategoryDetail()` call as-is (provides subcategories + totals immediately)
    - Wrap `transactions` in `Inertia::defer(fn () => $data['transactions'])`
    - Remove `allCategories` query entirely (now shared via middleware)
  - Notes: `subcategories`, `totalAmountCents`, `transactionCount` remain immediate. The ColorHeader renders instantly. Transactions load after.

- [x] Task 4: Refactor RecurringController to defer recurringMerchants
  - File: `app/Http/Controllers/RecurringController.php`
  - Action:
    - Wrap `recurringMerchants` in `Inertia::defer(fn () => $this->recurringDetectionService->detectRecurring($account))`
    - Keep `currentAccount` immediate
  - Notes: This is the highest-impact deferral — the `detectRecurring()` method does full table scans.

- [x] Task 5: Remove allCategories from SearchController
  - File: `app/Http/Controllers/SearchController.php`
  - Action:
    - Remove the `$allCategories` query and `allCategories` prop from the render array
    - (It's now provided globally by the middleware shared prop)
  - Notes: Search results and query remain immediate (already lazy via partial reloads).

- [x] Task 6: Update TypeScript types for deferred props
  - File: `resources/js/types/budget.ts`
  - Action:
    - Add `TrendData` type: `{ hasPreviousMonthData: boolean; categoryTrends: Record<string, number | null> }`
    - Update `DashboardProps`:
      - Remove `hasPreviousMonthData: boolean`
      - Change `imports: ImportRecord[]` → `imports?: ImportRecord[]`
      - Add `trendData?: TrendData`
    - Update `CategoryShowProps`:
      - Change `transactions: TransactionItem[]` → `transactions?: TransactionItem[]`
      - Remove `allCategories: CategoryOption[]` (now global shared prop)
    - Update `RecurringProps`:
      - Change `recurringMerchants: RecurringMerchant[]` → `recurringMerchants?: RecurringMerchant[]`
    - Update `SearchProps`:
      - Remove `allCategories: CategoryOption[]` (now global shared prop)
    - Add `SharedProps` type with `allCategories: CategoryOption[]` (used via `usePage().props`)
  - Notes: Deferred props are `undefined` on initial render, hence optional. Inside `<Deferred>` children they're guaranteed defined.

- [x] Task 7: Update Dashboard page with Deferred wrappers and skeleton
  - File: `resources/js/pages/dashboard.tsx`
  - Action:
    - Import `{ Deferred }` from `@inertiajs/react`
    - Import `{ Skeleton }` from `@/components/ui/skeleton`
    - Update props destructuring: `imports` → `imports`, `trendData` (both optional). Remove `hasPreviousMonthData`.
    - For category cards: compute `trendPercentage` from `trendData?.categoryTrends[cat.slug]`. Pass `hasPreviousMonthData` from `trendData?.hasPreviousMonthData`. Cards render immediately, trend arrows appear when `trendData` resolves.
    - For imports: wrap `ImportHistory` in `<Deferred data="imports" fallback={null}>`. Use `(imports ?? []).length > 0` guard.
    - Update `handleMonthChange` partial reload `only` array: replace `hasPreviousMonthData` with `trendData`, add `imports`
    - Update `handleAccountChange` partial reload `only` array similarly
    - Create inline `CategoryCardSkeleton` — no separate file needed. Shape: `div` with 48x48 rounded-xl Skeleton (icon) + two Skeleton lines (name, count) + Skeleton bar (amount). Matches CategoryCard layout.
    - (No `<Deferred>` for trend data — cards render immediately, trends merge progressively)
  - Notes: The `fallback={null}` for imports means the section simply doesn't render until data arrives. No visual jank since it's below the fold.

- [x] Task 8: Update Category Detail page with Deferred wrapper and skeleton
  - File: `resources/js/pages/categories/show.tsx`
  - Action:
    - Import `{ Deferred }` from `@inertiajs/react`
    - Import `{ Skeleton }` from `@/components/ui/skeleton`
    - Remove `allCategories` from props. Access via `usePage<{ allCategories: CategoryOption[] }>().props.allCategories`.
    - Mark `transactions` as optional in destructuring
    - Wrap the transaction list section (subcategories + transactions) in `<Deferred data="transactions" fallback={<TransactionListSkeleton />}>`
    - Create inline `TransactionListSkeleton` — 6 rows, each with Skeleton date (w-16) + Skeleton description (flex-1) + Skeleton amount (w-20). Wrapped in the same `rounded-xl bg-surface/80 backdrop-blur-2xl` container.
    - The ColorHeader renders immediately from `totalAmountCents` and `transactionCount` (immediate props).
  - Notes: Subcategories come from the same `getCategoryDetail()` call and are immediate. They render with the header.

- [x] Task 9: Update Recurring page with Deferred wrapper and skeleton
  - File: `resources/js/pages/recurring.tsx`
  - Action:
    - Import `{ Deferred }` from `@inertiajs/react`
    - Import `{ Skeleton }` from `@/components/ui/skeleton`
    - Mark `recurringMerchants` as optional in destructuring
    - Keep the header ("Recurring" title) outside the Deferred — renders immediately
    - Wrap the merchant list section in `<Deferred data="recurringMerchants" fallback={<RecurringPageSkeleton />}>`
    - Create inline `RecurringPageSkeleton` — 4 merchant card skeletons. Each: `div.bg-surface/80.backdrop-blur-2xl.border.rounded-xl.p-4` containing Skeleton dot (size-2 rounded-full) + two Skeleton text lines + Skeleton amount bar. Matches RecurringMerchantCard layout.
    - Move the count subtitle (`X recurring transactions`) inside the Deferred children (needs data to compute count).
  - Notes: This page has nothing to render before the data except the title. The skeleton provides structure during the load.

- [x] Task 10: Update Search page to use shared allCategories
  - File: `resources/js/pages/search.tsx`
  - Action:
    - Remove `allCategories` from props destructuring
    - Import `usePage` from `@inertiajs/react` (already imported)
    - Access `allCategories` via `usePage<{ allCategories: CategoryOption[] }>().props.allCategories`
    - Pass to `CategoryPicker` component as before
  - Notes: No Deferred needed on Search — results are already lazy via partial reloads.

- [x] Task 11: Update PHP feature tests for deferred props
  - File: `tests/Feature/CategoryControllerTest.php`
  - Action:
    - Update `test_show_includes_all_categories_for_picker`: assert `allCategories` is present as a shared prop (it now comes from middleware, not the controller). The `assertInertia` assertion should still work since shared props are included in the response.
    - Update `test_show_returns_inertia_response_with_category_data`: the `transactions` assertion may need adjustment since it's a deferred prop. On a normal test request, deferred props are NOT included. Use `->has('transactions')` only if the test framework resolves deferred props automatically, otherwise skip this assertion or test the deferred endpoint separately.
  - File: `tests/Feature/RecurringControllerTest.php`
  - Action:
    - Similar: `recurringMerchants` is now deferred. Tests asserting `has('recurringMerchants', N)` may fail on the initial response. Adjust tests to account for deferred props.
  - Notes: Inertia's `AssertableInertia` resolves deferred props in tests by default (it evaluates all closures). Verify this behavior — if so, no test changes needed beyond removing `allCategories` assertions from controller-level tests and adding them to a shared prop test.

- [x] Task 12: Verify and test
  - Action:
    - Run `sail composer test` (PHP tests)
    - Run `sail bun run test` (frontend tests)
    - Run `sail bun run types` (TypeScript type check)
    - Run `sail bun run lint` (ESLint)
    - Manual test: navigate between pages on Chrome DevTools mobile emulation with slow 3G throttling, verify skeletons appear and content loads progressively

### Acceptance Criteria

- [x] AC 1: Given a user navigates to the Dashboard, when the page loads, then `categoryTotals`, `incomeCents`, `expensesCents`, `availableMonths`, and `currentMonth` render immediately (no loading state). Trend arrows (`trendPercentage`) are absent initially and appear when `trendData` resolves.

- [x] AC 2: Given a user is on the Dashboard, when `imports` data resolves after initial render, then the ImportHistory section appears below the fold without visual jank.

- [x] AC 3: Given a user changes the month on the Dashboard via MonthStrip, when the partial reload fires with `only: [...]`, then all props (including `trendData` and `imports`) refresh correctly.

- [x] AC 4: Given a user navigates to a Category Detail page, when the page loads, then the ColorHeader (category name, color, icon, total, count) and subcategory breakdown render immediately. A skeleton placeholder appears where the transaction list will be.

- [x] AC 5: Given a user is on a Category Detail page, when the deferred `transactions` prop resolves, then the skeleton is replaced by the actual transaction list.

- [x] AC 6: Given a user navigates to the Recurring page, when the page loads, then the "Recurring" title renders immediately and 4 merchant card skeleton placeholders appear.

- [x] AC 7: Given a user is on the Recurring page, when the deferred `recurringMerchants` prop resolves, then the skeletons are replaced by actual merchant cards with correct data.

- [x] AC 8: Given a user taps a transaction on Category Detail or Search, when the CategoryPicker opens, then `allCategories` data is available (provided by shared middleware prop). Re-categorization works as before.

- [x] AC 9: Given a user navigates between pages rapidly on mobile, when pages transition, then each page renders its immediate content instantly (no blank white screen). Deferred content fills in after.

- [x] AC 10: Given all existing PHP feature tests, when `sail composer test` runs, then all tests pass.

- [x] AC 11: Given all existing frontend tests, when `sail bun run test` runs, then all tests pass.

- [x] AC 12: Given TypeScript type checks, when `sail bun run types` runs, then no type errors are reported.

## Additional Context

### Dependencies

- `Inertia::defer()` — `vendor/inertiajs/inertia-laravel/src/ResponseFactory.php:194`
- `DeferProp` class — `vendor/inertiajs/inertia-laravel/src/DeferProp.php`
- `<Deferred>` React component — `@inertiajs/react` (`types/Deferred.d.ts`)
- `Skeleton` component — `resources/js/components/ui/skeleton.tsx`

### Testing Strategy

- **PHP Feature tests:** Inertia's `AssertableInertia` evaluates deferred prop closures during testing, so existing assertions on prop values should continue to work. Verify by running tests after changes. If assertions fail for deferred props, adjust by testing the follow-up request explicitly.
- **Frontend tests:** No new component tests for `<Deferred>` wrappers (Inertia's responsibility). Skeleton components are inline presentational — visual verification sufficient.
- **TypeScript:** Run `sail bun run types` to catch any type mismatches from optional prop changes.
- **Manual:** Test page transitions on Chrome DevTools with mobile emulation and slow 3G throttling. Verify skeletons appear, content loads progressively, no blank screens.

### Notes

- **Performance trade-off:** Deferred props cause the controller to re-execute on follow-up requests. For Dashboard, `getMonthlyData()` runs on both the initial and deferred requests. For Category Detail, `getCategoryDetail()` runs twice. The trade-off is worth it: perceived load time drops significantly since the initial render returns instantly.
- **Recurring is the biggest win:** `detectRecurring()` is the most expensive operation in the app (full table scan). Deferring it means the page shell renders in <50ms instead of waiting for potentially seconds of DB work.
- **Dashboard trend arrows are progressive enhancement:** Category cards render with real data immediately. Trend arrows fade in when `trendData` resolves. This is graceful degradation — the core content is always fast.
- **`allCategories` consolidation:** Moving to a shared prop eliminates 2 identical queries (CategoryController + SearchController). The query returns ~13 rows with 4 columns — negligible overhead per request.
- **Future optimization:** If deferred follow-up queries become a concern, consider caching `getMonthlyData()` results or splitting `getCategoryDetail()` into separate methods for subcategories vs transactions.

## Review Notes

- Adversarial review completed (14 findings)
- Findings: 14 total, 2 fixed, 12 skipped (noise or non-issues after analysis)
- Resolution approach: auto-fix
- Fixed: F9 (SharedProps type now used in usePage() calls), F14 (safe default for transactions destructuring)
- Dismissed after deeper analysis: F4, F6, F8 (React re-render propagation handles these correctly)
