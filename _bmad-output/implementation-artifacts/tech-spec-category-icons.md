---
title: 'Category Icons'
slug: 'category-icons'
created: '2026-02-21'
status: 'completed'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Laravel 12', 'React 19', 'Inertia.js 2', 'lucide-react', 'Tailwind CSS 4', 'PostgreSQL']
files_to_modify: ['database/seeders/CategorySeeder.php', 'app/Services/BudgetQueryService.php', 'app/Http/Controllers/CategoryController.php', 'app/Http/Controllers/SearchController.php', 'resources/js/types/budget.ts', 'resources/js/lib/category-icons.ts', 'resources/js/components/CategoryCard.tsx', 'resources/js/components/CategoryPicker.tsx', 'resources/js/components/ColorHeader.tsx', 'resources/js/pages/dashboard.tsx', 'resources/js/pages/categories/show.tsx']
code_patterns: ['No API Resources - manual SELECT + array mapping in controllers/services', 'Inertia::render() passes props directly', 'lucide-react icons imported as named components', 'Tailwind utility classes for styling', 'motion/react for animations']
test_patterns: ['PHPUnit feature tests with RefreshDatabase', 'Inertia AssertableInertia for prop assertions', 'Vitest + @testing-library/react for frontend (no existing tests for CategoryCard/CategoryPicker)']
---

# Tech-Spec: Category Icons

**Created:** 2026-02-21

## Overview

### Problem Statement

Categories only display colored squares with no visual icon, making it harder to quickly identify them at a glance. Adding representative icons (like the Motivated app) would improve visual recognition and UX.

### Solution

Add a lucide-react icon to each of the 14 top-level categories, rendered as a dark semi-transparent icon (`text-black/60`) centered on the colored rounded square. Icons are hardcoded per category in the seeder and passed through to all frontend components that display categories.

### Scope

**In Scope:**
- Populate `icon` field in CategorySeeder with appropriate lucide icon names
- Add `icon` to TypeScript types (CategoryTotal, CategoryOption, CategoryShowProps)
- Update controllers/services to pass icon data to frontend
- Update CategoryCard (dashboard), CategoryPicker, and category detail header to render icons
- Create icon lookup utility (icon name string → LucideIcon component)

**Out of Scope:**
- User-editable icons (UI icon picker)
- Subcategory icons
- New migration (icon column already exists in categories table)
- SearchResultRow and RecurringMerchantCard (secondary views)

## Context for Development

### Codebase Patterns

- **No API Resources**: Category data is manually selected and mapped in controllers and `BudgetQueryService`. Each location independently selects columns and maps to arrays.
- **Inertia data flow**: Controllers call services → build arrays → `Inertia::render()` → React page components receive as props.
- **Icon pattern**: lucide-react icons are imported as named components (e.g., `import { Apple } from 'lucide-react'`). The `LucideIcon` type is used for icon component references. NavItem type already uses `icon?: LucideIcon | null`.
- **Color display**: `CategoryCard` uses a `w-12 h-12 rounded-xl` div with `backgroundColor`. `CategoryPicker` uses `size-4 rounded-full` / `size-3.5 rounded-full` spans. `ColorHeader` uses a `w-16 h-16 rounded-2xl` div with `rgba(0,0,0,0.15)` background.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `database/seeders/CategorySeeder.php` | Seeds 14 categories with `icon: null` |
| `app/Services/BudgetQueryService.php` | `getMonthlyData()` builds categoryTotals — SELECTs id, name, slug, color (no icon) |
| `app/Http/Controllers/CategoryController.php` | `show()` passes single category + allCategories — excludes icon |
| `app/Http/Controllers/SearchController.php` | `index()` passes allCategories — excludes icon |
| `resources/js/types/budget.ts` | `CategoryTotal`, `CategoryOption`, `CategoryShowProps` — no icon field |
| `resources/js/components/CategoryCard.tsx` | Dashboard card — colored `w-12 h-12 rounded-xl` div, no icon |
| `resources/js/components/CategoryPicker.tsx` | Category selector — colored `size-4 rounded-full` circle, no icon |
| `resources/js/components/ColorHeader.tsx` | Category detail header — `w-16 h-16 rounded-2xl` dark overlay square, no icon |
| `resources/js/pages/dashboard.tsx` | Passes `cat.color` to CategoryCard (will need `cat.icon`) |
| `resources/js/pages/categories/show.tsx` | Passes `category.color` to ColorHeader (will need `category.icon`) |
| `tests/Feature/CategorySeederTest.php` | Tests seeder creates 14 categories with correct data |
| `tests/Feature/CategoryControllerTest.php` | Tests category show returns correct Inertia props |

### Technical Decisions

**Icon Library:** lucide-react (already installed)

**Icon Style:** Dark semi-transparent (`text-black/60`) centered on colored rounded square background.

