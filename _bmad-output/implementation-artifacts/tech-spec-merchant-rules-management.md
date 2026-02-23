---
title: 'Merchant Rules Management Screen'
slug: 'merchant-rules-management'
created: '2026-02-23'
status: 'completed'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Laravel 12', 'React 19', 'Inertia.js 2', 'TypeScript', 'Tailwind CSS 4', 'shadcn/ui (Radix)', 'PostgreSQL']
files_to_modify:
  - 'resources/js/hooks/use-nav-items.ts'
  - 'resources/js/layouts/settings/layout.tsx'
  - 'resources/js/pages/categories/show.tsx'
  - 'resources/js/pages/search.tsx'
  - 'resources/js/components/TransactionRow.tsx'
  - 'resources/js/components/SearchResultRow.tsx'
  - 'app/Http/Controllers/TransactionController.php'
  - 'routes/settings.php'
  - 'routes/web.php'
  - 'resources/js/types/budget.ts'
files_to_create:
  - 'database/migrations/xxxx_normalize_merchant_pattern_case.php'
  - 'app/Http/Controllers/Settings/MerchantRulesController.php'
  - 'resources/js/pages/settings/merchant-rules.tsx'
  - 'resources/js/components/ApplyRuleConfirmDialog.tsx'
code_patterns: ['Inertia::render for page props', 'AppLayout > SettingsLayout nesting', 'router.patch() for mutations', 'sonner toast for feedback', 'Sheet (mobile) / DropdownMenu (desktop) pattern', 'Wayfinder route helpers']
test_patterns: ['PHPUnit with Mockery for backend', 'Vitest + @testing-library/react for frontend', 'Feature tests in tests/Feature/', 'Unit tests in tests/Unit/']
---

# Tech-Spec: Merchant Rules Management Screen

**Created:** 2026-02-23

## Overview

### Problem Statement

Merchant rules (auto-categorization mappings from transaction descriptions to categories) are created silently when a user corrects a transaction's category. There is no way to view, delete, or re-apply these rules. Users have zero visibility into what rules exist and no control over them.

### Solution

Two complementary features that give users full control over merchant rules:

1. **Inline apply on category correction** — When correcting a transaction's category, show a confirmation dialog offering "Just this one" or "All X matching" to retroactively apply the rule to all transactions with the same description. This is the primary UX for the most common use case.

2. **Merchant rules settings page** — A new page inside Settings listing all rules with pattern, category badge, match count, per-rule apply button, and delete. Accessible via a Settings nav item (gear icon) in both desktop and mobile navigation.

### Scope

**In Scope:**
- Enhanced category correction dialog: "Just this one" vs "All N matching" choice
- New Settings nav item in main navigation (gear icon) for both desktop and mobile
- Merchant rules list page inside Settings: pattern + category badge + match count + Apply + Delete
- Per-rule "Apply" action with confirmation showing exact impact (overwrites all matching, including previously categorized)
- Backend routes: list rules (with match counts), delete rule, apply single rule
- Success feedback after applying (toast: "Updated N transactions")

**Out of Scope:**
- Editing rule patterns or category assignments (view & delete only)
- Manual rule creation from the settings page
- Wildcard or partial matching changes to the matching engine
- Bulk "apply all rules" button (can be added later; per-rule apply covers the need)
- Other settings sections (accounts, profile already exist separately)

## Context for Development

### Codebase Patterns

**Page rendering:** Controllers return `Inertia::render('page-name', [...props])`. Pages receive typed props and use `usePage<SharedProps>()` for shared data like `allCategories`.

**Settings pages:** Follow `AppLayout > SettingsLayout` nesting pattern. SettingsLayout renders a sidebar nav on desktop and stacked nav on mobile. Sidebar items defined in `sidebarNavItems` array in `layouts/settings/layout.tsx`.

**Navigation:** `useNavItems()` hook returns `DockNavItem[]` consumed by both desktop top nav in `app-layout.tsx` and mobile `BottomDock.tsx`. Adding an item to this hook adds it to both surfaces.

