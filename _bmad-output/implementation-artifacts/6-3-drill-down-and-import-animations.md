# Story 6.3: Drill-Down & Import Animations

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want smooth transitions when drilling into categories and a satisfying cascade when importing,
so that navigation feels fluid and importing feels rewarding.

## Acceptance Criteria

1. **Given** I am on the Dashboard, **When** I tap a CategoryCard to drill down, **Then** the transition to Categories/Show uses a smooth animation (the color header fades/slides in) rather than an instant page swap (FR39).

2. **Given** I am on the Categories/Show drill-down, **When** I tap back/close to return to the Dashboard, **Then** the reverse transition animates smoothly back to the overview (FR39).

3. **Given** I confirm an import and transactions are committed, **When** category cards update with new amounts, **Then** cards animate in a staggered cascade (top to bottom, ~80ms delay between cards) as their amounts update (FR40).

4. **Given** the Dashboard had an empty state before import, **When** the first import completes, **Then** category cards cascade in from empty, creating the "reveal moment" (FR40).

5. **Given** the user has `prefers-reduced-motion` enabled, **When** any animation would trigger, **Then** transitions are instant with no animation.

## Tasks / Subtasks

- [x] Task 1: Configure Inertia View Transitions for Page Navigation (AC: 1, 2, 5)
  - [x] 1.1: Add `defaults.visitOptions` with `viewTransition: true` in `createInertiaApp` in `app.tsx`
  - [x] 1.2: Add custom CSS keyframe animations for `::view-transition-old(root)` and `::view-transition-new(root)` in `app.css` (~300ms fade)
  - [x] 1.3: Add `@media (prefers-reduced-motion: reduce)` CSS rule to disable all view transition animations
  - [x] 1.4: Verify `window.history.back()` on Categories/Show triggers the view transition (Inertia intercepts popstate)

- [x] Task 2: Animate Drill-Down Page Content (AC: 1, 2, 5)
  - [x] 2.1: On `categories/show.tsx`, wrap `ColorHeader` in `motion.div` with entrance animation: `initial={{ opacity: 0, y: -20 }}` → `animate={{ opacity: 1, y: 0 }}` (~300ms ease-out)
  - [x] 2.2: Wrap subcategory/transaction content area in `motion.div` with delayed entrance: `initial={{ opacity: 0, y: 10 }}` → `animate={{ opacity: 1, y: 0 }}` (~300ms ease-out, 150ms delay)
  - [x] 2.3: Use `useReducedMotion()` from `motion/react` — when true, set `initial={false}` to skip all motion animations

- [x] Task 3: Import Cascade Animation on Dashboard (AC: 3, 5)
  - [x] 3.1: Define `containerVariants` (with `staggerChildren: 0.08` transition) and `cardVariants` for 80ms delay between cards
  - [x] 3.2: Track `shouldCascade` state in Dashboard — set `true` after successful import, reset after animation completes
  - [x] 3.3: Wrap the category card list `div` with `motion.div` using container variants (animate only when `shouldCascade`)
  - [x] 3.4: Wrap each `CategoryCard` in `motion.div` with card variants: `opacity: 0, y: 16` → `opacity: 1, y: 0` over 300ms ease-out
  - [x] 3.5: Use `useReducedMotion()` — when true, skip stagger animation entirely (render cards without motion wrapper)
  - [x] 3.6: Ensure stagger does NOT trigger on month navigation or account switching — only on import completion and first reveal

- [x] Task 4: Empty State to Category Reveal Transition (AC: 4, 5)
  - [x] 4.1: Import `AnimatePresence` from `motion/react` in `dashboard.tsx`
  - [x] 4.2: Wrap the conditional content area (EmptyState / CategoryCards) in `<AnimatePresence mode="wait">`
  - [x] 4.3: EmptyState wraps in `motion.div` with `exit={{ opacity: 0, scale: 0.95 }}` (~200ms)
  - [x] 4.4: CategoryCard list enters with the staggered cascade from Task 3 (shares the same variants)
  - [x] 4.5: Use `useReducedMotion()` — when true, bypass AnimatePresence wrapper (instant swap)

