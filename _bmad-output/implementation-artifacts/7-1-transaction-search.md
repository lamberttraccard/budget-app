# Story 7.1: Transaction Search

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want to search for transactions by merchant name or description,
so that I can quickly find specific transactions without drilling through categories manually.

## Acceptance Criteria

1. **Given** I am on the Dashboard, **When** I tap the search icon/area, **Then** a search input appears at the top of the overview (FR43).

2. **Given** I type at least 2 characters in the search input, **When** the search executes, **Then** matching transactions are displayed below the input, showing: merchant name, category (with color dot), date, amount, and account (FR44).

3. **Given** search results are displayed, **When** I tap a transaction row, **Then** the category correction picker opens (same as drill-down correction flow).

4. **Given** BudgetQueryService handles the search, **When** a search query is received, **Then** it searches across all months and accounts using PostgreSQL `ILIKE` on the transaction description field (FR43).

5. **Given** the search input is empty or cleared, **When** I dismiss the search, **Then** the Dashboard returns to the normal category overview.

6. **Given** no transactions match the search query, **When** results render, **Then** "No transactions found" appears in tertiary text.

## Tasks / Subtasks

- [x] Task 1: Add search query support to BudgetQueryService (AC: 4)
  - [x] 1.1: Add `q` filter to `queryTransactions()` — when present, add `$query->where('transactions.description', 'ILIKE', '%' . $filters['q'] . '%')` before the existing filters
  - [x] 1.2: Add `category_color` to the select list in `queryTransactions()` by joining on `categories.color` — search results need category color for the dot indicator
  - [x] 1.3: Update the mapped output to include `categoryColor` alongside the existing `categoryName`

- [x] Task 2: Create SearchController with Inertia search page (AC: 1, 2, 4, 5, 6)
  - [x] 2.1: Create `app/Http/Controllers/SearchController.php` with `index()` method
  - [x] 2.2: Accept `q` (string, min 2 chars) and optional `account` (string) query parameters
  - [x] 2.3: When `q` is present and >= 2 chars, call `BudgetQueryService::queryTransactions(['q' => $q, 'account_type' => $account, 'limit' => 50])`
  - [x] 2.4: Return `Inertia::render('search', ['results' => $results['transactions'], 'totalCount' => $results['totalCount'], 'query' => $q, 'currentAccount' => $account, 'allCategories' => $allCategories])` — pass all categories for the correction picker
  - [x] 2.5: When `q` is empty/absent, return `Inertia::render('search', ['results' => [], 'totalCount' => 0, 'query' => '', 'currentAccount' => $account, 'allCategories' => $allCategories])`
  - [x] 2.6: Add route `Route::get('search', [SearchController::class, 'index'])->middleware(['auth', 'verified'])->name('search')` in `routes/web.php`

- [x] Task 3: Add SearchResultItem type and update budget.ts (AC: 2)
  - [x] 3.1: Add `SearchResultItem` type to `resources/js/types/budget.ts`:
    ```typescript
    export type SearchResultItem = {
        id: number;
        date: string;
        description: string;
        amountCents: number;
        categoryId: number;
        categoryName: string;
        categoryColor: string;
        accountName: string;
    };
    ```
  - [x] 3.2: Add `SearchProps` type:
    ```typescript
    export type SearchProps = {
        results: SearchResultItem[];
        totalCount: number;
        query: string;
        currentAccount: string;
        allCategories: CategoryOption[];
    };
    ```

- [x] Task 4: Create Search page component (AC: 1, 2, 3, 5, 6)
  - [x] 4.1: Create `resources/js/pages/search.tsx` as a new Inertia page
  - [x] 4.2: Render a search input at the top with `autoFocus` and the current query pre-filled
  - [x] 4.3: On input change (debounced ~300ms), navigate via `router.get('/search', { q: value, account: currentAccount }, { preserveState: true, replace: true })` for Inertia partial reload
  - [x] 4.4: Only trigger search when query length >= 2 characters; show hint text "Type at least 2 characters to search" when fewer
  - [x] 4.5: Display search results using `SearchResultRow` component (see Task 5) — each row shows: category color dot (8px), merchant name, date, amount, account badge
  - [x] 4.6: When no results: show "No transactions found" in tertiary text centered
  - [x] 4.7: Add a close/back button (X icon or back arrow) that navigates back to Dashboard via `router.visit('/dashboard')`
  - [x] 4.8: Wire category correction: on tap of a result row, open `CategoryPicker` with the same pattern as `categories/show.tsx` — `router.patch` on `/transactions/{id}` then reload search results
  - [x] 4.9: Show total result count: "X transactions found" in secondary text below the input

