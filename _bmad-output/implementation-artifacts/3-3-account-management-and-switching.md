# Story 3.3: Account Management & Switching

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want to switch between personal and professional account views so that my spending contexts stay separate,
so that professional expenses don't mix with my personal financial picture.

## Acceptance Criteria

1. **Given** I am on the Dashboard, **When** I look at the header, **Then** I see an account picker dropdown showing the currently selected account context ("Personal" by default) (FR19, FR20).

2. **Given** I tap the account picker, **When** the dropdown opens (shadcn/ui DropdownMenu), **Then** I see available account contexts (e.g. "Personal", "Professional") to choose from (FR18).

3. **Given** I select "Professional" from the dropdown, **When** the view updates, **Then** all Dashboard data (category amounts, SummaryWidget, category cards) reflects only transactions from professional accounts via Inertia partial reload (FR19).

4. **Given** I am in "Personal" view (default), **When** the Dashboard loads data, **Then** professional account transactions are excluded from all totals and category breakdowns (FR20).

5. **Given** I switch accounts and then navigate months, **When** month data loads, **Then** the account filter persists -- only the selected account context's transactions are shown.

6. **Given** I close and reopen the app, **When** the Dashboard loads, **Then** the account selection defaults to "Personal" (FR20).

## Tasks / Subtasks

- [x] Task 1: Add account type filtering to BudgetQueryService methods (AC: 3, 4)
  - [x] 1.1: Add `?string $accountType = null` parameter to `getAvailableMonths()` method; filter transactions by account type when provided
  - [x] 1.2: Add `?string $accountType = null` parameter to `getCategoryDetail()` method; filter both subcategory and transaction queries by account type when provided
  - [x] 1.3: Verify existing `getMonthlyData()` already has the `$accountType` parameter (it does -- no change needed)

- [x] Task 2: Add account context data to shared Inertia props (AC: 1, 2)
  - [x] 2.1: In `HandleInertiaRequests::share()`, add `accountContexts` prop: query distinct account types from the accounts table, returning array of `['value' => 'personal', 'label' => 'Personal']` objects
  - [x] 2.2: Only query when user is authenticated (return empty array otherwise)

- [x] Task 3: Update DashboardController to accept and forward account param (AC: 3, 4)
  - [x] 3.1: Accept `?account=personal|professional` query parameter, defaulting to `'personal'`
  - [x] 3.2: Validate account param is one of `'personal'` or `'professional'`; fall back to `'personal'` if invalid
  - [x] 3.3: Pass account type to `BudgetQueryService::getMonthlyData()` and `getAvailableMonths()`
  - [x] 3.4: Return `currentAccount` as an Inertia prop (string: `'personal'` or `'professional'`)

- [x] Task 4: Update CategoryController to accept and forward account param (AC: 3, 5)
  - [x] 4.1: Accept `?account=personal|professional` query parameter, defaulting to `'personal'`
  - [x] 4.2: Validate account param; fall back to `'personal'` if invalid
  - [x] 4.3: Pass account type to `BudgetQueryService::getCategoryDetail()`
  - [x] 4.4: Return `currentAccount` as an Inertia prop

- [x] Task 5: Create AccountPicker component (AC: 1, 2)
  - [x] 5.1: Create `resources/js/components/AccountPicker.tsx`
  - [x] 5.2: Props: `{ currentAccount: string, accountContexts: { value: string, label: string }[], onAccountChange: (account: string) => void }`
  - [x] 5.3: Render shadcn/ui DropdownMenu with trigger showing current account label + chevron down icon
  - [x] 5.4: Dropdown items for each available context; active context visually indicated with a check icon
  - [x] 5.5: If only one context exists, still render the trigger but as non-interactive label (no dropdown)
  - [x] 5.6: Trigger text style: `text-sm font-medium text-text-secondary hover:text-white`, chevron icon `size-4`

- [x] Task 6: Integrate AccountPicker into AppLayout (AC: 1, 2)
  - [x] 6.1: Accept `currentAccount` and `onAccountChange` props in AppLayout (passed from page components)
  - [x] 6.2: Read `accountContexts` from shared Inertia props via `usePage()`
  - [x] 6.3: Render AccountPicker in the header, right side (next to "budget-app" title)
  - [x] 6.4: Only render when `accountContexts.length > 0` (user has imported at least once)

- [x] Task 7: Update Dashboard page with account switching (AC: 1, 2, 3, 5, 6)
  - [x] 7.1: Add `currentAccount: string` to `DashboardProps`
  - [x] 7.2: Create `handleAccountChange` callback: `router.get('/dashboard', { month: currentMonth, account }, { preserveState: true, preserveScroll: true, only: ['categoryTotals', 'incomeCents', 'expensesCents', 'currentMonth', 'currentAccount', 'availableMonths'] })`
  - [x] 7.3: Update `handleMonthChange` to include current account: `router.get('/dashboard', { month, account: currentAccount }, { ... })`
  - [x] 7.4: Pass `currentAccount` and `handleAccountChange` to AppLayout

