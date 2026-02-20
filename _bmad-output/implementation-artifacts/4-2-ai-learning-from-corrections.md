# Story 4.2: AI Learning from Corrections

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want the system to remember my corrections and apply them automatically to future imports,
so that the AI gets smarter over time and I do less manual work.

## Acceptance Criteria

1. **Given** I correct a transaction's category, **When** the correction is saved, **Then** a `CategoryCorrected` event fires, and the `UpdateMerchantRule` listener persists a `merchant_pattern` -> `category_id` mapping in the `merchant_rules` table (FR9, FR23).

2. **Given** a correction is saved, **When** the Toast component renders, **Then** a subtle message appears at the bottom: "Got it, I'll remember" -- auto-dismisses after 2 seconds.

3. **Given** a merchant rule exists for "CARREFOUR CITY", **When** a future import contains a transaction with that merchant description, **Then** `MerchantMatchingService` instantly assigns the correct category with no AI/Gemini call needed (FR11).

4. **Given** multiple corrections have been made over time, **When** correction data is queried, **Then** all merchant rules persist across application restarts and updates (NFR13).

5. **Given** the `merchant_rules` table has learned patterns, **When** a new import is processed, **Then** known merchants are categorized instantly, and only unknown merchants go through the Gemini queue (FR9, FR11).

## Tasks / Subtasks

- [x] Task 1: Create `CategoryCorrected` event (AC: 1)
  - [x] 1.1: Create `app/Events/CategoryCorrected.php` following existing event patterns (`ImportCompleted`, `TransactionCategorized`)
  - [x] 1.2: Constructor takes: `public Transaction $transaction`, `public int $oldCategoryId`, `public int $newCategoryId`
  - [x] 1.3: Use traits: `Dispatchable`, `SerializesModels`

- [x] Task 2: Create `UpdateMerchantRule` listener (AC: 1, 3, 4, 5)
  - [x] 2.1: Create `app/Listeners/UpdateMerchantRule.php` following existing listener pattern (`CategorizeImportedTransactions`)
  - [x] 2.2: `handle(CategoryCorrected $event)` method extracts `$event->transaction->description` as `merchant_pattern` and `$event->newCategoryId` as `category_id`
  - [x] 2.3: Use `MerchantRule::updateOrCreate(['merchant_pattern' => $merchantPattern], ['category_id' => $categoryId])` to handle both new rules and re-corrections (unique constraint on `merchant_pattern`)
  - [x] 2.4: Listener runs synchronously (no `ShouldQueue`) -- rule creation must complete before the response returns to the frontend

- [x] Task 3: Modify `TransactionController::update()` to dispatch event (AC: 1)
  - [x] 3.1: Capture `$oldCategoryId = $transaction->category_id` before the update
  - [x] 3.2: After `$transaction->update(...)`, dispatch: `CategoryCorrected::dispatch($transaction->fresh(), $oldCategoryId, $validated['category_id'])`
  - [x] 3.3: Only dispatch if `$oldCategoryId !== (int) $validated['category_id']` (skip event if user selects same category)

- [x] Task 4: Register event-listener binding (AC: 1)
  - [x] 4.1: In `AppServiceProvider::boot()`, add: `Event::listen(CategoryCorrected::class, UpdateMerchantRule::class);`
  - [x] 4.2: Add use statements for `CategoryCorrected` and `UpdateMerchantRule`

- [x] Task 5: Install sonner and create Toaster setup (AC: 2)
  - [x] 5.1: Install sonner: `sail bun add sonner`
  - [x] 5.2: Add `<Toaster />` component in app root layout (`resources/js/layouts/app/app-layout.tsx`)
  - [x] 5.3: Configure Toaster: `<Toaster theme="dark" position="bottom-center" duration={2000} />` -- dark theme to match the app, bottom-center per UX spec, 2s auto-dismiss
  - [x] 5.4: No close button needed (per UX spec: auto-dismiss only)

- [x] Task 6: Add toast to correction flow in Categories/Show (AC: 2)
  - [x] 6.1: Import `{ toast }` from `'sonner'` in `resources/js/pages/categories/show.tsx`
  - [x] 6.2: In `handleCategorySelect` `onSuccess` callback, add: `toast("Got it, I'll remember")`
  - [x] 6.3: Toast fires after the CategoryPicker closes and amounts update -- the correction's impact is visible alongside the toast

