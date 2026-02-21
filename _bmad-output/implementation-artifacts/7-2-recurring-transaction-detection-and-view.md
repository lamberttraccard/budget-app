# Story 7.2: Recurring Transaction Detection & View

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want to see a list of my recurring transactions (subscriptions, rent, salary),
so that I can understand my fixed costs across all categories in one place.

## Acceptance Criteria

1. **Given** I want to view recurring transactions, **When** I access the recurring view (accessible from Dashboard via a subtle repeat/loop icon next to the search icon), **Then** I see a list of detected recurring transactions grouped by merchant (FR41).

2. **Given** BudgetQueryService/RecurringDetectionService analyzes transaction patterns, **When** it detects recurring transactions, **Then** it groups transactions by normalized merchant name and flags those appearing in 2+ consecutive months as recurring (FR42).

3. **Given** recurring transactions are displayed, **When** I view the list, **Then** each entry shows: merchant name, category (with color dot), typical amount, frequency (monthly), and last occurrence date.

4. **Given** a recurring transaction has varying amounts (e.g., electricity bill), **When** it is displayed, **Then** the typical amount shows the most recent amount, with a range indicator if amounts vary significantly.

5. **Given** I tap a recurring transaction entry, **When** the detail expands, **Then** I see the full history of that recurring transaction across all months.

6. **Given** the recurring view is accessed via MCP, **When** Claude calls a `get_recurring_transactions` tool, **Then** it returns the same recurring detection data in snake_case JSON format.

## Tasks / Subtasks

- [x] Task 1: Create RecurringDetectionService (AC: 2, 3, 4)
  - [x] 1.1: Create `app/Services/RecurringDetectionService.php` with `detectRecurring(?string $accountType = null): array` method
  - [x] 1.2: Query all transactions grouped by normalized merchant (`LOWER(TRIM(description))`) and month (`TO_CHAR(date, 'YYYY-MM')`)
  - [x] 1.3: Join on categories for category name/color and accounts for account filtering
  - [x] 1.4: In PHP, group results by normalized merchant and check for consecutive months (month N and month N+1)
  - [x] 1.5: For each recurring merchant, compute: most recent amount (typical), min/max amounts, month count, last seen month, whether amounts vary significantly (>20% spread from mean)
  - [x] 1.6: Return array of recurring merchants sorted by absolute typical amount descending (highest cost first)
  - [x] 1.7: Include full transaction history per merchant (id, date, amountCents, accountName) for expand view

- [x] Task 2: Create RecurringController with Inertia page (AC: 1, 3)
  - [x] 2.1: Create `app/Http/Controllers/RecurringController.php` with `index()` method
  - [x] 2.2: Accept optional `account` query parameter for account type filtering
  - [x] 2.3: Call `RecurringDetectionService::detectRecurring($accountType)` to get recurring data
  - [x] 2.4: Pass `allCategories` for potential category correction from recurring view
  - [x] 2.5: Return `Inertia::render('recurring', ['recurringMerchants' => ..., 'currentAccount' => ..., 'allCategories' => ...])`
  - [x] 2.6: Add route `Route::get('recurring', [RecurringController::class, 'index'])->middleware(['auth', 'verified'])->name('recurring')` in `routes/web.php`

- [x] Task 3: Add TypeScript types for recurring data (AC: 3, 4)
  - [x] 3.1: Add `RecurringTransaction` type to `resources/js/types/budget.ts`:
    ```typescript
    export type RecurringTransaction = {
        id: number;
        date: string;
        amountCents: number;
        accountName: string;
    };
    ```
  - [x] 3.2: Add `RecurringMerchant` type:
    ```typescript
    export type RecurringMerchant = {
        merchantName: string;
        categoryId: number;
        categoryName: string;
        categoryColor: string;
        typicalAmountCents: number;
        frequency: string;
        lastSeenMonth: string;
        monthCount: number;
        amountVaries: boolean;
        minAmountCents: number;
        maxAmountCents: number;
        transactions: RecurringTransaction[];
    };
    ```
  - [x] 3.3: Add `RecurringProps` type:
    ```typescript
    export type RecurringProps = {
        recurringMerchants: RecurringMerchant[];
        currentAccount: string | null;
        allCategories: CategoryOption[];
    };
    ```

