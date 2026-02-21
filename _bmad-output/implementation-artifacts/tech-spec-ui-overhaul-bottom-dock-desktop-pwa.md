---
title: 'UI Overhaul - Bottom Dock, Desktop Layout & PWA'
slug: 'ui-overhaul-bottom-dock-desktop-pwa'
created: '2026-02-21'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['react-19', 'typescript', 'tailwind-4', 'inertia-2', 'motion/react', 'lucide-react', 'shadcn-ui', 'vite', 'laravel-12']
files_to_modify:
  - 'resources/js/layouts/app/app-layout.tsx'
  - 'resources/js/pages/dashboard.tsx'
  - 'resources/js/pages/search.tsx'
  - 'resources/js/pages/recurring.tsx'
  - 'resources/js/types/ui.ts'
  - 'resources/views/app.blade.php'
  - 'resources/css/app.css'
files_to_create:
  - 'resources/js/components/BottomDock.tsx'
  - 'resources/js/components/__tests__/BottomDock.test.tsx'
  - 'public/manifest.json'
  - 'public/icons/icon-192x192.png'
  - 'public/icons/icon-512x512.png'
code_patterns:
  - 'Layout wrapper: AppLayout wraps all authenticated pages with header + main + Toaster'
  - 'Proxy pattern: layouts/app-layout.tsx re-exports layouts/app/app-layout.tsx'
  - 'Active page detection: useCurrentUrl() hook from @/hooks/use-current-url — provides isCurrentUrl() and currentUrl'
  - 'Mobile detection: useIsMobile() hook from @/hooks/use-mobile — uses matchMedia at 640px breakpoint'
  - 'Responsive breakpoints: sm: (640px) existing, lg: (1024px) new for desktop two-column'
  - 'Max-width constraint: max-w-[480px] mx-auto on header and main content'
  - 'Navigation: router.visit() for page changes, Link component for static navigation'
  - 'Icons: lucide-react exclusively, size-5 for nav icons, size-4 for inline'
  - 'Dark theme only: #0A0A0B background, #1C1C1E surface, #2C2C2E elevated'
  - 'Touch targets: size-11 (44px) for tappable elements'
test_patterns:
  - 'Vitest + @testing-library/react + jsdom'
  - 'Tests in __tests__/ directories next to components'
  - 'motion/react mocked with vi.mock() for animation testing'
  - 'Named imports from vitest: describe, it, expect, vi, beforeEach'
---

# Tech-Spec: UI Overhaul - Bottom Dock, Desktop Layout & PWA

**Created:** 2026-02-21

## Overview

### Problem Statement

The budget app's navigation is awkward — search and recurring buttons are squeezed inline next to the MonthStrip and easy to miss. The desktop layout wastes screen real estate with a narrow 480px centered column. The header burns space on "budget-app" text branding. There is no PWA support, so the app cannot be installed on a phone's home screen.

### Solution

Add a Motivated-style bottom dock with 3 navigation destinations (Dashboard, Recurring, Search), implement a responsive two-column desktop layout at `lg:` breakpoint with a desktop header nav, slim the header to a small app icon + account picker, and add a web app manifest with icons for PWA installability.

### Scope

**In Scope:**
- Bottom dock navigation component with 3 items: Dashboard, Recurring, Search (mobile only, `lg:hidden`)
- Desktop header navigation (horizontal links at `lg:` replacing the dock)
- Responsive two-column desktop layout at `lg:` (1024px) breakpoint
- Header redesign: small icon + desktop nav links + account picker, remove "budget-app" text
- PWA manifest.json + app icons (with maskable purpose) + iOS meta tags
- Adjust pages for new navigation (remove inline search/recurring buttons from dashboard, remove back buttons from search/recurring)
- Safe area inset handling for iOS standalone mode
- Toaster repositioned to avoid dock overlap on mobile
- Accessibility: `<nav>` landmark, `aria-current`, focus-visible styles