- [x] Task 5: Create SearchResultRow component (AC: 2, 3)
  - [x] 5.1: Create `resources/js/components/SearchResultRow.tsx`
  - [x] 5.2: Anatomy: category color dot (8px rounded-full, inline), merchant name (truncated), date (body-sm, tertiary), amount (right-aligned, tabular-nums), account name badge (if not default account)
  - [x] 5.3: Props: `SearchResultItem` + `onTap: (id, categoryId, element) => void`
  - [x] 5.4: Min height 44px for touch targets
  - [x] 5.5: Hover state: `bg-surface-elevated` transition

- [x] Task 6: Add search icon/link to Dashboard header (AC: 1, 5)
  - [x] 6.1: In `resources/js/pages/dashboard.tsx`, add a search icon button (lucide-react `Search` icon) in the header area, next to existing controls
  - [x] 6.2: On tap, navigate to `/search` via `router.visit('/search')` (or `router.get('/search')`)
  - [x] 6.3: Style: subtle tertiary icon, 44px touch target, positioned in the Dashboard header bar

- [x] Task 7: Tests & Verification (AC: 1-6)
  - [x] 7.1: Write `tests/Feature/SearchTest.php` — test search returns matching transactions via ILIKE, test empty query returns empty results, test min 2 char enforcement, test account filtering
  - [x] 7.2: Write `resources/js/__tests__/SearchResultRow.test.tsx` — test renders merchant, date, amount, category dot; test onTap callback fires
  - [x] 7.3: Run `sail artisan test` — all backend tests pass (no regressions)
  - [x] 7.4: Run `sail bun run test` — all frontend tests pass
  - [x] 7.5: Run `sail bun run build` — TypeScript compiles cleanly
  - [x] 7.6: Run `sail bun run types` — Type checking passes

## Dev Notes

### Architecture Overview for This Story

This story adds transaction search capability. The UX design spec defines a SearchBar that temporarily replaces the category list on the Dashboard. However, the cleanest implementation given the Inertia architecture is a **dedicated search page** (`/search`) — this keeps the Dashboard controller focused on category-level data, follows the established pattern of separate Inertia pages for distinct views, and avoids complex conditional rendering in the already-large `dashboard.tsx`.

```
Search Flow:
  │
  ├─ Dashboard:
  │   └─ Search icon (lucide-react) → router.visit('/search')
  │
  ├─ Search Page (/search):
  │   ├─ Search input (autoFocus, debounced Inertia reload)
  │   ├─ Results list (SearchResultRow components)
  │   ├─ Category correction (CategoryPicker reuse)
  │   └─ Back button → router.visit('/dashboard')
  │
  ├─ Backend:
  │   ├─ SearchController → BudgetQueryService::queryTransactions(['q' => ...])
  │   ├─ PostgreSQL ILIKE on transactions.description
  │   └─ Results: id, date, description, amountCents, categoryId, categoryName, categoryColor, accountName
  │
  └─ Reused Components:
      ├─ CategoryPicker (Sheet mobile / DropdownMenu desktop)
      ├─ formatAmount(), formatDate() utilities
      └─ AppLayout wrapper
```

### Implementation Details

**1. BudgetQueryService Search Filter:**

In `app/Services/BudgetQueryService.php`, add to `queryTransactions()`:

```php
if (isset($filters['q']) && strlen($filters['q']) >= 2) {
    $query->where('transactions.description', 'ILIKE', '%' . $filters['q'] . '%');
}
```