- [x] Task 4: Create Recurring page component (AC: 1, 3, 4, 5)
  - [x] 4.1: Create `resources/js/pages/recurring.tsx` as a new Inertia page
  - [x] 4.2: Page header with "Recurring" title and back button (ArrowLeft icon, navigates to `/dashboard`)
  - [x] 4.3: Render list of `RecurringMerchantCard` components (see Task 5), one per recurring merchant
  - [x] 4.4: Each card shows: merchant name, category color dot + name, typical amount, "Monthly" frequency, last seen month
  - [x] 4.5: Tapping a card toggles expanded state showing full transaction history (TransactionRow-style entries)
  - [x] 4.6: When amount varies, show range indicator below the typical amount (e.g., "€45.00 – €62.00" in secondary text)
  - [x] 4.7: Empty state: "No recurring transactions detected yet" centered in tertiary text
  - [x] 4.8: Show total recurring count in subtitle: "X recurring transactions"

- [x] Task 5: Create RecurringMerchantCard component (AC: 3, 4, 5)
  - [x] 5.1: Create `resources/js/components/RecurringMerchantCard.tsx`
  - [x] 5.2: Collapsed state anatomy: merchant name (15px, semibold, white), category color dot (8px) + category name (body-sm, secondary), typical amount (heading-sm, right-aligned), "Monthly" label (body-sm, secondary), last seen (body-sm, tertiary, e.g., "Feb 2026")
  - [x] 5.3: Expanded state: show all transactions as rows below the card (date, amount, account name), ordered by date descending
  - [x] 5.4: Chevron icon rotates on expand/collapse (ChevronDown)
  - [x] 5.5: Amount varies indicator: if `amountVaries` is true, show min-max range below typical amount in secondary text
  - [x] 5.6: Surface card styling (`bg-[#1C1C1E]` rounded-xl), 44px min touch target
  - [x] 5.7: Props: `RecurringMerchant` + `isExpanded: boolean` + `onToggle: () => void`

- [x] Task 6: Add recurring icon to Dashboard header (AC: 1)
  - [x] 6.1: In `resources/js/pages/dashboard.tsx`, add a `Repeat` icon (lucide-react) next to the existing Search icon in the header
  - [x] 6.2: On tap, navigate to `/recurring` via `router.visit('/recurring')`
  - [x] 6.3: Style: subtle tertiary icon, 44px touch target, same pattern as search icon button
  - [x] 6.4: Pass current account context to recurring page if applicable

- [x] Task 7: Create GetRecurringTransactions MCP tool (AC: 6)
  - [x] 7.1: Create `app/Mcp/Tools/GetRecurringTransactions.php`
  - [x] 7.2: Accept optional `account_type` parameter (personal/professional)
  - [x] 7.3: Call `RecurringDetectionService::detectRecurring($accountType)`
  - [x] 7.4: Transform output to snake_case JSON format with metadata wrapper: `{ data: [...], meta: { total_count } }`
  - [x] 7.5: Each recurring entry in snake_case: `merchant_name`, `category_name`, `category_id`, `typical_amount_cents`, `frequency`, `last_seen_month`, `month_count`, `amount_varies`, `transactions: [...]`

- [x] Task 8: Tests & Verification (AC: 1-6)
  - [x] 8.1: Write `tests/Feature/RecurringDetectionTest.php` — test recurring detection with consecutive months, non-consecutive months excluded, varying amounts flagged, account filtering, empty state
  - [x] 8.2: Write `tests/Feature/RecurringControllerTest.php` — test page renders, empty state, account filtering via query param
  - [x] 8.3: Write `resources/js/components/__tests__/RecurringMerchantCard.test.tsx` — test renders merchant, category dot, amount; test expand/collapse toggle; test amount varies indicator
  - [x] 8.4: Run `sail artisan test` — all backend tests pass (no regressions)
  - [x] 8.5: Run `sail bun run test` — all frontend tests pass
  - [x] 8.6: Run `sail bun run build` — TypeScript compiles cleanly
  - [x] 8.7: Run `sail bun run types` — type checking passes

