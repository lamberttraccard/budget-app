# AGENTS.md

Personal budget management app built with **Laravel 12 + React 19 + Inertia.js 2**: CSV transaction imports with AI categorization (Gemini via Laravel AI), multi-account support, and an MCP server. Database: PostgreSQL with pgvector. See [README.md](README.md) for features and local setup with Sail.

## Architecture

- Request flow: Laravel routes → controllers → `Inertia::render()` → React page components receive props. No client-side router; Inertia handles navigation with server-side routing.
- Data model: User → Accounts (1:N) → Transactions (1:N). Categories and Subcategories are shared. Transactions belong to Categories and optionally to Imports.
- `app/Services/`: core business logic (`BudgetQueryService`, `CsvImportService`, `CategorizationService`, `DeduplicationService`, `MerchantMatchingService`, `RecurringDetectionService`).
- `app/Parsers/`: bank-specific CSV parsers (Revolut, Banque Populaire) behind `CsvParserFactory`.
- `app/Mcp/`: MCP server and tools.
- `routes/web.php`: main web routes.
- `resources/js/pages/`: Inertia pages. `resources/js/components/ui/`: shadcn/ui components (Radix + Tailwind). `resources/js/types/budget.ts`: shared budget domain types. `resources/js/lib/`: helpers such as `formatAmount` and `formatDate`.

## Conventions

- Money: amounts are stored as **integer cents** (`amount_cents` in the database, `amountCents` in TypeScript). `formatAmount()` renders French-locale EUR, such as `1 500,00 €`.
- TypeScript: strict mode; `@/` maps to `resources/js/`; type imports go in separate statements (`import type { Foo } from 'module'`); imports are ordered builtin → external → internal → parent → sibling → index, alphabetized. ESLint enforces both.
- Frontend: Tailwind CSS 4 with shadcn/ui, animation with `motion/react`, icons from `lucide-react`.
- PHP: Laravel Pint style, constructor injection for services, events and listeners for decoupled side effects (such as `TransactionCorrected`), queued jobs for async work (CSV imports, AI categorization).
- Tests: frontend with Vitest, Testing Library, and jsdom (setup in `resources/js/test/setup.ts`); backend with PHPUnit and Mockery, factories in `database/factories/`.

## Deployment

- Platform: **Laravel Cloud** (organization `lambert`, application `budget-app`, region `eu-central-1`). Not Forge, not Cloudflare Workers.
- Production URL: https://budget.lamberttraccard.dev (custom domain, `APP_URL` in production). It is the only production URL: the Laravel Cloud address is disabled. Health check: `/up`.
- Checking production from an orb: a Cloudflare WAF rule on `lamberttraccard.dev` allows only France, so requests from an orb get a 403 unless they send the `X-Amp-Orb-Check` header with the `ORB_PROD_CHECK_TOKEN` Amp secret, for example `curl -H "X-Amp-Orb-Check: $ORB_PROD_CHECK_TOKEN" https://budget.lamberttraccard.dev/up`. Orbs receive the secret at startup; an orb that was already running must run `amp orb restart-processes` to load it.
- Environments: only `main` (ID `env-a1229a44-445d-42c1-ab09-f53511f80cc5`). There is no staging or preview environment.
- Deploys: push-to-deploy. Every push to `main` on `lamberttraccard/budget-app` starts a deploy. GitHub shows nothing about it, so follow the deploy with `./vendor/bin/cloud deployment:list <env-id> --json -n` until its status is `deployment.succeeded` or `deployment.failed`.
- Build command (set in the Cloud dashboard, not in the repo): `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`, `npm install -g bun`, `bun install`, `bun run build`. Deploy command: `php artisan migrate --force`. Migrations therefore run on every deploy.
- Infrastructure: one `flex-512mb` App instance with hibernation, a Laravel Cloud managed queue (`QUEUE_CONNECTION=cloud`, 0 to 3 workers), a Neon serverless Postgres cluster named `budget-app`, no Redis or cache cluster, scheduler off (the app defines no scheduled tasks). Production drivers: cache `database`, session `cookie`, mail `log` (production sends no email), logs `laravel-cloud-socket,nightwatch`.
- Variables set on the environment: `APP_KEY` and `GEMINI_API_KEY`; Cloud injects `DB_*`, queue, and Nightwatch variables. Cloud caches config at deploy time: set any new variable with `./vendor/bin/cloud environment:variables` before pushing the code that needs it.
- Monitoring: Laravel Nightwatch is active in production.
- `.env.example` documents the production settings in a comment block; the Cloud dashboard is the source of truth.