**Out of Scope:**
- Service worker / offline caching
- Import flow refactor (stays on dashboard as-is)
- New features or backend changes
- Custom brand icon design (use lucide `Wallet` icon for header, generate PWA icons)
- iOS splash screen images (`apple-touch-startup-image`)

## Context for Development

### Codebase Patterns

- **Layout architecture**: Two-file proxy pattern. `layouts/app-layout.tsx` re-exports `layouts/app/app-layout.tsx`. All authenticated pages use `<AppLayout>` which provides: header (logo text + AccountPicker), main content area (max-w-[480px] centered), and Toaster.
- **Routing**: Inertia.js server-side routing via `routes/web.php`. No client-side router. Pages navigate with `router.visit()`, `router.get()`. Current URL available via `usePage().url`.
- **URL detection hook**: `useCurrentUrl()` from `@/hooks/use-current-url` provides `isCurrentUrl(path)` for exact match and `currentUrl` string. Uses `usePage()` internally. **Must use this for dock active state — do not duplicate pattern.**
- **Mobile detection hook**: `useIsMobile()` from `@/hooks/use-mobile` uses `matchMedia` at 640px. Available but not needed for dock (use CSS `lg:hidden` instead).
- **Responsive approach**: Single breakpoint at `sm:` (640px). Mobile-first with `px-4 sm:px-8` padding. Content constrained to `max-w-[480px] mx-auto`.
- **Component style**: Functional components with React hooks. `useCallback` for event handlers. `motion/react` for animations. Icons from `lucide-react`. shadcn/ui for Dialog, Sheet, DropdownMenu, Toast (sonner).
- **Navigation per page**: Each sub-page (search, recurring, categories/show) has its own back button (`<ArrowLeft>`) + `handleBack()` using `window.history.back()` with fallback to `/dashboard`.
- **Props pattern**: Inertia pages receive typed props via generic types in `types/budget.ts`. Layout receives `AppLayoutProps` from `types/ui.ts`. Shared page props (like `accountContexts`) available via `usePage().props`.
- **View transitions**: CSS in `app.css` applies fade-in/fade-out on `::view-transition-old(root)` / `::view-transition-new(root)`. The dock must be excluded from these transitions.
- **File input for import**: Dashboard owns the file input + drag-and-drop + confirmation dialog. Import is not a separate page — it's inline on the dashboard.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `resources/js/layouts/app/app-layout.tsx` | Main layout shell — header + main + Toaster. **Primary edit target.** |
| `resources/js/layouts/app-layout.tsx` | Proxy re-export. No changes needed. |
| `resources/js/pages/dashboard.tsx` | Dashboard page — MonthStrip, search/recurring buttons (to remove), category cards, import flow. |
| `resources/js/pages/search.tsx` | Search page — back button to remove, navigation now via dock. |
| `resources/js/pages/recurring.tsx` | Recurring page — back button to remove, navigation now via dock. |
| `resources/js/pages/categories/show.tsx` | Category drill-down — back button stays (drill-down is not a dock item). No changes. |
| `resources/js/types/ui.ts` | `AppLayoutProps` type definition — needs `currentAccount` forwarding. |
| `resources/js/types/budget.ts` | All page prop types. |
| `resources/js/hooks/use-current-url.ts` | `useCurrentUrl()` hook — use `isCurrentUrl()` for dock active state. **Do not duplicate.** |
| `resources/js/hooks/use-mobile.tsx` | `useIsMobile()` hook — available if needed. |
| `resources/css/app.css` | Tailwind theme, CSS variables, view transition animations. |
| `resources/views/app.blade.php` | HTML shell — needs manifest link + iOS meta tags + viewport-fit. |
| `resources/js/components/app-logo-icon.tsx` | Existing SVG icon (Laravel logo). Left as-is (starter kit artifact, not imported by our code). |
| `public/favicon.svg` | Current favicon (Laravel red logo). |
| `public/apple-touch-icon.png` | Current Apple touch icon. |
| `routes/web.php` | Route definitions: `/dashboard`, `/search`, `/recurring`, `/categories/{category}`. |

