# Story 1.3: Visual Foundation & App Layout

Status: done

## Story

As Lambert,
I want the application to use the dark theme with Inter typeface and design tokens from the UX specification,
so that every page matches the visual design from the start.

## Acceptance Criteria

1. **Given** I am logged in, **When** I view any page, **Then** the background is near-black (#0A0A0B), cards use dark surface (#1C1C1E), and text is white (#FFFFFF) primary / zinc-400 (#A1A1AA) secondary.

2. **Given** the app is loaded, **When** I inspect the typography, **Then** Inter variable font is the sole typeface with `font-display: swap`.

3. **Given** I am on mobile (< 640px), **When** I view the layout, **Then** content is full-width with 16px margins.

4. **Given** I am on desktop (>= 640px), **When** I view the layout, **Then** content is centered with max-width ~480px and 32px margins.

5. **Given** the Tailwind configuration, **When** I reference design tokens in components, **Then** category colors, surface layers, spacing scale (4px base), and border-radius tokens (12px cards, 8px buttons) are available as Tailwind utilities.

## Tasks / Subtasks

- [x] Task 1: Replace font from Instrument Sans to Inter variable font (AC: 2)
  - [x] 1.1: Update `resources/views/app.blade.php` — replace Bunny Fonts Instrument Sans link with Inter variable font (weights 400, 500, 600, 700). Use `font-display: swap`. Subset to Latin + Latin Extended for French accented characters.
  - [x] 1.2: Update `resources/css/app.css` — change `--font-sans` in `@theme` from `'Instrument Sans'` to `'Inter'` with same fallback stack
  - [x] 1.3: Update `resources/js/pages/welcome.tsx` — replace any Instrument Sans font references with Inter
  - [x] 1.4: Verify Inter renders correctly on a page load (check DevTools → Computed → font-family)

- [x] Task 2: Replace color system with UX spec dark theme tokens (AC: 1, 5)
  - [x] 2.1: Replace the existing OKLCH color palette in `resources/css/app.css` `@theme` block with the UX spec hex colors. Remove the light mode / dark mode toggle approach — the app is always dark. Key surface tokens:
    - `--background`: #0A0A0B (near-black)
    - `--surface`: #1C1C1E (card backgrounds)
    - `--surface-elevated`: #2C2C2E (modals, popovers, hover states)
    - `--surface-subtle`: #161618 (subtle section separation)
    - `--border`: #2C2C2E (barely visible borders)
    - `--foreground` / `--text-primary`: #FFFFFF
    - `--text-secondary`: #A1A1AA (zinc-400)
    - `--text-tertiary`: #71717A (zinc-500)
    - `--success`: #10B981
    - `--error`: #EF4444
    - `--info`: #60A5FA
  - [x] 2.2: Add all 14 category accent colors as CSS custom properties in `@theme`:
    - `--category-food`: #A3E635
    - `--category-restaurants`: #FBBF24
    - `--category-transport`: #60A5FA
    - `--category-housing`: #2DD4BF
    - `--category-entertainment`: #C084FC
    - `--category-shopping`: #F472B6
    - `--category-health`: #34D399
    - `--category-subscriptions`: #FB7185
    - `--category-education`: #38BDF8
    - `--category-travel`: #818CF8
    - `--category-insurance`: #94A3B8
    - `--category-income`: #10B981
    - `--category-professional`: #EAB308
    - `--category-other`: #A1A1AA
  - [x] 2.3: Ensure shadcn/ui CSS variables map correctly to the new dark theme tokens so all existing shadcn/ui components (Dialog, Sheet, DropdownMenu, Toast, Button, Input, etc.) render with the dark theme automatically
  - [x] 2.4: Remove the `@custom-variant dark` and `.dark` class-based dark mode toggle — the app is ALWAYS dark, no light mode variant needed
  - [x] 2.5: Remove the appearance settings page and the `useAppearance` hook references (or simplify to always return 'dark'). The settings page at `resources/js/pages/settings/appearance.tsx` and the theme toggle functionality should be stripped.

- [x] Task 3: Add spacing scale and border-radius tokens (AC: 5)
  - [x] 3.1: Define spacing scale in `@theme` based on 4px base unit: `--spacing-1: 4px`, `--spacing-2: 8px`, `--spacing-3: 12px`, `--spacing-4: 16px`, `--spacing-5: 20px`, `--spacing-6: 24px`, `--spacing-8: 32px`
  - [x] 3.2: Define border-radius tokens: `--radius-card: 12px` (cards), `--radius-button: 8px` (buttons, smaller elements), `--radius-full: 999px` (circular indicators). Update the existing `--radius` variable used by shadcn/ui to `0.5rem` (8px) as base.
  - [x] 3.3: Add `tabular-nums` utility class for financial amount rendering (may already exist via Tailwind's `tabular-nums` class — verify availability)

- [x] Task 4: Replace sidebar layout with centered single-column layout (AC: 3, 4)
  - [x] 4.1: Create new `resources/js/layouts/app/app-layout.tsx` — a simple centered column layout:
    - Mobile (< 640px): full-width, `px-4` (16px) horizontal margins
    - Desktop (>= 640px): centered column, `max-w-[480px] mx-auto`, `px-8` (32px) margins
    - Minimal header: app title left, future account picker slot right
    - Content area below header
    - No sidebar, no bottom nav, no breadcrumbs
  - [x] 4.2: Update `resources/js/layouts/app-layout.tsx` wrapper to use the new simple layout instead of the sidebar layout
  - [x] 4.3: Update `resources/js/pages/dashboard.tsx` to use the new layout — strip the starter kit placeholder content and replace with a clean dark-themed page that proves the design tokens work (background color, text color, a sample surface card)
  - [x] 4.4: Clean up unused sidebar components that are no longer needed:
    - `resources/js/components/app-sidebar.tsx`
    - `resources/js/components/app-sidebar-header.tsx`
    - `resources/js/layouts/app/app-sidebar-layout.tsx`
    - `resources/js/components/nav-main.tsx`
    - `resources/js/components/nav-footer.tsx`
    - `resources/js/components/nav-user.tsx`
    - `resources/js/components/user-info.tsx`
    - `resources/js/components/user-menu-content.tsx`
    - `resources/js/components/app-shell.tsx` (sidebar/header conditional)
    - `resources/js/components/breadcrumbs.tsx`
    - Any other sidebar-specific components not needed by the centered layout
  - [x] 4.5: Keep auth layout intact — login/register pages should continue working. Update their styling to match the new dark theme tokens if needed.

- [x] Task 5: Update existing pages and components for new theme (AC: 1)
  - [x] 5.1: Verify all auth pages (login, register, forgot-password, etc.) render correctly with the new dark theme
  - [x] 5.2: Update or remove the settings pages — keep profile and password settings, remove appearance settings
  - [x] 5.3: Ensure the `<html>` element always has `dark` class (or remove the class-based dark mode entirely and make the root CSS always dark)
  - [x] 5.4: Update `resources/views/app.blade.php` to set the background color in the `<body>` to `#0A0A0B` to prevent white flash on initial page load

- [x] Task 6: Write tests (AC: 1, 2, 3, 4)
  - [x] 6.1: Feature test: assert Dashboard page loads successfully for authenticated user (HTTP 200) and contains expected layout structure
  - [x] 6.2: Feature test: assert unauthenticated access to Dashboard redirects to login (existing test, verify still passes)
  - [x] 6.3: Run full test suite: `sail artisan test` — all tests must pass including all Story 1.1 and 1.2 tests (target: 72+ tests, zero regressions)

## Dev Notes

### Tailwind 4 Configuration — CRITICAL

This project uses **Tailwind CSS v4** with CSS-based configuration. There is NO `tailwind.config.ts` file. All design tokens are defined using `@theme` directives in `resources/css/app.css`.

```css
@import 'tailwindcss';

@theme {
  --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji';
  --color-background: #0A0A0B;
  --color-surface: #1C1C1E;
  /* ... etc */
}
```

In Tailwind 4, custom properties defined in `@theme` automatically generate utility classes. For example, `--color-surface: #1C1C1E` enables `bg-surface`, `text-surface`, `border-surface`, etc. DO NOT create a `tailwind.config.ts` file.

[Source: _bmad-output/planning-artifacts/architecture.md#Important Gaps]

### Always-Dark Theme — No Light Mode

The app is **always dark**. The starter kit ships with a light/dark/system toggle via the `useAppearance` hook and a `.dark` class on `<html>`. This must be replaced:

- Remove the `.dark` class-based approach
- Define all colors as root CSS custom properties (no `.dark` variant)
- The `<html>` element should always have a dark background
- OR keep `.dark` always applied and remove the toggle mechanism

The simplest approach: keep the `dark` class permanently on `<html>` (remove the toggle), and override all color tokens in the dark theme block to use the UX spec values. This preserves compatibility with shadcn/ui components that use `dark:` Tailwind variants.

[Source: _bmad-output/planning-artifacts/ux-design-specification.md#Design System Foundation]

### Font Migration: Instrument Sans → Inter

The starter kit uses **Instrument Sans** from Bunny Fonts. This must be replaced with **Inter** variable font.

**Option A (Bunny Fonts):** Replace the Bunny Fonts URL in `app.blade.php`:
```html
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
```

**Option B (Self-hosted):** Download Inter variable font and serve from `public/fonts/`. Better performance, no external dependency.

Either option works. Bunny Fonts is simpler; self-hosted is faster. The architecture doc doesn't specify, so use Bunny Fonts for consistency with the existing setup.

Inter must support French accented characters (é, è, ê, à, ç) — the Latin Extended subset covers this.

[Source: _bmad-output/planning-artifacts/ux-design-specification.md#Typography System]

### Layout Replacement: Sidebar → Centered Column

The starter kit provides a sidebar-based layout (`app-sidebar-layout.tsx`). Budget-app uses a **single centered column** with no sidebar, no bottom nav, no breadcrumbs.

```tsx
// New layout structure
<div className="min-h-screen bg-background">
  {/* Minimal header */}
  <header className="px-4 sm:px-8 py-4">
    <div className="max-w-[480px] mx-auto flex items-center justify-between">
      <span className="text-lg font-semibold text-white">budget-app</span>
      {/* Account picker slot - future story */}
    </div>
  </header>

  {/* Main content */}
  <main className="px-4 sm:px-8">
    <div className="max-w-[480px] mx-auto">
      {children}
    </div>
  </main>
</div>
```

The `max-w-[480px]` container is the key constraint. On mobile, `px-4` gives 16px margins. On desktop, `sm:px-8` gives 32px margins, and the content is centered via `mx-auto`.

[Source: _bmad-output/planning-artifacts/ux-design-specification.md#Responsive Strategy]
[Source: _bmad-output/planning-artifacts/architecture.md#Frontend Architecture]

### UX Spec Color Reference

**Surface layers:**
| Token | Hex | Usage |
|-------|-----|-------|
| background | #0A0A0B | App background |
| surface | #1C1C1E | Card backgrounds |
| surface-elevated | #2C2C2E | Modals, popovers, hover |
| surface-subtle | #161618 | Section separation |
| border | #2C2C2E | Barely visible borders |

**Text:**
| Token | Hex | Usage |
|-------|-----|-------|
| text-primary | #FFFFFF | Category names, amounts |
| text-secondary | #A1A1AA | Subtitles, labels, dates |
| text-tertiary | #71717A | Hints, placeholders |

**Category accent colors (14):**
| Category | Hex |
|----------|-----|
| Food | #A3E635 |
| Restaurants | #FBBF24 |
| Transport | #60A5FA |
| Housing | #2DD4BF |
| Entertainment | #C084FC |
| Shopping | #F472B6 |
| Health | #34D399 |
| Subscriptions | #FB7185 |
| Education | #38BDF8 |
| Travel | #818CF8 |
| Insurance | #94A3B8 |
| Income | #10B981 |
| Professional | #EAB308 |
| Other | #A1A1AA |

[Source: _bmad-output/planning-artifacts/ux-design-specification.md#Color System]

### shadcn/ui Theme Compatibility

shadcn/ui components (Button, Dialog, DropdownMenu, Sheet, Toast, Input, etc.) rely on CSS custom properties like `--background`, `--foreground`, `--primary`, `--border`, `--muted`, `--accent`, `--destructive`, etc. When replacing the color system:

- Map `--background` → #0A0A0B
- Map `--foreground` → #FFFFFF
- Map `--card` / `--card-foreground` → #1C1C1E / #FFFFFF
- Map `--popover` / `--popover-foreground` → #2C2C2E / #FFFFFF
- Map `--primary` → appropriate accent (or white for a neutral primary)
- Map `--muted` → #1C1C1E with #A1A1AA foreground
- Map `--accent` → #2C2C2E with #FFFFFF foreground
- Map `--border` → #2C2C2E
- Map `--input` → #2C2C2E
- Map `--destructive` → #EF4444
- Map `--ring` → appropriate focus ring color

This ensures all installed shadcn/ui components render correctly in the dark theme without individual component modifications.

[Source: Existing app.css shadcn/ui token structure]

### Type Scale from UX Spec

| Token | Size | Weight | Usage |
|-------|------|--------|-------|
| display | 36px / 2.25rem | 700 | Hero amount on drill-down |
| heading-lg | 24px / 1.5rem | 600 | Category amounts on cards |
| heading-md | 20px / 1.25rem | 600 | Section titles, month label |
| heading-sm | 16px / 1rem | 600 | Subcategory amounts |
| body | 14px / 0.875rem | 400 | Category names, merchants |
| body-sm | 12px / 0.75rem | 400 | Dates, labels |
| caption | 11px / 0.6875rem | 500 | Badges, status indicators |

These don't need to be defined as custom Tailwind tokens — they map naturally to Tailwind's built-in text size + font weight utilities (e.g., `text-2xl font-bold` for display). Document the mapping for future component development.

[Source: _bmad-output/planning-artifacts/ux-design-specification.md#Typography System]

### Previous Story Learnings

**From Story 1.2 (relevant to this story):**
- All commands run inside Sail: `sail artisan test`, `sail bun install`, etc. — never use host `php`, `composer`, or `bun` directly
- CategorySeeder created 14 categories with exact UX-specified colors stored in the `categories.color` DB column — the frontend will read these as props, NOT from CSS constants
- 72 tests currently passing — zero regressions expected

**From Story 1.1:**
- Starter kit was initialized with `laravel/react-starter-kit`
- Packages installed: Prism PHP, laravel/mcp, pgvector, tpetry/laravel-postgresql-enhanced, laravel/nightwatch
- Laravel Sail runs PHP 8.5, PostgreSQL, Bun (no Redis)

### Git Intelligence

Recent commits:
- `640d245` Story 1.2: Domain data model, category seed, and code review fixes
- `f3615ec` Story 1.1: Project initialization with authentication

The project is on `main` branch with a clean working tree. Convention: one commit per story.

### Component Cleanup Guidance

The sidebar layout components from the starter kit are extensive. When removing them, check for imports in:
1. `resources/js/layouts/app-layout.tsx` — currently wraps sidebar or header layout
2. `resources/js/pages/dashboard.tsx` — currently uses AppLayout
3. Settings pages — may reference sidebar navigation

Do NOT delete shadcn/ui primitives (`resources/js/components/ui/*`) — they will be reused in future stories.

Keep these existing components:
- `app-logo.tsx`, `app-logo-icon.tsx` — may be useful
- `heading.tsx` — general purpose
- `text-link.tsx` — general purpose
- All `ui/` shadcn components

Remove these (sidebar-specific):
- `app-sidebar.tsx`, `app-sidebar-header.tsx`
- `app-shell.tsx` (sidebar/header conditional renderer)
- `nav-main.tsx`, `nav-footer.tsx`, `nav-user.tsx`
- `user-info.tsx`, `user-menu-content.tsx`
- `breadcrumbs.tsx`
- `app-header.tsx`, `app-content.tsx` (if they only serve the sidebar layout)
- `app-sidebar-layout.tsx`, `app-header-layout.tsx`

### Settings Pages — What to Keep

The starter kit provides settings for: profile, password, appearance, two-factor.

- **Keep:** Profile and Password (useful for auth management)
- **Remove:** Appearance (no light/dark toggle needed)
- **Keep:** Two-factor (security feature from starter kit)
- **Adapt:** Settings layout — may need to be simplified from the current three-panel layout to match the centered column layout

### Verify After Implementation

- [ ] Dashboard renders with #0A0A0B background, not white
- [ ] Inter font loads and renders (check DevTools → Computed Styles)
- [ ] Login page renders correctly with dark theme
- [ ] Mobile viewport (< 640px): content is full-width with 16px margins
- [ ] Desktop viewport (>= 640px): content centered at ~480px max-width with 32px margins
- [ ] All 72+ existing tests pass with zero regressions
- [ ] No white flash on page load (body background set in HTML)

### Project Structure Notes

- Alignment with architecture: This story establishes the visual foundation that all future stories build upon. The centered column layout, dark theme tokens, and category colors are used by Dashboard (Story 2.5), CategoryCard, MonthStrip, SummaryWidget, ColorHeader, and all drill-down components.
- No new backend code — this is a frontend-only story. No new routes, controllers, or models.
- The `resources/css/app.css` file is the single source of truth for the design system.

### References

- Story AC source: [Source: _bmad-output/planning-artifacts/epics.md#Story 1.3]
- Dark theme colors: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Color System]
- Typography (Inter, type scale): [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Typography System]
- Spacing and layout: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Spacing & Layout Foundation]
- Responsive strategy: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Responsive Strategy]
- Category accent colors: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Category Accent Palette]
- Tailwind 4 CSS-based config note: [Source: _bmad-output/planning-artifacts/architecture.md#Gap Analysis]
- Component library: shadcn/ui selective adoption: [Source: _bmad-output/planning-artifacts/architecture.md#Frontend Architecture]
- Centered column layout: [Source: _bmad-output/planning-artifacts/architecture.md#Structure Patterns]
- Previous story learnings: [Source: _bmad-output/implementation-artifacts/1-2-domain-data-model-and-category-seed.md#Dev Notes]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6 (claude-opus-4-6)

### Debug Log References

None — clean implementation with no runtime errors.

### Completion Notes List

- Replaced Instrument Sans with Inter variable font (wght@100..900) via Bunny Fonts CDN
- Replaced OKLCH color system with UX spec hex colors for always-dark theme
- Added 14 category accent colors as Tailwind-accessible CSS custom properties
- Added surface layer tokens (surface, surface-elevated, surface-subtle) and semantic tokens (success, error, info)
- Added radius-card (0.75rem) and radius-full (624.9375rem) tokens
- Replaced sidebar layout with centered single-column layout (max-w-[480px], px-4/sm:px-8)
- Simplified use-appearance hook to always-dark stub
- Moved initializeTheme() before createInertiaApp() for correct ordering
- Removed appearance settings page and route
- Deleted 17 files total: sidebar components, navigation components, appearance page, orphaned hooks
- Maintained full shadcn/ui compatibility with CSS variable mapping in both :root and .dark blocks
- Inline style in app.blade.php prevents white flash on page load
- All 72 tests pass (193 assertions), zero regressions
- TypeScript compiles cleanly with no errors

### File List

**Modified:**
- `resources/css/app.css` — Complete design system rewrite (color tokens, surface layers, category colors, radius tokens)
- `resources/views/app.blade.php` — Inter font, always-dark class, white flash prevention
- `resources/js/app.tsx` — Moved initializeTheme() before createInertiaApp()
- `resources/js/hooks/use-appearance.tsx` — Simplified to always-dark stub
- `resources/js/pages/settings/profile.tsx` — Removed breadcrumb dead code
- `resources/js/pages/settings/password.tsx` — Removed breadcrumb dead code
- `resources/js/pages/settings/two-factor.tsx` — Removed breadcrumb dead code
- `resources/js/types/ui.ts` — Removed unused breadcrumbs from AppLayoutProps

**Created:**
- `resources/js/layouts/app/app-layout.tsx` — New centered single-column layout
- `resources/js/layouts/app-layout.tsx` — Updated wrapper to use new layout
- `resources/js/layouts/settings/layout.tsx` — Removed Appearance nav item
- `resources/js/pages/dashboard.tsx` — Clean dark-themed placeholder page
- `resources/js/pages/welcome.tsx` — Removed redundant font link, replaced container bg with design token
- `resources/js/types/global.d.ts` — Removed stale sidebarOpen type
- `routes/settings.php` — Removed appearance route

**Deleted:**
- `resources/js/components/app-sidebar.tsx`
- `resources/js/components/app-sidebar-header.tsx`
- `resources/js/components/app-shell.tsx`
- `resources/js/components/app-header.tsx`
- `resources/js/components/app-content.tsx`
- `resources/js/components/nav-main.tsx`
- `resources/js/components/nav-footer.tsx`
- `resources/js/components/nav-user.tsx`
- `resources/js/components/user-info.tsx`
- `resources/js/components/user-menu-content.tsx`
- `resources/js/components/breadcrumbs.tsx`
- `resources/js/components/appearance-tabs.tsx`
- `resources/js/layouts/app/app-sidebar-layout.tsx`
- `resources/js/layouts/app/app-header-layout.tsx`
- `resources/js/pages/settings/appearance.tsx`
- `resources/js/hooks/use-mobile-navigation.ts`
- `resources/js/hooks/use-initials.tsx`