### Laravel Cloud CLI

`./vendor/bin/cloud` (laravel/cloud-cli, dev dependency) reads `LARAVEL_CLOUD_TOKEN` from the environment. Useful read-only commands:

```bash
./vendor/bin/cloud application:list --json -n
./vendor/bin/cloud environment:get env-a1229a44-445d-42c1-ab09-f53511f80cc5 --json -n
./vendor/bin/cloud deployment:list env-a1229a44-445d-42c1-ab09-f53511f80cc5 --json -n
./vendor/bin/cloud command:run env-a1229a44-445d-42c1-ab09-f53511f80cc5 --cmd='php artisan about' -n
```

Never pass `--show-sensitive`, and never print raw API responses: environment payloads contain secrets.

## Commands

The README prefixes commands with `./vendor/bin/sail` for Docker. In an Amp orb, PHP, Composer, Bun, and PostgreSQL run natively, so drop the prefix.

| Purpose | Command |
|---|---|
| Full PHP check (what CI runs) | `composer test`: Pint `--test`, PHPStan (Larastan level 7, `phpstan.neon`), then PHPUnit |
| PHPUnit only | `php artisan test` (uses the `testing` database from `phpunit.xml`) |
| Frontend tests | `bun run test` (Vitest) |
| TypeScript | `bun run types` |
| PHP style fix | `composer lint` (Pint) |
| Frontend lint and format checks (what CI runs) | `bun run lint:check` (ESLint), `bun run format:check` (Prettier on `resources/`) |
| Frontend lint and format fixes (rewrite files) | `bun run lint` (ESLint `--fix`), `bun run format` (Prettier) |
| Build assets | `bun run build` (also generates the gitignored Wayfinder files in `resources/js/{actions,routes,wayfinder}`) |
| Dev server | `composer dev` (server, queue, Pail, Vite) |

CI (`.github/workflows/`) runs on pushes and pull requests to `main`, `develop`, `master`, and `workos`:
- `tests.yml`: Pint, PHPStan, asset build, PHPUnit against `pgvector/pgvector:pg17`.
- `lint.yml` (workflow `linter`): `composer test:lint`, `bun run format:check`, then `php artisan wayfinder:generate --with-form` and `bun run lint:check`. Each step fails on any issue and fixes nothing; run `composer lint`, `bun run format`, and `bun run lint` locally to fix.

ESLint needs the generated Wayfinder files (`resources/js/{actions,routes,wayfinder}`) to classify `@/actions` and `@/routes` imports, and ignores the files themselves. Generate them with `bun run build` or `php artisan wayfinder:generate --with-form` before linting.

Package manager: Bun only (`bun.lock`); do not use npm or yarn.

## Amp orb

- `.agents/setup` installs PHP 8.5 (sury), Composer, PostgreSQL 17 with pgvector, then runs `composer install`, `bun install`, writes `.env` from `.env.example` (with `DB_HOST=127.0.0.1` and `MAIL_MAILER=log`), generates the key, migrates, seeds an empty database, and builds assets. It is idempotent. `.agents/resume` only starts PostgreSQL.
- PostgreSQL: `127.0.0.1:5432`, user `sail`, password `password`, databases `budget_app` (app) and `testing` (PHPUnit).
- `amp orb services ensure` starts `web` (portal) and `queue` from `.amp/services.yaml`. The web service serves built assets, so run `bun run build` after frontend changes.
- Seeded login: `test@example.com` / `password` (from `DemoSeeder`).
- AI categorization needs `GEMINI_API_KEY`; tests do not.
