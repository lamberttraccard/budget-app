# Story 4.1: Single-Tap Category Correction

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want to correct a miscategorized transaction with a single tap to select the right category,
so that my spending data is accurate without tedious editing.

## Acceptance Criteria

1. **Given** I am viewing transactions in the category drill-down, **When** I tap a miscategorized transaction row, **Then** a category picker appears showing all 14 categories with their color icons and names (FR21).

2. **Given** I am on mobile (< 640px), **When** the category picker opens, **Then** it renders as a bottom Sheet (shadcn/ui) sliding up from the bottom of the screen (FR22).

3. **Given** I am on desktop (>= 640px), **When** the category picker opens, **Then** it renders as a DropdownMenu (shadcn/ui) anchored to the transaction row (FR22).

4. **Given** the category picker is open, **When** I tap the correct category, **Then** the transaction's category is updated via `router.patch('/transactions/{id}', { category_id })`, the picker dismisses immediately, and amounts update in real time on both the drill-down and overview (FR21, FR22).

5. **Given** the current (incorrect) category is shown in the picker, **When** I look at the list, **Then** the current category is visually de-emphasized so the correct options stand out.

6. **Given** I made a correction but it was wrong, **When** I tap the same transaction again, **Then** I can re-correct with the same two-tap flow -- no undo mechanism needed.

## Tasks / Subtasks

- [x] Task 1: Create PATCH route for transaction category update (AC: 4)
  - [x] 1.1: Add `PATCH /transactions/{transaction}` route in `routes/web.php`, protected by `auth` middleware
  - [x] 1.2: Route name: `transactions.update`

- [x] Task 2: Create TransactionController with update method (AC: 4)
  - [x] 2.1: Create `app/Http/Controllers/TransactionController.php`
  - [x] 2.2: Implement `update(Request $request, Transaction $transaction)` method
  - [x] 2.3: Validate request: `category_id` required, must exist in categories table
  - [x] 2.4: Update `$transaction->category_id` and save
  - [x] 2.5: Return `back()` (Inertia will handle the redirect and prop refresh)

- [x] Task 3: Create CategoryPicker component with responsive rendering (AC: 1, 2, 3, 5)
  - [x] 3.1: Create `resources/js/components/CategoryPicker.tsx`
  - [x] 3.2: Props: `{ categories: CategoryOption[], currentCategoryId: number, transactionId: number, isOpen: boolean, onClose: () => void, onSelect: (transactionId: number, categoryId: number) => void, triggerRef?: React.RefObject<HTMLElement> }`
  - [x] 3.3: Define `CategoryOption` type: `{ id: number, name: string, color: string }`
  - [x] 3.4: Use existing `useIsMobile()` hook from `@/hooks/use-mobile` to detect mobile vs desktop
  - [x] 3.5: **Mobile (< 640px):** Render a bottom Sheet (shadcn/ui `Sheet` with `side="bottom"`) containing the scrollable category list
  - [x] 3.6: **Desktop (>= 640px):** Render a DropdownMenu (shadcn/ui `DropdownMenu`) anchored to the transaction row
  - [x] 3.7: Each category item shows: colored circle indicator (16px, category accent color), category name (14px, white text)
  - [x] 3.8: Current category is visually de-emphasized: reduced opacity (opacity-40) and shows a subtle "(current)" label in tertiary text
  - [x] 3.9: Non-current categories have full opacity and hover state (elevated surface background)
  - [x] 3.10: Sheet/dropdown dismisses immediately on category selection

- [x] Task 4: Make TransactionRow interactive (AC: 1, 6)
  - [x] 4.1: Add `id: number` and `categoryId: number` props to TransactionRow (already in TransactionItem type)
  - [x] 4.2: Add `onTap: (transactionId: number) => void` callback prop
  - [x] 4.3: Make the row a `<button>` element with `cursor-pointer` and hover state (`hover:bg-surface-elevated` transition)
  - [x] 4.4: On tap, call `onTap(id)` to notify parent
  - [x] 4.5: Preserve existing 44px minimum touch target height