**Category correction flow (current):** Single-tap, zero-confirmation. `TransactionRow` / `SearchResultRow` → `onTap` → sets state → `CategoryPicker` opens → `onSelect` → `router.patch('/transactions/{id}', { category_id })` → toast. State machine duplicated in `show.tsx` and `search.tsx`.

**Category picker:** `CategoryPicker` component uses `Sheet` (mobile) and `DropdownMenu` (desktop), both from Radix UI. Desktop uses an invisible anchor `span` repositioned via `getBoundingClientRect()`.

**Dialogs:** `Dialog` component from shadcn/ui exists at `components/ui/dialog.tsx` (Radix AlertDialog) — available but unused in current correction flow. `sonner` toast used for success feedback.

**Route helpers:** Wayfinder auto-generates TypeScript route helpers from Laravel routes. Import pattern: `import { index } from '@/routes/merchant-rules'`.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `app/Models/MerchantRule.php` | Rule model: `merchant_pattern` (unique), `category_id` (FK) |
| `app/Services/MerchantMatchingService.php` | `match()` and `matchBatch()` — case-insensitive exact matching |
| `app/Http/Controllers/TransactionController.php` | PATCH handler: validates, updates, dispatches `CategoryCorrected` |
| `app/Events/CategoryCorrected.php` | Event: carries transaction, oldCategoryId, newCategoryId |
| `app/Listeners/UpdateMerchantRule.php` | Listener: `MerchantRule::updateOrCreate` on description → category |
| `resources/js/components/CategoryPicker.tsx` | Mobile Sheet / Desktop DropdownMenu category selector |
| `resources/js/components/TransactionRow.tsx` | Clickable transaction row on category detail page |
| `resources/js/components/SearchResultRow.tsx` | Clickable transaction row on search page |
| `resources/js/pages/categories/show.tsx` | Category detail page — has `handleCategorySelect` with `router.patch` |
| `resources/js/pages/search.tsx` | Search page — duplicated `handleCategorySelect` pattern |
| `resources/js/pages/settings/profile.tsx` | Canonical settings page template (AppLayout > SettingsLayout) |
| `resources/js/layouts/settings/layout.tsx` | Settings sidebar nav + content wrapper |
| `resources/js/hooks/use-nav-items.ts` | Source of truth for top nav + bottom dock items |
| `resources/js/components/BottomDock.tsx` | Mobile fixed-bottom nav |
| `resources/js/layouts/app/app-layout.tsx` | App shell: header nav, main content, bottom dock |
| `resources/js/types/budget.ts` | Shared TypeScript types |
| `routes/settings.php` | Settings routes (profile, password, two-factor) |
| `routes/web.php` | Main web routes including PATCH transactions |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shares `allCategories` globally to all Inertia pages |
| `resources/js/components/ui/dialog.tsx` | shadcn/ui Dialog component (Radix AlertDialog) |

### Technical Decisions