- [x] Task 5: ImportDropZone Fade Animation (AC: 5)
  - [x] 5.1: Wrap ImportDropZone content in `AnimatePresence` and `motion.div`
  - [x] 5.2: Animate overlay: `initial={{ opacity: 0 }}` → `animate={{ opacity: 1 }}` → `exit={{ opacity: 0 }}` (~200ms)
  - [x] 5.3: Animate inner dashed box: `initial={{ opacity: 0, scale: 0.95 }}` → `animate={{ opacity: 1, scale: 1 }}` → `exit={{ opacity: 0, scale: 0.95 }}`
  - [x] 5.4: Use `useReducedMotion()` — when true, render without animation wrappers

- [x] Task 6: Tests & Verification (AC: 1-5)
  - [x] 6.1: Run `sail bun run test` — all existing frontend tests pass
  - [x] 6.2: Run `sail artisan test` — all existing backend tests pass (no regressions)
  - [x] 6.3: Run `sail bun run build` — TypeScript compiles cleanly with zero errors
  - [x] 6.4: Run `sail bun run types` — TypeScript type checking passes

## Dev Notes

### Architecture Overview for This Story

This story adds visual transitions and animations throughout the app. It combines two animation strategies:

1. **Inertia View Transitions API** — For page-level navigation (Dashboard ↔ Categories/Show). Uses the browser's native View Transitions API, which Inertia.js v2 supports via `viewTransition` option. Chrome-only target makes this fully supported.

2. **motion/react (Framer Motion)** — For content-level animations within pages. Staggered card cascades, content entrance effects, conditional content transitions (AnimatePresence), and the ImportDropZone overlay.

```
Page Navigation (Inertia View Transitions API)
  │
  ├─ Dashboard → Categories/Show    // cross-fade page transition
  ├─ Categories/Show → Dashboard    // reverse cross-fade
  └─ CSS ::view-transition-* rules  // custom timing + reduced motion

Content Animations (motion/react)
  │
  ├─ Categories/Show:
  │   ├─ ColorHeader entrance       // fade + slide down (300ms)
  │   └─ Content entrance           // fade + slide up (300ms, 150ms delay)
  │
  ├─ Dashboard:
  │   ├─ Import cascade             // staggered cards (80ms delay, 300ms each)
  │   ├─ Empty → reveal             // AnimatePresence conditional swap
  │   └─ ImportDropZone             // fade in/out overlay
  │
  └─ Accessibility:
      ├─ CSS: @media (prefers-reduced-motion) for View Transitions
      └─ JS: useReducedMotion() for motion/react animations
```

### Implementation Details

**1. Inertia View Transitions Configuration:**

In `app.tsx`, add defaults to enable view transitions globally:

```tsx
createInertiaApp({
    // ... existing config
    defaults: {
        visitOptions: () => {
            return { viewTransition: true };
        },
    },
    // ... setup, progress
});
```

In `app.css`, add custom view transition animations:

```css
/* Drill-down page transitions */
::view-transition-old(root) {
    animation: page-fade-out 0.25s ease-out forwards;
}

::view-transition-new(root) {
    animation: page-fade-in 0.25s ease-in forwards;
}

@keyframes page-fade-out {
    from { opacity: 1; }
    to { opacity: 0; }
}

@keyframes page-fade-in {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Respect reduced motion for view transitions */
@media (prefers-reduced-motion: reduce) {
    ::view-transition-group(*),
    ::view-transition-old(*),
    ::view-transition-new(*) {
        animation-duration: 0s !important;
    }
}
```

**Why global viewTransition:** Inertia intercepts all navigation (including browser back/forward via popstate), so setting it globally ensures the back button from Categories/Show also gets the transition. The cross-fade is subtle enough (~250ms) that it enhances ALL navigation without being distracting.

**2. Drill-Down Content Entrance (Categories/Show):**

