# Story 6.2: Animated Number Transitions

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want category amounts to animate smoothly when values change,
so that the interface feels responsive and alive rather than static.

## Acceptance Criteria

1. **Given** I am on the Dashboard viewing category amounts, **When** I navigate to a different month via the MonthStrip, **Then** category amounts animate from old values to new values with a smooth counting transition (~400ms duration, ease-out) (FR38).

2. **Given** an import completes and category amounts update, **When** the Dashboard re-renders with new data, **Then** amounts animate from previous values to updated values (FR38).

3. **Given** the SummaryWidget shows Income and Expenses, **When** values change (month navigation or import), **Then** those amounts also animate with the same counting transition.

4. **Given** the user has `prefers-reduced-motion` enabled, **When** any animation would trigger, **Then** values update instantly with no animation.

## Tasks / Subtasks

- [x] Task 1: Install Framer Motion (AC: 1-4)
  - [x] 1.1: Run `sail bun add motion` to install the Motion package (rebranded from framer-motion, v12.x)
  - [x] 1.2: Verify `motion` appears in `package.json` dependencies
  - [x] 1.3: Run `sail bun run build` to confirm TypeScript compiles with the new dependency

- [x] Task 2: Create AnimatedNumber component (AC: 1, 2, 3, 4)
  - [x] 2.1: Create `resources/js/components/AnimatedNumber.tsx`
  - [x] 2.2: Use `useMotionValue` + `animate()` + `useTransform` from `motion/react` for precise 400ms ease-out tween
  - [x] 2.3: Accept props: `value` (integer cents), `formatter` (function, defaults to `formatAbsAmount`), `className` (string, optional)
  - [x] 2.4: Use `useTransform(motionValue, formatter)` to apply French locale formatting at each animation frame
  - [x] 2.5: Import and use `useReducedMotion()` from `motion/react` — when true, skip animation and set value instantly via `motionValue.set(value)`
  - [x] 2.6: Render via `<motion.span className={className}>{display}</motion.span>` for performant updates without React re-renders per frame
  - [x] 2.7: Handle initial mount: set initial value without animating (no counting from 0 on first render)
  - [x] 2.8: Cleanup: return `controls.stop()` from useEffect to prevent stale animations

- [x] Task 3: Integrate AnimatedNumber into CategoryCard (AC: 1, 2, 4)
  - [x] 3.1: Import `AnimatedNumber` component
  - [x] 3.2: Replace static `formatAbsAmount(amountCents)` with `<AnimatedNumber value={amountCents} className="text-2xl font-semibold tabular-nums" style={{ color }} />`
  - [x] 3.3: Ensure `tabular-nums` is preserved on the AnimatedNumber span for stable layout during animation
  - [x] 3.4: Verify trend indicator below the amount is NOT animated (stays static text)

- [x] Task 4: Integrate AnimatedNumber into SummaryWidget (AC: 3, 4)
  - [x] 4.1: Import `AnimatedNumber` component
  - [x] 4.2: Replace static Income amount `formatAmount(incomeCents)` with `<AnimatedNumber value={incomeCents} formatter={formatAmount} className="text-lg font-semibold tabular-nums text-[#10B981]" />`
  - [x] 4.3: Replace static Expenses amount `formatAmount(expensesCents)` with `<AnimatedNumber value={expensesCents} formatter={formatAbsAmount} className="text-lg font-semibold tabular-nums text-[#FB7185]" />`
  - [x] 4.4: When `showDashes` is true, bypass AnimatedNumber and show em-dash directly (no animation for dashes)

- [x] Task 5: Integrate AnimatedNumber into ColorHeader (AC: 1, 2, 4)
  - [x] 5.1: Import `AnimatedNumber` component
  - [x] 5.2: Replace static `formatAbsAmount(totalAmountCents)` with `<AnimatedNumber value={totalAmountCents} className="text-4xl font-bold tabular-nums ..." />`
  - [x] 5.3: Maintain the dynamic text color logic (light/dark based on background luminance)

- [x] Task 6: Write Tests (AC: 1-4)
  - [x] 6.1: Test AnimatedNumber renders formatted value correctly (snapshot or text content)
  - [x] 6.2: Test that the component renders without crashing for zero, positive, and negative cent values
  - [x] 6.3: Test that `prefers-reduced-motion` media query is respected (component renders final value immediately)
  - [x] 6.4: Run `sail artisan test` — all existing tests pass with zero regressions
  - [x] 6.5: Run `sail bun run build` — TypeScript compiles cleanly with no errors

## Dev Notes

### Architecture Overview for This Story