## Dev Notes

### Architecture Overview for This Story

This story adds recurring transaction detection and a dedicated view page. The architecture follows the established pattern from Story 7.1 (dedicated Inertia page, service-layer logic, MCP tool), with the addition of a new `RecurringDetectionService` that encapsulates the detection algorithm.

```
Recurring Flow:
  │
  ├─ Dashboard:
  │   └─ Repeat icon (lucide-react) → router.visit('/recurring')
  │
  ├─ Recurring Page (/recurring):
  │   ├─ Page header: "Recurring" title + back button
  │   ├─ RecurringMerchantCard list (grouped by merchant)
  │   ├─ Expand/collapse per merchant (full transaction history)
  │   └─ Empty state if no recurring detected
  │
  ├─ Backend:
  │   ├─ RecurringController → RecurringDetectionService::detectRecurring()
  │   ├─ Detection: GROUP BY normalized merchant + month → PHP consecutive check
  │   └─ Results: merchantName, category, typicalAmount, frequency, lastSeen, history
  │
  ├─ MCP:
  │   └─ GetRecurringTransactions → RecurringDetectionService (snake_case output)
  │
  └─ Reused Components/Patterns:
      ├─ AppLayout wrapper
      ├─ formatAmount(), formatDate() utilities
      ├─ Surface card styling (same as drill-down)
      └─ 44px touch targets, dark theme
```

### Implementation Details

**1. RecurringDetectionService — Core Algorithm:**

The detection algorithm has two phases: SQL aggregation and PHP consecutive-month analysis.

**Phase 1 — SQL Aggregation:**
```php
$query = Transaction::query()
    ->select([
        DB::raw("LOWER(TRIM(transactions.description)) as normalized_merchant"),
        DB::raw("MAX(transactions.description) as display_merchant"),
        DB::raw("TO_CHAR(transactions.date, 'YYYY-MM') as month"),
        DB::raw('COUNT(*) as tx_count'),
        DB::raw('SUM(transactions.amount_cents) as total_amount'),
    ])
    ->groupBy(
        DB::raw("LOWER(TRIM(transactions.description))"),
        DB::raw("TO_CHAR(transactions.date, 'YYYY-MM')")
    )
    ->orderBy('normalized_merchant')
    ->orderBy('month');

if ($accountType !== null) {
    $query->join('accounts', 'transactions.account_id', '=', 'accounts.id')
        ->where('accounts.type', $accountType);
}
```

**Phase 2 — Consecutive Month Detection (PHP):**
```php
private function hasConsecutiveMonths(array $months): bool
{
    sort($months);
    for ($i = 1; $i < count($months); $i++) {
        $prev = Carbon::createFromFormat('Y-m', $months[$i - 1]);
        $current = Carbon::createFromFormat('Y-m', $months[$i]);
        if ($prev->copy()->addMonth()->format('Y-m') === $current->format('Y-m')) {
            return true;
        }
    }
    return false;
}
```

**Why two phases, not pure SQL:**
- Pure SQL consecutive-month detection requires window functions (LAG/LEAD) and CTEs, making the query complex and hard to test
- The dataset is small (single user, ~1000s of transactions) — the PHP loop is negligible
- PHP logic is easier to unit test and debug
- The SQL does the heavy lifting (aggregation), PHP does the logical filtering

**2. Normalized Merchant Name:**

Using `LOWER(TRIM(description))` for normalization. This handles:
- Case variations: "Netflix" vs "NETFLIX" vs "netflix"
- Leading/trailing whitespace

Not handled (acceptable for MVP):
- Suffix variations: "NETFLIX.COM" vs "NETFLIX" — these remain separate entries
- If better normalization is needed later, `merchant_rules` table already provides learned mappings

For the display name, `MAX(description)` picks one consistent representation (usually the most "complete" version due to alphabetical ordering of uppercased strings).

**3. Typical Amount and Variation:**

- `typicalAmountCents`: The most recent transaction's amount_cents (not average, to match AC4)
- `amountVaries`: True if `(maxAmount - minAmount) / abs(avgAmount) > 0.20` (20% spread threshold)
- When varies: Display range as "€45.00 – €62.00" below the typical amount
- When stable: No range indicator shown