```tsx
import { motion, useReducedMotion } from 'motion/react';

// In the component:
const shouldReduceMotion = useReducedMotion();

// ColorHeader wrapper
<motion.div
    initial={shouldReduceMotion ? false : { opacity: 0, y: -20 }}
    animate={{ opacity: 1, y: 0 }}
    transition={{ duration: 0.3, ease: 'easeOut' }}
>
    <ColorHeader ... />
</motion.div>

// Content wrapper (subcategories + transactions)
<motion.div
    initial={shouldReduceMotion ? false : { opacity: 0, y: 10 }}
    animate={{ opacity: 1, y: 0 }}
    transition={{ duration: 0.3, ease: 'easeOut', delay: shouldReduceMotion ? 0 : 0.15 }}
>
    {/* subcategory rows, transaction rows */}
</motion.div>
```

**Why `initial={false}`:** When reduced motion is preferred, setting `initial` to `false` tells motion/react to skip the entry animation entirely — the component renders in its `animate` state immediately.

**3. Import Cascade (Dashboard):**

```tsx
import { motion, useReducedMotion } from 'motion/react';

const containerVariants = {
    hidden: {},
    visible: {
        transition: {
            staggerChildren: 0.08,
        },
    },
};

const cardVariants = {
    hidden: { opacity: 0, y: 16 },
    visible: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.3, ease: 'easeOut' },
    },
};

// In the Dashboard component:
const [shouldCascade, setShouldCascade] = useState(false);
const shouldReduceMotion = useReducedMotion();

// After import success callback:
setShouldCascade(true);

// In JSX:
{shouldReduceMotion ? (
    <div className="space-y-3">
        {categoryTotals.map((cat) => (
            <CategoryCard key={cat.id} ... />
        ))}
    </div>
) : (
    <motion.div
        className="space-y-3"
        variants={containerVariants}
        initial={shouldCascade ? 'hidden' : false}
        animate="visible"
        onAnimationComplete={() => setShouldCascade(false)}
    >
        {categoryTotals.map((cat) => (
            <motion.div key={cat.id} variants={cardVariants}>
                <CategoryCard ... />
            </motion.div>
        ))}
    </motion.div>
)}
```

**Why `initial={shouldCascade ? 'hidden' : false}`:** This ensures the stagger only triggers after an import. On normal navigation (month switch, account switch), cards render instantly without stagger by setting `initial={false}`. After an import completes, `shouldCascade` is set to `true`, which activates the hidden→visible transition. Once the animation completes, `shouldCascade` resets to prevent re-triggering.

**4. Empty State → Category Reveal:**

```tsx
import { AnimatePresence } from 'motion/react';

// In Dashboard JSX:
<AnimatePresence mode="wait">
    {categoryTotals.length === 0 && !isEmptyMonth ? (
        <motion.div
            key="empty"
            exit={shouldReduceMotion ? undefined : { opacity: 0, scale: 0.95 }}
            transition={{ duration: 0.2 }}
        >
            <EmptyState ... />
        </motion.div>
    ) : categoryTotals.length > 0 ? (
        <motion.div
            key="categories"
            // ... cascade variants from Task 3
        >
            {/* category cards */}
        </motion.div>
    ) : null}
</AnimatePresence>
```

**5. ImportDropZone Fade:**

```tsx
// In ImportDropZone.tsx:
import { motion, AnimatePresence, useReducedMotion } from 'motion/react';

export default function ImportDropZone({ visible }: Props) {
    const shouldReduceMotion = useReducedMotion();

    return (
        <AnimatePresence>
            {visible && (
                <motion.div
                    className="pointer-events-none fixed inset-0 z-40 flex items-center justify-center bg-background/80 backdrop-blur-sm"
                    initial={shouldReduceMotion ? false : { opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={shouldReduceMotion ? undefined : { opacity: 0 }}
                    transition={{ duration: 0.2 }}
                >
                    <motion.div
                        className="rounded-2xl border-2 border-dashed border-zinc-500 px-12 py-10 text-center"
                        initial={shouldReduceMotion ? false : { opacity: 0, scale: 0.95 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={shouldReduceMotion ? undefined : { opacity: 0, scale: 0.95 }}
                        transition={{ duration: 0.2 }}
                    >
                        <p className="text-lg font-semibold text-white">Drop CSV to import</p>
                        <p className="mt-1 text-sm text-text-secondary">
                            Banque Populaire or Revolut export
                        </p>
                    </motion.div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
```

