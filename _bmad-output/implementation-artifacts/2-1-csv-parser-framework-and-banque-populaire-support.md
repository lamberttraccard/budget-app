# Story 2.1: CSV Parser Framework & Banque Populaire Support

Status: done

## Story

As Lambert,
I want to parse Banque Populaire CSV exports with correct handling of European formats and French characters,
so that my bank transactions can be ingested into the application.

## Acceptance Criteria

1. **Given** a Banque Populaire CSV file with semicolon delimiters and comma decimal separators, **When** the file is processed by BanquePopulaireParser, **Then** each row is parsed into a TransactionData DTO with date, description, amountCents (integer), and rawLine (FR1, FR3).

2. **Given** a CSV with DD/MM/YYYY date format, **When** it is parsed, **Then** dates are correctly converted to ISO 8601 format (FR3).

3. **Given** a CSV encoded in ISO-8859-1 with French accented characters (e.g. "Boulangerie Lefevre"), **When** it is parsed, **Then** merchant names are correctly normalized to UTF-8 without corruption (FR4, NFR11).

4. **Given** the CsvParserFactory receives a file, **When** it calls `canParse()` on registered parsers, **Then** BanquePopulaireParser correctly identifies its format and the factory returns the matching parser.

5. **Given** a CSV that matches no registered parser, **When** CsvParserFactory attempts detection, **Then** an UnrecognizedCsvFormatException is thrown with a clear message listing expected formats (FR7).

## Tasks / Subtasks

- [x] Task 1: Create CSV Parser Strategy Pattern Foundation (AC: 4, 5)
  - [x] 1.1: Create `app/Parsers/CsvParserInterface.php` with `canParse(string $content, string $filename): bool` and `parse(string $content): Collection` methods
  - [x] 1.2: Create `app/Parsers/TransactionData.php` as a simple DTO with properties: `string $date` (ISO 8601), `string $description`, `int $amountCents`, `string $rawLine`
  - [x] 1.3: Create `app/Parsers/CsvParserFactory.php` with `detect(string $content, string $filename): CsvParserInterface` method that iterates registered parsers, calls `canParse()`, and returns the first match
  - [x] 1.4: Create `app/Exceptions/UnrecognizedCsvFormatException.php` thrown when no parser matches, with message listing expected formats (e.g., "Unrecognized CSV format. Expected: Banque Populaire or Revolut export.")
  - [x] 1.5: Create `app/Exceptions/CsvParseException.php` for parse-time failures (malformed rows, invalid data)
  - [x] 1.6: Register parsers in `app/Providers/AppServiceProvider.php` -- bind `CsvParserFactory` as singleton with array of registered parser class names