**4. Transaction History per Merchant:**

For the expand view, we need the actual transaction records. This is a second query — only fetched when needed (or can be included upfront since the page is server-rendered via Inertia). Given the Inertia architecture (no lazy loading), include the history data in the initial page load. The data volume is manageable (recurring merchants × ~12 transactions each).

```php
// After detecting recurring merchants, fetch their full history
$recurringDescriptions = collect($recurringMerchants)->pluck('normalizedMerchant');

$transactions = Transaction::query()
    ->whereRaw("LOWER(TRIM(description)) IN (" .
        $recurringDescriptions->map(fn() => '?')->implode(',') . ")",
        $recurringDescriptions->toArray()
    )
    ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
    ->select(['transactions.id', 'transactions.date', 'transactions.description',
              'transactions.amount_cents', 'accounts.name as account_name'])
    ->orderByDesc('transactions.date')
    ->get();
```

**5. RecurringController:**

Follows the same pattern as SearchController — constructor-injected service, Inertia render:

```php
class RecurringController extends Controller
{
    public function __construct(
        private RecurringDetectionService $recurringDetectionService,
    ) {}

    public function index(Request $request): Response
    {
        $account = $request->string('account')->toString() ?: null;
        $recurringMerchants = $this->recurringDetectionService->detectRecurring($account);

        $allCategories = Category::whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'color' => $c->color])
            ->toArray();

        return Inertia::render('recurring', [
            'recurringMerchants' => $recurringMerchants,
            'currentAccount' => $account,
            'allCategories' => $allCategories,
        ]);
    }
}
```

**6. Recurring Page Component (`recurring.tsx`):**

The page uses local state (`expandedMerchant`) to track which card is expanded. Only one card expanded at a time (accordion behavior).

```tsx
const [expandedMerchant, setExpandedMerchant] = useState<string | null>(null);

const toggleMerchant = (merchantName: string) => {
    setExpandedMerchant(prev => prev === merchantName ? null : merchantName);
};
```

**7. GetRecurringTransactions MCP Tool:**

Follows the same pattern as `GetCategoryTotals`. Calls `RecurringDetectionService::detectRecurring()` and transforms keys to snake_case.

### Edge Cases

1. **Merchant with only 2 non-consecutive months (e.g., Jan and March):** Not flagged as recurring — the consecutive month requirement filters it out. This is correct behavior.

2. **Merchant that was recurring but stopped:** Still shows in the list if it had 2+ consecutive months historically. The `lastSeenMonth` field shows when it was last seen, helping the user identify stale recurring items.

3. **Same merchant across personal and professional accounts:** When no account filter is applied, transactions from both account types contribute to the consecutive month check. When filtered, only that account's transactions are considered.

4. **Merchant with category correction mid-stream:** The category shown is from the most recent transaction (MAX by date). If a user corrected the category for recent transactions, that corrected category is displayed.

5. **Single transaction per merchant per month vs. multiple:** Both count. A merchant with 3 transactions in January and 1 in February still qualifies. The detection groups by merchant+month, so the month appears regardless of count.

6. **Very new users with < 2 months of data:** No recurring transactions can be detected. Empty state is shown: "No recurring transactions detected yet."

7. **Varying amounts with extreme outliers:** The 20% spread threshold may flag merchants with one unusual charge. This is acceptable — the range indicator shows the user the full picture.

8. **Hundreds of recurring merchants:** Unlikely for a single user but handled — the list is scrollable with no pagination (Inertia server-renders all data). If performance becomes an issue, add a limit parameter.

### Previous Story Intelligence

**From Story 7-1 (Transaction Search):**
- Dashboard header has a Search icon button — recurring icon goes right next to it
- Dedicated Inertia page pattern (`/search` → `search.tsx`) — recurring follows same pattern (`/recurring` → `recurring.tsx`)
- `router.visit()` for page navigation from Dashboard
- AppLayout wrapper used for consistent page structure
- 44px touch targets, dark surface cards, category color dots — all established patterns
- Backend: 328 tests passing (12 search tests added in 7-1)
- Frontend: 15 tests passing (5 search tests added in 7-1)
- Build and type checking clean
- Known pre-existing failures: DeduplicationServiceTest, TransactionAmountCentsTest (unrelated)