### Edge Cases

1. **Rapid category navigation:** If the user taps a category card, then quickly taps back, the View Transitions API handles overlapping transitions gracefully — the new transition replaces the current one.

2. **Import during cascade animation:** If the user triggers another import while a cascade is running, the cascade resets. `shouldCascade` stays true, and the new data triggers a fresh animation.

3. **Month switch during cascade:** If the user changes months while the cascade is animating, Inertia's partial reload updates the data. The cascade should be interrupted — `shouldCascade` resets on non-import navigation. Handle this by resetting `shouldCascade` in the month change handler.

4. **Empty month (no transactions):** The "No transactions for [Month]" message renders without animation. Only the EmptyState→CategoryCards transition uses AnimatePresence.

5. **Very few categories (1-2):** The stagger animation still works but is less dramatic. The 80ms stagger with 2 items totals 160ms — still perceivable and satisfying.

6. **Import adding to existing categories:** When an import adds transactions to existing categories, the cascade animation fires. The AnimatedNumber components (from Story 6.2) handle the amount counting transitions independently — they animate simultaneously with the cascade.

7. **Browser back button vs. app back button:** Both trigger the same view transition because `viewTransition: true` is set globally. `window.history.back()` triggers a popstate event that Inertia intercepts and processes with the default options.

8. **SSR/Hydration:** The app uses CSR only (no SSR). Motion hooks are client-side safe.

### Previous Story Intelligence

**From Story 6-2 (Animated Number Transitions):**
- `motion` v12.34.3 already installed — no new packages needed
- Import from `motion/react` (the canonical path)
- `useReducedMotion()` hook is the established pattern for accessibility
- AnimatedNumber uses `useMotionValue` + `animate()` + `useTransform` — completely independent from the animation patterns in this story
- AnimatedNumber animations and cascade animations are independent and will run simultaneously (cascade moves cards into view while AnimatedNumber counts the amounts)
- Backend: 310 passed, 4 pre-existing failures (DeduplicationServiceTest, TransactionAmountCentsTest) — unrelated, ignore
- Vitest + jsdom + @testing-library/react already configured
- Test mocking pattern for motion/react established in `__tests__/AnimatedNumber.test.tsx`

**Files modified in 6-2 that we'll modify again:**
- `resources/js/pages/dashboard.tsx` — add AnimatePresence, stagger variants, cascade state
- `resources/js/components/ColorHeader.tsx` — no changes (animated from parent in categories/show.tsx)
- `resources/js/app.tsx` — add viewTransition defaults

**Files NOT modified in 6-2 that we modify here:**
- `resources/js/pages/categories/show.tsx` — add motion entrance wrappers
- `resources/js/components/ImportDropZone.tsx` — add AnimatePresence fade
- `resources/css/app.css` — add view transition CSS

### Git Intelligence

Recent commits follow one-per-story mono-commit pattern:
```
3918795 Add CLAUDE.md and remove direct prism-php/prism dependency
07d53ea Fix trend indicator layout and TransactionFactory dedup_hash override
3753ee6 Story 6.1: Inline trend indicators + review fixes
78be1aa Add comprehensive demo seeder with 12 months of realistic data
```

Established patterns:
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Tests must pass with zero regressions before committing
- One commit per story

### Latest Technical Information