- Merchant rules page lives inside the existing Settings section (`resources/js/pages/settings/`)
- Settings gets added to main navigation (both desktop top nav and mobile bottom dock)
- Re-apply overwrites ALL matching transactions (including previously categorized) — this is intentional per user requirement
- Match count per rule displayed on the list serves as implicit preview of apply impact
- Inline correction dialog shows match count and offers "just this one" vs "all matching" choice
- Do NOT fire `CategoryCorrected` event during bulk apply to avoid circular rule updates
- The existing `handleCategorySelect` in both `show.tsx` and `search.tsx` needs modification to show a confirmation dialog when matching count > 1
- Backend needs a new endpoint to count matching transactions for a given description (used by the inline dialog)
- Backend needs a new endpoint to bulk-update transactions by description pattern
- **F1 fix:** PATCH `update()` flashes `updated_count` to session when `apply_to_all` is true. Frontend reads it from Inertia's page props (`flash.updated_count`) for the toast. No raw JSON needed.
- **F2 fix:** Picker `onClose` must NOT clear `selectedTransactionId` when a pending category selection exists. Introduce `pendingCategoryId` state — picker close sets `pendingCategoryId` and keeps `selectedTransactionId` alive for the confirmation dialog. State is only fully cleared after dialog action or cancel.
- **F3 fix:** Normalize `merchant_pattern` to lowercase before storage in `UpdateMerchantRule` listener (`mb_strtolower(trim(...))`). Add a migration with a unique functional index on `LOWER(merchant_pattern)` and drop the existing unique constraint. This eliminates duplicate near-identical rules.
- **F4 fix:** Settings page Apply action uses `router.post()` (Inertia) instead of raw `fetch()`. Controller `apply()` returns `back()` with flashed `updated_count`. Both Apply and Delete use Inertia consistently.
- **F5 fix:** Change `onTap` callback in `TransactionRow` and `SearchResultRow` to accept an object: `{ id, categoryId, description, element }`. This prevents argument-order bugs.
- **F6 fix:** Skip the entire correction flow (no match-count fetch, no dialog, no PATCH) when the selected category matches the transaction's current category. Guard in `handleCategorySelect`.
- **F7 fix:** Settings page Apply and Delete buttons get `disabled` state via `isProcessing` flag, same pattern as `isPatching` in the inline flow.
- **F8 fix:** The `fetch()` call for match-count (GET, CSRF-exempt) must check `response.ok` before parsing JSON. On failure (e.g., 419 session expired), show a toast error and abort instead of crashing.
- **F9 fix:** Apply confirmation dialog copy explicitly states: "This will update all N transactions with this description, including any previously categorized."
- **F10 fix:** Rules list sorted alphabetically by `merchant_pattern` (default). No pagination for v1 (acceptable for personal app scale).

## Implementation Plan

### Tasks

#### Task 0: Backend — Migration to normalize merchant_pattern case

- **File:** `database/migrations/xxxx_normalize_merchant_pattern_case.php` (new)
- **Action:** Create migration that:
  1. Lowercases all existing `merchant_pattern` values: `UPDATE merchant_rules SET merchant_pattern = LOWER(merchant_pattern)`
  2. Drops the existing unique index on `merchant_pattern`.
  3. Adds a unique index on `merchant_pattern` (now that all values are lowercase, the standard unique constraint works).
  4. Handles potential duplicates from lowercasing: use `ON CONFLICT` or delete duplicates first (keep the most recent by `updated_at`).
- **File:** `app/Listeners/UpdateMerchantRule.php`
- **Action:** Change the listener to lowercase before storage:
  ```php
  MerchantRule::updateOrCreate(
      ['merchant_pattern' => mb_strtolower(trim($event->transaction->description))],
      ['category_id' => $event->newCategoryId],
  );
  ```
  This ensures all new rules are stored lowercase, consistent with the migration.

#### Task 1: Backend — Enhance TransactionController for bulk apply

- **File:** `app/Http/Controllers/TransactionController.php`
- **Action:** Modify `update()` to accept optional `apply_to_all` boolean parameter.
  - Add validation rule: `'apply_to_all' => ['sometimes', 'boolean']`
  - When `apply_to_all` is `true`: after updating the single transaction and dispatching `CategoryCorrected`, also bulk-update all other transactions with the same description (case-insensitive) to the new category. Use `Transaction::whereRaw('LOWER(description) = ?', [mb_strtolower(trim($transaction->description))])->where('id', '!=', $transaction->id)->update(['category_id' => $validated['category_id']])`. Do NOT fire `CategoryCorrected` for the bulk-updated transactions (only for the original one, which creates/updates the merchant rule).
  - Capture the count of bulk-updated rows and flash it to the session: `session()->flash('updated_count', $bulkCount + 1)` (the +1 includes the original transaction).
  - Return `back()` as before (Inertia redirect). The flashed `updated_count` is available to the frontend via Inertia's shared props.