**From Story 4-1 (Category Correction):**
- `CategoryPicker` available for reuse if correction from recurring view is needed
- `router.patch('/transactions/{id}', { category_id })` is the established correction pattern

**Files created in 7-1 relevant to this story:**
- `resources/js/pages/search.tsx` — reference for page structure
- `resources/js/components/SearchResultRow.tsx` — reference for row component pattern
- `app/Http/Controllers/SearchController.php` — reference for controller pattern

### Git Intelligence

Recent commits:
```
7513ca2 Story 7.1: Transaction search with cross-account ILIKE + review fixes
161afea Stories 6.2 & 6.3: Animated number transitions, drill-down & import animations + review fixes
3918795 Add CLAUDE.md and remove direct prism-php/prism dependency
07d53ea Fix trend indicator layout and TransactionFactory dedup_hash override
```

Patterns:
- One commit per story (sometimes combined with review fixes)
- All commands via Sail (`sail artisan test`, `sail bun run build`, etc.)

### Latest Technical Information

**PostgreSQL GROUP BY + LOWER/TRIM:**
- `LOWER(TRIM(description))` is evaluated per-row during the GROUP BY scan. For a single-user app with ~1000s of transactions, this is fast (< 100ms).
- No index needed. If performance becomes an issue later, a functional index can be added: `CREATE INDEX idx_transactions_norm_desc ON transactions (LOWER(TRIM(description)));`

**Carbon Month Comparison:**
- `Carbon::createFromFormat('Y-m', $month)` creates a Carbon instance from a "YYYY-MM" string
- `$prev->copy()->addMonth()->format('Y-m')` computes the next month accounting for year boundaries (December → January)
- `copy()` is critical to avoid mutating the original Carbon instance

**Inertia Accordion Pattern:**
- No built-in accordion in shadcn/ui that we currently use
- Implementing with local React state (`useState`) is simpler and avoids adding new dependencies
- The accordion only manages open/closed state — no animation needed for MVP (though motion/react is available if desired)

### Project Structure Notes

- Aligns with architecture plan: `RecurringDetectionService` in `app/Services/`, `RecurringController` in `app/Http/Controllers/`
- `recurring.tsx` page in `resources/js/pages/` follows Inertia page convention
- `RecurringMerchantCard` in `resources/js/components/` follows component-per-view pattern
- `GetRecurringTransactions` MCP tool in `app/Mcp/Tools/` follows existing MCP tool pattern
- No deviation from project structure

### Architecture Compliance

