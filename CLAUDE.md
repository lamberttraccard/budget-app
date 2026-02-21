# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Personal budget management app built with **Laravel 12 + React 19 + Inertia.js 2**. Features CSV transaction imports with AI-powered categorization (Gemini via Laravel AI), multi-account support, and an MCP server for AI integration. Database is PostgreSQL with pgvector.

## Commands

All commands run inside the Sail container. Use `./vendor/bin/sail` (aliased as `sail` below) as the prefix.

### Development
```bash
sail up -d                        # Start containers
sail composer dev                 # Runs Laravel server + queue worker + Pail logs + Vite (all at once)
sail bun run dev                  # Vite dev server only
```

### Testing
```bash
sail composer test                # PHP: clears config, lint check (Pint), then PHPUnit
sail artisan test                 # PHPUnit only
sail bun run test                 # Vitest (frontend, single run)
```

### Linting & Formatting
```bash
sail bun run lint                 # ESLint with auto-fix
sail bun run format               # Prettier on resources/
sail bun run types                # TypeScript type checking (tsc --noEmit)
sail composer lint                # PHP Pint auto-fix
```

### Setup
```bash
sail composer setup               # Full init: install deps, .env, key:generate, migrate, bun install, build
```

## Architecture

### Request Flow
Laravel routes → Controllers → Inertia::render() → React page components receive props. No SPA router; Inertia handles navigation with server-side routing.

### Key Directories
- `app/Services/` — Core business logic (BudgetQueryService, CsvImportService, CategorizationService, DeduplicationService, MerchantMatchingService)
- `app/Parsers/` — Bank-specific CSV parsers (Revolut, Banque Populaire, etc.)
- `app/Mcp/` — MCP server tools for AI integration
- `resources/js/pages/` — Inertia page components (dashboard.tsx, categories/)
- `resources/js/components/ui/` — shadcn/ui components (Radix primitives + Tailwind)
- `resources/js/types/budget.ts` — Shared TypeScript types for budget domain
- `resources/js/lib/` — Utility functions (formatAmount, formatDate)
- `routes/web.php` — Main web routes

### Data Model
User → Accounts (1:N) → Transactions (1:N). Categories and Subcategories are shared. Transactions belong to Categories and optionally to Imports.

## Conventions

### Monetary Values
All amounts stored as **integer cents** (`amount_cents` in DB, `amountCents` in TypeScript). Display formatting uses `formatAmount()` which renders French locale EUR (e.g., `1 500,00 €`).

### TypeScript
- Separate type imports required: `import type { Foo } from 'module'` (enforced by ESLint)
- Import order enforced: builtin → external → internal → parent → sibling → index (alphabetical within groups)
- Path alias: `@/` maps to `resources/js/`
- Strict mode enabled

### Frontend
- **Bun** as package manager (not npm/yarn)
- Tailwind CSS 4 with shadcn/ui component library
- Animation via `motion/react` (Framer Motion)
- Icons via `lucide-react`

### PHP
- Laravel Pint for code style
- Constructor injection for services
- Events + Listeners for decoupled side effects (e.g., TransactionCorrected)
- Queued jobs for async work (CSV imports, AI categorization)

### Testing
- Frontend: Vitest + @testing-library/react + jsdom. Setup file at `resources/js/test/setup.ts`
- Backend: PHPUnit with Mockery. Factories in `database/factories/`