- [x] Task 8: Update CategoryCard to forward account param (AC: 5)
  - [x] 8.1: Add `currentAccount: string` prop to CategoryCard
  - [x] 8.2: Update Link href: `` `/categories/${id}?month=${currentMonth}&account=${currentAccount}` ``
  - [x] 8.3: Update Dashboard to pass `currentAccount` prop to each CategoryCard

- [x] Task 9: Update Categories/Show page to preserve account context (AC: 5)
  - [x] 9.1: Add `currentAccount: string` to `CategoryShowProps`
  - [x] 9.2: Pass `currentAccount` to AppLayout for header display
  - [x] 9.3: Ensure back navigation from drill-down preserves the account param

- [x] Task 10: Update import flow to support account type selection (AC: 2, 3)
  - [x] 10.1: Add `account_type` field to import confirmation dialog -- radio group with "Personal" (default) and "Professional"
  - [x] 10.2: Pass `account_type` to `ImportController::store()` request
  - [x] 10.3: Update `ImportController::store()` to use the provided `account_type` instead of hardcoded `'personal'`
  - [x] 10.4: Validate `account_type` as `'personal'` or `'professional'` in the store request

- [x] Task 11: Update TypeScript types (AC: all)
  - [x] 11.1: Add `AccountContext` type: `{ value: string; label: string }`
  - [x] 11.2: Add `currentAccount: string` to `DashboardProps`
  - [x] 11.3: Add `currentAccount: string` to `CategoryShowProps`
  - [x] 11.4: Update `AppLayoutProps` to accept optional `currentAccount` and `onAccountChange` props
  - [x] 11.5: Add `accountContexts: AccountContext[]` to shared page props type

- [x] Task 12: Write Feature Tests (AC: 1-6)
  - [x] 12.1: Test DashboardController defaults to personal account filtering (no `?account=` param)
  - [x] 12.2: Test DashboardController with `?account=professional` returns only professional account transactions
  - [x] 12.3: Test DashboardController with `?account=personal` excludes professional account transactions
  - [x] 12.4: Test DashboardController with invalid `?account=` value defaults to personal
  - [x] 12.5: Test account filter persists with month param: `?month=2026-01&account=professional`
  - [x] 12.6: Test `availableMonths` is scoped to account type
  - [x] 12.7: Test CategoryController with `?account=professional` returns only professional transactions
  - [x] 12.8: Test shared props include `accountContexts` with correct data
  - [x] 12.9: Test ImportController `store` with `account_type=professional` creates professional account
  - [x] 12.10: Run `sail artisan test` -- all existing + new tests pass with zero regressions
  - [x] 12.11: Verify TypeScript compiles cleanly: `sail bun run build`

## Dev Notes

### Architecture Overview for This Story

This story adds account context switching to the Dashboard and category drill-down. It's the third and final story in Epic 3 (Spending Exploration) and introduces the account dimension alongside the existing month dimension for data filtering.

The core pattern mirrors month navigation: a query parameter (`?account=personal`) flows through the controller to BudgetQueryService, and an Inertia partial reload updates the view without a full page load. The account picker lives in the header (AppLayout) per the UX spec, using the already-installed shadcn/ui DropdownMenu.

```
AppLayout (MODIFIED -- account picker in header)
  │
  ├─ AccountPicker (NEW) -- shadcn/ui DropdownMenu in header
  │     ├─ Trigger: "Personal ▾" (default)
  │     └─ Items: Personal, Professional (from shared Inertia props)
  │
  └─ Dashboard (MODIFIED -- account filtering)
       ├─ MonthStrip (existing -- unchanged, but month change preserves account)
       ├─ SummaryWidget (existing -- unchanged, amounts reflect filtered data)
       ├─ CategoryCards (MODIFIED -- links include ?account= param)
       └─ Import Dialog (MODIFIED -- account type selector added)
```

**Data flow for account switching:**
```
User taps "Professional" in AccountPicker
  → onAccountChange("professional") callback fires
  → Dashboard calls router.get('/dashboard', { month: currentMonth, account: 'professional' }, {
      preserveState: true,
      preserveScroll: true,
      only: ['categoryTotals', 'incomeCents', 'expensesCents', 'currentMonth', 'currentAccount', 'availableMonths']
    })
  → DashboardController processes ?account=professional
    → BudgetQueryService::getMonthlyData(2026, 2, 'professional')
    → BudgetQueryService::getAvailableMonths('professional')
    → Returns filtered data as Inertia props
  → Inertia merges new props into existing page state
  → Dashboard re-renders: AccountPicker shows "Professional",
    SummaryWidget shows professional totals, CategoryCards show professional amounts
```

**Key design decisions:**

- **Account type, not account ID:** The filter is by account TYPE (`personal` / `professional`), not by individual bank account. "Personal" context includes ALL personal accounts (Banque Populaire Personal + Revolut Personal). This aligns with the AC: "available account contexts" -- context = type.