Also add `categories.color as category_color` to the select list and `'categoryColor' => $row->category_color` to the mapped output.

**Why ILIKE, not full-text search:** The dataset is modest (single user, ~1000s of transactions). PostgreSQL ILIKE is simple, requires no index changes, and handles the use case perfectly. FR43 explicitly specifies ILIKE. If performance becomes an issue later, a `pg_trgm` GIN index can be added non-disruptively.

**2. SearchController:**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\BudgetQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(
        private BudgetQueryService $budgetQueryService,
    ) {}

    public function index(Request $request): Response
    {
        $q = $request->string('q')->trim()->toString();
        $account = $request->string('account')->toString() ?: 'personal';

        $results = [];
        $totalCount = 0;

        if (strlen($q) >= 2) {
            $data = $this->budgetQueryService->queryTransactions([
                'q' => $q,
                'account_type' => $account !== 'all' ? $account : null,
                'limit' => 50,
            ]);
            $results = $data['transactions'];
            $totalCount = $data['totalCount'];
        }

        $allCategories = Category::whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
            ])
            ->toArray();

        return Inertia::render('search', [
            'results' => $results,
            'totalCount' => $totalCount,
            'query' => $q,
            'currentAccount' => $account,
            'allCategories' => $allCategories,
        ]);
    }
}
```

**3. Search Page Component (`search.tsx`):**

The search page uses **Inertia partial reloads with `replace: true`** for the debounced search. This updates the URL (enabling back-button and shareable search URLs) without pushing new history entries for each keystroke.

```tsx
// Debounced search with Inertia:
const debouncedSearch = useMemo(
    () => debounce((value: string) => {
        router.get('/search',
            { q: value, account: currentAccount },
            { preserveState: true, replace: true }
        );
    }, 300),
    [currentAccount]
);
```

**4. SearchResultRow vs. TransactionRow:**

A new `SearchResultRow` component is needed rather than extending `TransactionRow` because:
- SearchResultRow shows additional context: category color dot, account name badge
- TransactionRow is optimized for the drill-down view where category context is already established
- Keeping them separate avoids conditional prop complexity and follows the existing component-per-view pattern

**5. Category Correction from Search:**

Reuse the exact same pattern from `categories/show.tsx`:
1. Tap a `SearchResultRow` → set `selectedTransaction` state
2. Open `CategoryPicker` (Sheet on mobile, DropdownMenu on desktop)
3. On category select → `router.patch('/transactions/{id}', { category_id }, { preserveState: true, onSuccess: () => router.reload() })`
4. Search results refresh automatically via the Inertia reload

### Edge Cases

1. **Search query with special characters:** PostgreSQL ILIKE handles French accented characters natively (é, è, ê, etc.). Searching "boulangerie" matches "BOULANGERIE LEFÈVRE" because ILIKE is case-insensitive. Special regex characters in LIKE (%, _) should be escaped — use `str_replace(['%', '_'], ['\%', '\_'], $filters['q'])` before the ILIKE query.

2. **Very long search queries:** The `limit(50)` cap on `queryTransactions()` prevents oversized result sets. The total count is displayed so the user knows there are more matches.

3. **Search while on different account context:** The search page accepts an `account` parameter. By default it searches "personal" (matching the app's default). The account context persists from the Dashboard if the user navigated with an active account selection.

4. **Rapid typing (debounce):** The 300ms debounce prevents excessive Inertia requests. Each new request cancels the previous one automatically (Inertia's default behavior).

5. **Empty search after having results:** When the user clears the input (< 2 chars), the controller returns empty results. The page shows the "Type at least 2 characters" hint.

6. **Category correction updates search results:** After correcting a transaction's category from search results, the page reloads via `router.reload()`. The corrected transaction now shows the new category dot and name in the results.

7. **Search input auto-focus:** The input has `autoFocus` so the user can start typing immediately when arriving at the search page.

8. **Transaction with null category:** Transactions with `category_id = null` (uncategorized, e.g. freshly imported before AI categorization runs) are handled via LEFT JOIN in `queryTransactions()`. They appear in results with `categoryName = 'Uncategorized'` and `categoryColor = '#6B7280'` (gray). Regression test added.

### Previous Story Intelligence

**From Story 6-3 (Drill-Down & Import Animations):**
- motion/react v12.34.3 installed — available if entrance animations desired for search results
- `useReducedMotion()` pattern established for accessibility
- Inertia View Transitions enabled globally — search page navigation gets smooth transitions for free
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Backend: 314+ tests passing. Known pre-existing failures: DeduplicationServiceTest, TransactionAmountCentsTest (unrelated)
- Frontend: 8 tests passing
- One commit per story pattern

**Files modified in 6-3 relevant to this story:**
- `resources/js/pages/dashboard.tsx` — will be modified again (add search icon)
- `resources/js/components/CategoryPicker.tsx` — reused as-is for correction from search

**From Story 4-1 (Single-Tap Category Correction):**
- `router.patch('/transactions/{id}', { category_id })` is the correction pattern
- `CategoryPicker` accepts `categories`, `currentCategoryId`, `isOpen`, `onClose`, `onSelect`, `triggerRef`
- Toast "Got it, I'll remember" fires automatically via the `TransactionController@update` backend

### Git Intelligence

Recent commits:
```
161afea Stories 6.2 & 6.3: Animated number transitions, drill-down & import animations + review fixes
3918795 Add CLAUDE.md and remove direct prism-php/prism dependency
07d53ea Fix trend indicator layout and TransactionFactory dedup_hash override
3753ee6 Story 6.1: Inline trend indicators + review fixes
```

Established patterns:
- One commit per story (sometimes combined: "Stories 6.2 & 6.3")
- Review fixes bundled into the story commit
- All commands via Sail

### Latest Technical Information

**PostgreSQL ILIKE Performance:**
- ILIKE is case-insensitive pattern matching in PostgreSQL. For the `description` column (VARCHAR), it performs a sequential scan by default.
- For a single-user app with ~1000s of transactions, this is fast enough (< 50ms).
- If future scale requires it, add: `CREATE INDEX idx_transactions_description_trgm ON transactions USING gin (description gin_trgm_ops);` with `CREATE EXTENSION pg_trgm;` — this makes ILIKE use the GIN index.
- No index changes needed for this story.

**Inertia.js `replace: true`:**
- When used with `router.get()`, replaces the current history entry instead of pushing a new one.
- Ideal for search-as-you-type: avoids polluting browser history with every keystroke.
- Combined with `preserveState: true`, maintains component state (scroll position, focus) across reloads.

### Project Structure Notes

- Aligns with architecture plan: `SearchController` in `app/Http/Controllers/`, search page in `resources/js/pages/`
- `SearchResultRow` added to `resources/js/components/` — follows the component-per-view convention (TransactionRow for drill-down, SearchResultRow for search)
- No deviation from unified project structure

### Architecture Compliance

| Requirement | Implementation |
|-------------|----------------|
| FR43: Transaction search via ILIKE | `BudgetQueryService::queryTransactions()` with `q` filter + PostgreSQL ILIKE |
| FR44: Search results display | `SearchResultRow` with merchant, category dot, date, amount, account |
| Category correction from search | Reuse `CategoryPicker` + `router.patch` pattern |
| BudgetQueryService as shared layer [arch] | Search queries go through BudgetQueryService, not direct DB |
| Inertia page pattern [arch] | Dedicated `/search` Inertia page, not raw JSON API |
| camelCase Inertia props [arch] | `allCategories`, `totalCount`, `currentAccount` |
| Constructor injection [arch] | `SearchController` injects `BudgetQueryService` |
| SearchBar UX spec [UX] | Search icon on Dashboard, dedicated search page with results |
| 44px touch targets [UX] | All result rows min-h-[44px] |
| Dark theme [UX] | Surface backgrounds, white text, category accent colors |

### Library & Framework Requirements

**No new packages to install.** This story uses only:
- Laravel 12 (existing) — controllers, routing, Inertia responses
- React 19 (existing) — components, hooks, state
- Inertia.js v2 (existing) — `router.get()`, `router.patch()`, `Inertia::render()`
- PostgreSQL (existing) — ILIKE operator
- lucide-react (existing) — `Search`, `X`, `ArrowLeft` icons
- shadcn/ui (existing) — `Input` component for search field
- motion/react (existing) — optional entrance animations for results

### File Structure Requirements

**New files:**
```
app/Http/Controllers/SearchController.php        (NEW — search endpoint)
resources/js/pages/search.tsx                     (NEW — search Inertia page)
resources/js/components/SearchResultRow.tsx        (NEW — search result row component)
tests/Feature/SearchTest.php                      (NEW — backend search tests)
resources/js/__tests__/SearchResultRow.test.tsx    (NEW — frontend component test)
```

**Modified files:**
```
app/Services/BudgetQueryService.php               (MODIFY — add 'q' filter + categoryColor to queryTransactions)
resources/js/types/budget.ts                      (MODIFY — add SearchResultItem and SearchProps types)
resources/js/pages/dashboard.tsx                  (MODIFY — add search icon in header)
routes/web.php                                    (MODIFY — add search route)
```

**No changes needed:**
```
resources/js/components/CategoryPicker.tsx         (reused as-is)
resources/js/components/TransactionRow.tsx          (not modified, SearchResultRow is separate)
app/Http/Controllers/TransactionController.php     (PATCH endpoint already exists)
resources/js/lib/formatAmount.ts                   (unchanged)
resources/js/lib/formatDate.ts                     (unchanged)
```

### Testing Strategy

**Backend tests (SearchTest.php):**
1. `test_search_returns_matching_transactions` — create transactions, search by description, assert matches returned
2. `test_search_requires_minimum_2_characters` — query with 1 char returns empty
3. `test_search_is_case_insensitive` — search "carrefour" matches "CARREFOUR CITY"
4. `test_search_handles_french_accented_characters` — search "boulangerie" matches "Boulangerie Lefèvre"
5. `test_search_filters_by_account_type` — personal account filter excludes professional transactions
6. `test_search_returns_category_details` — results include categoryName and categoryColor
7. `test_search_with_no_matches_returns_empty` — non-matching query returns empty array
8. `test_search_escapes_special_characters` — query with % or _ is treated as literal

**Frontend tests (SearchResultRow.test.tsx):**
1. `test_renders_merchant_name_and_amount` — displays description and formatted amount
2. `test_renders_category_color_dot` — dot with correct background color
3. `test_calls_onTap_with_transaction_id` — click fires callback

**Regression testing:**
- `sail artisan test` — all existing backend tests pass
- `sail bun run test` — all existing frontend tests pass
- `sail bun run build` — TypeScript compiles cleanly
- `sail bun run types` — type checking passes

### What This Story Does NOT Include

- **MCP SearchTransactions tool** — Architecture defines this but it belongs in a future story or as part of Epic 7's MCP extension
- **RecurringDetectionService** — Story 7.2 covers recurring transactions
- **Full-text search index** — ILIKE is sufficient; GIN/pg_trgm index deferred until performance requires it
- **Search from within category drill-down** — Search is accessible from Dashboard only
- **Search history or suggestions** — Not in requirements

### References

- Epic 7 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 7.1]
- FR43 requirement: [Source: _bmad-output/planning-artifacts/epics.md#Requirements Inventory]
- FR44 requirement: [Source: _bmad-output/planning-artifacts/epics.md#Requirements Inventory]
- SearchBar component spec: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#SearchBar]
- BudgetQueryService architecture: [Source: _bmad-output/planning-artifacts/architecture.md#Service Boundary]
- SearchController in project structure: [Source: _bmad-output/planning-artifacts/architecture.md#Complete Project Directory Structure]
- Category correction pattern: [Source: _bmad-output/planning-artifacts/architecture.md#Category correction]
- Naming conventions: [Source: _bmad-output/planning-artifacts/architecture.md#Naming Patterns]
- Amount handling: [Source: _bmad-output/planning-artifacts/architecture.md#Format Patterns]
- Previous story 6-3: [Source: _bmad-output/implementation-artifacts/6-3-drill-down-and-import-animations.md]
- BudgetQueryService source: [Source: app/Services/BudgetQueryService.php]
- TransactionRow component: [Source: resources/js/components/TransactionRow.tsx]
- CategoryPicker component: [Source: resources/js/components/CategoryPicker.tsx]
- TypeScript types: [Source: resources/js/types/budget.ts]
- Dashboard page: [Source: resources/js/pages/dashboard.tsx]
- DashboardController: [Source: app/Http/Controllers/DashboardController.php]
- Routes: [Source: routes/web.php]
- Transaction model: [Source: app/Models/Transaction.php]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

- Vite manifest error on first test run — resolved by running `sail bun run build` to include new `search.tsx` page in manifest
- `@testing-library/user-event` not installed — switched to `fireEvent` from `@testing-library/react` for click testing
- ESLint `react-hooks/immutability` error for `let timer` in `useMemo` — refactored to use `useRef` with `useCallback`

### Completion Notes List

- **Task 1:** Added `q` filter with ILIKE to `queryTransactions()`, added `categories.color` to select, `categoryColor` to mapped output, and special character escaping for `%` and `_`
- **Task 2:** Created `SearchController` with constructor-injected `BudgetQueryService`, min 2-char query enforcement, optional account filter, category list for picker. Added `/search` route with auth middleware.
- **Task 3:** Added `SearchResultItem` and `SearchProps` types to `budget.ts`
- **Task 4:** Created `search.tsx` Inertia page with debounced search (300ms via `useRef`), Inertia partial reloads with `replace: true`, category correction via `CategoryPicker` reuse, back navigation, clear button, empty/hint states
- **Task 5:** Created `SearchResultRow` component with category color dot (8px), merchant name, date, amount, account badge, 44px touch targets, hover state
- **Task 6:** Added Search icon (lucide-react) button next to MonthStrip in dashboard, 44px touch target, navigates to `/search`
- **Task 7:** 12 backend tests (SearchTest.php), 5 frontend tests (SearchResultRow.test.tsx). All 326 backend + 14 frontend tests pass. Build and type checking clean.

### File List

**New files:**
- app/Http/Controllers/SearchController.php
- resources/js/pages/search.tsx
- resources/js/components/SearchResultRow.tsx
- tests/Feature/SearchTest.php
- resources/js/components/__tests__/SearchResultRow.test.tsx

**Modified files:**
- app/Services/BudgetQueryService.php (added `q` filter, `categoryColor` to queryTransactions)
- resources/js/types/budget.ts (added SearchResultItem, SearchProps types)
- resources/js/pages/dashboard.tsx (added Search icon button next to MonthStrip)
- routes/web.php (added search route)

### Change Log

- 2026-02-21: Story 7.1 Transaction Search — implemented full search feature with ILIKE backend, dedicated search page, SearchResultRow component, category correction from search results, dashboard search icon, 12 backend tests + 5 frontend tests
- 2026-02-21: Code Review Fixes (AI) — Fixed 9 issues: (HIGH) handleAccountChange navigating to dashboard instead of search, search icon not passing account context; (MEDIUM) INNER→LEFT JOIN for uncategorized transactions, debounce timer cleanup on unmount, backslash escaping in ILIKE, categoryName now displayed in SearchResultRow; (LOW) duplicate "No transactions found" removed, aria-label on search button, brittle test count removed. All 326 backend + 15 frontend tests pass.
- 2026-02-21: Bug Fix — Search defaulted currentAccount to 'personal', filtering out professional account transactions. Fixed SearchController to return null when no account specified, updated search.tsx to use buildSearchParams helper that only includes account when set. Search now global across all accounts by default (per AC4).
- 2026-02-21: Re-Review Fixes (AI) — Added 2 regression tests (cross-account search, uncategorized transactions), aria-label on back button, updated stale Dev Notes. All 328 backend + 15 frontend tests pass.