**Icon Mapping:** Static `Record<string, LucideIcon>` lookup in `resources/js/lib/category-icons.ts`. All 14 icons imported at the top. No dynamic imports — zero runtime overhead.

**Icon Field:** Stored as kebab-case lucide icon name string in DB (e.g., `"apple"`, `"shopping-bag"`). Mapped to component via lookup at render time.

**Icon Assignments:**
| Category | Lucide Icon Name |
|---|---|
| Food | `apple` |
| Restaurants | `utensils` |
| Transport | `car` |
| Housing | `house` |
| Entertainment | `film` |
| Shopping | `shopping-bag` |
| Health | `heart-pulse` |
| Subscriptions | `credit-card` |
| Education | `graduation-cap` |
| Travel | `plane` |
| Insurance | `shield` |
| Income | `wallet` |
| Professional | `briefcase` |
| Other | `ellipsis` |

## Implementation Plan

### Tasks

Tasks are ordered by dependency (data layer first, then types, then UI).

- [x] Task 1: Populate icon values in CategorySeeder
  - File: `database/seeders/CategorySeeder.php`
  - Action: Replace `'icon' => null` with the corresponding lucide icon name string for each of the 14 categories (e.g., `'icon' => 'apple'` for Food). Use the Icon Assignments table above.
  - Notes: Run `sail artisan db:seed --class=CategorySeeder` after to update existing DB rows (seeder uses `updateOrCreate`).

- [x] Task 2: Add icon to BudgetQueryService categoryTotals query
  - File: `app/Services/BudgetQueryService.php`
  - Action: In `getMonthlyData()`, add `'icon'` to the SELECT columns list (around line 14-26) and add `'icon' => $row->icon` to the mapped array (around line 35-42).
  - Notes: The icon field is nullable — categories without icons will pass `null`.

- [x] Task 3: Add icon to CategoryController responses
  - File: `app/Http/Controllers/CategoryController.php`
  - Action: (a) In `show()`, add `'icon'` to the single category array (lines 33-38). (b) In the `allCategories` query (line 45-47), add `'icon'` to the `get()` column list and the `map()` output array.
  - Notes: Both the category detail and the picker receive icon data.

- [x] Task 4: Add icon to SearchController allCategories
  - File: `app/Http/Controllers/SearchController.php`
  - Action: In `index()`, add `'icon'` to the `get()` column list (line 35) and the `map()` output array (lines 37-41).
  - Notes: Mirrors the same pattern as CategoryController.

- [x] Task 5: Add icon to TypeScript types
  - File: `resources/js/types/budget.ts`
  - Action: Add `icon: string | null;` to: `CategoryTotal`, `CategoryOption`, and `CategoryShowProps.category`.
  - Notes: Nullable because subcategories or future categories might not have icons.

- [x] Task 6: Create icon lookup utility
  - File: `resources/js/lib/category-icons.ts` (NEW)
  - Action: Create a file that imports all 14 lucide icons and exports a `Record<string, LucideIcon>` map keyed by the kebab-case icon name. Export a `getCategoryIcon(iconName: string | null): LucideIcon | null` helper function.
  - Notes: Example structure:
    ```typescript
    import type { LucideIcon } from 'lucide-react';
    import { Apple, Utensils, Car, House, Film, ShoppingBag, HeartPulse, CreditCard, GraduationCap, Plane, Shield, Wallet, Briefcase, Ellipsis } from 'lucide-react';

    const iconMap: Record<string, LucideIcon> = {
      'apple': Apple,
      'utensils': Utensils,
      'car': Car,
      'house': House,
      'film': Film,
      'shopping-bag': ShoppingBag,
      'heart-pulse': HeartPulse,
      'credit-card': CreditCard,
      'graduation-cap': GraduationCap,
      'plane': Plane,
      'shield': Shield,
      'wallet': Wallet,
      'briefcase': Briefcase,
      'ellipsis': Ellipsis,
    };

    export function getCategoryIcon(iconName: string | null): LucideIcon | null {
      if (!iconName) return null;
      return iconMap[iconName] ?? null;
    }
    ```

- [x] Task 7: Update CategoryCard to render icon
  - File: `resources/js/components/CategoryCard.tsx`
  - Action: (a) Add `icon: string | null` to Props type. (b) Import `getCategoryIcon` from `@/lib/category-icons`. (c) Inside the colored `w-12 h-12 rounded-xl` div, render the icon component centered with `className="size-6 text-black/60"`. Add `flex items-center justify-center` to the div.
  - Notes: If icon is null, fall back to empty colored square (current behavior).

- [x] Task 8: Pass icon to CategoryCard from dashboard
  - File: `resources/js/pages/dashboard.tsx`
  - Action: Add `icon={cat.icon}` prop to both `<CategoryCard>` render locations (reduced motion and animated paths, around lines 248-258 and 270-280).
  - Notes: Two render paths exist — one for reduced motion, one for animated. Both need the prop.