- [x] Task 5: Update Categories/Show page for correction flow (AC: 1, 4, 5, 6)
  - [x] 5.1: Add `categories` prop to `CategoryShowProps` -- array of all top-level categories `{ id: number, name: string, color: string }[]` (for the picker)
  - [x] 5.2: Add state: `selectedTransactionId: number | null` (which transaction's picker is open)
  - [x] 5.3: Add state: `selectedTransactionCategoryId: number | null` (current category of selected transaction)
  - [x] 5.4: Create `handleTransactionTap(transactionId: number, categoryId: number)` -- sets selected state and opens picker
  - [x] 5.5: Create `handleCategorySelect(transactionId: number, categoryId: number)` callback:
    - Call `router.patch(\`/transactions/${transactionId}\`, { category_id: categoryId }, { preserveScroll: true, onSuccess: () => setSelectedTransactionId(null) })`
  - [x] 5.6: Pass `id`, `categoryId`, `onTap` props to each TransactionRow
  - [x] 5.7: Render CategoryPicker with selected transaction state, categories list, and selection handler
  - [x] 5.8: Inertia's automatic page prop refresh after PATCH ensures amounts update in the drill-down

- [x] Task 6: Update CategoryController to include all categories as prop (AC: 1)
  - [x] 6.1: In `CategoryController::show()`, query all top-level categories: `Category::whereNull('parent_id')->orderBy('name')->get(['id', 'name', 'color'])`
  - [x] 6.2: Pass as `allCategories` Inertia prop (array of `{ id, name, color }`)

- [x] Task 7: Update TypeScript types (AC: all)
  - [x] 7.1: Add `CategoryOption` type to `resources/js/types/budget.ts`: `{ id: number; name: string; color: string }`
  - [x] 7.2: Add `allCategories: CategoryOption[]` to `CategoryShowProps`
  - [x] 7.3: Update `TransactionRow` props type (add `onTap` callback, ensure `id` and `categoryId` are passed)

- [x] Task 8: Write Feature Tests (AC: 1-6)
  - [x] 8.1: Test `PATCH /transactions/{id}` with valid `category_id` updates the transaction's category
  - [x] 8.2: Test `PATCH /transactions/{id}` with invalid `category_id` returns validation error
  - [x] 8.3: Test `PATCH /transactions/{id}` requires authentication (unauthenticated returns 302 redirect)
  - [x] 8.4: Test `PATCH /transactions/{id}` with non-existent transaction returns 404
  - [x] 8.5: Test CategoryController show response includes `allCategories` prop with all 14 top-level categories
  - [x] 8.6: Test re-correction: PATCH same transaction twice with different category_id succeeds both times
  - [x] 8.7: Test PATCH returns redirect back (Inertia-compatible response)
  - [x] 8.8: Run `sail artisan test` -- all existing + new tests pass with zero regressions
  - [x] 8.9: Verify TypeScript compiles cleanly: `sail bun run build`

## Dev Notes

### Architecture Overview for This Story

This story introduces the category correction interaction -- the first write operation on transactions from the frontend. It's the first story in Epic 4 (Category Correction & AI Learning) and creates the foundation that Story 4.2 will build upon (event dispatch, merchant rules learning, toast feedback).

The core pattern is simple: tap a transaction in the drill-down, pick a category from a responsive picker (Sheet on mobile, DropdownMenu on desktop), and `router.patch` sends the update. Inertia handles the page prop refresh automatically.

```
Categories/Show.tsx (MODIFIED -- transactions now interactive)
  │
  ├─ TransactionRow (MODIFIED -- clickable, triggers picker)
  │     └─ onTap → sets selectedTransactionId state
  │
  └─ CategoryPicker (NEW -- responsive category selection)
        ├─ Mobile: Sheet (bottom, scrollable category list)
        └─ Desktop: DropdownMenu (anchored to transaction row)
              │
              └─ onSelect → router.patch('/transactions/{id}', { category_id })
                    → TransactionController::update()
                    → Transaction model updated
                    → Inertia redirects back → props refresh
                    → UI reflects new category
```

**Data flow for category correction:**
```
User taps TransactionRow in Categories/Show
  → handleTransactionTap(transactionId, currentCategoryId) fires
  → CategoryPicker opens (Sheet on mobile, DropdownMenu on desktop)
  → User taps correct category
  → handleCategorySelect(transactionId, newCategoryId) fires
  → router.patch('/transactions/{transactionId}', { category_id: newCategoryId }, {
      preserveScroll: true,
      onSuccess: () => closePickerState()
    })
  → Laravel: TransactionController::update()
    → Validates category_id exists
    → Transaction->update(['category_id' => newCategoryId])
    → return back()
  → Inertia automatically refreshes page props
  → CategoryController::show() re-fetches data from BudgetQueryService
  → Updated amounts, transaction lists reflect the correction
  → User sees transaction with new category, amounts updated
```

**Key design decisions:**

- **No CategoryCorrected event in this story:** The event dispatch and UpdateMerchantRule listener are Story 4.2. This story focuses solely on the UI correction flow and backend PATCH. This separation is intentional -- 4.1 gets the interaction right, 4.2 adds the learning pipeline.

- **No toast notification in this story:** The "Got it, I'll remember" toast is Story 4.2. No toast library needs to be installed for 4.1.

- **`router.patch` not `useForm`:** Per architecture spec, single-field updates use `router.patch` directly. Lighter weight, no form state management needed.

- **Inertia auto-refresh:** After `router.patch` returns a redirect back, Inertia automatically re-fetches the current page's props. The CategoryController::show() method re-queries BudgetQueryService, which returns updated amounts. No manual state management needed for UI updates.

- **All categories passed as prop:** Rather than a separate API call to fetch categories for the picker, all 14 top-level categories are passed as an `allCategories` Inertia prop from CategoryController. This avoids an extra HTTP request and keeps the pattern consistent (server data via Inertia props).

- **`useIsMobile()` hook (existing):** The project already has a `use-mobile.tsx` hook at `resources/js/hooks/use-mobile.tsx`. Use this instead of creating a new `useViewport` hook. It likely uses the 640px breakpoint matching the project's single breakpoint strategy.

- **Sheet for mobile, DropdownMenu for desktop:** Both shadcn/ui components are already installed in the project. The CategoryPicker wraps both and conditionally renders based on viewport.

- **Transaction becomes a button:** The TransactionRow currently renders as a `<div>`. To make it interactive and accessible, it should become a `<button>` element. This ensures keyboard accessibility and native tap behavior.

### TransactionController Implementation

```php
// app/Http/Controllers/TransactionController.php
<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
        ]);

        $transaction->update([
            'category_id' => $validated['category_id'],
        ]);

        return back();
    }
}
```

**Notes:**
- Minimal controller -- just validates and updates
- Returns `back()` for Inertia-compatible redirect
- Story 4.2 will add: `CategoryCorrected` event dispatch after the update
- No authorization check beyond `auth` middleware -- single-user app

### Route Addition

```php
// routes/web.php -- add within auth middleware group
Route::patch('/transactions/{transaction}', [TransactionController::class, 'update'])
    ->name('transactions.update');
```

### CategoryController Update

```php
// In CategoryController::show() -- add allCategories prop
return Inertia::render('categories/show', [
    'category' => [...],
    'subcategories' => $data['subcategories'],
    'transactions' => $data['transactions'],
    'totalAmountCents' => $data['totalAmountCents'],
    'transactionCount' => $data['transactionCount'],
    'currentMonth' => $monthParam,
    'currentAccount' => $accountParam,
    'allCategories' => Category::whereNull('parent_id')
        ->orderBy('name')
        ->get(['id', 'name', 'color'])
        ->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'color' => $c->color,
        ])
        ->toArray(),
]);
```

### TransactionRow Update

```tsx
// resources/js/components/TransactionRow.tsx
type Props = {
    id: number;
    date: string;
    description: string;
    amountCents: number;
    categoryId: number;
    onTap?: (transactionId: number, categoryId: number) => void;
};

export default function TransactionRow({ id, date, description, amountCents, categoryId, onTap }: Props) {
    return (
        <button
            type="button"
            onClick={() => onTap?.(id, categoryId)}
            className="flex w-full items-center gap-3 min-h-[44px] py-2.5 px-1 text-left
                       hover:bg-surface-elevated rounded-lg transition-colors cursor-pointer"
        >
            <span className="text-xs text-text-tertiary w-12 shrink-0">
                {formatDate(date)}
            </span>
            <span className="text-sm text-white truncate flex-1">
                {description}
            </span>
            <span className="text-sm font-semibold text-white tabular-nums shrink-0">
                {formatAbsAmount(amountCents)}
            </span>
        </button>
    );
}
```

**Changes from current:**
- Added `id`, `categoryId`, and `onTap` props
- Changed from `<div>` to `<button>` for interactivity
- Added `hover:bg-surface-elevated` for visual feedback
- Added `cursor-pointer` for clarity
- Added `rounded-lg transition-colors` for smooth interaction
- Preserved 44px min touch target

### CategoryPicker Component

```tsx
// resources/js/components/CategoryPicker.tsx
import { useIsMobile } from '@/hooks/use-mobile';
import {
    Sheet, SheetContent, SheetHeader, SheetTitle,
} from '@/components/ui/sheet';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type CategoryOption = {
    id: number;
    name: string;
    color: string;
};

type Props = {
    categories: CategoryOption[];
    currentCategoryId: number;
    isOpen: boolean;
    onClose: () => void;
    onSelect: (categoryId: number) => void;
};

export default function CategoryPicker({
    categories, currentCategoryId, isOpen, onClose, onSelect,
}: Props) {
    const isMobile = useIsMobile();

    const categoryList = categories.map((cat) => {
        const isCurrent = cat.id === currentCategoryId;
        return (
            <button
                key={cat.id}
                type="button"
                onClick={() => {
                    if (!isCurrent) {
                        onSelect(cat.id);
                    }
                }}
                className={`flex items-center gap-3 w-full px-4 py-3 min-h-[44px]
                    rounded-lg transition-colors text-left
                    ${isCurrent
                        ? 'opacity-40 cursor-default'
                        : 'hover:bg-surface-elevated cursor-pointer'
                    }`}
                disabled={isCurrent}
            >
                <span
                    className="size-4 rounded-full shrink-0"
                    style={{ backgroundColor: cat.color }}
                />
                <span className="text-sm text-white flex-1">{cat.name}</span>
                {isCurrent && (
                    <span className="text-xs text-text-tertiary">(current)</span>
                )}
            </button>
        );
    });

    if (isMobile) {
        return (
            <Sheet open={isOpen} onOpenChange={(open) => !open && onClose()}>
                <SheetContent side="bottom" className="bg-surface rounded-t-2xl">
                    <SheetHeader>
                        <SheetTitle className="text-white text-base">
                            Choose Category
                        </SheetTitle>
                    </SheetHeader>
                    <div className="max-h-[60vh] overflow-y-auto py-2">
                        {categoryList}
                    </div>
                </SheetContent>
            </Sheet>
        );
    }

    // Desktop: DropdownMenu
    // Note: DropdownMenu is controlled via open state
    return (
        <DropdownMenu open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DropdownMenuTrigger asChild>
                <span />
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-64 max-h-[400px] overflow-y-auto"
            >
                {categories.map((cat) => {
                    const isCurrent = cat.id === currentCategoryId;
                    return (
                        <DropdownMenuItem
                            key={cat.id}
                            onClick={() => !isCurrent && onSelect(cat.id)}
                            className={`flex items-center gap-3 min-h-[36px]
                                ${isCurrent ? 'opacity-40' : ''}`}
                            disabled={isCurrent}
                        >
                            <span
                                className="size-3.5 rounded-full shrink-0"
                                style={{ backgroundColor: cat.color }}
                            />
                            <span className="flex-1">{cat.name}</span>
                            {isCurrent && (
                                <span className="text-xs text-text-tertiary">(current)</span>
                            )}
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
```

**Implementation notes:**
- Uses existing `useIsMobile()` hook to determine rendering mode
- Mobile: shadcn/ui Sheet with `side="bottom"` for bottom sheet UX
- Desktop: shadcn/ui DropdownMenu for inline dropdown
- Current category de-emphasized with `opacity-40` and "(current)" label
- 44px min touch targets on mobile
- Scrollable content for both modes
- Note: The DropdownMenu approach on desktop may need refinement for anchoring to the transaction row. An alternative is to use a Popover component positioned near the clicked row. The dev agent should evaluate the best approach.

### Categories/Show.tsx Integration

Key changes to `resources/js/pages/categories/show.tsx`:

**1. Add new props and state:**
```tsx
export default function Show({
    category, subcategories, transactions,
    totalAmountCents, transactionCount,
    currentMonth, currentAccount,
    allCategories,          // NEW
}: CategoryShowProps) {
    const [selectedTransactionId, setSelectedTransactionId] = useState<number | null>(null);
    const [selectedTransactionCategoryId, setSelectedTransactionCategoryId] = useState<number | null>(null);
```

**2. Transaction tap handler:**
```tsx
const handleTransactionTap = useCallback((transactionId: number, categoryId: number) => {
    setSelectedTransactionId(transactionId);
    setSelectedTransactionCategoryId(categoryId);
}, []);
```

**3. Category select handler:**
```tsx
const handleCategorySelect = useCallback((categoryId: number) => {
    if (selectedTransactionId === null) return;
    router.patch(`/transactions/${selectedTransactionId}`, {
        category_id: categoryId,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            setSelectedTransactionId(null);
            setSelectedTransactionCategoryId(null);
        },
    });
}, [selectedTransactionId]);
```

**4. Pass props to TransactionRow:**
```tsx
{transactions.map((t) => (
    <TransactionRow
        key={t.id}
        id={t.id}
        date={t.date}
        description={t.description}
        amountCents={t.amountCents}
        categoryId={t.categoryId}
        onTap={handleTransactionTap}
    />
))}
```

**5. Render CategoryPicker:**
```tsx
<CategoryPicker
    categories={allCategories}
    currentCategoryId={selectedTransactionCategoryId ?? 0}
    isOpen={selectedTransactionId !== null}
    onClose={() => {
        setSelectedTransactionId(null);
        setSelectedTransactionCategoryId(null);
    }}
    onSelect={handleCategorySelect}
/>
```

### TypeScript Type Updates

```typescript
// resources/js/types/budget.ts -- additions

export type CategoryOption = {
    id: number;
    name: string;
    color: string;
};

// Update CategoryShowProps
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
    currentAccount: string;
    allCategories: CategoryOption[];     // NEW
};
```

**Note:** `TransactionItem` already includes `id: number` and `categoryId: number`. The Categories/Show page currently passes these from BudgetQueryService data but TransactionRow doesn't use them. This story bridges that gap.

### Testing Strategy

**Feature tests in `tests/Feature/TransactionControllerTest.php` (NEW):**

```php
class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_can_update_transaction_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $oldCategory = Category::where('slug', 'food')->first();
        $newCategory = Category::where('slug', 'restaurants')->first();

        $transaction = Transaction::factory()->create([
            'category_id' => $oldCategory->id,
            'account_id' => $account->id,
            'date' => now(),
            'amount_cents' => -5000,
        ]);

        $response = $this->actingAs($user)
            ->patch("/transactions/{$transaction->id}", [
                'category_id' => $newCategory->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'category_id' => $newCategory->id,
        ]);
    }

    public function test_update_requires_valid_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $category = Category::where('slug', 'food')->first();

        $transaction = Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
        ]);

        $response = $this->actingAs($user)
            ->patch("/transactions/{$transaction->id}", [
                'category_id' => 99999,
            ]);

        $response->assertSessionHasErrors('category_id');
    }

    public function test_update_requires_authentication(): void
    {
        $account = Account::factory()->personal()->create();
        $category = Category::where('slug', 'food')->first();

        $transaction = Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
        ]);

        $response = $this->patch("/transactions/{$transaction->id}", [
            'category_id' => $category->id,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_update_nonexistent_transaction_returns_404(): void
    {
        $user = User::factory()->create();
        $category = Category::where('slug', 'food')->first();

        $response = $this->actingAs($user)
            ->patch('/transactions/99999', [
                'category_id' => $category->id,
            ]);

        $response->assertNotFound();
    }

    public function test_can_re_correct_transaction_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $food = Category::where('slug', 'food')->first();
        $restaurants = Category::where('slug', 'restaurants')->first();
        $transport = Category::where('slug', 'transport')->first();

        $transaction = Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
        ]);

        // First correction
        $this->actingAs($user)
            ->patch("/transactions/{$transaction->id}", ['category_id' => $restaurants->id])
            ->assertRedirect();

        // Second correction (re-correct)
        $this->actingAs($user)
            ->patch("/transactions/{$transaction->id}", ['category_id' => $transport->id])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'category_id' => $transport->id,
        ]);
    }

    public function test_category_id_is_required(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $category = Category::where('slug', 'food')->first();

        $transaction = Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
        ]);

        $response = $this->actingAs($user)
            ->patch("/transactions/{$transaction->id}", []);

        $response->assertSessionHasErrors('category_id');
    }
}
```

**Extend `tests/Feature/CategoryControllerTest.php`:**

```php
public function test_show_includes_all_categories_for_picker(): void
{
    $user = User::factory()->create();
    $category = Category::where('slug', 'food')->first();
    $account = Account::factory()->personal()->create();

    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $account->id,
        'date' => now(),
        'amount_cents' => -5000,
    ]);

    $response = $this->actingAs($user)
        ->get("/categories/{$category->id}");

    $response->assertInertia(fn ($page) => $page
        ->has('allCategories', 14)  // All 14 seeded top-level categories
        ->has('allCategories.0', fn ($cat) => $cat
            ->has('id')
            ->has('name')
            ->has('color')
        )
    );
}
```

### Previous Story Intelligence

**From Story 3.3 (Account Management -- direct predecessor):**
- 227 tests, 1024 assertions, zero regressions -- maintain this standard
- All commands via Sail: `sail artisan test`, `sail bun run build`
- `router.get` with `preserveState` and `only` for partial reloads -- this story uses `router.patch` with `preserveScroll` instead (different verb, different options)
- CategoryController pattern: accepts query params, calls BudgetQueryService, returns Inertia props -- extend with `allCategories` prop
- TypeScript types in `resources/js/types/budget.ts` -- extend there
- `TransactionItem` type already includes `id` and `categoryId` fields
- Account factories: `Account::factory()->personal()`, `Account::factory()->professional()` -- reuse in tests

**From Story 3.1 (Category Drill-Down):**
- `Categories/Show.tsx` renders TransactionRows inside subcategory groups
- TransactionRow is currently a display-only `<div>` -- needs conversion to interactive `<button>`
- `activeSubcategoryId` state manages which subcategory's transactions are visible -- correction flow overlays on top of this
- ColorHeader and SubcategoryRow components -- no changes needed

**From Story 2.6 (Import UI):**
- Dialog component already in use -- pattern reference for future import deletion (Story 4.3)
- `router.post` pattern with `onSuccess` callback -- similar pattern for `router.patch`

### Git Intelligence

Recent commits (one per story, mono-commit pattern):
- `b78665e` Story 3.3: Account management and switching with Personal/Professional filtering + review fixes
- `4a12bc2` Story 3.2: Month navigation with MonthStrip and Inertia partial reloads + review fixes
- `0a33353` Story 3.1: Category drill-down with color takeover + review fixes

**Established patterns:**
- One commit per story with descriptive message
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Tests must pass with zero regressions before committing
- Feature tests use `RefreshDatabase` trait + seed categories
- Test file naming: `{Subject}Test.php` in `tests/Feature/`

### Architecture Compliance

| Requirement | Implementation |
|-------------|----------------|
| Category correction via `router.patch` [arch#Frontend Architecture] | `router.patch('/transactions/{id}', { category_id })` |
| Sheet on mobile, DropdownMenu on desktop [ux#Component Strategy] | CategoryPicker wraps both, uses `useIsMobile()` hook |
| No confirmation dialog for corrections [ux#Feedback Patterns] | Direct update on category selection, no "are you sure?" |
| 44px minimum touch targets [ux#Accessibility] | TransactionRow maintains `min-h-[44px]`, Sheet items have `py-3 min-h-[44px]` |
| Inertia props in camelCase [arch#Naming Patterns] | `allCategories`, `categoryId`, `transactionId` |
| Services are stateless [arch#Service Boundary] | No service changes needed -- controller directly updates model |
| Controllers return Inertia responses [arch#Web Boundary] | TransactionController returns `back()` -- Inertia handles redirect |
| 4 shadcn/ui components [ux#Design System] | Uses existing Sheet and DropdownMenu -- no new shadcn/ui installs |
| Single breakpoint 640px [ux#Responsive] | `useIsMobile()` hook uses the same breakpoint |

### Library & Framework Requirements

| Library | Version | Usage in This Story |
|---------|---------|-------------------|
| @radix-ui/react-dialog | Already installed | Used by Sheet component (bottom sheet) |
| @radix-ui/react-dropdown-menu | Already installed | Used by DropdownMenu component |
| @inertiajs/react | Already installed | `router.patch` for transaction updates |

**No new packages needed for this story.**

### File Structure Requirements

**New files to create:**
```
app/Http/Controllers/TransactionController.php      (NEW)
resources/js/components/CategoryPicker.tsx           (NEW)
tests/Feature/TransactionControllerTest.php          (NEW)
```

**Modified files:**
```
routes/web.php                                       (add PATCH route)
app/Http/Controllers/CategoryController.php          (add allCategories prop)
resources/js/components/TransactionRow.tsx            (make interactive)
resources/js/pages/categories/show.tsx               (correction flow integration)
resources/js/types/budget.ts                         (add CategoryOption, update CategoryShowProps)
tests/Feature/CategoryControllerTest.php             (add allCategories test)
```

### What This Story Does NOT Include

- **CategoryCorrected event dispatch** -- Story 4.2. TransactionController just updates the model.
- **UpdateMerchantRule listener** -- Story 4.2. No merchant learning pipeline.
- **Toast notification ("Got it, I'll remember")** -- Story 4.2. No toast library install.
- **Import deletion** -- Story 4.3. Separate story for destructive import removal.
- **Subcategory correction** -- If a transaction has a subcategory, the correction assigns a new top-level category. Subcategory reassignment is not in scope.
- **Batch corrections** -- No multi-select. One transaction at a time.
- **Offline support** -- Requires server for `router.patch`.
- **Animation on category change** -- No transition animation when a transaction's category updates. The Inertia page refresh handles the visual update.

### Edge Cases to Handle

1. **Transaction disappears from current drill-down after correction:** When a transaction is moved to a different category, the Inertia page refresh will remove it from the current category's transaction list. This is correct behavior -- the transaction is no longer in this category.

2. **Empty category after all corrections:** If all transactions in a category are corrected away, the drill-down should show an empty state. BudgetQueryService already handles this (returns empty transactions array).

3. **Correcting to same category:** The CategoryPicker de-emphasizes the current category with `opacity-40` and disables the button. Clicking it does nothing.

4. **Rapid corrections:** If the user corrects one transaction and immediately taps another before the first PATCH completes, Inertia's request handling should queue the requests. The `onSuccess` callback closes the picker only for the completed request. However, to avoid race conditions, consider disabling the transaction list while a PATCH is in flight (use `router.patch`'s `processing` state or a local loading state).

5. **Desktop DropdownMenu positioning:** The DropdownMenu needs to be visually associated with the clicked transaction row. Since DropdownMenu uses a trigger element, the implementation may need to use a Popover instead, or position the trigger dynamically. The dev agent should evaluate the best approach and may use `DropdownMenu` with the trigger being the TransactionRow itself, or switch to a Popover/Dialog pattern if positioning proves difficult.

### Project Structure Notes

- Components in `resources/js/components/` (flat) [Source: architecture.md#Structure Patterns]
- Controllers in `app/Http/Controllers/` [Source: architecture.md#Structure Patterns]
- Feature tests in `tests/Feature/` [Source: architecture.md#Test Organization]
- Types in `resources/js/types/budget.ts` [Source: architecture.md#Structure Patterns]

### References

- Epic 4 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 4.1]
- FR21-FR23 requirements: [Source: _bmad-output/planning-artifacts/epics.md#Requirements Inventory]
- Category correction UX: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Journey 4: Category Correction]
- Correction action pattern: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Action Patterns]
- Sheet/DropdownMenu component strategy: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Component Strategy]
- `router.patch` for corrections: [Source: _bmad-output/planning-artifacts/architecture.md#Frontend Architecture]
- No confirmation for corrections: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Feedback Patterns]
- TransactionController placement: [Source: _bmad-output/planning-artifacts/architecture.md#Project Structure]
- CategoryCorrected event (Story 4.2): [Source: _bmad-output/planning-artifacts/architecture.md#Communication Patterns]
- BudgetQueryService: [Source: app/Services/BudgetQueryService.php]
- CategoryController: [Source: app/Http/Controllers/CategoryController.php]
- TransactionRow: [Source: resources/js/components/TransactionRow.tsx]
- Categories/Show: [Source: resources/js/pages/categories/show.tsx]
- TransactionItem type: [Source: resources/js/types/budget.ts]
- Sheet component: [Source: resources/js/components/ui/sheet.tsx]
- DropdownMenu component: [Source: resources/js/components/ui/dropdown-menu.tsx]
- useIsMobile hook: [Source: resources/js/hooks/use-mobile.tsx]
- Previous story 3.3: [Source: _bmad-output/implementation-artifacts/3-3-account-management-and-switching.md]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

None - clean implementation with no blockers.

### Completion Notes List

- **Task 1-2 (Backend):** Created `TransactionController` with PATCH route for category updates. Validates `category_id` exists, updates transaction, returns `back()` for Inertia redirect.
- **Task 3 (CategoryPicker):** Responsive component using `useIsMobile()` hook — Sheet on mobile (bottom slide-up), DropdownMenu on desktop. Current category de-emphasized with opacity-40 and "(current)" label. Extracted reusable `CategoryItem` subcomponent.
- **Task 4 (TransactionRow):** Converted from `<div>` to `<button>` for accessibility and tap handling. Added `id`, `categoryId`, `onTap` props. Preserved 44px min touch target.
- **Task 5 (Categories/Show):** Integrated correction flow with state management (`selectedTransactionId`, `selectedTransactionCategoryId`). `handleTransactionTap` opens picker, `handleCategorySelect` fires `router.patch` with `preserveScroll`. CategoryPicker rendered at page level.
- **Task 6 (CategoryController):** Added `allCategories` Inertia prop — queries all top-level categories ordered by name.
- **Task 7 (Types):** Added `CategoryOption` type, extended `CategoryShowProps` with `allCategories`.
- **Task 8 (Tests):** 8 new feature tests covering: valid update, invalid category, auth required, 404, allCategories prop, re-correction, redirect back, required field. All 235 tests pass (1055 assertions), zero regressions. TypeScript builds cleanly.

### Change Log

- 2026-02-20: Story 4.1 implementation — single-tap category correction with responsive CategoryPicker, TransactionController PATCH endpoint, and 8 feature tests.
- 2026-02-20: Code review fixes — [H1] Fixed desktop DropdownMenu anchoring to transaction row via triggerRef positioning, [H2] Corrected useIsMobile breakpoint from 768px to 640px per UX spec, [M1] Added isPatching guard to prevent concurrent corrections, [M2] Added sprint-status.yaml and use-mobile.tsx to File List, [M3] Moved allCategories test to CategoryControllerTest.php, [M4] Fixed React.RefObject import in CategoryPicker.

### File List

**New files:**
- `app/Http/Controllers/TransactionController.php`
- `resources/js/components/CategoryPicker.tsx`
- `tests/Feature/TransactionControllerTest.php`

**Modified files:**
- `routes/web.php` (added PATCH /transactions/{transaction} route)
- `app/Http/Controllers/CategoryController.php` (added allCategories prop to show())
- `resources/js/components/TransactionRow.tsx` (converted div to button, added id/categoryId/onTap props)
- `resources/js/pages/categories/show.tsx` (integrated correction flow with state and CategoryPicker)
- `resources/js/types/budget.ts` (added CategoryOption type, updated CategoryShowProps)
- `resources/js/hooks/use-mobile.tsx` (corrected breakpoint from 768px to 640px per UX spec)
- `_bmad-output/implementation-artifacts/sprint-status.yaml` (updated story status to review, epic-4 to in-progress)
- `tests/Feature/CategoryControllerTest.php` (added allCategories picker test — moved from TransactionControllerTest)