### Technical Decisions

- **Dock items**: Dashboard (`LayoutGrid`), Recurring (`Repeat`), Search (`Search`) — all lucide-react icons. Import is an *action*, not a *destination*, so it stays as a button on the dashboard.
- **Dock active state logic**: Use `useCurrentUrl()` hook. Dashboard is active when URL starts with `/dashboard` OR `/categories` (drill-down is a sub-navigation of dashboard). Recurring active for `/recurring`. Search active for `/search`.
- **Dock account context**: Dock links must preserve `currentAccount` as a query parameter. The dock receives `currentAccount` as a prop from AppLayout (which already has it). Links render as `/recurring?account={currentAccount}` when an account is selected.
- **Desktop breakpoint**: `lg:` (1024px) for two-column layout. Tablets in portrait (768px) keep single-column mobile experience.
- **Desktop navigation**: At `lg:`, the dock is hidden. Instead, the **header** shows horizontal nav links (Dashboard | Recurring | Search) between the Wallet icon and AccountPicker. These links use the same `useCurrentUrl()` active-state logic and account-preserving behavior.
- **Desktop layout**: Dashboard uses `lg:grid lg:grid-cols-[320px_1fr] lg:gap-8`. Left panel is sticky (SummaryWidget + ImportHistory). Right panel scrolls (MonthStrip + category cards). Both panels constrain inner content — right column keeps `max-w-[480px]` so cards don't stretch unnaturally.
- **SummaryWidget placement**: Single instance approach. On mobile, SummaryWidget renders in the normal vertical flow (above MonthStrip). On desktop, it renders in the left panel. Achieve this by rendering SummaryWidget once inside the left panel `<div>`, which on mobile collapses out of the grid into normal flow. The grid only activates at `lg:`, so on mobile the left/right divs stack vertically in source order.
- **View transitions**: Add `view-transition-name: dock` to the dock element and update CSS to exclude it from the page transition animation. This prevents the dock from fading during page navigations.
- **Toaster position**: Change Toaster to `position="top-center"` to avoid any dock overlap. This is the simplest, most reliable approach — no breakpoint-conditional offsets needed.
- **Safe areas**: `env(safe-area-inset-bottom)` padding on dock for iPhone home indicator. Requires `viewport-fit=cover` on viewport meta tag.
- **Import button on dashboard**: Keep existing inline "Import CSV" button below category cards. No floating action button needed.
- **Back buttons**: Remove from search and recurring pages (dock handles navigation). Keep on categories/show (drill-down needs explicit back).
- **PWA manifest**: Static file at `public/manifest.json`. Icons with `"purpose": "any maskable"` for Android adaptive icon support. Scope set to `/` to prevent standalone mode breakout.
- **Accessibility**: Dock wrapped in `<nav aria-label="Main navigation">`, active link gets `aria-current="page"`, all interactive elements get `focus-visible:ring-2 focus-visible:ring-white/50 focus-visible:outline-none`.

## Implementation Plan

### Tasks

#### Task 1: Create BottomDock component
- File: `resources/js/components/BottomDock.tsx` (new)
- Action: Create a fixed bottom navigation bar with 3 items:
  - Dashboard: `LayoutGrid` icon, label "Dashboard", links to `/dashboard`
  - Recurring: `Repeat` icon, label "Recurring", links to `/recurring`
  - Search: `Search` icon, label "Search", links to `/search`
- **Active state**: Use `useCurrentUrl()` from `@/hooks/use-current-url`. Active logic:
  - Dashboard: `currentUrl === '/dashboard' || currentUrl.startsWith('/categories')`
  - Recurring: `currentUrl === '/recurring'`
  - Search: `currentUrl === '/search'`
  - Active styling: `text-white`, inactive: `text-text-tertiary`