- **Query parameter, not shared state:** Account selection is a URL query parameter (`?account=personal`), NOT stored in session or cookie. This means:
  - AC #5 (persist across month changes) works because both `month` and `account` are passed together in each `router.get`
  - AC #6 (default to Personal on reopen) works because no `?account=` param = default `'personal'`

- **Account contexts via shared Inertia props:** The list of available contexts comes from `HandleInertiaRequests::share()`, not from a page-specific prop. This makes it available to all pages (Dashboard, Categories/Show) without each controller needing to query it.

- **Partial reload includes `availableMonths`:** Unlike month switching (where `availableMonths` stays constant), account switching changes which months have data. So `availableMonths` must be in the `only` list for account changes.

- **Import flow modification:** The import confirmation dialog gets an account type radio group (Personal/Professional), defaulting to Personal. This is the mechanism for creating professional accounts. No separate account management UI needed.

- **AccountPicker vs DropdownMenu directly in layout:** A dedicated AccountPicker component encapsulates the dropdown logic, making the AppLayout clean. If only one context exists, the component renders as a static label (no dropdown) to avoid an empty menu.

### BudgetQueryService Extensions

**Existing method -- no changes needed:**
```php
public function getMonthlyData(int $year, int $month, ?string $accountType = null): array
```
Already supports `$accountType` parameter. DashboardController just needs to pass it.

**`getAvailableMonths()` -- add account filtering:**
```php
public function getAvailableMonths(?string $accountType = null): array
{
    $query = Transaction::query()
        ->select(DB::raw("DISTINCT TO_CHAR(date, 'YYYY-MM') as month"));

    if ($accountType !== null) {
        $query->join('accounts', 'transactions.account_id', '=', 'accounts.id')
              ->where('accounts.type', $accountType);
    }

    return $query->orderBy('month')
        ->pluck('month')
        ->toArray();
}
```

**`getCategoryDetail()` -- add account filtering:**
```php
public function getCategoryDetail(int $categoryId, int $year, int $month, ?string $accountType = null): array
```
Add account join/filter to both the subcategories query and the transactions query, following the same pattern as `getMonthlyData()`.

### DashboardController Update

```php
public function index(Request $request): Response
{
    $monthParam = $request->query('month', now()->format('Y-m'));

    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)) {
        $monthParam = now()->format('Y-m');
    }

    $accountParam = $request->query('account', 'personal');
    if (!in_array($accountParam, ['personal', 'professional'], true)) {
        $accountParam = 'personal';
    }

    [$year, $month] = array_map('intval', explode('-', $monthParam));

    $data = $this->budgetQueryService->getMonthlyData($year, $month, $accountParam);

    return Inertia::render('dashboard', [
        'categoryTotals'  => $data['categoryTotals'],
        'incomeCents'     => $data['incomeCents'],
        'expensesCents'   => $data['expensesCents'],
        'currentMonth'    => $monthParam,
        'currentAccount'  => $accountParam,
        'availableMonths' => $this->budgetQueryService->getAvailableMonths($accountParam),
        'importPreview'   => session('importPreview'),
    ]);
}
```

**Changes:**
- Accept `?account=personal|professional` query param (default: `'personal'`)
- Validate to allowlist
- Pass to `getMonthlyData()` and `getAvailableMonths()`
- Return `currentAccount` as Inertia prop

### CategoryController Update

```php
public function show(Category $category, Request $request): Response
{
    $monthParam = $request->query('month', now()->format('Y-m'));

    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)) {
        $monthParam = now()->format('Y-m');
    }

    $accountParam = $request->query('account', 'personal');
    if (!in_array($accountParam, ['personal', 'professional'], true)) {
        $accountParam = 'personal';
    }

    [$year, $month] = array_map('intval', explode('-', $monthParam));

    $data = $this->budgetQueryService->getCategoryDetail($category->id, $year, $month, $accountParam);

    return Inertia::render('categories/show', [
        'category' => [
            'id'    => $category->id,
            'name'  => $category->name,
            'color' => $category->color,
            'slug'  => $category->slug,
        ],
        'subcategories'    => $data['subcategories'],
        'transactions'     => $data['transactions'],
        'totalAmountCents' => $data['totalAmountCents'],
        'transactionCount' => $data['transactionCount'],
        'currentMonth'     => $monthParam,
        'currentAccount'   => $accountParam,
    ]);
}
```

**Changes:** Same account validation pattern, forwarded to `getCategoryDetail()`, returned as prop.

### HandleInertiaRequests Shared Props

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'name' => config('app.name'),
        'auth' => [
            'user' => $request->user(),
        ],
        'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        'accountContexts' => $request->user()
            ? Account::query()
                ->select('type')
                ->distinct()
                ->orderBy('type')
                ->get()
                ->map(fn ($a) => [
                    'value' => $a->type,
                    'label' => ucfirst($a->type),
                ])
                ->values()
                ->toArray()
            : [],
    ];
}
```

**Change:** Added `accountContexts` shared prop. Returns distinct account types with labels. Only queries when authenticated. Import the `Account` model.

### AccountPicker Component

```tsx
// resources/js/components/AccountPicker.tsx
import { ChevronDownIcon, CheckIcon } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type AccountContext = {
    value: string;
    label: string;
};

