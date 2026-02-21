# Sprint Change Proposal - Phase 2: Polish & Insight

**Date:** 2026-02-21
**Author:** Lambert
**Status:** Approved

---

## Section 1: Issue Summary

**Trigger:** The budget-app MVP is complete. All 5 epics (17 stories) have been implemented and marked done. Lambert wants to begin Phase 2 ("Polish & Insight") features as defined in the PRD.

**Context:** This is a planned evolution, not a correction. The MVP delivers financial clarity; Phase 2 makes the experience feel alive and adds transaction discovery capabilities.

**Evidence:** Sprint status shows all epics `done`. PRD explicitly defines Phase 2 features at a high level.

## Section 2: Impact Analysis

### Epic Impact

No changes to Epics 1-5. Two new epics added:

- **Epic 6: Visual Polish & Animations** (3 stories, FR36-FR40)
- **Epic 7: Transaction Discovery** (2 stories, FR41-FR44)

### Artifact Updates Required

| Artifact | Changes |
|----------|---------|
| PRD | Add FR36-FR44, update Phase 2 status to "In Progress" |
| Architecture | Add Framer Motion package, animation patterns, new services/components/pages, update deferred items |
| UX Spec | Add trend indicator to CategoryCard, new AnimatedNumber/SearchBar/RecurringList components, animation feedback patterns |
| Epics doc | Add Epic 6 and Epic 7 with 5 stories total |
| Sprint Status | Add epic-6, epic-7 entries with stories in backlog |

### Technical Impact

Low risk. All changes are additive. One new dependency (Framer Motion). Existing BudgetQueryService extended, not rewritten.

## Section 3: Recommended Approach

**Path:** Direct Adjustment -- add new epics and stories to the existing plan.

**Rationale:** MVP is stable and complete. Phase 2 features are well-defined, low-risk, and build naturally on existing architecture. No rollbacks or scope reductions needed.

**Effort:** Medium. Epic 6 is frontend-heavy. Epic 7 requires backend + frontend.

**Risk:** Low. No architectural changes, no data model changes, no breaking changes.

## Section 4: Detailed Change Proposals

### Proposal 1: PRD Updates -- New Functional Requirements

**Add after FR35:**

#### Visual Polish

- **FR36:** The system displays a month-over-month percentage change indicator next to each category amount on the overview (e.g., ↓12%, ↑8%)
- **FR37:** The system compares the current month's category total to the previous month's total to calculate the trend percentage
- **FR38:** Category amounts animate with number transitions when values change (month navigation, import completion)
- **FR39:** Drill-down navigation uses smooth animated transitions between overview and category detail views
- **FR40:** After import confirmation, category cards animate in a staggered cascade as amounts update

#### Transaction Discovery

- **FR41:** Lambert can view a list of recurring transactions detected across all categories (e.g., subscriptions, rent, salary)
- **FR42:** The system identifies recurring transactions by grouping transactions with the same merchant appearing in 2+ consecutive months
- **FR43:** Lambert can search transactions by merchant name or description across all months and accounts
- **FR44:** Search results display matching transactions with their category, date, amount, and account context

**Update Post-MVP section:** Change Phase 2 status from "(Post-MVP)" to "(In Progress)" and cross-reference FR36-FR44.

### Proposal 2: New Epics & Stories

#### Epic 6: Visual Polish & Animations

Lambert's budget overview feels alive with trend indicators showing spending changes and smooth animations throughout the interface.

**FRs covered:** FR36, FR37, FR38, FR39, FR40

**Story 6.1: Inline Trend Indicators**

As Lambert,
I want to see a percentage change next to each category amount compared to last month,
So that I instantly know if my spending in a category is trending up or down.

**Acceptance Criteria:**

**Given** I am viewing the Dashboard for a month with data
**When** the previous month also has data for a category
**Then** a trend indicator appears next to the category amount (e.g., "↓12%" in green, "↑8%" in coral) (FR36)

**Given** BudgetQueryService calculates trends
**When** DashboardController passes category data as Inertia props
**Then** each category includes a `trendPercentage` (integer, nullable) comparing current month total to previous month total (FR37)

**Given** a category exists in the current month but not the previous month
**When** the trend is calculated
**Then** the trend indicator shows "new" in tertiary text instead of a percentage

**Given** a category's trend is exactly 0%
**When** the indicator renders
**Then** it shows "—" in tertiary text (no arrow, no color)