- **Account context**: Accept `currentAccount?: string` prop. When set, append `?account={currentAccount}` to Recurring and Search links. Dashboard link always goes to `/dashboard` (account is handled by the page).
- **Accessibility**:
  - Wrap in `<nav aria-label="Main navigation">`
  - Active link gets `aria-current="page"`
  - All links get `focus-visible:ring-2 focus-visible:ring-white/50 focus-visible:outline-none focus-visible:rounded-lg`
- **Styling**: `fixed bottom-0 inset-x-0`, `bg-background/80 backdrop-blur-lg`, `border-t border-white/5`, `h-16`, `pb-[env(safe-area-inset-bottom)]`
- **View transition exclusion**: Add inline style `viewTransitionName: 'dock'`
- Touch targets: Each item is a `<Link>` (from `@inertiajs/react`) wrapping icon + label, flex column, minimum 44px tap area
- Hidden on desktop: `lg:hidden`
- Icons: `size-5` consistent with existing nav icon sizing
- Labels: `text-[10px] font-medium` below each icon

#### Task 2: Update AppLayout — integrate dock + slim header + desktop nav
- File: `resources/js/layouts/app/app-layout.tsx`
- Action:
  - **Header redesign**: Replace `"budget-app"` text span with:
    - Small `Wallet` icon (lucide-react, `size-6`, `text-white`) on the left
    - Desktop nav links (hidden on mobile): `<nav className="hidden lg:flex items-center gap-1" aria-label="Main navigation">` with 3 `<Link>` elements styled as pills: `px-3 py-1.5 rounded-lg text-sm font-medium`. Active link: `bg-surface-elevated text-white`. Inactive: `text-text-tertiary hover:text-white`. Use `useCurrentUrl()` for active state with same logic as dock (Dashboard active for `/dashboard` and `/categories/*`).
    - AccountPicker stays on the right.
  - **Import BottomDock**: Add `<BottomDock currentAccount={currentAccount} />` after Toaster, outside the main content `<div>`.
  - **Main content padding**: Add `pb-20 lg:pb-0` to `<main>` to account for dock height on mobile.
  - **Toaster**: Change `position="bottom-center"` to `position="top-center"`. Remove `duration` if still 2000 (keep or adjust as desired).
  - **Desktop max-width**: Change `max-w-[480px]` to `max-w-[480px] lg:max-w-[960px]` on both header and main content wrappers.
- Notes: Import `{ Wallet }` from `lucide-react`. Import `{ Link }` from `@inertiajs/react`. Import `{ useCurrentUrl }` from `@/hooks/use-current-url`. Import `BottomDock` from `@/components/BottomDock`.

#### Task 3: Update AppLayout types
- File: `resources/js/types/ui.ts`
- Action: `AppLayoutProps` already has `currentAccount?: string`. Verify this is sufficient for both the dock and the desktop nav. No changes likely needed, but confirm the prop is threaded through the proxy layout file correctly.