type Props = {
    currentAccount: string;
    accountContexts: AccountContext[];
    onAccountChange: (account: string) => void;
};

export default function AccountPicker({ currentAccount, accountContexts, onAccountChange }: Props) {
    const currentLabel = accountContexts.find(c => c.value === currentAccount)?.label ?? 'Personal';

    // If only one context exists, render as static label
    if (accountContexts.length <= 1) {
        return (
            <span className="text-sm font-medium text-text-secondary">
                {currentLabel}
            </span>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="flex items-center gap-1 text-sm font-medium text-text-secondary hover:text-white transition-colors outline-none">
                {currentLabel}
                <ChevronDownIcon className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {accountContexts.map((context) => (
                    <DropdownMenuItem
                        key={context.value}
                        onClick={() => onAccountChange(context.value)}
                        className="flex items-center justify-between gap-4"
                    >
                        {context.label}
                        {context.value === currentAccount && (
                            <CheckIcon className="size-4 text-white" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
```

**Implementation notes:**
- Uses shadcn/ui DropdownMenu (already installed in the project)
- Shows a check icon next to the active context
- When only one context exists, renders as a static label (no dropdown menu)
- Trigger text matches UX spec: "Personal ▾" style with secondary text color

### AppLayout Update

```tsx
// resources/js/layouts/app/app-layout.tsx
import { usePage } from '@inertiajs/react';
import type { AppLayoutProps } from '@/types';
import AccountPicker from '@/components/AccountPicker';

type SharedProps = {
    accountContexts: { value: string; label: string }[];
};

export default function AppLayout({ children, currentAccount, onAccountChange }: AppLayoutProps) {
    const { accountContexts } = usePage<{ props: SharedProps }>().props as unknown as SharedProps;

    return (
        <div className="min-h-screen bg-background">
            <header className="px-4 sm:px-8 py-4">
                <div className="max-w-[480px] mx-auto flex items-center justify-between">
                    <span className="text-lg font-semibold text-white">
                        budget-app
                    </span>
                    {accountContexts?.length > 0 && currentAccount && onAccountChange && (
                        <AccountPicker
                            currentAccount={currentAccount}
                            accountContexts={accountContexts}
                            onAccountChange={onAccountChange}
                        />
                    )}
                </div>
            </header>

            <main className="px-4 sm:px-8">
                <div className="max-w-[480px] mx-auto">
                    {children}
                </div>
            </main>
        </div>
    );
}
```

**Changes:**
- Accept optional `currentAccount` and `onAccountChange` props
- Read `accountContexts` from shared Inertia props
- Render AccountPicker in header right side when account data is available
- Backward compatible: pages that don't pass account props won't render the picker

### Dashboard Integration

Key changes to `resources/js/pages/dashboard.tsx`:

**1. Destructure new prop:**
```tsx
export default function Dashboard({
    categoryTotals,
    incomeCents,
    expensesCents,
    currentMonth,
    currentAccount,
    availableMonths,
    importPreview,
}: DashboardProps) {
```

**2. Account change handler:**
```tsx
const handleAccountChange = useCallback((account: string) => {
    router.get('/dashboard', { month: currentMonth, account }, {
        preserveState: true,
        preserveScroll: true,
        only: ['categoryTotals', 'incomeCents', 'expensesCents', 'currentMonth', 'currentAccount', 'availableMonths'],
    });
}, [currentMonth]);
```

**3. Update month change handler to include account:**
```tsx
const handleMonthChange = useCallback((month: string) => {
    router.get('/dashboard', { month, account: currentAccount }, {
        preserveState: true,
        preserveScroll: true,
        only: ['categoryTotals', 'incomeCents', 'expensesCents', 'currentMonth'],
    });
}, [currentAccount]);
```

**4. Pass account props to AppLayout:**
```tsx
<AppLayout currentAccount={currentAccount} onAccountChange={handleAccountChange}>
```

**5. Pass account to CategoryCard:**
```tsx
<CategoryCard
    key={cat.id}
    id={cat.id}
    name={cat.name}
    color={cat.color}
    amountCents={cat.amountCents}
    transactionCount={cat.transactionCount}
    currentMonth={currentMonth}
    currentAccount={currentAccount}
/>
```

### CategoryCard Update

```tsx
type Props = {
    id: number;
    name: string;
    color: string;
    amountCents: number;
    transactionCount: number;
    currentMonth: string;
    currentAccount: string;
};

export default function CategoryCard({ id, name, color, amountCents, transactionCount, currentMonth, currentAccount }: Props) {
    return (
        <Link
            href={`/categories/${id}?month=${currentMonth}&account=${currentAccount}`}
            ...
        >
```

**Change:** Added `currentAccount` prop and included it in the drill-down Link href as `&account=` query parameter.

### Import Confirmation Dialog Update

Add an account type selector to the import confirmation dialog in `dashboard.tsx`:

```tsx
{/* Inside the Dialog, after the import preview info */}
<div className="flex justify-between text-sm items-center">
    <span className="text-text-secondary">Account</span>
    <div className="flex gap-2">
        <button
            type="button"
            onClick={() => setImportAccountType('personal')}
            className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                importAccountType === 'personal'
                    ? 'bg-surface-elevated text-white'
                    : 'text-text-secondary hover:text-white'
            }`}
        >
            Personal
        </button>
        <button
            type="button"
            onClick={() => setImportAccountType('professional')}
            className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                importAccountType === 'professional'
                    ? 'bg-surface-elevated text-white'
                    : 'text-text-secondary hover:text-white'
            }`}
        >
            Professional
        </button>
    </div>
</div>
```

Add state: `const [importAccountType, setImportAccountType] = useState<string>('personal');`

Update `handleConfirmImport` to include `account_type`:
```tsx
router.post('/imports', { csv_file: file, account_type: importAccountType }, { ... });
```

### ImportController Update

```php
public function store(Request $request): RedirectResponse
{
    $request->validate([
        'csv_file' => ['required', 'file', 'max:5120'],
        'account_type' => ['sometimes', 'in:personal,professional'],
    ]);

    $accountType = $request->input('account_type', 'personal');

    // ... parse CSV ...

    $account = Account::firstOrCreate(
        ['bank' => $parser->bankName(), 'type' => $accountType],
        ['name' => $parser->bankName().' '.ucfirst($accountType)],
    );

    // ... import ...
}
```

**Change:** Accept optional `account_type` field (defaults to `'personal'`), use it in `firstOrCreate`.

### TypeScript Type Updates

```typescript
// resources/js/types/budget.ts

export type AccountContext = {
    value: string;
    label: string;
};

export type DashboardProps = {
    categoryTotals: CategoryTotal[];
    incomeCents: number;
    expensesCents: number;
    currentMonth: string;
    currentAccount: string;         // NEW: 'personal' | 'professional'
    availableMonths: string[];
    importPreview?: ImportPreview | null;
};

export type CategoryShowProps = {
    category: {
        id: number;
        name: string;
        color: string;
        slug: string;
    };
    subcategories: SubcategoryTotal[];
    transactions: TransactionItem[];
    totalAmountCents: number;
    transactionCount: number;
    currentMonth: string;
    currentAccount: string;         // NEW: 'personal' | 'professional'
};
```

```typescript
// resources/js/types/index.ts -- update AppLayoutProps

export type AppLayoutProps = {
    children: ReactNode;
    currentAccount?: string;                              // NEW
    onAccountChange?: (account: string) => void;          // NEW
};
```

### Testing Strategy

**Feature tests in `tests/Feature/DashboardTest.php` (extend existing):**

```php
public function test_dashboard_defaults_to_personal_account(): void
{
    $user = User::factory()->create();
    $personalAccount = Account::factory()->personal()->create();
    $professionalAccount = Account::factory()->professional()->create();
    $category = Category::where('slug', 'food')->first();

    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $personalAccount->id,
        'date' => now(),
        'amount_cents' => -5000,
    ]);
    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $professionalAccount->id,
        'date' => now(),
        'amount_cents' => -9000,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->where('currentAccount', 'personal')
        ->has('categoryTotals', 1)
        ->where('categoryTotals.0.amountCents', -5000) // Only personal
    );
}

public function test_dashboard_professional_filter_excludes_personal(): void
{
    $user = User::factory()->create();
    $personalAccount = Account::factory()->personal()->create();
    $professionalAccount = Account::factory()->professional()->create();
    $category = Category::where('slug', 'food')->first();

    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $personalAccount->id,
        'date' => now(),
        'amount_cents' => -5000,
    ]);
    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $professionalAccount->id,
        'date' => now(),
        'amount_cents' => -9000,
    ]);

    $response = $this->actingAs($user)->get('/dashboard?account=professional');

    $response->assertInertia(fn ($page) => $page
        ->where('currentAccount', 'professional')
        ->has('categoryTotals', 1)
        ->where('categoryTotals.0.amountCents', -9000) // Only professional
    );
}

public function test_dashboard_invalid_account_defaults_to_personal(): void
{
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard?account=invalid');

    $response->assertInertia(fn ($page) => $page
        ->where('currentAccount', 'personal')
    );
}

public function test_dashboard_account_and_month_filters_combine(): void
{
    $user = User::factory()->create();
    $personalAccount = Account::factory()->personal()->create();
    $professionalAccount = Account::factory()->professional()->create();
    $category = Category::where('slug', 'food')->first();

    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $personalAccount->id,
        'date' => '2026-01-15',
        'amount_cents' => -5000,
    ]);
    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $professionalAccount->id,
        'date' => '2026-01-15',
        'amount_cents' => -9000,
    ]);
    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $personalAccount->id,
        'date' => '2026-02-15',
        'amount_cents' => -3000,
    ]);

    // January + Professional = only the 9000 transaction
    $response = $this->actingAs($user)->get('/dashboard?month=2026-01&account=professional');

    $response->assertInertia(fn ($page) => $page
        ->where('currentMonth', '2026-01')
        ->where('currentAccount', 'professional')
        ->has('categoryTotals', 1)
        ->where('categoryTotals.0.amountCents', -9000)
    );
}