#### Task 2: Backend — Add match count endpoint

- **File:** `app/Http/Controllers/TransactionController.php`
- **Action:** Add a new `matchCount()` method.
  - Validate: `'description' => ['required', 'string', 'min:1']` — reject empty/missing descriptions.
  - Returns JSON response: `{ count: N }` where N = number of transactions with matching description (case-insensitive).
  - Query: `Transaction::whereRaw('LOWER(description) = ?', [mb_strtolower(trim($description))])->count()`
- **File:** `routes/web.php`
- **Action:** Add route: `Route::get('transactions/match-count', [TransactionController::class, 'matchCount'])->middleware(['auth', 'verified'])->name('transactions.match-count');`
  - Place BEFORE the `transactions/{transaction}` route to avoid route parameter conflict.

#### Task 3: Backend — MerchantRulesController

- **File:** `app/Http/Controllers/Settings/MerchantRulesController.php` (new)
- **Action:** Create controller with three methods:

  **`index()`** — List all rules with match counts and category data.
  - Query: `MerchantRule::with('category')->select('merchant_rules.*')->selectSub(...)` with a correlated subquery counting matching transactions per rule.
  - Alternatively, load all rules then map with individual count queries (acceptable for personal app scale).
  - Sort rules alphabetically by `merchant_pattern` (default sort order).
  - Return `Inertia::render('settings/merchant-rules', ['rules' => $rules])` where each rule has: `id`, `merchantPattern`, `categoryId`, `categoryName`, `categoryColor`, `categoryIcon`, `matchCount`.

  **`destroy(MerchantRule $merchantRule)`** — Delete a rule.
  - `$merchantRule->delete()`
  - Return `back()`.

  **`apply(MerchantRule $merchantRule)`** — Bulk-apply a single rule to all matching transactions.
  - Query: `Transaction::whereRaw('LOWER(description) = ?', [mb_strtolower($merchantRule->merchant_pattern)])->update(['category_id' => $merchantRule->category_id])`
  - Flash `updated_count` to session: `session()->flash('updated_count', $count)`.
  - Return `back()` (Inertia redirect). Frontend reads the flashed count for the toast.
  - Do NOT dispatch `CategoryCorrected` events for these updates.

#### Task 4: Backend — Routes for merchant rules

- **File:** `routes/settings.php`
- **Action:** Add to the `auth + verified` group:
  ```php
  Route::get('settings/merchant-rules', [MerchantRulesController::class, 'index'])->name('merchant-rules.index');
  Route::delete('settings/merchant-rules/{merchantRule}', [MerchantRulesController::class, 'destroy'])->name('merchant-rules.destroy');
  Route::post('settings/merchant-rules/{merchantRule}/apply', [MerchantRulesController::class, 'apply'])->name('merchant-rules.apply');
  ```
- **Also:** Update the existing `Route::redirect('settings', '/settings/profile')` to `Route::redirect('settings', '/settings/merchant-rules')` since merchant rules is now the primary settings page.
- **Post-step:** Run `sail artisan wayfinder:generate` to generate TypeScript route helpers. **Verify** the generated file path and export names before writing imports in Task 7.

#### Task 5: Frontend — TypeScript types

- **File:** `resources/js/types/budget.ts`
- **Action:** Add new types:
  ```typescript
  export type MerchantRuleItem = {
      id: number;
      merchantPattern: string;
      categoryId: number;
      categoryName: string;
      categoryColor: string;
      categoryIcon: string | null;
      matchCount: number;
  };

  export type MerchantRulesProps = {
      rules: MerchantRuleItem[];
  };
  ```

#### Task 6: Frontend — Add Settings to main navigation

- **File:** `resources/js/hooks/use-nav-items.ts`
- **Action:** Add a Settings nav item to the returned array:
  ```typescript
  { label: 'Settings', href: buildHref('/settings/merchant-rules'), icon: Settings, isActive: currentUrl.startsWith('/settings') }
  ```
  - Import `Settings` icon from `lucide-react`.
  - The href points directly to merchant-rules since that's the primary settings page for this app.
  - `isActive` matches any `/settings/*` URL.