- [x] Task 2: Implement BanquePopulaireParser (AC: 1, 2, 3)
  - [x] 2.1: Create `app/Parsers/BanquePopulaireParser.php` implementing `CsvParserInterface`
  - [x] 2.2: Implement `canParse()` -- detect by checking for expected header columns: `Compte`, `Date de comptabilisation`, `Date opération`, `Libellé`, `Référence`, `Date valeur`, `Montant` (semicolon-delimited)
  - [x] 2.3: Implement encoding detection and conversion: convert file content from ISO-8859-15 (Latin-9, Banque Populaire's actual encoding) to UTF-8 using `mb_convert_encoding()` before parsing. Handle both ISO-8859-15 and ISO-8859-1 gracefully (FR4, NFR11)
  - [x] 2.4: Parse semicolon-delimited rows using `str_getcsv($line, ';')` after encoding conversion
  - [x] 2.5: Convert DD/MM/YYYY dates from the `Date opération` column to ISO 8601 (`YYYY-MM-DD`) using Carbon (FR3)
  - [x] 2.6: Convert comma-decimal amounts from `Montant` column to integer cents: parse `"-6,00"` -> replace comma with dot -> multiply by 100 -> cast to int. Handle variable decimal places (e.g., `"-81,4"` -> `-8140`) (FR1, FR3)
  - [x] 2.7: Map to TransactionData DTO: `date` from `Date opération`, `description` from `Libellé`, `amountCents` from `Montant`, `rawLine` as the original CSV line
  - [x] 2.8: Skip the header row (first row with column names)
  - [x] 2.9: Handle edge cases: empty lines, lines with insufficient columns, `\r\n` line endings from Windows-style exports

- [x] Task 3: Write Unit Tests for BanquePopulaireParser (AC: 1, 2, 3, 4, 5)
  - [x] 3.1: Create `tests/Unit/BanquePopulaireParserTest.php`
  - [x] 3.2: Test `canParse()` returns true for valid BP CSV content with correct headers
  - [x] 3.3: Test `canParse()` returns false for non-BP CSV content (Revolut format, random text, empty content)
  - [x] 3.4: Test parsing a valid multi-row CSV produces correct TransactionData DTOs with expected date, description, amountCents
  - [x] 3.5: Test DD/MM/YYYY dates are converted to ISO 8601 (`YYYY-MM-DD`)
  - [x] 3.6: Test comma decimal amounts are correctly converted to integer cents (including negative amounts, variable decimal places like `-81,4`)
  - [x] 3.7: Test ISO-8859-15 encoded content with French accented characters (`é`, `è`, `ê`, `à`, `ç`) is correctly decoded to UTF-8
  - [x] 3.8: Test empty lines and malformed rows are handled gracefully (skipped or throw CsvParseException)
  - [x] 3.9: Test `\r\n` line endings are handled correctly

- [x] Task 4: Write Unit Tests for CsvParserFactory (AC: 4, 5)
  - [x] 4.1: Create `tests/Unit/CsvParserFactoryTest.php`
  - [x] 4.2: Test factory returns BanquePopulaireParser for BP-formatted content
  - [x] 4.3: Test factory throws UnrecognizedCsvFormatException for unknown format
  - [x] 4.4: Test exception message lists expected formats

- [x] Task 5: Run Full Test Suite (AC: all)
  - [x] 5.1: Run `sail artisan test` -- all existing 79 tests must pass with zero regressions
  - [x] 5.2: All new parser tests must pass
  - [x] 5.3: TypeScript compiles cleanly (`sail bun run build`)

## Dev Notes

### Banque Populaire CSV Format Specification

Real-world Banque Populaire CSV exports have these characteristics:

| Property | Value |
|----------|-------|
| **Delimiter** | Semicolon (`;`) |
| **Encoding** | ISO-8859-15 (Latin-9, superset of ISO-8859-1 with Euro sign) |
| **Date format** | DD/MM/YYYY (e.g., `30/09/2020`) |
| **Decimal separator** | Comma (`,`) -- e.g., `-6,00` or `-81,4` |
| **Thousands separator** | None |
| **Header row** | Yes (1 row) |
| **Amount column** | Single `Montant` column (negative = debit, positive = credit) |
| **Text enclosure** | None (fields are not quoted) |
| **Line ending** | Windows-style `\r\n` common |

**Column headers (in order):** `Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant`

**Sample data:**
```
Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant
34405000000;30/07/2020;30/09/2020;EURO VIR TRESORERIE;QWERTYU;30/09/2020;-6,00
34405000000;30/07/2020;30/09/2020;BOULANGERIE LEFÈVRE;AZERTYU;30/09/2020;-4,50
34405000000;30/07/2020;30/09/2020;EURO VIR M SMITH;0045POIU;30/09/2020;245,00
```

**Field mapping to TransactionData:**
- `Date opération` -> `date` (converted DD/MM/YYYY -> YYYY-MM-DD)
- `Libellé` -> `description` (encoding-normalized)
- `Montant` -> `amountCents` (comma decimal -> integer cents)
- Full original line -> `rawLine`

**Fields NOT mapped** (available for future use): `Compte` (account number), `Date de comptabilisation` (posting date), `Référence` (bank reference), `Date valeur` (value date).

### Architecture Patterns to Follow

**CSV Parser Strategy Pattern** [Source: _bmad-output/planning-artifacts/architecture.md#Process Patterns]:
```php
interface CsvParserInterface {
    public function canParse(string $content, string $filename): bool;
    public function parse(string $content): Collection;
}
```

- `CsvParserFactory::detect($content, $filename)` iterates registered parsers, returns the first that `canParse()` returns true
- Each parser normalizes to a common `TransactionData` DTO: `date`, `description`, `amountCents`, `rawLine`
- Adding a new bank = one new class implementing `CsvParserInterface`, registered in the factory

**Amount handling** [Source: _bmad-output/planning-artifacts/architecture.md#Format Patterns]:
- ALL amounts MUST be stored as integer cents -- **never floats, never pre-formatted strings**
- Parse `"-6,00"` -> replace `,` with `.` -> `floatval()` -> `* 100` -> `(int) round()` to avoid floating-point precision errors
- Example: `"-81,4"` -> `-81.4` -> `-8140.0` -> `-8140`

**File placement** [Source: _bmad-output/planning-artifacts/architecture.md#Structure Patterns]:
- Parsers go in `app/Parsers/` (flat directory)
- Custom exceptions go in `app/Exceptions/`
- Services go in `app/Services/` (flat)
- Unit tests go in `tests/Unit/`
- Follow Laravel naming: PascalCase classes, camelCase methods

### Encoding Conversion Strategy

Use `mb_convert_encoding()` to convert the entire file content from ISO-8859-15 to UTF-8 **before** parsing CSV rows. This ensures all French accented characters (`é`, `è`, `ê`, `à`, `ç`, `ù`, `ô`, `ï`) survive the pipeline intact.

```php
// Convert encoding BEFORE parsing
$utf8Content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-15');
```

**Why not `iconv()`?** The `mb_convert_encoding()` function is more portable across platforms. `iconv()` behavior varies between GNU libiconv and libiconv implementations.

**Why ISO-8859-15 and not ISO-8859-1?** Banque Populaire actually exports in ISO-8859-15 (Latin-9), which is a superset of ISO-8859-1 that includes the Euro sign. The `canParse()` method should try ISO-8859-15 first, but both encodings should work for typical French characters.

### Parsing Implementation Notes

**Line-by-line approach:**
1. Convert entire file content to UTF-8
2. Split by lines (`explode("\n", ...)` after normalizing `\r\n` -> `\n`)
3. Parse first line as header, validate expected columns
4. For each data line: `str_getcsv($line, ';')` to get fields
5. Map fields to TransactionData DTO

**Amount conversion (critical -- avoid floating-point errors):**
```php
// Replace comma with dot, parse, multiply by 100, round then cast
$amountCents = (int) round(floatval(str_replace(',', '.', $montant)) * 100);
```

The `round()` before `(int)` cast is essential to avoid issues like `4.50 * 100 = 449.99999...` rounding to 449 instead of 450.

### Existing Codebase Context

**Models already exist** (created in Story 1.2):
- `Transaction` model: `amount_cents` (int), `date` (date), `description` (string), `dedup_hash` (string, unique), `category_id`, `account_id`, `import_id`
- `Import` model: `file_name` (string), `bank` (string), `status` (string, default: pending), `transaction_count` (int), `imported_at` (nullable timestamp)
- `Account` model: `name` (string), `bank` (string), `type` (enum: personal|professional)

**Factories already exist** with `expense()`, `income()`, `personal()`, `professional()`, `completed()`, `pending()` states.

**Directories to create** (do not exist yet):
- `app/Parsers/` -- new directory for parser strategy pattern
- `app/Exceptions/` -- new directory for custom exceptions (Note: Laravel's base exceptions dir is at `app/Exceptions/` but does not exist in this project yet)

**Packages available** (already installed in Story 1.1):
- Carbon (via Laravel) for date parsing
- mbstring extension (standard PHP) for encoding conversion
- No additional packages needed for this story

### Testing Patterns

Follow existing test patterns from Story 1.2:
- Use `RefreshDatabase` trait only if tests touch the database (parser tests are pure unit tests, no DB needed)
- Use descriptive test method names: `test_parses_valid_bp_csv_with_correct_amounts()`
- Use PHPUnit assertions: `assertCount()`, `assertSame()`, `assertEquals()`, `assertInstanceOf()`
- Test fixtures: embed sample CSV content as PHP strings in test files (no external fixture files needed for small samples)

**79 existing tests must continue passing** (201 assertions). Run `sail artisan test` to verify.

### Previous Story Learnings

**From Story 1.4 (most recent):**
- All commands run inside Sail: `sail artisan test`, `sail bun run build`
- 79 tests passing, 201 assertions
- Convention: one commit per story
- TrustProxies configured for Laravel Cloud
- Nightwatch installed and configured

**From Story 1.2:**
- Database schema complete with all tables needed
- CategorySeeder creates 14 categories with colors
- `QUEUE_CONNECTION=database` configured (jobs table exists)
- Factory states defined for all models

**From Story 1.1:**
- PHP 8.5, PostgreSQL, Bun runtime via Laravel Sail
- All additional packages installed (Prism PHP, laravel/mcp, pgvector, etc.)

### Git Intelligence

Recent commits (one per story convention):
- `520aee7` Story 1.4: Production deployment with HTTPS and monitoring
- `0f99855` Story 1.3: Visual foundation and app layout
- `640d245` Story 1.2: Domain data model, category seed, and code review fixes
- `f3615ec` Story 1.1: Project initialization with authentication

Clean `main` branch. Convention: one commit per story with descriptive message.

### What This Story Does NOT Include

- **RevolutParser** -- That's Story 2.2
- **Import pipeline / deduplication** -- That's Story 2.3
- **AI categorization** -- That's Story 2.4
- **Dashboard UI** -- That's Story 2.5
- **Import UI (drag-and-drop)** -- That's Story 2.6
- **Actual file upload/storage** -- This story only handles parsing content strings; file upload comes in Story 2.6
- **Database persistence of transactions** -- This story creates the parser framework and DTOs; persistence is Story 2.3's import pipeline

### Project Structure Notes

- Aligns with architecture directory structure: `app/Parsers/`, `app/Exceptions/`
- No conflicts with existing code -- all new files in new directories
- `AppServiceProvider` will be modified to register parser bindings
- No frontend changes in this story (pure backend)

### References

- Epic 2 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 2.1]
- CSV parser strategy pattern: [Source: _bmad-output/planning-artifacts/architecture.md#Process Patterns]
- Amount handling rules: [Source: _bmad-output/planning-artifacts/architecture.md#Format Patterns]
- File structure: [Source: _bmad-output/planning-artifacts/architecture.md#Structure Patterns]
- Naming conventions: [Source: _bmad-output/planning-artifacts/architecture.md#Naming Patterns]
- Previous story context: [Source: _bmad-output/implementation-artifacts/1-4-production-deployment-with-https-and-monitoring.md]
- Banque Populaire CSV format: [Source: doc4-fr.openflyers.com/Banque-Populaire-CSV-import-model]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

- Initial `str_getcsv()` calls failed on PHP 8.5 due to mandatory `$escape` parameter deprecation — fixed by passing explicit `''` (disable escaping) as 4th argument
- Test CSV content was initially UTF-8 but parser expects ISO-8859-15 input — fixed by encoding all test fixtures via `mb_convert_encoding()` to match real-world BP export encoding

### Completion Notes List

- Implemented CsvParserInterface strategy pattern with canParse/parse contract
- Created TransactionData readonly DTO with date, description, amountCents, rawLine
- Built CsvParserFactory with auto-detection via canParse() iteration
- Created UnrecognizedCsvFormatException and CsvParseException custom exceptions
- Implemented BanquePopulaireParser: ISO-8859-15 encoding conversion, semicolon delimiter parsing, DD/MM/YYYY date conversion via Carbon, comma-decimal to integer cents conversion with round() for precision
- Registered CsvParserFactory as singleton in AppServiceProvider with BanquePopulaireParser
- 13 unit tests for BanquePopulaireParser covering all acceptance criteria
- 3 unit tests for CsvParserFactory covering detection and error handling
- All 95 tests pass (79 existing + 16 new), 233 assertions, zero regressions
- TypeScript compiles cleanly

### Senior Developer Review (AI)

**Reviewer:** Lambert (via Claude Opus 4.6 adversarial review)
**Date:** 2026-02-20
**Outcome:** Approved with fixes applied

**Issues Found:** 1 High, 3 Medium, 2 Low — all fixed automatically

1. **[FIXED][HIGH]** Exception message in CsvParserFactory used class names ("BanquePopulaireParser") instead of user-friendly bank names ("Banque Populaire"). Fixed by transforming class names with regex.
2. **[FIXED][MEDIUM]** No error handling for malformed dates in `parseDate()` — Carbon would throw unhandled exception. Fixed by wrapping in try/catch that throws `CsvParseException`.
3. **[FIXED][MEDIUM]** No validation for empty/non-numeric amounts in `parseAmount()` — `floatval()` silently returned 0. Fixed by validating with `is_numeric()` before conversion.
4. **[FIXED][MEDIUM]** Missing test coverage for `CsvParseException` throw paths (empty content, invalid headers, invalid dates, empty amounts). Added 4 new tests.
5. **[FIXED][LOW]** Dead code: empty-content check in `parse()` was unreachable (`explode` always returns at least one element). Fixed by using `array_shift` result check.
6. **[FIXED][LOW]** Replaced `strtok()` (global static state) with `explode()` in `canParse()`.

**Post-fix test results:** 99 tests pass (79 existing + 20 new), 241 assertions, zero regressions.

### Change Log

- 2026-02-20: Story 2.1 implementation — CSV parser strategy pattern framework and Banque Populaire parser with full test coverage
- 2026-02-20: Code review fixes — user-friendly error messages, data validation in parser, additional test coverage (6 issues fixed)

### File List

- app/Parsers/CsvParserInterface.php (new)
- app/Parsers/TransactionData.php (new)
- app/Parsers/CsvParserFactory.php (new)
- app/Parsers/BanquePopulaireParser.php (new)
- app/Exceptions/UnrecognizedCsvFormatException.php (new)
- app/Exceptions/CsvParseException.php (new)
- app/Providers/AppServiceProvider.php (modified)
- tests/Unit/BanquePopulaireParserTest.php (new)
- tests/Unit/CsvParserFactoryTest.php (new)