public function test_available_months_scoped_to_account(): void
{
    $user = User::factory()->create();
    $personalAccount = Account::factory()->personal()->create();
    $professionalAccount = Account::factory()->professional()->create();
    $category = Category::where('slug', 'food')->first();

    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $personalAccount->id,
        'date' => '2026-01-15',
        'amount_cents' => -5000,
    ]);
    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $professionalAccount->id,
        'date' => '2026-02-15',
        'amount_cents' => -9000,
    ]);

    // Personal view should only see January
    $response = $this->actingAs($user)->get('/dashboard?account=personal');

    $response->assertInertia(fn ($page) => $page
        ->has('availableMonths', 1)
        ->where('availableMonths.0', '2026-01')
    );
}

public function test_category_show_filters_by_account(): void
{
    $user = User::factory()->create();
    $personalAccount = Account::factory()->personal()->create();
    $professionalAccount = Account::factory()->professional()->create();
    $category = Category::where('slug', 'food')->first();

    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $personalAccount->id,
        'date' => now(),
        'amount_cents' => -5000,
        'description' => 'Personal food',
    ]);
    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $professionalAccount->id,
        'date' => now(),
        'amount_cents' => -9000,
        'description' => 'Professional food',
    ]);

    $response = $this->actingAs($user)
        ->get("/categories/{$category->id}?account=personal");

    $response->assertInertia(fn ($page) => $page
        ->where('currentAccount', 'personal')
        ->has('transactions', 1)
        ->where('transactions.0.description', 'Personal food')
    );
}