#### Task 4: Dashboard — remove inline nav buttons + add desktop two-column
- File: `resources/js/pages/dashboard.tsx`
- Action:
  - **Remove** the search and recurring `<button>` elements from the MonthStrip row (the `<button>` elements with `Repeat` and `Search` icons inside the `flex items-center gap-2` wrapper). The dock now handles navigation to those pages.
  - **Remove** `Search` and `Repeat` imports from lucide-react (only if no longer used elsewhere in this file).
  - **Simplify MonthStrip row**: The `<div className="flex items-center gap-2">` wrapper that held MonthStrip + buttons becomes just the MonthStrip directly (no wrapper needed).
  - **Desktop two-column layout**: Wrap the dashboard content in a responsive grid. The key insight: on mobile, the grid doesn't activate, so the two `<div>`s stack vertically in source order — no need to duplicate SummaryWidget.
    ```tsx
    <div className="lg:grid lg:grid-cols-[320px_1fr] lg:gap-8">
      {/* Left panel — sticky on desktop, normal flow on mobile */}
      <div className="lg:sticky lg:top-4 lg:self-start space-y-5">
        <SummaryWidget incomeCents={incomeCents} expensesCents={expensesCents} showDashes={isEmptyMonth} />
        {imports.length > 0 && <ImportHistory imports={imports} />}
      </div>
      {/* Right panel */}
      <div className="space-y-5 max-w-[480px]">
        {hasAnyData && <MonthStrip ... />}
        {/* ... category cards, empty state, import button ... */}
      </div>
    </div>
    ```
  - **Right column width**: Add `max-w-[480px]` to the right panel div so category cards don't stretch beyond their designed width on wide desktop screens.
- Notes: The import button ("Import CSV"), drag-and-drop overlay, and confirmation dialog are unaffected. Error display stays at the top.

#### Task 5: Search page — remove back button
- File: `resources/js/pages/search.tsx`
- Action:
  - **Remove** the back button `<button>` element (with `ArrowLeft` icon) and the `handleBack` function. Navigation is now via the dock/header nav.
  - **Remove** `ArrowLeft` import from lucide-react.
  - **Adjust header**: The search input becomes the full-width header element. The `<div className="flex items-center gap-3">` wrapper simplifies — remove the back button, keep only the search input `<div className="relative flex-1">` (change `flex-1` to `w-full`).

#### Task 6: Recurring page — remove back button
- File: `resources/js/pages/recurring.tsx`
- Action:
  - **Remove** the back button `<button>` element (with `ArrowLeft` icon) and the `handleBack` function. Navigation is now via the dock/header nav.
  - **Remove** `ArrowLeft` import from lucide-react.
  - **Adjust header**: The `<div className="flex items-center gap-3">` wrapper simplifies — the `<div>` with title + count becomes the standalone page header.

#### Task 7: CSS — view transition exclusion for dock
- File: `resources/css/app.css`
- Action:
  - Add CSS rule to exclude the dock from page transitions:
    ```css
    ::view-transition-group(dock) {
      animation: none;
    }
    ```
  - This pairs with the `viewTransitionName: 'dock'` inline style set on the BottomDock component in Task 1.
- Notes: No `pb-safe` utility needed — the dock uses Tailwind arbitrary value `pb-[env(safe-area-inset-bottom)]` directly.

#### Task 8: PWA manifest + icons
- File: `public/manifest.json` (new)
- Action: Create web app manifest:
  ```json
  {
    "name": "Budget",
    "short_name": "Budget",
    "description": "Personal budget management",
    "id": "/dashboard",
    "start_url": "/dashboard",
    "scope": "/",
    "display": "standalone",
    "orientation": "any",
    "background_color": "#0A0A0B",
    "theme_color": "#0A0A0B",
    "icons": [
      {
        "src": "/icons/icon-192x192.png",
        "sizes": "192x192",
        "type": "image/png",
        "purpose": "any maskable"
      },
      {
        "src": "/icons/icon-512x512.png",
        "sizes": "512x512",
        "type": "image/png",
        "purpose": "any maskable"
      }
    ]
  }
  ```
- File: `public/icons/icon-192x192.png` and `public/icons/icon-512x512.png` (new)
- Action: Generate PNG icons. Create a simple SVG with a Wallet-style icon (white on `#0A0A0B` background), then convert to PNG at 192x192 and 512x512. The `purpose: "any maskable"` means the icon must have safe area padding (~20% on each side) so it looks good when Android crops it into a circle/squircle.
- Also update `public/apple-touch-icon.png` (180x180) with the same design.