#### Task 7: Frontend — Add Merchant Rules to settings sidebar

- **File:** `resources/js/layouts/settings/layout.tsx`
- **Action:** Add entry to `sidebarNavItems` array:
  ```typescript
  { title: 'Merchant Rules', href: index(), icon: null }
  ```
  - Import Wayfinder route helper: `import { index } from '@/routes/merchant-rules'`.

#### Task 8: Frontend — Create ApplyRuleConfirmDialog component

- **File:** `resources/js/components/ApplyRuleConfirmDialog.tsx` (new)
- **Action:** Create a confirmation dialog using shadcn `Dialog` component.
- **Props:**
  ```typescript
  type Props = {
      isOpen: boolean;
      description: string;
      categoryName: string;
      categoryColor: string;
      matchCount: number;
      isLoading: boolean;
      onApplyOne: () => void;
      onApplyAll: () => void;
      onCancel: () => void;
  };
  ```
- **UI:**
  - Title: "Recategorize to {categoryName}?"
  - Body: Shows the transaction description. Below it: "This will update all {matchCount} transactions with this description, including any previously categorized."
  - Buttons: "Just this one" (secondary) and "All {matchCount} matching" (primary). Both disabled when `isLoading` is true.
  - When `matchCount <= 1`, the dialog should not be shown (caller handles this).
- **Category badge:** Show category color dot next to the category name (same pattern as used elsewhere in the app).

#### Task 9: Frontend — Modify category correction flow in show.tsx

- **File:** `resources/js/pages/categories/show.tsx`
- **Action:** Modify `handleCategorySelect` to insert a confirmation step:

  **New state variables:**
  - `pendingCategoryId: number | null` — the category the user selected in the picker, awaiting confirmation.
  - `selectedDescription: string | null` — stored from the `onTap` callback.
  - `matchCount: number | null` — fetched count, drives dialog display.

  **Modified flow:**
  0. **Guard:** If the selected category === the transaction's current category, ignore the selection (no-op). This prevents fetching/dialog for re-selecting the same category.
  1. When user selects a category in the picker, store `pendingCategoryId` and close the picker. **Critical: the picker's `onClose` must NOT clear `selectedTransactionId` or `selectedDescription` when `pendingCategoryId` is set.** Only clear all state when `pendingCategoryId` is null (i.e., user dismissed the picker without selecting).
  2. Fetch match count: `fetch('/transactions/match-count?description=' + encodeURIComponent(description))`. Check `response.ok` before parsing JSON — on failure (e.g., 419 session expired), show error toast and clear pending state.
  3. If `matchCount === 1`: immediately fire `router.patch()` as before (no dialog).
  4. If `matchCount > 1`: open `ApplyRuleConfirmDialog` with the count.
  5. On "Just this one": `router.patch('/transactions/{id}', { category_id })`.
  6. On "All matching": `router.patch('/transactions/{id}', { category_id, apply_to_all: true })`.
  7. On cancel: clear all pending state, do nothing.
  8. Toast message: For "just this one" → `toast("Got it, I'll remember")`. For "all matching" → read flashed `updated_count` from Inertia page props in `onSuccess` and toast `"Updated ${count} transactions"`.

  **Reading flash data:** In the `router.patch` `onSuccess` callback, access `usePage().props.flash.updated_count` to get the actual count from the server.

#### Task 10: Frontend — Modify category correction flow in search.tsx

- **File:** `resources/js/pages/search.tsx`
- **Action:** Apply the same modifications as Task 9. The state machine is duplicated here.
- **Also:** `SearchResultRow` needs to pass `description` in its `onTap` callback, same as `TransactionRow` in Task 9.

#### Task 11: Frontend — Update TransactionRow and SearchResultRow