This story adds smooth counting animations to all financial amounts in the app. It's a frontend-only change — no backend modifications needed. The primary work is creating a reusable `AnimatedNumber` component and integrating it into three existing components.

```
AnimatedNumber.tsx (NEW)
  │
  ├─ useMotionValue(initialValue)     // Framer Motion animatable value
  ├─ animate(motionValue, target, {   // Precise tween control
  │     duration: 0.4,
  │     ease: "easeOut"
  │   })
  ├─ useTransform(motionValue, fmt)   // Format at each frame
  ├─ useReducedMotion()               // Accessibility check
  │
  └─ <motion.span>{display}</motion.span>  // Performant render

Integration points:
  ├─ CategoryCard.tsx   — amount display (text-2xl, category color)
  ├─ SummaryWidget.tsx  — income + expenses (text-lg, emerald/coral)
  └─ ColorHeader.tsx    — drill-down total (text-4xl, dynamic contrast)
```

### Implementation Details

**AnimatedNumber Component Design:**

```tsx
import { motion, useMotionValue, useTransform, animate, useReducedMotion } from 'motion/react';
import { useEffect, useRef } from 'react';
import { formatAbsAmount } from '@/lib/formatAmount';

interface AnimatedNumberProps {
    value: number;
    formatter?: (cents: number) => string;
    className?: string;
    style?: React.CSSProperties;
}

export function AnimatedNumber({
    value,
    formatter = formatAbsAmount,
    className,
    style,
}: AnimatedNumberProps) {
    const shouldReduceMotion = useReducedMotion();
    const motionValue = useMotionValue(value);
    const display = useTransform(motionValue, (v) => formatter(Math.round(v)));
    const isInitialMount = useRef(true);

    useEffect(() => {
        if (isInitialMount.current) {
            isInitialMount.current = false;
            motionValue.set(value);
            return;
        }

        if (shouldReduceMotion) {
            motionValue.set(value);
            return;
        }

        const controls = animate(motionValue, value, {
            duration: 0.4,
            ease: 'easeOut',
        });

        return () => controls.stop();
    }, [value, shouldReduceMotion, motionValue]);

    return (
        <motion.span className={className} style={style}>
            {display}
        </motion.span>
    );
}
```

**Key design decisions:**

1. **`animate()` over `useSpring()`:** The acceptance criteria specify ~400ms duration with ease-out. `animate()` gives precise duration control, whereas `useSpring()` uses spring physics where duration is approximate.

2. **`useTransform` for formatting:** The formatter runs on every animation frame via `useTransform`, keeping French locale formatting (`1 234,56 €`) correct throughout the animation. `Math.round(v)` ensures we pass integer cents to the formatter.

3. **`<motion.span>` rendering:** Motion updates the DOM directly from the MotionValue without triggering React re-renders per frame. This is critical for 60fps animation performance.

4. **Initial mount guard:** The `isInitialMount` ref prevents counting up from 0 on first render. The component should display the final value immediately on mount, then animate only on subsequent value changes.

5. **Style prop forwarding:** The `style` prop is needed for CategoryCard's inline `color` property (category accent color).

**CategoryCard integration:**

```tsx
// Before (static):
<p className="text-2xl font-semibold tabular-nums" style={{ color }}>
    {formatAbsAmount(amountCents)}
</p>

// After (animated):
<AnimatedNumber
    value={amountCents}
    className="text-2xl font-semibold tabular-nums"
    style={{ color }}
/>
```

**SummaryWidget integration:**

```tsx
// Income (uses formatAmount for signed value — income is positive)
{showDashes ? (
    <p className="text-lg font-semibold tabular-nums text-[#10B981]">{'\u2014'}</p>
) : (
    <AnimatedNumber
        value={incomeCents}
        formatter={formatAmount}
        className="text-lg font-semibold tabular-nums text-[#10B981]"
    />
)}

// Expenses (uses formatAbsAmount — expenses are negative cents, display absolute)
{showDashes ? (
    <p className="text-lg font-semibold tabular-nums text-[#FB7185]">{'\u2014'}</p>
) : (
    <AnimatedNumber
        value={expensesCents}
        formatter={formatAbsAmount}
        className="text-lg font-semibold tabular-nums text-[#FB7185]"
    />
)}
```

**ColorHeader integration:**

```tsx
// Before:
<p className={`text-4xl font-bold tabular-nums ${textPrimary}`}>
    {formatAbsAmount(totalAmountCents)}
</p>

// After:
<AnimatedNumber
    value={totalAmountCents}
    className={`text-4xl font-bold tabular-nums ${textPrimary}`}
/>
```