#### Task 9: HTML shell — manifest link + iOS meta tags + viewport-fit
- File: `resources/views/app.blade.php`
- Action:
  - Update viewport meta to include `viewport-fit=cover`:
    ```html
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    ```
  - Add manifest link: `<link rel="manifest" href="/manifest.json">`
  - Add theme color: `<meta name="theme-color" content="#0A0A0B">`
  - Add iOS PWA meta tags (still needed for iOS Safari as of 2026):
    ```html
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    ```
  - Update `apple-touch-icon` link if a new icon is created at Task 8.

### Acceptance Criteria

- [ ] AC 1: Given I am on any authenticated page on mobile (< 1024px), when I look at the bottom of the screen, then I see a fixed dock with 3 items: Dashboard (LayoutGrid), Recurring (Repeat), Search (Search), each with an icon and label.

- [ ] AC 2: Given I am on the dashboard, when I look at the dock, then the Dashboard item is highlighted (white text/icon) and others are dimmed (tertiary text color).

- [ ] AC 3: Given I am on the category drill-down page (`/categories/5`), when I look at the dock, then the Dashboard item is highlighted (because drill-down is a sub-navigation of dashboard).

- [ ] AC 4: Given I am on the recurring page, when I look at the dock, then the Recurring item is highlighted.

- [ ] AC 5: Given I tap a dock item while viewing a specific account, when the page navigates, then the account filter is preserved in the URL query parameter.

- [ ] AC 6: Given I am on desktop (>= 1024px viewport), when I look at the bottom of the screen, then the dock is hidden. Instead, the header shows horizontal nav links (Dashboard, Recurring, Search) with the active page highlighted.

- [ ] AC 7: Given I am on the dashboard on desktop (>= 1024px), when I view the layout, then I see a two-column layout: left panel with SummaryWidget (sticky), right panel with MonthStrip + category cards (max-width 480px).

- [ ] AC 8: Given I am on the dashboard on mobile, when I view the layout, then I see the single-column layout with SummaryWidget above MonthStrip above category cards (same vertical order, no inline search/recurring buttons).

- [ ] AC 9: Given I am on the dashboard, when I look for search and recurring buttons inline with MonthStrip, then they are NOT present (removed — dock/header nav handles this).

- [ ] AC 10: Given I am on the search page, when I look for a back button, then there is no back arrow — navigation is via the dock (mobile) or header nav (desktop).

- [ ] AC 11: Given I am on the recurring page, when I look for a back button, then there is no back arrow — navigation is via the dock (mobile) or header nav (desktop).

- [ ] AC 12: Given I am on the category drill-down page, when I look for a back button, then there IS a back arrow (unchanged — drill-down is not a primary nav destination).

- [ ] AC 13: Given I look at the app header on any page, when I inspect it, then I see a small Wallet icon on the left and the AccountPicker on the right — no "budget-app" text. On desktop, nav links are visible between them.

- [ ] AC 14: Given I am using an iPhone with the home indicator bar, when I view the dock in standalone PWA mode, then the dock has proper bottom padding that doesn't overlap the home indicator.

- [ ] AC 15: Given I open the app on my phone's browser, when I choose "Add to Home Screen", then the app installs with the correct name ("Budget"), icon, and opens in standalone mode (no browser chrome).

- [ ] AC 16: Given the app is installed as a PWA, when it launches, then the status bar matches the dark theme (`#0A0A0B` background) and content fills the full screen.

- [ ] AC 17: Given toast notifications appear, when I am on mobile with the dock visible, then the toast appears at the top of the screen (not overlapping the dock).

- [ ] AC 18: Given I navigate between pages using the dock, when the page transitions, then the dock does NOT fade/flash — it remains stable while only the main content transitions.

- [ ] AC 19: Given I use keyboard navigation (Tab key), when I focus dock items, then I see a visible focus ring and can activate items with Enter/Space.

- [ ] AC 20: Given a screen reader is active, when it encounters the dock, then it announces "Main navigation" landmark and "current page" for the active item.