- **File:** `resources/js/components/TransactionRow.tsx`
- **Action:** Change `onTap` callback to accept an object instead of positional args:
  ```typescript
  onTap?: (data: { id: number; categoryId: number; description: string; element: HTMLElement }) => void;
  ```
  Update `onClick` to pass the object. This prevents argument-order bugs if the signature ever changes again.
- **File:** `resources/js/components/SearchResultRow.tsx`
- **Action:** Same change — object-style `onTap` callback.
- **Important:** Search codebase for any other consumers of `TransactionRow` or `SearchResultRow` and update their `onTap` handlers to use the new object signature.

#### Task 12: Frontend — Create merchant rules settings page

- **File:** `resources/js/pages/settings/merchant-rules.tsx` (new)
- **Action:** Create page following the `AppLayout > SettingsLayout` pattern.
- **Props:** `MerchantRulesProps` (receives `rules` array from controller).
- **UI:** List/table of rules, each row showing:
  - **Pattern:** The `merchantPattern` string (truncated if very long, with tooltip).
  - **Category:** Color dot + category name badge.
  - **Match count:** "N transactions" text.
  - **Actions:** "Apply" button and "Delete" button (trash icon).
- **Apply action:** On click, show a confirmation dialog: "Apply rule to {matchCount} transactions? This will update all transactions matching '{merchantPattern}' to {categoryName}, including any previously categorized." Buttons: "Cancel" / "Apply". On confirm: `router.post('/settings/merchant-rules/{id}/apply')`. In `onSuccess`, read `flash.updated_count` from Inertia page props and toast "Updated {N} transactions". Both Apply and Delete buttons are disabled while any action is in flight (`isProcessing` state).
- **Delete action:** On click, `router.delete('/settings/merchant-rules/{id}')` → toast "Rule deleted". Button disabled while processing.
- **Empty state:** When no rules exist, show a helpful message: "No merchant rules yet. Rules are created automatically when you recategorize a transaction."

#### Task 13: Backend tests

- **File:** `tests/Feature/MerchantRulesControllerTest.php` (new)
- **Tests:**
  - `test_index_lists_rules_with_match_counts`: Create rules + transactions, verify response includes correct match counts.
  - `test_destroy_deletes_rule`: Create a rule, DELETE it, verify gone.
  - `test_apply_updates_matching_transactions`: Create a rule + transactions with matching descriptions (some with different categories), POST apply, verify all now have the rule's category.
  - `test_apply_returns_updated_count`: Verify the JSON response contains the correct `updated` count.
- **File:** `tests/Feature/TransactionControllerTest.php` (existing or new)
- **Tests:**
  - `test_update_with_apply_to_all_updates_matching_transactions`: PATCH with `apply_to_all: true`, verify all matching transactions updated.
  - `test_update_without_apply_to_all_updates_only_single_transaction`: PATCH without `apply_to_all`, verify only the target transaction updated (existing behavior preserved).
  - `test_match_count_returns_correct_count`: GET match-count with a description, verify JSON count.

### Acceptance Criteria