- [x] Task 7: Write Feature Tests (AC: 1-5)
  - [x] 7.1: Test `CategoryCorrected` event dispatches when transaction category is updated via PATCH
  - [x] 7.2: Test `CategoryCorrected` event does NOT dispatch when category_id is unchanged (same category selected)
  - [x] 7.3: Test `UpdateMerchantRule` listener creates a new `MerchantRule` record with correct `merchant_pattern` and `category_id`
  - [x] 7.4: Test re-correction updates existing `MerchantRule` (same merchant_pattern, new category_id) via `updateOrCreate`
  - [x] 7.5: Test `MerchantMatchingService::match()` returns correct category for a description that has a merchant rule
  - [x] 7.6: Test integration: correct a transaction -> rule created -> `MerchantMatchingService` now matches that description
  - [x] 7.7: Test merchant rules persist (verify database record exists after correction)
  - [x] 7.8: Run `sail artisan test` -- all existing + new tests pass with zero regressions
  - [x] 7.9: Verify TypeScript compiles cleanly: `sail bun run build`

## Dev Notes

### Architecture Overview for This Story

This story adds the AI learning pipeline to category corrections. Story 4.1 built the correction UI interaction (tap transaction -> pick category -> PATCH). This story closes the learning loop: each correction fires an event, a listener persists the merchant-to-category mapping, and future imports use those mappings for instant categorization.

The architecture follows the event-driven pattern established in the codebase (`ImportCompleted` -> `CategorizeImportedTransactions`). The new `CategoryCorrected` event fires synchronously, and the `UpdateMerchantRule` listener persists the rule before the HTTP response returns.

```
Categories/Show.tsx (MODIFIED -- toast added to onSuccess)
  │
  └─ handleCategorySelect()
        │
        └─ router.patch('/transactions/{id}', { category_id })
              │
              └─ TransactionController::update() (MODIFIED -- dispatches event)
                    │
                    ├─ Validates category_id
                    ├─ Captures $oldCategoryId
                    ├─ Updates transaction
                    └─ CategoryCorrected::dispatch(transaction, oldId, newId)
                          │
                          └─ UpdateMerchantRule::handle() (NEW)
                                │
                                └─ MerchantRule::updateOrCreate(
                                      ['merchant_pattern' => description],
                                      ['category_id' => newCategoryId]
                                   )
```

**Data flow for the learning pipeline:**

```
User taps transaction in drill-down (Story 4.1 flow)
  → CategoryPicker opens → user selects new category
  → router.patch('/transactions/{id}', { category_id: X })
  → TransactionController::update()
    → $oldCategoryId = $transaction->category_id
    → $transaction->update(['category_id' => newCategoryId])
    → CategoryCorrected::dispatch($transaction->fresh(), $oldCategoryId, $newCategoryId)
      → UpdateMerchantRule::handle()
        → MerchantRule::updateOrCreate(
            ['merchant_pattern' => $transaction->description],
            ['category_id' => $newCategoryId]
          )
  → return back()
  → Inertia refreshes page props
  → onSuccess callback fires:
    → Picker closes
    → toast("Got it, I'll remember") appears (auto-dismiss 2s)
    → UI reflects updated amounts

Future import with same merchant:
  → CategorizationService::categorize()
    → MerchantMatchingService::matchBatch() finds the rule
    → Transaction instantly categorized (no Gemini AI call needed)
```

**Key design decisions:**

- **Synchronous listener, not queued:** `UpdateMerchantRule` runs synchronously within the request lifecycle. The rule must be persisted before the response returns so the merchant_rules table is immediately up to date. The operation is trivial (one `updateOrCreate`) -- no performance concern.

- **`updateOrCreate` for idempotency:** The `merchant_rules` table has a UNIQUE constraint on `merchant_pattern`. Using `updateOrCreate` handles both new rules and re-corrections gracefully. If the user corrects "CARREFOUR" to "Food" and later re-corrects to "Restaurants", the rule is updated, not duplicated.

