# AGENTS.md

See [CLAUDE.md](CLAUDE.md) for the project overview and conventions.

- Check command: `composer test` runs Pint (test mode), PHPStan (Larastan, `phpstan.neon`), and PHPUnit. CI runs the same checks in `.github/workflows/tests.yml`.
- The app deploys on Laravel Cloud; use `./vendor/bin/cloud` (laravel/cloud-cli) to inspect it.