| Requirement | Implementation |
|-------------|----------------|
| FR41: Recurring transaction view | `recurring.tsx` page with RecurringMerchantCard list |
| FR42: Recurring detection (2+ consecutive months) | `RecurringDetectionService` with SQL aggregation + PHP consecutive check |
| BudgetQueryService pattern [arch] | New dedicated RecurringDetectionService (complex detection logic warrants separate service) |
| Inertia page pattern [arch] | Dedicated `/recurring` Inertia page |
| camelCase Inertia props [arch] | `recurringMerchants`, `currentAccount`, `allCategories` |
| snake_case MCP responses [arch] | `GetRecurringTransactions` tool returns snake_case JSON with metadata |
| Constructor injection [arch] | `RecurringController` injects `RecurringDetectionService` |
| RecurringList UX spec [UX] | Page header with back button, merchant cards, expand to history, empty state |
| 44px touch targets [UX] | All cards min-h-[44px] |
| Dark theme [UX] | Surface backgrounds (#1C1C1E), white text, category accent colors |
| Repeat icon access [UX] | Dashboard header icon next to search icon |

### Library & Framework Requirements

**No new packages to install.** This story uses only:
- Laravel 12 (existing) — controllers, routing, Inertia responses, Eloquent
- React 19 (existing) — components, hooks, state
- Inertia.js v2 (existing) — `router.visit()`, `Inertia::render()`
- PostgreSQL (existing) — LOWER(), TRIM(), TO_CHAR(), GROUP BY
- Carbon (existing via Laravel) — month comparison logic
- lucide-react (existing) — `Repeat`, `ArrowLeft`, `ChevronDown` icons
- laravel/mcp (existing) — MCP tool registration

### File Structure Requirements

**New files:**
```
app/Services/RecurringDetectionService.php       (NEW — recurring detection algorithm)
app/Http/Controllers/RecurringController.php     (NEW — recurring page endpoint)
app/Mcp/Tools/GetRecurringTransactions.php       (NEW — MCP tool for recurring data)
resources/js/pages/recurring.tsx                  (NEW — recurring Inertia page)
resources/js/components/RecurringMerchantCard.tsx (NEW — recurring merchant card component)
tests/Feature/RecurringDetectionTest.php          (NEW — backend detection tests)
tests/Feature/RecurringControllerTest.php         (NEW — backend controller tests)
resources/js/components/__tests__/RecurringMerchantCard.test.tsx (NEW — frontend component test)
```

**Modified files:**
```
resources/js/types/budget.ts                     (MODIFY — add RecurringTransaction, RecurringMerchant, RecurringProps types)
resources/js/pages/dashboard.tsx                 (MODIFY — add Repeat icon in header next to Search icon)
routes/web.php                                    (MODIFY — add recurring route)
```

**No changes needed:**
```
app/Services/BudgetQueryService.php              (not modified — RecurringDetectionService is separate)
resources/js/components/CategoryPicker.tsx        (available for reuse but not modified)
resources/js/lib/formatAmount.ts                  (unchanged)
resources/js/lib/formatDate.ts                    (unchanged)
```

### Testing Strategy

**Backend tests (RecurringDetectionTest.php):**
1. `test_detects_recurring_with_consecutive_months` — create transactions for same merchant in Jan+Feb, assert detected
2. `test_excludes_non_consecutive_months` — create transactions for same merchant in Jan+March (gap), assert NOT detected
3. `test_handles_multiple_recurring_merchants` — create 3 merchants with consecutive months, assert all 3 detected
4. `test_flags_varying_amounts` — create merchant with amounts varying >20%, assert `amountVaries` is true
5. `test_stable_amounts_not_flagged` — create merchant with identical amounts, assert `amountVaries` is false
6. `test_filters_by_account_type` — create recurring in personal+professional, filter by personal, assert only personal returned
7. `test_returns_transaction_history` — assert each recurring merchant includes full transaction list
8. `test_empty_when_no_transactions` — no transactions, assert empty array returned
9. `test_single_month_merchant_excluded` — merchant in only one month, assert not detected
10. `test_most_recent_amount_as_typical` — assert typicalAmountCents is from the most recent transaction

**Backend tests (RecurringControllerTest.php):**
1. `test_recurring_page_renders` — GET /recurring returns Inertia response with expected props
2. `test_recurring_page_empty_state` — no transactions, page renders with empty array
3. `test_recurring_page_filters_by_account` — `?account=personal` filters results
4. `test_recurring_page_requires_auth` — unauthenticated redirected to login

**Frontend tests (RecurringMerchantCard.test.tsx):**
1. `test_renders_merchant_name_and_amount` — displays merchant name and formatted amount
2. `test_renders_category_color_dot` — dot with correct background color
3. `test_toggle_expand_collapse` — click fires onToggle callback
4. `test_shows_amount_range_when_varies` — when amountVaries=true, range is displayed
5. `test_hides_range_when_stable` — when amountVaries=false, no range shown

**Regression testing:**
- `sail artisan test` — all existing backend tests pass
- `sail bun run test` — all existing frontend tests pass
- `sail bun run build` — TypeScript compiles cleanly
- `sail bun run types` — type checking passes

### What This Story Does NOT Include

- **Category correction from recurring view** — Not in the AC. Users can correct categories from the drill-down or search views. Can be added as a follow-up if needed.
- **Editing or removing recurring markers** — The detection is fully automatic. No manual override for "this is not recurring."
- **Push notifications for recurring charges** — Not in requirements. Phase 3 consideration.
- **Predicted upcoming charges** — Not in requirements. Would require date prediction logic.
- **SearchTransactions MCP tool** — Mentioned in architecture but not in this story's AC. Could be a follow-up.
- **Recurring grouping by `merchant_rules`** — For MVP, using `LOWER(TRIM(description))`. Could be enhanced later to use learned merchant rules for better normalization.

### References

- Epic 7 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 7.2]
- FR41 requirement: [Source: _bmad-output/planning-artifacts/epics.md#Requirements Inventory]
- FR42 requirement: [Source: _bmad-output/planning-artifacts/epics.md#Requirements Inventory]
- RecurringList UX spec: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#RecurringList]
- Recurring View navigation pattern: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Recurring View]
- RecurringDetectionService in architecture: [Source: _bmad-output/planning-artifacts/architecture.md#Service Boundary]
- GetRecurringTransactions MCP tool: [Source: _bmad-output/planning-artifacts/architecture.md#Complete Project Directory Structure]
- Naming conventions: [Source: _bmad-output/planning-artifacts/architecture.md#Naming Patterns]
- Amount handling: [Source: _bmad-output/planning-artifacts/architecture.md#Format Patterns]
- MCP response structure: [Source: _bmad-output/planning-artifacts/architecture.md#Communication Patterns]
- Previous story 7-1: [Source: _bmad-output/implementation-artifacts/7-1-transaction-search.md]
- BudgetQueryService source: [Source: app/Services/BudgetQueryService.php]
- Dashboard page: [Source: resources/js/pages/dashboard.tsx]
- TypeScript types: [Source: resources/js/types/budget.ts]
- Routes: [Source: routes/web.php]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

- RecurringDetectionTest: min/max assertion on negative amounts was initially inverted (min(-4500, -6200) = -6200, not -4500). Fixed test assertion order.

### Completion Notes List

- Task 1: Created RecurringDetectionService with two-phase detection algorithm (SQL aggregation + PHP consecutive-month check via Carbon). All 10 unit tests pass.
- Task 2: Created RecurringController with constructor-injected service, optional account filtering, allCategories for picker. Route added. 4 feature tests pass.
- Task 3: Added RecurringTransaction, RecurringMerchant, RecurringProps types to budget.ts.
- Task 4: Created recurring.tsx Inertia page with header, back button, merchant card list, accordion expand/collapse, empty state, and recurring count subtitle.
- Task 5: Created RecurringMerchantCard component with collapsed/expanded states, category dot, typical amount, amount varies range indicator, chevron rotation, surface card styling, 44px touch targets.
- Task 6: Added Repeat icon to dashboard header next to Search icon, navigates to /recurring.
- Task 7: Created GetRecurringTransactions MCP tool with snake_case output, data/meta wrapper, optional account_type filter.
- Task 8: All tests pass — 342 backend (14 new), 20 frontend (5 new). Build clean. Types clean. PHP Pint clean. ESLint has only pre-existing issues.

### File List

**New files:**
- app/Services/RecurringDetectionService.php
- app/Http/Controllers/RecurringController.php
- app/Mcp/Tools/GetRecurringTransactions.php
- resources/js/pages/recurring.tsx
- resources/js/components/RecurringMerchantCard.tsx
- tests/Feature/RecurringDetectionTest.php
- tests/Feature/RecurringControllerTest.php
- resources/js/components/__tests__/RecurringMerchantCard.test.tsx

**Modified files:**
- resources/js/types/budget.ts (added RecurringTransaction, RecurringMerchant, RecurringProps types)
- resources/js/pages/dashboard.tsx (added Repeat icon import and button next to Search)
- routes/web.php (added recurring route + RecurringController import)

### Change Log

- 2026-02-21: Story 7.2 implementation — recurring transaction detection & view with MCP tool
- 2026-02-21: Code review fixes — H1: fixed reversed amount range display for expenses (swapped formatAbsAmount order in RecurringMerchantCard, fixed frontend test data to match backend output); H2: fixed categoryId TypeScript type to allow null; M1: dashboard recurring icon now passes currentAccount to recurring page URL; M2: removed unused allCategories from controller, type, and test; M3: added account parameter validation in RecurringController