- **`$oldCategoryId !== $newCategoryId` guard:** If the user opens the picker and selects the same category (no actual change), we skip event dispatch. This prevents unnecessary rule writes and a confusing "Got it, I'll remember" toast when nothing changed.

- **`$transaction->fresh()` in event:** After `$transaction->update()`, the model instance's `category_id` is updated in memory. We pass `$transaction->fresh()` to the event to ensure the event payload has the latest database state. This is a defensive practice following the existing `TransactionCategorized` event pattern in `CategorizationService`.

- **Sonner for toast (not custom component):** The UX spec explicitly lists Toast as one of 4 shadcn/ui components to adopt. Sonner is the de facto toast library for shadcn/ui projects -- it's lightweight (~4KB), has dark theme support, and requires minimal setup (one `<Toaster />` in the layout). No need for a custom toast implementation.

- **Toast placement and duration:** Per UX spec: "subtle toast appears at the bottom," "auto-dismisses after 2 seconds," "No close button needed." Sonner configuration: `position="bottom-center"`, `duration={2000}`, no `closeButton`.

- **MerchantMatchingService already integrated:** The existing `CategorizationService.categorize()` already calls `MerchantMatchingService.matchBatch()` before dispatching AI categorization. Adding rules to `merchant_rules` via this story means those rules are automatically used on the next import. No changes needed to the import pipeline.

### CategoryCorrected Event

```php
// app/Events/CategoryCorrected.php
<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CategoryCorrected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Transaction $transaction,
        public int $oldCategoryId,
        public int $newCategoryId,
    ) {}
}
```

**Pattern follows:** `app/Events/ImportCompleted.php` and `app/Events/TransactionCategorized.php`. Uses `Dispatchable` for `::dispatch()` static method and `SerializesModels` for safe serialization if ever queued in the future.

### UpdateMerchantRule Listener

```php
// app/Listeners/UpdateMerchantRule.php
<?php

namespace App\Listeners;

use App\Events\CategoryCorrected;
use App\Models\MerchantRule;

class UpdateMerchantRule
{
    public function handle(CategoryCorrected $event): void
    {
        MerchantRule::updateOrCreate(
            ['merchant_pattern' => $event->transaction->description],
            ['category_id' => $event->newCategoryId],
        );
    }
}
```

**Pattern follows:** `app/Listeners/CategorizeImportedTransactions.php`. Concise -- extracts the transaction's `description` field as the merchant pattern and maps it to the new category. `updateOrCreate` handles unique constraint gracefully.

### TransactionController Update

```php
// app/Http/Controllers/TransactionController.php -- modified update method
public function update(Request $request, Transaction $transaction): RedirectResponse
{
    $validated = $request->validate([
        'category_id' => ['required', 'exists:categories,id'],
    ]);

    $oldCategoryId = $transaction->category_id;

    $transaction->update([
        'category_id' => $validated['category_id'],
    ]);

    if ($oldCategoryId !== (int) $validated['category_id']) {
        CategoryCorrected::dispatch($transaction->fresh(), $oldCategoryId, (int) $validated['category_id']);
    }

    return back();
}
```

**Changes from Story 4.1:**
- Added `$oldCategoryId` capture before update
- Added conditional `CategoryCorrected::dispatch()` after update
- Guard: only dispatches if category actually changed
- Added `use App\Events\CategoryCorrected;` import

### AppServiceProvider Registration

```php
// app/Providers/AppServiceProvider.php -- add to boot()
use App\Events\CategoryCorrected;
use App\Listeners\UpdateMerchantRule;

// Inside boot() method, alongside existing Event::listen:
Event::listen(CategoryCorrected::class, UpdateMerchantRule::class);
```

**Follows existing pattern:** `Event::listen(ImportCompleted::class, CategorizeImportedTransactions::class);` already in `boot()`.

### Sonner Toast Setup

**Install:**
```bash
sail bun add sonner
```

**Toaster in app layout:**

The Toaster component must be rendered once in the application's root layout. Check for the app entry point -- likely `resources/js/layouts/app-layout.tsx` or the Inertia app wrapper in `resources/js/app.tsx`.

```tsx
// In the root layout or app.tsx
import { Toaster } from 'sonner';

// Inside the component's return, at the root level:
<Toaster
    theme="dark"
    position="bottom-center"
    duration={2000}
/>
```