**Given** the trend indicator is displayed
**When** I look at the CategoryCard
**Then** the indicator appears below the amount in body-sm size, using emerald (#10B981) for decreases (spending down = good) and coral (#FB7185) for increases

**Story 6.2: Animated Number Transitions**

As Lambert,
I want category amounts to animate smoothly when values change,
So that the interface feels responsive and alive rather than static.

**Acceptance Criteria:**

**Given** I am on the Dashboard viewing category amounts
**When** I navigate to a different month via the MonthStrip
**Then** category amounts animate from old values to new values with a smooth counting transition (~400ms duration) (FR38)

**Given** an import completes and category amounts update
**When** the Dashboard re-renders with new data
**Then** amounts animate from previous values to updated values (FR38)

**Given** the SummaryWidget shows Income and Expenses
**When** values change (month navigation or import)
**Then** those amounts also animate with the same counting transition

**Given** the user has `prefers-reduced-motion` enabled
**When** any animation would trigger
**Then** values update instantly with no animation

**Story 6.3: Drill-Down & Import Animations**

As Lambert,
I want smooth transitions when drilling into categories and a satisfying cascade when importing,
So that navigation feels fluid and importing feels rewarding.

**Acceptance Criteria:**

**Given** I am on the Dashboard
**When** I tap a CategoryCard to drill down
**Then** the transition to Categories/Show uses a smooth animation (the color header expands from the card position or fades in) rather than an instant page swap (FR39)

**Given** I am on the Categories/Show drill-down
**When** I tap back/close to return to the Dashboard
**Then** the reverse transition animates smoothly back to the overview

**Given** I confirm an import and transactions are committed
**When** category cards update with new amounts
**Then** cards animate in a staggered cascade (top to bottom, ~80ms delay between cards) as their amounts update (FR40)

**Given** the Dashboard had an empty state before import
**When** the first import completes
**Then** category cards cascade in from empty, creating the "reveal moment"

**Given** the user has `prefers-reduced-motion` enabled
**When** any animation would trigger
**Then** transitions are instant with no animation

#### Epic 7: Transaction Discovery

Lambert can find specific transactions through search and identify recurring spending patterns across categories.

**FRs covered:** FR41, FR42, FR43, FR44

**Story 7.1: Transaction Search**

As Lambert,
I want to search for transactions by merchant name or description,
So that I can quickly find specific transactions without drilling through categories manually.

**Acceptance Criteria:**

**Given** I am on the Dashboard
**When** I tap the search icon/area
**Then** a search input appears at the top of the overview (FR43)

**Given** I type at least 2 characters in the search input
**When** the search executes
**Then** matching transactions are displayed below the input, showing: merchant name, category (with color dot), date, amount, and account (FR44)

**Given** search results are displayed
**When** I tap a transaction row
**Then** the category correction picker opens (same as drill-down correction flow)

**Given** BudgetQueryService handles the search
**When** a search query is received
**Then** it searches across all months and accounts using PostgreSQL `ILIKE` on the transaction description field (FR43)

**Given** the search input is empty or cleared
**When** I dismiss the search
**Then** the Dashboard returns to the normal category overview

**Given** no transactions match the search query
**When** results render
**Then** "No transactions found" appears in tertiary text

**Story 7.2: Recurring Transaction Detection & View**

As Lambert,
I want to see a list of my recurring transactions (subscriptions, rent, salary),
So that I can understand my fixed costs across all categories in one place.

**Acceptance Criteria:**

**Given** I want to view recurring transactions
**When** I access the recurring view (accessible from Dashboard, e.g., a subtle icon or menu item)
**Then** I see a list of detected recurring transactions grouped by merchant (FR41)

**Given** BudgetQueryService analyzes transaction patterns
**When** it detects recurring transactions
**Then** it groups transactions by normalized merchant name and flags those appearing in 2+ consecutive months as recurring (FR42)

**Given** recurring transactions are displayed
**When** I view the list
**Then** each entry shows: merchant name, category (with color dot), typical amount, frequency (monthly), and last occurrence date

**Given** a recurring transaction has varying amounts (e.g., electricity bill)
**When** it is displayed
**Then** the typical amount shows the most recent amount, with a range indicator if amounts vary significantly

**Given** I tap a recurring transaction entry
**When** the detail expands
**Then** I see the full history of that recurring transaction across all months

**Given** the recurring view is accessed via MCP
**When** Claude calls a `get_recurring_transactions` tool
**Then** it returns the same recurring detection data in snake_case JSON format

### Proposal 3: Architecture Updates

- **New package:** Framer Motion (React animation library)
- **New services:** `RecurringDetectionService.php`
- **Extended services:** `BudgetQueryService.php` (trend calculation, search, recurring detection)
- **New components:** `AnimatedNumber.tsx`, `SearchBar.tsx`, `RecurringList.tsx`
- **New pages:** `Recurring/Index.tsx`
- **New MCP tools:** `SearchTransactions.php`, `GetRecurringTransactions.php`
- **New hooks:** `useReducedMotion.ts`
- **Animation patterns:** Number transitions (~400ms), drill-down transitions (~300ms), import cascade (~80ms stagger), all respecting prefers-reduced-motion

### Proposal 4: UX Design Updates

- **CategoryCard updated:** Trend indicator (↓12% / ↑8%) below amount, emerald for decreases, coral for increases
- **New AnimatedNumber component:** ~400ms ease-out counting transition, French locale formatting per frame, tabular-nums preserved
- **New SearchBar component:** Expands from icon in Dashboard header, replaces category list with results, dismiss restores overview
- **New RecurringList component:** Page with merchant-grouped recurring transactions, expandable history, accessed from Dashboard header icon
- **Animation feedback patterns:** All animations decorative, never blocking. prefers-reduced-motion respected throughout.
- **Navigation patterns:** Search is inline overlay on Dashboard (one-screen principle preserved). Recurring is a separate Inertia page.

## Section 5: Implementation Handoff

**Change Scope:** Minor -- direct implementation by development team (Lambert).

**Implementation Sequence:**

1. Update planning artifacts (PRD, Architecture, UX Spec, Epics doc)
2. Update sprint-status.yaml with Epic 6 and Epic 7 entries
3. Implement Epic 6 stories in order (6.1 → 6.2 → 6.3)
4. Implement Epic 7 stories in order (7.1 → 7.2)

**Success Criteria:**

- Trend indicators visible on all category cards with previous month data
- Amounts animate smoothly on month navigation and import
- Drill-down and import transitions feel fluid
- Search finds transactions across all months/accounts
- Recurring transactions detected and viewable
- All animations respect prefers-reduced-motion
- MCP extended with search and recurring tools