### Edge Cases

1. **First render (no previous value):** The `isInitialMount` ref ensures no animation on mount. The formatted value appears instantly.

2. **Rapid month switching:** If the user scrolls through months quickly, `controls.stop()` in the cleanup function interrupts the current animation and starts a new one from wherever the value currently is. This creates a smooth chase effect rather than animation queue buildup.

3. **Zero values:** `formatAbsAmount(0)` → `"0,00 €"`. Animation from non-zero to zero works normally.

4. **Same value (no change):** If the month switch returns identical amounts, `animate()` with same start/end produces no visual change. No special handling needed.

5. **Very large deltas:** Animating from 100 cents to 5,000,000 cents works fine — the formatter handles any integer. The counting speed is automatically fast because the duration is fixed at 400ms regardless of delta.

6. **Negative amounts (signed):** SummaryWidget's income uses `formatAmount` (which includes sign). The animation correctly interpolates through negative/positive values. **Note:** SummaryWidget expenses formatter was changed from `formatAmount` to `formatAbsAmount` during integration — this removes the redundant negative sign under the "Expenses" label (intentional UX improvement).

7. **`showDashes` toggle in SummaryWidget:** When switching to an empty month, the component switches to showing em-dashes instead of AnimatedNumber. No animation for this transition — it's a conditional render swap.

8. **SSR/Hydration:** Framer Motion's hooks are client-side only. Since this is an Inertia app with SSR disabled by default, no hydration issues expected.

### Previous Story Intelligence

**From Story 6-1 (Inline Trend Indicators):**
- Test suite: 33 Dashboard tests, 398 assertions — maintain this baseline
- `trendPercentage` and `hasPreviousMonthData` already implemented on DashboardController
- CategoryCard already has a `TrendIndicator` sub-component — the trend indicator is static text, NOT to be animated
- Key pattern: CategoryCard receives props from Dashboard.tsx map; `formatAbsAmount` used for display
- SummaryWidget uses `formatAmount` (signed) for income, conditional em-dash for empty months
- ColorHeader uses `formatAbsAmount` with dynamic text color based on background luminance
- Note from 6-1: "Framer Motion is NOT needed for this story. Framer Motion will be added in Story 6.2."
- 4 pre-existing test failures in DeduplicationServiceTest and TransactionAmountCentsTest — unrelated, ignore

**Files modified in 6-1 that we'll modify again:**
- `resources/js/components/CategoryCard.tsx` — wrap amount with AnimatedNumber
- `resources/js/pages/dashboard.tsx` — no changes needed (data flow already correct)
- `resources/js/types/budget.ts` — no changes needed

### Git Intelligence

Recent commits follow one-per-story mono-commit pattern:
```
3753ee6 Story 6.1: Inline trend indicators + review fixes
78be1aa Add comprehensive demo seeder with 12 months of realistic data
4893705 Mark all epics done + Story 1.4 review fixes
```

Established patterns:
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Tests must pass with zero regressions before committing
- One commit per story
- Feature tests use `RefreshDatabase` trait + seed categories where needed

### Latest Technical Information

**Framer Motion / Motion Library:**
- The library has been rebranded from `framer-motion` to `motion` (they are functionally identical)
- Latest stable: v12.x (on npm as `motion`)
- Import from `motion/react` (new canonical path) or `framer-motion` (legacy, still works)
- Architecture doc says `framer/motion` but the correct npm package is `motion`
- Key APIs: `useMotionValue`, `animate()`, `useTransform`, `useReducedMotion` — all available in the free package
- `useReducedMotion()` listens to `prefers-reduced-motion: reduce` media query, re-renders on changes
- `animate(motionValue, target, { duration, ease })` provides precise tween control
- `<motion.span>{motionValue}</motion.span>` updates DOM directly without React re-renders

**Note:** The architecture document lists `framer/motion` as the package. The actual npm package name is `motion` (rebranded). Install with `sail bun add motion`.

### Project Structure Notes