**Key Toaster configuration:**
- `theme="dark"` -- matches the dark app theme (#0A0A0B background)
- `position="bottom-center"` -- UX spec: "subtle toast appears at the bottom"
- `duration={2000}` -- UX spec: "auto-dismiss after 2 seconds"
- No `closeButton` prop -- UX spec: "No close button needed"

### Categories/Show.tsx Toast Integration

```tsx
// resources/js/pages/categories/show.tsx -- add import
import { toast } from 'sonner';

// Modify handleCategorySelect onSuccess callback:
const handleCategorySelect = useCallback((categoryId: number) => {
    if (selectedTransactionId === null || isPatching) return;
    setIsPatching(true);
    router.patch(`/transactions/${selectedTransactionId}`, {
        category_id: categoryId,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            setSelectedTransactionId(null);
            setSelectedTransactionCategoryId(null);
            toast("Got it, I'll remember");  // NEW: AI learning toast
        },
        onFinish: () => setIsPatching(false),
    });
}, [selectedTransactionId, isPatching]);
```

**Minimal change:** One import line, one `toast()` call in the existing `onSuccess` callback. The toast fires after the picker closes and amounts update, so the user sees the correction reflected AND the acknowledgment simultaneously.

### Testing Strategy

**Feature tests in `tests/Feature/TransactionControllerTest.php` (EXTEND existing):**

```php
// Add these tests to the existing TransactionControllerTest

public function test_category_corrected_event_dispatches_on_update(): void
{
    Event::fake([CategoryCorrected::class]);

    $user = User::factory()->create();
    $account = Account::factory()->personal()->create();
    $food = Category::where('slug', 'food')->first();
    $restaurants = Category::where('slug', 'restaurants')->first();

    $transaction = Transaction::factory()->create([
        'category_id' => $food->id,
        'account_id' => $account->id,
        'description' => 'CARREFOUR CITY PARIS',
    ]);

    $this->actingAs($user)
        ->patch("/transactions/{$transaction->id}", [
            'category_id' => $restaurants->id,
        ]);

    Event::assertDispatched(CategoryCorrected::class, function ($event) use ($transaction, $food, $restaurants) {
        return $event->transaction->id === $transaction->id
            && $event->oldCategoryId === $food->id
            && $event->newCategoryId === $restaurants->id;
    });
}

public function test_category_corrected_event_not_dispatched_when_same_category(): void
{
    Event::fake([CategoryCorrected::class]);

    $user = User::factory()->create();
    $account = Account::factory()->personal()->create();
    $food = Category::where('slug', 'food')->first();

    $transaction = Transaction::factory()->create([
        'category_id' => $food->id,
        'account_id' => $account->id,
    ]);

    $this->actingAs($user)
        ->patch("/transactions/{$transaction->id}", [
            'category_id' => $food->id,
        ]);

    Event::assertNotDispatched(CategoryCorrected::class);
}
```

**Listener test in `tests/Feature/UpdateMerchantRuleTest.php` (NEW):**

```php
class UpdateMerchantRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_listener_creates_merchant_rule_on_correction(): void
    {
        $account = Account::factory()->personal()->create();
        $food = Category::where('slug', 'food')->first();
        $restaurants = Category::where('slug', 'restaurants')->first();

        $transaction = Transaction::factory()->create([
            'category_id' => $restaurants->id,
            'account_id' => $account->id,
            'description' => 'BOULANGERIE LEFEVRE',
        ]);

        $event = new CategoryCorrected($transaction, $food->id, $restaurants->id);
        $listener = new UpdateMerchantRule();
        $listener->handle($event);

        $this->assertDatabaseHas('merchant_rules', [
            'merchant_pattern' => 'BOULANGERIE LEFEVRE',
            'category_id' => $restaurants->id,
        ]);
    }

    public function test_listener_updates_existing_rule_on_re_correction(): void
    {
        $account = Account::factory()->personal()->create();
        $food = Category::where('slug', 'food')->first();
        $restaurants = Category::where('slug', 'restaurants')->first();
        $transport = Category::where('slug', 'transport')->first();

        // Create initial rule
        MerchantRule::create([
            'merchant_pattern' => 'UBER EATS',
            'category_id' => $restaurants->id,
        ]);

        $transaction = Transaction::factory()->create([
            'category_id' => $transport->id,
            'account_id' => $account->id,
            'description' => 'UBER EATS',
        ]);

        // Re-correct: restaurants -> transport
        $event = new CategoryCorrected($transaction, $restaurants->id, $transport->id);
        $listener = new UpdateMerchantRule();
        $listener->handle($event);

        $this->assertDatabaseCount('merchant_rules', 1);
        $this->assertDatabaseHas('merchant_rules', [
            'merchant_pattern' => 'UBER EATS',
            'category_id' => $transport->id,
        ]);
    }

    public function test_integration_correction_enables_future_matching(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $other = Category::where('slug', 'other')->first();
        $food = Category::where('slug', 'food')->first();

        // Correct a transaction (full flow via HTTP)
        $transaction = Transaction::factory()->create([
            'category_id' => $other->id,
            'account_id' => $account->id,
            'description' => 'PICARD SURGELES',
        ]);

        $this->actingAs($user)
            ->patch("/transactions/{$transaction->id}", [
                'category_id' => $food->id,
            ]);

        // Verify rule was created
        $this->assertDatabaseHas('merchant_rules', [
            'merchant_pattern' => 'PICARD SURGELES',
            'category_id' => $food->id,
        ]);

        // Verify MerchantMatchingService now matches this description
        $matchingService = app(MerchantMatchingService::class);
        $matchedCategory = $matchingService->match('PICARD SURGELES');

        $this->assertNotNull($matchedCategory);
        $this->assertEquals($food->id, $matchedCategory->id);
    }

    public function test_merchant_rules_persist_across_queries(): void
    {
        $food = Category::where('slug', 'food')->first();

        MerchantRule::create([
            'merchant_pattern' => 'MONOPRIX',
            'category_id' => $food->id,
        ]);

        // Verify it persists (simple but confirms NFR13)
        $this->assertDatabaseHas('merchant_rules', [
            'merchant_pattern' => 'MONOPRIX',
            'category_id' => $food->id,
        ]);

        // Also verify via service
        $matchingService = app(MerchantMatchingService::class);
        $matched = $matchingService->match('MONOPRIX');
        $this->assertNotNull($matched);
        $this->assertEquals($food->id, $matched->id);
    }
}
```

### Previous Story Intelligence

**From Story 4.1 (Single-Tap Category Correction -- direct predecessor):**
- 235 tests, 1055 assertions, zero regressions -- maintain this standard
- All commands via Sail: `sail artisan test`, `sail bun run build`
- `TransactionController::update()` currently just validates and updates -- must add event dispatch
- `handleCategorySelect` in `categories/show.tsx` uses `router.patch` with `preserveScroll: true` and `onSuccess`/`onFinish` callbacks -- add `toast()` in `onSuccess`
- `isPatching` state guard prevents concurrent corrections -- this remains unchanged
- `CategoryPicker` component needs no changes -- it delegates selection to the parent
- `useIsMobile()` hook uses 640px breakpoint (corrected in 4.1 code review)

**From Story 2.4 (AI Auto-Categorization Engine):**
- `CategorizationService::categorize()` already calls `MerchantMatchingService::matchBatch()` first
- Transactions matched by rules are instantly categorized, bypassing the Gemini queue
- The hybrid pattern is already in place -- Story 4.2 just populates the `merchant_rules` table

**From Story 4.1 code review fixes:**
- Fixed desktop DropdownMenu anchoring via triggerRef
- Added isPatching guard for concurrent request prevention
- Corrected useIsMobile breakpoint to 640px

### Git Intelligence

Recent commits (one per story, mono-commit pattern):
- `dde98cc` Story 4.1: Single-tap category correction with responsive CategoryPicker + review fixes

**Established patterns:**
- One commit per story with descriptive message
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Tests must pass with zero regressions before committing
- Feature tests use `RefreshDatabase` trait + seed categories
- Test file naming: `{Subject}Test.php` in `tests/Feature/`

### Architecture Compliance

| Requirement | Implementation |
|-------------|----------------|
| CategoryCorrected event on correction [arch#Communication Patterns] | `CategoryCorrected::dispatch()` in TransactionController after update |
| UpdateMerchantRule listener [arch#AI Learning Pipeline] | Listener persists `merchant_pattern` -> `category_id` via `updateOrCreate` |
| Events: PascalCase past tense [arch#Naming Patterns] | `CategoryCorrected` -- follows `ImportCompleted`, `TransactionCategorized` |
| Listeners use constructor injection [arch#Process Patterns] | `UpdateMerchantRule` -- no dependencies needed (direct model call) |
| Toast for AI learning only [ux#Feedback Patterns] | Sonner toast: "Got it, I'll remember" -- 2s auto-dismiss |
| No close button on toast [ux#Feedback Patterns] | Toaster configured without `closeButton` |
| 4 shadcn/ui components [ux#Component Strategy] | Toast (sonner) is the 4th shadcn/ui component adopted (DropdownMenu, Sheet, Dialog, Toast) |
| Event registration in AppServiceProvider [arch#Provider Pattern] | `Event::listen()` in `boot()` alongside existing bindings |

### Library & Framework Requirements

| Library | Version | Usage in This Story |
|---------|---------|-------------------|
| sonner | Latest (~v2.x) | Toast notifications for AI learning acknowledgment |

**One new package: `sonner`.**
- Install via: `sail bun add sonner`
- Lightweight (~4KB), Tailwind-compatible, dark theme support
- Renders `<Toaster />` component once in app layout
- `toast()` function called from React components

### File Structure Requirements

**New files to create:**
```
app/Events/CategoryCorrected.php                    (NEW)
app/Listeners/UpdateMerchantRule.php                 (NEW)
tests/Feature/UpdateMerchantRuleTest.php             (NEW)
```

**Modified files:**
```
app/Http/Controllers/TransactionController.php       (add event dispatch)
app/Providers/AppServiceProvider.php                 (register event-listener)
resources/js/pages/categories/show.tsx               (add toast import and call)
resources/js/layouts/app-layout.tsx                  (add Toaster component) *or equivalent root layout*
package.json                                         (sonner dependency added)
bun.lock                                             (updated)
```

### What This Story Does NOT Include

- **MerchantMatchingService changes** -- It already works perfectly. Story 4.2 only populates the `merchant_rules` table; the matching service already queries it during imports.
- **CategorizationService changes** -- The hybrid flow (merchant match first, AI fallback) is already implemented. No modifications needed.
- **Import pipeline changes** -- The learning pipeline benefits imports automatically through the existing `merchant_rules` table lookup.
- **Batch correction** -- Each correction creates/updates one rule. No bulk rule management UI.
- **Merchant pattern normalization** -- Rules are stored as-is from `transaction.description`. Matching is case-insensitive via `LOWER()` in `MerchantMatchingService`. No fuzzy matching or stemming.
- **Rule management UI** -- No screen to view, edit, or delete merchant rules. Rules are managed implicitly through corrections.
- **Import deletion impact on rules** -- Per epics AC: "learned merchant rules are preserved -- they were learned from corrections, not from the import itself." Rules survive import deletion (Story 4.3).

### Edge Cases to Handle

1. **Same category re-selection:** If the user opens the picker and selects the current category, the `$oldCategoryId !== $newCategoryId` guard skips event dispatch. No rule is created, no toast appears. The PATCH still succeeds (transaction stays unchanged).

2. **Re-correction of same merchant:** If "CARREFOUR" was corrected to "Food" and later re-corrected to "Restaurants", `updateOrCreate` updates the existing rule. Only one rule per merchant pattern. The latest correction wins.

3. **Multiple transactions with same description:** If 5 transactions all say "CARREFOUR CITY" and the user corrects one, the rule is created for that description. The remaining 4 transactions keep their current categories (they were assigned during import). On the NEXT import, all "CARREFOUR CITY" transactions will be auto-categorized per the rule.

4. **Empty or very short description:** Some transactions may have cryptic descriptions like "PMT REF 4829371". The rule is still created with the exact description. If the same reference appears in a future import, it matches. If it doesn't, the rule is harmless.

5. **Toast appearing on failed PATCH:** The toast only fires in `onSuccess`, not `onFinish`. If the PATCH fails (validation error, network issue), no toast appears and no rule is created (the event doesn't dispatch on failure).

6. **Concurrent corrections:** The `isPatching` guard from Story 4.1 prevents concurrent PATCH requests. Each correction completes fully (including event + listener) before the next can begin.

### Project Structure Notes

- Events in `app/Events/` (following `ImportCompleted.php`, `TransactionCategorized.php`) [Source: architecture.md#Structure Patterns]
- Listeners in `app/Listeners/` (following `CategorizeImportedTransactions.php`) [Source: architecture.md#Structure Patterns]
- Event registration in `AppServiceProvider::boot()` [Source: app/Providers/AppServiceProvider.php]
- Toast library (sonner) is a frontend dependency only [Source: ux-design-specification.md#Component Strategy]

### References

- Epic 4 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 4.2]
- FR9, FR11, FR23 requirements: [Source: _bmad-output/planning-artifacts/epics.md#Requirements Inventory]
- CategoryCorrected event + UpdateMerchantRule listener: [Source: _bmad-output/planning-artifacts/architecture.md#Communication Patterns]
- Toast UX spec ("Got it, I'll remember", 2s auto-dismiss): [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Feedback Patterns]
- Toast as shadcn/ui component: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Component Strategy]
- Emotional design -- "AI is a learner, not an authority": [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Emotional Design Principles]
- MerchantMatchingService implementation: [Source: app/Services/MerchantMatchingService.php]
- CategorizationService hybrid flow: [Source: app/Services/CategorizationService.php]
- MerchantRule model: [Source: app/Models/MerchantRule.php]
- merchant_rules migration (unique constraint): [Source: database/migrations/2026_02_20_101007_create_merchant_rules_table.php]
- Existing event patterns: [Source: app/Events/ImportCompleted.php], [Source: app/Events/TransactionCategorized.php]
- Existing listener pattern: [Source: app/Listeners/CategorizeImportedTransactions.php]
- Event registration pattern: [Source: app/Providers/AppServiceProvider.php]
- TransactionController (Story 4.1): [Source: app/Http/Controllers/TransactionController.php]
- Categories/Show correction flow: [Source: resources/js/pages/categories/show.tsx]
- Previous story 4.1: [Source: _bmad-output/implementation-artifacts/4-1-single-tap-category-correction.md]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6 (claude-opus-4-6)

### Debug Log References

None -- clean implementation with no debug issues.

### Completion Notes List

- Created `CategoryCorrected` event following `ImportCompleted` pattern with `Dispatchable` and `SerializesModels` traits
- Created `UpdateMerchantRule` listener using `updateOrCreate` for idempotent merchant rule persistence
- Modified `TransactionController::update()` to capture old category, dispatch event only when category actually changes
- Registered `CategoryCorrected` -> `UpdateMerchantRule` binding in `AppServiceProvider::boot()`
- Installed sonner v2.0.7 via `sail bun add sonner`
- Added `<Toaster />` to `app/app-layout.tsx` with dark theme, bottom-center, 2s auto-dismiss
- Added `toast("Got it, I'll remember")` in `handleCategorySelect` onSuccess callback
- Added 2 new event dispatch tests to `TransactionControllerTest` (dispatch on change, skip on same category)
- Created `UpdateMerchantRuleTest` with 4 tests: rule creation, re-correction update, integration flow, persistence
- All 241 tests pass (1066 assertions), zero regressions
- TypeScript builds cleanly

### Change Log

- 2026-02-20: Story 4.2 implementation -- AI learning from corrections pipeline (event, listener, toast, tests)
- 2026-02-20: Code review fixes -- wrapped correction+event in DB::transaction, removed unnecessary fresh(), added trim() to merchant_pattern, fixed test data consistency, completed File List

### File List

**New files:**
- app/Events/CategoryCorrected.php
- app/Listeners/UpdateMerchantRule.php
- tests/Feature/UpdateMerchantRuleTest.php

**Modified files:**
- app/Http/Controllers/TransactionController.php
- app/Providers/AppServiceProvider.php
- resources/js/pages/categories/show.tsx
- resources/js/layouts/app/app-layout.tsx
- tests/Feature/TransactionControllerTest.php
- package.json
- bun.lock
- _bmad-output/implementation-artifacts/sprint-status.yaml