- [x] Task 9: Update CategoryPicker to render icon
  - File: `resources/js/components/CategoryPicker.tsx`
  - Action: (a) Import `getCategoryIcon` from `@/lib/category-icons`. (b) In the `CategoryItem` component, replace the colored circle `<span>` with a colored circle containing the icon. Change from `size-4 rounded-full` to `size-7 rounded-lg flex items-center justify-center` with the icon rendered as `className="size-4 text-black/60"` inside. (c) Do the same for the desktop `DropdownMenuItem` span (change from `size-3.5 rounded-full` to `size-6 rounded-md flex items-center justify-center`).
  - Notes: Add `icon` to `CategoryOption` type (done in Task 5). The `CategoryPicker` receives `CategoryOption[]` which will now include icon.

- [x] Task 10: Update ColorHeader to render icon
  - File: `resources/js/components/ColorHeader.tsx`
  - Action: (a) Add `icon: string | null` to Props type. (b) Import `getCategoryIcon` from `@/lib/category-icons`. (c) Inside the existing `w-16 h-16 rounded-2xl` dark overlay div, render the icon centered with `className="size-8 text-black/60"`. Add `flex items-center justify-center` to the div.
  - Notes: The div already has `backgroundColor: 'rgba(0,0,0,0.15)'` — the icon sits on top of this overlay.

- [x] Task 11: Pass icon to ColorHeader from category show page
  - File: `resources/js/pages/categories/show.tsx`
  - Action: Add `icon={category.icon}` prop to the `<ColorHeader>` component (around line 96).
  - Notes: `category.icon` comes from `CategoryShowProps` updated in Task 5.

- [x] Task 12: Update backend tests
  - File: `tests/Feature/CategorySeederTest.php`
  - Action: Add a test `test_food_category_has_correct_icon` that verifies `$food->icon === 'apple'`. Add a test `test_all_categories_have_icons` that verifies no top-level category has `icon: null`.
  - File: `tests/Feature/CategoryControllerTest.php`
  - Action: In `test_show_returns_inertia_response_with_category_data`, add `->where('category.icon', $category->icon)`. In `test_show_includes_all_categories_for_picker`, add `->has('icon')` to the allCategories assertion.

### Acceptance Criteria

- [x] AC 1: Given the CategorySeeder has been run, when querying all top-level categories, then each of the 14 categories has a non-null `icon` field matching the Icon Assignments table.
- [x] AC 2: Given a user views the dashboard with transactions, when the category cards render, then each card displays the category's lucide icon centered in the colored square with `text-black/60` styling.
- [x] AC 3: Given a user taps a transaction to change its category, when the CategoryPicker opens, then each category option shows its icon inside a small colored shape next to the name.
- [x] AC 4: Given a user navigates to a category detail page, when the ColorHeader renders, then the icon is displayed centered in the large colored header square with `text-black/60` styling.
- [x] AC 5: Given a category has `icon: null` (e.g., a subcategory), when the component renders, then it falls back to the current behavior (colored shape with no icon) without errors.
- [x] AC 6: Given the seeder is run twice (idempotent), when checking the database, then exactly 14 categories exist and all have correct icon values.

## Additional Context

### Dependencies

- **lucide-react**: Already installed. No new dependencies required.
- **Database**: The `icon` VARCHAR column already exists in the `categories` table (nullable). No migration needed.
- **Seeder re-run**: After deploying, run `sail artisan db:seed --class=CategorySeeder` to populate icon values for existing categories (uses `updateOrCreate`).

### Testing Strategy

**Backend (PHPUnit):**
- Update `CategorySeederTest` to verify icon values are populated correctly
- Update `CategoryControllerTest` to verify icon is included in Inertia responses
- All existing tests must continue to pass

**Frontend (Manual):**
- Verify dashboard category cards show icons
- Verify CategoryPicker shows icons in both mobile (Sheet) and desktop (DropdownMenu) modes
- Verify category detail page ColorHeader shows icon
- Verify categories with null icons gracefully fall back to empty colored shape

### Notes

- **Future enhancement**: If users want custom icons, the DB field is already a string — a future icon picker could update this field without schema changes.
- **Icon sizing**: `size-6` (24px) for dashboard cards, `size-4` (16px) for picker items, `size-8` (32px) for the detail header — proportional to each component's colored shape.
- **Picker shape change**: The CategoryPicker currently uses `rounded-full` circles. The spec changes these to `rounded-lg` / `rounded-md` squares to better house the icons (matching the Motivated app style). This is a visual change that improves consistency with CategoryCard.

## Review Notes
- Adversarial review completed
- Findings: 2 total, 2 fixed, 0 skipped
- Resolution approach: auto-fix
- F1 (Important): Added missing `icon` field to `getMonthlyComparison` response array
- F2 (Important): Changed ColorHeader icon to use computed `textSecondary` class instead of hardcoded `text-black/60` for proper contrast on dark backgrounds