- `resources/js/components/AnimatedNumber.tsx` — NEW component [Source: architecture.md#Structure Patterns, listed under components]
- `resources/js/components/CategoryCard.tsx` — MODIFY to use AnimatedNumber [Source: architecture.md#Structure Patterns]
- `resources/js/components/SummaryWidget.tsx` — MODIFY to use AnimatedNumber [Source: architecture.md#Structure Patterns]
- `resources/js/components/ColorHeader.tsx` — MODIFY to use AnimatedNumber [Source: architecture.md#Structure Patterns]
- `package.json` — MODIFY to add `motion` dependency
- No backend changes. No new routes. No new controllers. No database changes.

### Architecture Compliance

| Requirement | Implementation |
|-------------|----------------|
| AnimatedNumber component [arch#Format Patterns] | Created as `AnimatedNumber.tsx` in components/, uses Framer Motion |
| formatAmount.ts at each frame [arch#Format Patterns] | `useTransform(motionValue, formatter)` applies formatter every frame |
| tabular-nums preserved [arch#Format Patterns] | `tabular-nums` class passed via `className` prop to AnimatedNumber |
| prefers-reduced-motion [arch#Frontend Architecture] | `useReducedMotion()` from motion/react — instant value swap when true |
| Framer Motion package [arch#Additional Packages] | `motion` (rebranded framer-motion) v12.x installed via Bun |
| Amount handling as integer cents [arch#Format Patterns] | AnimatedNumber receives cents, formatter converts to display |
| No new backend code [arch#Service Boundary] | Purely frontend change — no controller/service modifications |
| Single breakpoint at 640px [UX#Breakpoint Strategy] | AnimatedNumber renders identically on mobile and desktop |

### Library & Framework Requirements

**New package to install:**
- `motion` (v12.x) — the rebranded Framer Motion package. Install: `sail bun add motion`
- Import from `motion/react` for React-specific hooks and components

**No other new packages needed.** This story uses only:
- React 19 (existing) — hooks, refs, effects
- Motion v12 (new) — animation primitives
- Tailwind CSS (existing) — styling via className

### File Structure Requirements

**New files:**
```
resources/js/components/AnimatedNumber.tsx   (CREATE — reusable animated amount component)
```

**Modified files:**
```
resources/js/components/CategoryCard.tsx     (MODIFY — wrap amount with AnimatedNumber)
resources/js/components/SummaryWidget.tsx    (MODIFY — wrap income/expenses with AnimatedNumber)
resources/js/components/ColorHeader.tsx      (MODIFY — wrap total amount with AnimatedNumber)
package.json                                 (MODIFY — add motion dependency)
bun.lock                                     (AUTO-UPDATED by bun add)
```

**No changes needed:**
```
resources/js/pages/dashboard.tsx             (data flow already correct)
resources/js/types/budget.ts                 (types already correct)
resources/js/lib/formatAmount.ts             (formatter functions unchanged)
app/Http/Controllers/DashboardController.php (no backend changes)
```

### Testing Strategy

**This is a frontend-only animation change.** Testing animated values in a Laravel test suite is not practical. Focus testing on:

1. **Regression testing** — Run `sail artisan test` to ensure all existing feature tests pass unchanged. The AnimatedNumber wrapper should not affect the data or props flowing through the components.

2. **Build verification** — Run `sail bun run build` to confirm TypeScript compiles with the new `motion` dependency and all component changes.

3. **Manual verification** — Use the demo seeder data (12 months) to verify:
   - Navigate between months: amounts count from old to new values
   - Import new data: amounts update with animation
   - SummaryWidget Income/Expenses animate
   - ColorHeader amount animates on drill-down data changes
   - Enable "Reduce Motion" in OS settings: all amounts snap instantly

4. **No new feature tests needed** — The existing DashboardTest.php tests verify that correct prop values are passed to Inertia. The AnimatedNumber component is a presentational wrapper that doesn't change what values are rendered, only how they transition visually.

### What This Story Does NOT Include

- **Drill-down page transitions** — AnimatePresence/page transitions are Story 6.3.
- **Import cascade animation** — Staggered card reveal is Story 6.3.
- **Trend indicator animation** — Trend text (↑8%, ↓12%) stays static.
- **Transaction count animation** — Only financial amounts animate, not counts.
- **Backend changes** — No controller, service, or database modifications.
- **New pages or routes** — Purely component-level changes.

### References

- Epic 6 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 6.2]
- FR38 requirement: [Source: _bmad-output/planning-artifacts/epics.md#Requirements Inventory]
- AnimatedNumber UX spec: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#AnimatedNumber]
- Animation feedback patterns: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Feedback Patterns]
- Framer Motion package: [Source: _bmad-output/planning-artifacts/architecture.md#Additional Packages]
- Amount handling patterns: [Source: _bmad-output/planning-artifacts/architecture.md#Format Patterns]
- Animated amounts spec: [Source: _bmad-output/planning-artifacts/architecture.md#Format Patterns → Animated Amounts]
- prefers-reduced-motion: [Source: _bmad-output/planning-artifacts/architecture.md#Frontend Architecture]
- CategoryCard component: [Source: resources/js/components/CategoryCard.tsx]
- SummaryWidget component: [Source: resources/js/components/SummaryWidget.tsx]
- ColorHeader component: [Source: resources/js/components/ColorHeader.tsx]
- formatAmount utility: [Source: resources/js/lib/formatAmount.ts]
- Previous story 6.1: [Source: _bmad-output/implementation-artifacts/6-1-inline-trend-indicators.md]
- Sprint status: [Source: _bmad-output/implementation-artifacts/sprint-status.yaml]
- Motion docs (animate): [Source: https://motion.dev/docs/animate]
- Motion docs (useReducedMotion): [Source: https://motion.dev/docs/react-use-reduced-motion]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6 (claude-opus-4-6)

### Debug Log References

- No blocking issues encountered during implementation.

### Completion Notes List

- Installed `motion` v12.34.3 (rebranded Framer Motion) via `sail bun add motion`
- Created `AnimatedNumber` component using `useMotionValue` + `animate()` + `useTransform` for precise 400ms ease-out tween animation
- Component renders via `<motion.span>` for 60fps DOM updates without React re-renders per frame
- Initial mount guard (`isInitialMount` ref) prevents counting up from 0 on first render
- `useReducedMotion()` respects `prefers-reduced-motion` accessibility setting — instantly snaps to final value
- Integrated AnimatedNumber into CategoryCard (amount display with category accent color via style prop)
- Integrated AnimatedNumber into SummaryWidget (income with `formatAmount`, expenses with `formatAbsAmount`, em-dash bypass when `showDashes` is true)
- Integrated AnimatedNumber into ColorHeader (drill-down total with dynamic text color based on background luminance)
- Set up Vitest + jsdom + @testing-library/react as devDependencies for frontend component testing
- 7 AnimatedNumber unit tests: positive/zero/negative values, custom formatter, className/style forwarding, reduced motion
- Backend regression: 310 passed, 4 pre-existing failures (DeduplicationServiceTest, TransactionAmountCentsTest) — no new regressions
- TypeScript build: clean compilation with zero errors

### Implementation Plan

Followed story tasks 1-6 sequentially. AnimatedNumber component created per Dev Notes specification. Integrated into 3 existing components as presentational wrappers. All acceptance criteria satisfied through the animation primitives and accessibility hook.

### File List

**New files:**
- `resources/js/components/AnimatedNumber.tsx` — Reusable animated number component
- `resources/js/components/__tests__/AnimatedNumber.test.tsx` — 7 unit tests for AnimatedNumber
- `resources/js/test/setup.ts` — Vitest test setup (jest-dom matchers)
- `vitest.config.ts` — Vitest configuration (jsdom environment, path aliases)

**Modified files:**
- `resources/js/components/CategoryCard.tsx` — Replaced static amount with AnimatedNumber
- `resources/js/components/SummaryWidget.tsx` — Replaced static income/expenses with AnimatedNumber
- `resources/js/components/ColorHeader.tsx` — Replaced static total amount with AnimatedNumber
- `package.json` — Added `motion` dependency, test devDependencies (vitest, jsdom, @testing-library/react, @testing-library/jest-dom), `test` script
- `bun.lock` — Auto-updated by bun add
- `_bmad-output/implementation-artifacts/sprint-status.yaml` — Sprint tracking status update

## Change Log

| Date | Change |
|------|--------|
| 2026-02-21 | Story 6.2: Added AnimatedNumber component with Motion library. Integrated into CategoryCard, SummaryWidget, and ColorHeader. All financial amounts now animate with 400ms ease-out tween on value changes. Respects prefers-reduced-motion. Added Vitest frontend test suite with 7 tests. |
| 2026-02-21 | Review fixes: (1) CategoryCard layout preserved via parent `flex-col` — no `block` class needed (span is a flex item). (2) Documented SummaryWidget expenses formatter change from `formatAmount` to `formatAbsAmount` — intentional UX improvement (no redundant negative sign under "Expenses" label). (3) Added sprint-status.yaml to File List. (4) Added initial mount test verifying animate() is not called on first render. |
| 2026-02-21 | Second review fixes: (1) Fixed CJS `require('react')` → async `import('react')` in test mock. (2) Added test for animate() being called on value change. (3) Removed redundant `globals: true` from vitest.config.ts (tests import explicitly). (4) Noted 11 ESLint import-reorder files as collateral changes. (5) Noted CategoryCard changes were committed in 07d53ea (story 6-1 fix commit) rather than a dedicated 6-2 commit. |