- [ ] AC 1: Given a user on the category detail page viewing transactions, when they tap a transaction and select a new category, and there are multiple transactions with the same description, then a confirmation dialog appears showing "Just this one" and "All N matching" options with the correct count.
- [ ] AC 2: Given the confirmation dialog is shown, when the user selects "Just this one", then only the tapped transaction is recategorized and a merchant rule is created/updated.
- [ ] AC 3: Given the confirmation dialog is shown, when the user selects "All N matching", then ALL transactions with the same description are recategorized to the new category and a merchant rule is created/updated.
- [ ] AC 4: Given a user on the category detail page, when they tap a transaction and select a new category, and there is only 1 transaction with that description, then no dialog is shown and the category is applied immediately (current behavior preserved).
- [ ] AC 5: Given the same correction flow on the search page, when performing the same actions as AC 1–4, then the behavior is identical.
- [ ] AC 6: Given a user navigating the app, when they look at the bottom dock (mobile) or top nav (desktop), then a Settings icon is visible and tapping it navigates to the merchant rules settings page.
- [ ] AC 7: Given a user on the merchant rules settings page, then they see a list of all merchant rules with: the pattern text, category color dot + name, match count, Apply button, and Delete button.
- [ ] AC 8: Given a user on the merchant rules page, when they click "Apply" on a rule, then a confirmation dialog shows the impact (pattern, category, count), and upon confirming, all matching transactions are updated and a toast shows "Updated N transactions".
- [ ] AC 9: Given a user on the merchant rules page, when they click "Delete" on a rule, then the rule is removed and no longer appears in the list.
- [ ] AC 10: Given no merchant rules exist, when the user visits the merchant rules settings page, then a helpful empty state message is shown.
- [ ] AC 11: Given a bulk apply action (either inline or from settings page), when the transactions are updated, then NO `CategoryCorrected` events are fired for the bulk-updated transactions (only for the original correction in the inline flow).

## Additional Context

### Dependencies

- No new packages needed — all UI components (Dialog, Sheet, Button, etc.) already exist in shadcn/ui
- Wayfinder needs to be regenerated after adding new routes (`sail artisan wayfinder:generate`)
- `sonner` toast already in use for success feedback
- Inertia flash data: check if `HandleInertiaRequests` middleware already shares `flash` data. If not, add `'flash' => ['updated_count' => fn () => $request->session()->get('updated_count')]` to the shared props. This is how the frontend reads `updated_count` after bulk-apply actions.

### Testing Strategy

**Backend (PHPUnit):**
- Feature test for `MerchantRulesController`: index (lists rules with counts), destroy (deletes rule), apply (bulk updates transactions and returns count)
- Feature test for enhanced `TransactionController::update`: `apply_to_all` parameter correctly bulk-updates or doesn't
- Feature test for `matchCount` endpoint: returns correct count for given description

**Frontend (Vitest + testing-library):**
- Component test for `ApplyRuleConfirmDialog`: renders correctly, fires callbacks
- Page-level test for merchant rules settings page: renders rules, handles apply/delete

**Manual testing:**
- Verify inline correction flow on both category detail and search pages
- Verify settings page navigation from both mobile dock and desktop nav
- Test with 0 rules (empty state), 1 rule, many rules
- Test apply with transactions across multiple accounts

### Notes

- Current merchant rules are exact case-insensitive matches on the full description string
- Rules are created via the `UpdateMerchantRule` listener when `CategoryCorrected` event fires
- Matching happens in `MerchantMatchingService::matchBatch()` during import categorization (Pass 2)
- The `MerchantRule` model has fields: `merchant_pattern` (unique), `category_id` (FK)
- No user scoping — rules are global (single-user app)
- The category correction state machine is duplicated between `show.tsx` and `search.tsx` — both need the same modification
- `TransactionController::update` currently has no ownership check (any auth user can patch any transaction) — not in scope to fix but worth noting
- The match count fetch in the inline flow adds a small round-trip. Could be optimized later by preloading counts when the picker opens, but the simplicity of a single fetch on category selection is preferred for v1.
- Future enhancement: "Apply all rules" bulk action on the settings page (fires all rules at once). Deferred to keep scope tight.

## Review Notes

- Adversarial review completed
- Findings: 11 total, 4 fixed, 3 acknowledged (out of scope), 4 skipped (undecided/mitigated/cosmetic)
- Resolution approach: auto-fix
- **Fixed:** F4 (delete confirmation dialog), F5 (stale closure ref guard), F6 (N+1 → subquery), F9 (toast fallback)
- **Acknowledged, not fixed:** F1 (user scoping — out of scope per spec, single-user app), F2 (event inside transaction — correct behavior), F7 (duplicated state machine — future refactor)
- **Skipped:** F3 (undecided), F8 (undecided), F10 (cosmetic, linter handled), F11 (mitigated)