public function test_shared_props_include_account_contexts(): void
{
    $user = User::factory()->create();
    Account::factory()->personal()->create();
    Account::factory()->professional()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->has('accountContexts', 2)
    );
}

public function test_import_with_professional_account_type(): void
{
    $user = User::factory()->create();
    Storage::fake('local');
    $csvContent = /* valid Banque Populaire CSV content */;

    $response = $this->actingAs($user)->post('/imports', [
        'csv_file' => UploadedFile::fake()->createWithContent('test.csv', $csvContent),
        'account_type' => 'professional',
    ]);

    $this->assertDatabaseHas('accounts', [
        'type' => 'professional',
    ]);
}
```

### Previous Story Intelligence

**From Story 3.2 (Month Navigation -- direct predecessor):**
- 213 tests, 890 assertions, zero regressions -- maintain this standard (expect ~223+ after this story)
- All commands via Sail: `sail artisan test`, `sail bun run build`
- `handleMonthChange` pattern: `router.get('/dashboard', { month }, { preserveState: true, preserveScroll: true, only: [...] })` -- reuse for account switching
- MonthStrip does NOT need modification -- it passes month changes to the parent callback, and the parent (Dashboard) handles query composition
- `formatMonthLabel()` used for empty-month message -- unchanged
- TypeScript types in `resources/js/types/budget.ts` -- extend there
- `DashboardController` pattern: param validation → BudgetQueryService → Inertia::render
- Partial reload with `only` list -- account switching follows same pattern but includes `availableMonths` in the `only` list (unlike month changes, account changes affect which months have data)

**From Story 3.1 (Category Drill-Down):**
- `CategoryController` pattern mirrors `DashboardController` -- same month validation, same BudgetQueryService injection
- `CategoryCard` link includes `?month=` query param -- extend to include `?account=`
- ColorHeader in Categories/Show page -- no changes needed
- `BudgetQueryService::getCategoryDetail()` needs account filtering added

**From Story 2.6 (Import UI):**
- Import confirmation dialog pattern: Dialog with preview info and Confirm/Cancel buttons
- `handleConfirmImport` uses `router.post('/imports', { csv_file: file })` -- extend to include `account_type`
- Import state management: `useState` for file, uploading state, dialog visibility -- add `importAccountType` state

### Git Intelligence

Recent commits (one per story, mono-commit pattern):
- `4a12bc2` Story 3.2: Month navigation with MonthStrip and Inertia partial reloads + review fixes
- `0a33353` Story 3.1: Category drill-down with color takeover + review fixes
- `20e8809` Story 2.6: Import UI with drag-and-drop, file picker, and confirmation dialog + review fixes

**Established patterns:**
- One commit per story
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Tests must pass with zero regressions before committing
- Feature tests use `RefreshDatabase` + seed required data
- Test file placement: `tests/Feature/` for HTTP and DB-dependent tests

### Architecture Compliance

| Requirement | Implementation |
|-------------|----------------|
| Account picker as DropdownMenu in header [arch#Frontend Architecture] | shadcn/ui DropdownMenu in AppLayout header, right side |
| Account-scoped filtering via BudgetQueryService [arch#Service Boundary] | `$accountType` parameter on all three query methods |
| Inertia partial reloads for account switching [arch#Frontend Architecture] | `router.get` with `preserveState: true, only: [...]` |
| Inertia props in camelCase [arch#Naming Patterns] | `currentAccount`, `accountContexts` |
| Services are stateless [arch#Service Boundary] | Account type passed as method parameter, not stored in service state |
| Amount storage as integer cents [arch#Data Architecture] | No change -- amounts flow through existing formatAmount pipeline |
| Default to Personal context [FR20] | `$request->query('account', 'personal')` -- no param = personal |
| Professional excluded from personal view [FR20] | Account type filter on BudgetQueryService queries isolates contexts |
| DropdownMenu themed to dark [ux#Design System] | shadcn/ui DropdownMenu inherits dark theme via CSS variables |

### Existing Components Modified

| Component | File | Change |
|-----------|------|--------|
| AppLayout | `resources/js/layouts/app/app-layout.tsx` | Add account picker in header |
| CategoryCard | `resources/js/components/CategoryCard.tsx` | Add `currentAccount` prop to Link href |
| SummaryWidget | `resources/js/components/SummaryWidget.tsx` | No changes needed |
| MonthStrip | `resources/js/components/MonthStrip.tsx` | No changes needed |
| ImportDropZone | `resources/js/components/ImportDropZone.tsx` | No changes needed |
| EmptyState | `resources/js/components/EmptyState.tsx` | No changes needed |

### What This Story Does NOT Include

- **Individual account management (create/edit/delete)** -- Accounts are auto-created via import. No separate account CRUD UI.
- **Account picker persistence across sessions** -- Defaults to "Personal" on every page load. No cookie/session storage.
- **"All accounts" view** -- The picker shows Personal or Professional. No combined view. (Could be added as enhancement.)
- **Account switching on the category drill-down page** -- The drill-down page receives the account param via URL but has no AccountPicker of its own. The user switches accounts on the Dashboard and drills down from there. (Wait -- the AppLayout IS shared, so the picker will appear on drill-down too. But clicking it from the drill-down should navigate back to Dashboard with the new account filter, not attempt a partial reload on the drill-down page.)
- **Transaction tappability / correction** -- Story 4.1.
- **Import deletion** -- Story 4.3.
- **Multiple parsers auto-detecting professional accounts** -- The parser doesn't know about account types. The user selects Personal/Professional during import confirmation.

### Edge Case: AccountPicker on Categories/Show Page

The AccountPicker lives in AppLayout, which wraps all pages including Categories/Show. When the user taps an account context from the drill-down page:

**Option A:** Navigate back to Dashboard with the new account. This is cleanest because the drill-down data would need a full reload anyway.

**Implementation:** In Categories/Show, the `onAccountChange` callback should use `router.get('/dashboard', { account })` instead of a partial reload on the current page. This gives a clean transition back to the Dashboard with the new account context.

```tsx
// In categories/show.tsx
const handleAccountChange = useCallback((account: string) => {
    router.get('/dashboard', { account });
}, []);
```

### Project Structure Notes

**New files to create:**
```
resources/js/
├── components/
│   └── AccountPicker.tsx                        (NEW)
```

**Modified files:**
```
app/Services/BudgetQueryService.php              (add account filtering to getAvailableMonths, getCategoryDetail)
app/Http/Controllers/DashboardController.php     (accept ?account= param, pass to service, return currentAccount prop)
app/Http/Controllers/CategoryController.php      (accept ?account= param, pass to service, return currentAccount prop)
app/Http/Controllers/ImportController.php        (accept account_type in store request)
app/Http/Middleware/HandleInertiaRequests.php     (add accountContexts to shared props)
resources/js/types/budget.ts                     (add AccountContext, currentAccount to DashboardProps and CategoryShowProps)
resources/js/types/index.ts                      (update AppLayoutProps with optional account props)
resources/js/layouts/app/app-layout.tsx           (render AccountPicker in header)
resources/js/pages/dashboard.tsx                 (account switching handler, pass account to month change and CategoryCard)
resources/js/pages/categories/show.tsx           (accept currentAccount, handle account change as navigation to Dashboard)
resources/js/components/CategoryCard.tsx          (add currentAccount prop to Link href)
tests/Feature/DashboardTest.php                  (add ~8 new test methods)
tests/Feature/CategoryControllerTest.php         (add account filtering test)
```

**Aligns with architecture directory structure:**
- Components in `resources/js/components/` (flat) [Source: architecture.md#Structure Patterns]
- Services in `app/Services/` (flat) [Source: architecture.md#Structure Patterns]
- Types in `resources/js/types/` [Source: architecture.md#Structure Patterns]
- shadcn/ui in `resources/js/components/ui/` [Source: architecture.md#Structure Patterns]

### References

- Epic 3 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 3.3]
- FR18-FR20 requirements: [Source: _bmad-output/planning-artifacts/epics.md#Requirements Inventory]
- Account picker UX: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Account Switching]
- DropdownMenu component: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Component Strategy]
- Account-scoped filtering: [Source: _bmad-output/planning-artifacts/architecture.md#Requirements to Structure Mapping]
- Inertia partial reload pattern: [Source: _bmad-output/planning-artifacts/architecture.md#Frontend Architecture]
- BudgetQueryService: [Source: app/Services/BudgetQueryService.php]
- DashboardController: [Source: app/Http/Controllers/DashboardController.php]
- CategoryController: [Source: app/Http/Controllers/CategoryController.php]
- ImportController: [Source: app/Http/Controllers/ImportController.php]
- HandleInertiaRequests: [Source: app/Http/Middleware/HandleInertiaRequests.php]
- Dashboard page: [Source: resources/js/pages/dashboard.tsx]
- AppLayout: [Source: resources/js/layouts/app/app-layout.tsx]
- CategoryCard: [Source: resources/js/components/CategoryCard.tsx]
- shadcn/ui DropdownMenu: [Source: resources/js/components/ui/dropdown-menu.tsx]
- Previous story 3.2: [Source: _bmad-output/implementation-artifacts/3-2-month-navigation.md]
- TypeScript types: [Source: resources/js/types/budget.ts]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

No debug issues encountered. All implementations followed the story Dev Notes specifications exactly.

### Completion Notes List

- **Task 1:** Added `?string $accountType` parameter to `getAvailableMonths()` and `getCategoryDetail()` in BudgetQueryService. Both methods join the accounts table and filter by `accounts.type` when provided. Verified `getMonthlyData()` already supported this parameter.
- **Task 2:** Added `accountContexts` shared Inertia prop via `HandleInertiaRequests::share()`. Queries distinct account types from the accounts table, only when user is authenticated.
- **Task 3:** Updated DashboardController to accept `?account=` query parameter (validated to 'personal'|'professional', defaults to 'personal'). Passes to both `getMonthlyData()` and `getAvailableMonths()`. Returns `currentAccount` as Inertia prop.
- **Task 4:** Updated CategoryController with identical account parameter validation pattern. Passes to `getCategoryDetail()` and returns `currentAccount` prop.
- **Task 5:** Created AccountPicker component using shadcn/ui DropdownMenu. Shows current account label with chevron; renders static label when only one context exists; check icon indicates active selection.
- **Task 6:** Integrated AccountPicker into AppLayout header (right side). Reads `accountContexts` from shared Inertia props. Only renders when account data is available and page passes account props.
- **Task 7:** Updated Dashboard page with `handleAccountChange` callback using Inertia partial reload. Updated `handleMonthChange` to preserve account context. Passes account props to AppLayout and CategoryCard.
- **Task 8:** Added `currentAccount` prop to CategoryCard; included `&account=` in drill-down Link href.
- **Task 9:** Updated Categories/Show page to accept `currentAccount` and pass it to AppLayout. Account switching from drill-down navigates back to Dashboard with new account context.
- **Task 10:** Added account type selector (Personal/Professional toggle buttons) to import confirmation dialog. Updated ImportController to accept and validate `account_type` parameter.
- **Task 11:** Added `AccountContext` type, `currentAccount` to DashboardProps and CategoryShowProps, optional account props to AppLayoutProps.
- **Task 12:** Added 13 new feature tests across DashboardTest (7), CategoryControllerTest (1), BudgetQueryServiceTest (3), ImportControllerTest (2). All 226 tests pass with 1020 assertions. TypeScript compiles cleanly.

### File List

**New files:**
- `resources/js/components/AccountPicker.tsx`

**Modified files:**
- `app/Services/BudgetQueryService.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/CategoryController.php`
- `app/Http/Controllers/ImportController.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/js/types/budget.ts`
- `resources/js/types/ui.ts`
- `resources/js/layouts/app/app-layout.tsx`
- `resources/js/pages/dashboard.tsx`
- `resources/js/pages/categories/show.tsx`
- `resources/js/components/CategoryCard.tsx`
- `tests/Feature/BudgetQueryServiceTest.php`
- `tests/Feature/DashboardTest.php`
- `tests/Feature/CategoryControllerTest.php`
- `tests/Feature/ImportControllerTest.php`

## Change Log

- 2026-02-20: Story 3.3 implementation complete. Added account context switching to Dashboard and category drill-down with Personal/Professional filtering via BudgetQueryService. Created AccountPicker component using shadcn/ui DropdownMenu. Updated import flow to support account type selection. 13 new tests added (226 total, 1020 assertions). TypeScript compiles cleanly.
- 2026-02-20: Code review fixes applied. (1) Qualified column references in BudgetQueryService::getCategoryDetail() transaction query to prevent potential ambiguity with account join. (2) Updated DashboardTest props smoke test to verify currentAccount. (3) Added early-return in AccountPicker to prevent redundant Inertia reload on same-account click. (4) Added test for invalid account_type on ImportController::store(). 227 tests, 1024 assertions. TypeScript compiles cleanly.