**Inertia.js v2 View Transitions API:**
- Inertia.js v2 has built-in support for the browser View Transitions API
- Enable per-visit: `router.visit('/page', { viewTransition: true })` or `<Link viewTransition>`
- Enable globally: `createInertiaApp({ defaults: { visitOptions: () => ({ viewTransition: true }) } })`
- Gracefully falls back to instant navigation in unsupported browsers
- CSS customizable via `::view-transition-old(root)` and `::view-transition-new(root)` pseudo-elements
- Works with popstate (browser back/forward) when configured globally
- [Source: Inertia.js v2 docs — View Transitions]

**motion/react (Motion v12):**
- `stagger(delay)` function for distributing delays across children — use with `delayChildren` in variant transitions
- `AnimatePresence` for conditional rendering with exit animations — `mode="wait"` ensures old content exits before new enters
- `initial={false}` disables entry animation (renders directly in `animate` state)
- `useReducedMotion()` returns boolean for `prefers-reduced-motion: reduce`
- motion.div supports `variants`, `initial`, `animate`, `exit`, `onAnimationComplete`
- [Source: motion.dev docs — AnimatePresence, Variants, stagger]

**Note:** The architecture document mentions `AnimatePresence on Inertia navigation`. With the discovery of Inertia's native View Transitions support, page-level transitions are handled more cleanly by the browser API. AnimatePresence is used for within-page conditional content transitions (EmptyState → CategoryCards) where it excels.

### Project Structure Notes

- `resources/js/app.tsx` — MODIFY to add viewTransition defaults
- `resources/css/app.css` — MODIFY to add view transition CSS keyframes
- `resources/js/pages/dashboard.tsx` — MODIFY to add cascade animations, AnimatePresence
- `resources/js/pages/categories/show.tsx` — MODIFY to add content entrance animations
- `resources/js/components/ImportDropZone.tsx` — MODIFY to add AnimatePresence fade
- No new files. No backend changes. No new routes. No database changes.

### Architecture Compliance