## Additional Context

### Dependencies

- No new npm packages needed. All tools already available:
  - `lucide-react` for icons (`LayoutGrid`, `Repeat`, `Search`, `Wallet`)
  - `@inertiajs/react` for `Link` component and `usePage()` (via `useCurrentUrl` hook)
  - `motion/react` optional for dock entrance animation
- Existing hooks to reuse:
  - `@/hooks/use-current-url` — `useCurrentUrl()` with `isCurrentUrl()` and `currentUrl`
  - `@/hooks/use-mobile` — `useIsMobile()` (available if needed, but CSS `lg:hidden` preferred)
- PWA icons: Need to generate PNG files at 192x192, 512x512, and 180x180 (apple-touch). Must include safe area padding for maskable purpose.
- No backend changes required.

### Testing Strategy

**Unit tests (Vitest + testing-library):**
- `resources/js/components/__tests__/BottomDock.test.tsx`:
  - Renders 3 dock items with correct labels ("Dashboard", "Recurring", "Search")
  - Highlights Dashboard when `currentUrl` is `/dashboard` (mock `usePage()`)
  - Highlights Dashboard when `currentUrl` is `/categories/5` (drill-down = dashboard)
  - Highlights Recurring when `currentUrl` is `/recurring`
  - Highlights Search when `currentUrl` is `/search`
  - Appends `?account=personal` to links when `currentAccount="personal"` prop is set
  - Each item renders as an Inertia `<Link>` with correct `href`
  - Active item has `aria-current="page"` attribute
  - Wrapped in `<nav>` with `aria-label="Main navigation"`
  - Has `lg:hidden` class for desktop hiding

**Manual testing:**
- Mobile (iPhone Safari): Verify dock renders at bottom, safe area padding works, tap targets are large enough, "Add to Home Screen" works, standalone mode looks correct, dock doesn't fade during page transitions
- Mobile (Android Chrome): Verify manifest install prompt, standalone mode, theme color, adaptive icon renders correctly
- Desktop (Chrome/Firefox): Verify dock is hidden, header nav links appear, two-column layout at 1024px+, layout collapses to single-column below 1024px
- Tablet (iPad portrait ~768px): Verify single-column layout with dock (NOT two-column)
- Toast notifications: Verify they appear at top-center, no overlap with dock
- Keyboard navigation: Tab through dock items, verify focus ring visibility
- Account preservation: Switch to "Professional" account, tap Recurring in dock, verify account param preserved

### Notes

- **Party mode decision**: The dock contains Dashboard, Recurring, Search — NOT Import. Import is an *action* (occasional), not a *navigation destination* (daily). This avoids lifting the import flow to the layout.
- **Adversarial review fixes applied**: All 16 findings from the adversarial review have been addressed:
  - F1: Desktop nav links added to header (no broken navigation)
  - F2: Toaster moved to `top-center` (no offset type issues)
  - F3: Single SummaryWidget instance via CSS grid stacking (no duplication)
  - F4: `useCurrentUrl()` hook used for active state detection
  - F5: PWA manifest includes `maskable` purpose, `scope`, `id`, `orientation`
  - F6: View transition exclusion via `view-transition-name: dock` + CSS
  - F7: Dashboard highlighted on `/categories/*` URLs
  - F8: Proxy file removed from modify list, `app-logo-icon.tsx` left as-is
  - F9: No dead `pb-safe` utility — dock uses inline Tailwind value
  - F10: Full accessibility spec: `<nav>`, `aria-current`, focus-visible
  - F11: Dock preserves `currentAccount` as query parameter
  - F12: Right column constrained to `max-w-[480px]`
  - F13: iOS meta tags kept with context note
  - F14: Accepted — dock renders on hydration, acceptable tradeoff
  - F15: Structural descriptions used instead of line numbers
  - F16: Label changed from "Home" to "Dashboard" for consistency