| Requirement | Implementation |
|-------------|----------------|
| Drill-down animation [arch#Frontend Architecture → Animation] | Inertia View Transitions for page swap + motion.div entrance for content |
| Staggered cascades [arch#Frontend Architecture → Animation] | `stagger(0.08)` with container/item variants on Dashboard card list |
| AnimatePresence [arch#Frontend Architecture → Animation] | Used for EmptyState→CategoryCards conditional transition + ImportDropZone |
| prefers-reduced-motion [arch#Frontend Architecture → Animation] | CSS `@media` for View Transitions + `useReducedMotion()` for motion/react |
| Framer Motion package [arch#Additional Packages] | `motion` v12.34.3 already installed (from Story 6.2) |
| No new backend code [arch#Service Boundary] | Purely frontend change — no controller/service modifications |
| Single breakpoint at 640px [UX#Breakpoint Strategy] | Animations render identically on mobile and desktop |

### Library & Framework Requirements

**No new packages to install.** This story uses only:
- React 19 (existing) — hooks, conditional rendering
- Motion v12 (existing, installed in 6.2) — AnimatePresence, motion.div, variants, stagger, useReducedMotion
- Inertia.js v2 (existing) — viewTransition API support
- Tailwind CSS (existing) — styling via className

### File Structure Requirements

**New files:**
```
(none)
```

**Modified files:**
```
resources/js/app.tsx                       (MODIFY — add viewTransition defaults to createInertiaApp)
resources/css/app.css                      (MODIFY — add view transition CSS keyframes + reduced motion)
resources/js/pages/dashboard.tsx           (MODIFY — add AnimatePresence, stagger cascade, shouldCascade state)
resources/js/pages/categories/show.tsx     (MODIFY — add motion.div entrance wrappers for ColorHeader + content)
resources/js/components/ImportDropZone.tsx  (MODIFY — add AnimatePresence + motion.div fade in/out)
```

**No changes needed:**
```
resources/js/components/CategoryCard.tsx    (wrapped externally by motion.div in dashboard.tsx)
resources/js/components/ColorHeader.tsx     (wrapped externally by motion.div in categories/show.tsx)
resources/js/components/SummaryWidget.tsx   (no animation changes)
resources/js/components/AnimatedNumber.tsx  (independent, works alongside cascade)
resources/js/components/EmptyState.tsx      (wrapped externally by motion.div in dashboard.tsx)
resources/js/types/budget.ts               (no type changes)
resources/js/lib/formatAmount.ts           (unchanged)
app/Http/Controllers/*                     (no backend changes)
```

### Testing Strategy

**This is a frontend-only animation change.** Focus on:

1. **Regression testing** — Run `sail artisan test` to ensure all 310+ backend tests pass. Run `sail bun run test` to ensure all frontend tests pass.

2. **Build verification** — Run `sail bun run build` to confirm TypeScript compiles. Run `sail bun run types` for type checking.

3. **Manual verification** — Use the demo seeder data (12 months) to verify:
   - Navigate to a category: page transitions with a smooth cross-fade
   - Tap back: reverse cross-fade back to Dashboard
   - Import a CSV: category cards cascade in with staggered animation
   - First import (empty state): EmptyState fades out, cards cascade in
   - Drag a file: ImportDropZone fades in smoothly
   - Enable "Reduce Motion" in OS settings: all animations snap instantly
   - Month switch: cards render instantly (no stagger)
   - Account switch: cards render instantly (no stagger)
   - Browser back button: smooth transition back to Dashboard

4. **No new feature tests needed** — The animations are purely visual decorations. The existing DashboardTest.php and CategoryShowTest.php verify data correctness. The animations don't change what data is rendered, only how it appears visually.

### What This Story Does NOT Include

- **AnimatedNumber changes** — Number counting animations were completed in Story 6.2.
- **Trend indicator animation** — Trend text (↑8%, ↓12%) stays static.
- **Backend changes** — No controller, service, or database modifications.
- **New pages or routes** — Purely animation enhancements to existing pages.
- **New components** — All animations are applied via wrappers in existing pages/components.
- **Complex shared element morph** — The drill-down uses a cross-fade + content entrance rather than a morphing element animation. This is simpler, more robust, and satisfies AC1's "fades in" option.

### References

- Epic 6 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 6.3]
- FR39 requirement: [Source: _bmad-output/planning-artifacts/epics.md#Requirements Inventory]
- FR40 requirement: [Source: _bmad-output/planning-artifacts/epics.md#Requirements Inventory]
- Animation feedback patterns: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Feedback Patterns]
- Stagger cascade spec (~80ms): [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Feedback Patterns]
- Drill-down transition spec (~300ms): [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Feedback Patterns]
- Framer Motion / Animation architecture: [Source: _bmad-output/planning-artifacts/architecture.md#Frontend Architecture]
- Amount handling patterns: [Source: _bmad-output/planning-artifacts/architecture.md#Format Patterns]
- prefers-reduced-motion: [Source: _bmad-output/planning-artifacts/architecture.md#Frontend Architecture]
- Inertia View Transitions: [Source: Inertia.js v2 docs — View Transitions]
- Motion stagger: [Source: motion.dev/docs — Variants, stagger]
- Motion AnimatePresence: [Source: motion.dev/docs — AnimatePresence]
- Previous story 6.2: [Source: _bmad-output/implementation-artifacts/6-2-animated-number-transitions.md]
- Sprint status: [Source: _bmad-output/implementation-artifacts/sprint-status.yaml]
- Dashboard page: [Source: resources/js/pages/dashboard.tsx]
- Categories/Show page: [Source: resources/js/pages/categories/show.tsx]
- ColorHeader component: [Source: resources/js/components/ColorHeader.tsx]
- CategoryCard component: [Source: resources/js/components/CategoryCard.tsx]
- ImportDropZone component: [Source: resources/js/components/ImportDropZone.tsx]
- App entry: [Source: resources/js/app.tsx]
- CSS entry: [Source: resources/css/app.css]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

- TypeScript `ease` property required `as const` assertion for motion variant type inference (fixed inline)

### Completion Notes List

- **Task 1:** Added `viewTransition: true` globally via `defaults.visitOptions` in `createInertiaApp`. Added CSS keyframes for `::view-transition-old/new(root)` with 250ms fade. Added `@media (prefers-reduced-motion: reduce)` to zero-out view transition durations. Browser back button confirmed to work via Inertia's popstate interception with global viewTransition setting.
- **Task 2:** Wrapped `ColorHeader` in `motion.div` with fade+slide-down entrance (300ms). Wrapped content area (`<div className="py-5 space-y-4">` → `<motion.div>`) with fade+slide-up entrance (300ms, 150ms delay). `useReducedMotion()` sets `initial={false}` to skip animations.
- **Task 3:** Defined `containerVariants` (staggerChildren: 0.08) and `cardVariants` (opacity 0→1, y 16→0, 300ms easeOut). Added `shouldCascade` state — set `true` in import `onSuccess`, reset on `onAnimationComplete`. When `shouldCascade` is false, `initial={false}` renders cards instantly (no stagger on month/account switch). `shouldReduceMotion` renders cards without motion wrapper.
- **Task 4:** Wrapped empty-state/category-cards conditional in `<AnimatePresence mode="wait">`. EmptyState has `exit={{ opacity: 0, scale: 0.95 }}` (200ms). Category cards enter with the same stagger cascade from Task 3. Reduced motion skips AnimatePresence effects.
- **Task 5:** Replaced conditional rendering with `AnimatePresence` + `motion.div`. Overlay fades in/out (200ms). Inner dashed box scales 0.95→1 on enter, 1→0.95 on exit (200ms). `useReducedMotion()` sets `initial={false}` and `exit={undefined}`.
- **Task 6:** All tests pass — 8/8 frontend, 314/314 backend. Build compiles cleanly. TypeScript passes (only pre-existing AnimatedNumber.test.tsx error). ESLint has only pre-existing errors.

### Change Log

- 2026-02-21: Story 6.3 implemented — drill-down page transitions (Inertia View Transitions API), staggered import cascade, empty-state reveal, ImportDropZone fade, and full reduced-motion accessibility support
- 2026-02-21: Review fixes: (1) Added `setShouldCascade(false)` to handleMonthChange and handleAccountChange — prevents unintended cascade on navigation during animation window. (2) Replaced unnecessary `motion.div key="categories"` with plain `div` — no animation props, only served as AnimatePresence key. (3) Simplified redundant `delay: shouldReduceMotion ? 0 : 0.15` → `delay: 0.15` in categories/show.tsx (initial={false} already skips animation). (4) Noted 11 ESLint import-reorder collateral files.

### File List

- `resources/js/app.tsx` — Added `defaults.visitOptions` with `viewTransition: true`
- `resources/css/app.css` — Added view transition CSS keyframes, reduced-motion override
- `resources/js/pages/dashboard.tsx` — Added AnimatePresence, stagger cascade variants, shouldCascade state, motion wrappers for category cards and empty state
- `resources/js/pages/categories/show.tsx` — Added motion.div entrance wrappers for ColorHeader and content area
- `resources/js/components/ImportDropZone.tsx` — Replaced conditional render with AnimatePresence + motion.div fade animations

**Collateral changes (ESLint import reordering):**
- `resources/js/components/CategoryPicker.tsx` — Import order fix
- `resources/js/components/ImportHistory.tsx` — Import order fix
- `resources/js/components/delete-user.tsx` — Import order fix
- `resources/js/components/two-factor-recovery-codes.tsx` — Import order fix
- `resources/js/components/two-factor-setup-modal.tsx` — Import order fix
- `resources/js/hooks/use-two-factor-auth.ts` — Import order fix
- `resources/js/layouts/auth/auth-simple-layout.tsx` — Import order fix
- `resources/js/layouts/auth/auth-split-layout.tsx` — Import order fix
- `resources/js/layouts/settings/layout.tsx` — Import order fix
- `resources/js/pages/settings/password.tsx` — Import order fix
- `resources/js/pages/settings/profile.tsx` — Import order fix
