# Story 2.2: Revolut CSV Parser

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want to parse Revolut CSV exports for both personal and professional accounts,
so that all my bank data can be imported into the application.

## Acceptance Criteria

1. **Given** a Revolut CSV export (comma-delimited, YYYY-MM-DD dates, dot decimal separators), **When** the file is processed by RevolutParser, **Then** each row is parsed into a TransactionData DTO with correct date, description, and amountCents (FR2, FR3).

2. **Given** a Revolut CSV with both completed and pending transactions, **When** it is parsed, **Then** only completed transactions are included (State = "COMPLETED").

3. **Given** the CsvParserFactory receives a Revolut file, **When** it calls `canParse()` on registered parsers, **Then** RevolutParser correctly identifies its format.

4. **Given** both BanquePopulaireParser and RevolutParser are registered, **When** a CSV is submitted, **Then** the factory correctly distinguishes between the two formats.

## Tasks / Subtasks

- [x] Task 1: Implement RevolutParser for Personal Accounts (AC: 1, 2, 3)
  - [x] 1.1: Create `app/Parsers/RevolutParser.php` implementing `CsvParserInterface`
  - [x] 1.2: Implement `canParse()` — detect by checking for expected header columns: `Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance` (comma-delimited)
  - [x] 1.3: Implement `parse()` — comma-delimited CSV parsing with `str_getcsv($line, ',', '"', '')`
  - [x] 1.4: Parse `Completed Date` column (format: `YYYY-MM-DD HH:MM:SS`) to ISO 8601 date (`YYYY-MM-DD`) using Carbon
  - [x] 1.5: Convert dot-decimal amounts from `Amount` column to integer cents: parse `"-42.50"` → `floatval()` → `* 100` → `(int) round()` → `-4250`
  - [x] 1.6: Filter by `State` column: only include rows where State equals `COMPLETED` (skip PENDING, REVERTED, FAILED, DECLINED)
  - [x] 1.7: Map to TransactionData DTO: `date` from `Completed Date`, `description` from `Description`, `amountCents` from `Amount`, `rawLine` as the original CSV line
  - [x] 1.8: Handle edge cases: empty lines, lines with insufficient columns, rows with empty Completed Date (pending transactions without completion date)

- [x] Task 2: Support Revolut Business Account Format (AC: 1, 3)
  - [x] 2.1: Extend `canParse()` to also detect Business format headers: `Date started (UTC),Date completed (UTC),ID,Type,Description,Reference,Payer,Card number,Orig currency,Orig amount,Payment currency,Amount,Fee,Balance,Account,...`
  - [x] 2.2: Implement format detection logic: determine if file is Personal or Business format based on header columns
  - [x] 2.3: Parse Business format: use `Date completed (UTC)` for date, `Description` for description, `Amount` for amountCents
  - [x] 2.4: Business format has no `State` column — include all rows (Business exports typically only contain completed transactions)

- [x] Task 3: Register RevolutParser in CsvParserFactory (AC: 3, 4)
  - [x] 3.1: Add `RevolutParser::class` to the parser array in `AppServiceProvider.php` singleton registration
  - [x] 3.2: Verify factory correctly routes BP CSV to BanquePopulaireParser and Revolut CSV to RevolutParser

- [x] Task 4: Write Unit Tests for RevolutParser (AC: 1, 2, 3, 4)
  - [x] 4.1: Create `tests/Unit/RevolutParserTest.php`
  - [x] 4.2: Test `canParse()` returns true for valid Revolut Personal CSV content with correct headers
  - [x] 4.3: Test `canParse()` returns true for valid Revolut Business CSV content with correct headers
  - [x] 4.4: Test `canParse()` returns false for non-Revolut CSV content (BP format, random text, empty content)
  - [x] 4.5: Test parsing a valid Personal multi-row CSV produces correct TransactionData DTOs with expected date, description, amountCents
  - [x] 4.6: Test YYYY-MM-DD HH:MM:SS dates are correctly converted to ISO 8601 date only (`YYYY-MM-DD`)
  - [x] 4.7: Test dot-decimal amounts are correctly converted to integer cents (including negative amounts, credits, zero amounts)
  - [x] 4.8: Test only COMPLETED transactions are included — PENDING, REVERTED, FAILED, DECLINED rows are skipped
  - [x] 4.9: Test parsing a valid Business format CSV produces correct TransactionData DTOs
  - [x] 4.10: Test empty lines, malformed rows, and rows with empty Completed Date are handled gracefully (skipped)
  - [x] 4.11: Test UTF-8 characters in Description are preserved correctly
  - [x] 4.12: Test `rawLine` preserves the original CSV line
  - [x] 4.13: Test empty content throws CsvParseException
  - [x] 4.14: Test invalid headers throw CsvParseException

- [x] Task 5: Update CsvParserFactory Tests (AC: 4)
  - [x] 5.1: Update `tests/Unit/CsvParserFactoryTest.php` to test factory returns RevolutParser for Revolut-formatted content
  - [x] 5.2: Test factory correctly distinguishes between BP and Revolut formats
  - [x] 5.3: Test factory exception message now lists both "Banque Populaire" and "Revolut" as expected formats

- [x] Task 6: Run Full Test Suite (AC: all)
  - [x] 6.1: Run `sail artisan test` — all existing 99 tests must pass with zero regressions
  - [x] 6.2: All new RevolutParser tests must pass
  - [x] 6.3: TypeScript compiles cleanly (`sail bun run build`)

## Dev Notes

### Revolut CSV Format Specification

Revolut exports CSV in two formats depending on the account type:

#### Personal Account Format (10 columns)

| Property | Value |
|----------|-------|
| **Delimiter** | Comma (`,`) |
| **Encoding** | UTF-8 (no conversion needed) |
| **Date format** | `YYYY-MM-DD HH:MM:SS` (e.g., `2023-03-15 14:30:00`) |
| **Decimal separator** | Dot (`.`) — e.g., `-42.50` |
| **Thousands separator** | None typical for amounts < 1000; possible comma for large amounts (handle with care) |
| **Header row** | Yes (1 row) |
| **Amount column** | Single `Amount` column (negative = debit, positive = credit) |
| **Text enclosure** | Double quotes when needed (`"`) |
| **Line ending** | `\n` (Unix-style) or `\r\n` |

**Column headers (in order):** `Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance`

**Sample data (Personal):**
```
Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance
CARD_PAYMENT,Current,2023-03-15 14:30:00,2023-03-16 08:00:00,Carrefour,-42.50,0.00,EUR,COMPLETED,972.50
TRANSFER,Current,2023-03-14 09:00:00,2023-03-14 09:05:00,John Doe,150.00,0.00,EUR,COMPLETED,1015.00
CARD_PAYMENT,Current,2023-03-13 18:22:00,,Amazon,-29.99,0.00,EUR,PENDING,865.01
TOPUP,Current,2023-03-12 08:00:00,2023-03-12 08:01:00,Top-Up by *1234,500.00,0.00,EUR,COMPLETED,895.00
CARD_PAYMENT,Current,2023-03-11 12:00:00,2023-03-11 12:00:00,Uber,-8.50,0.00,EUR,REVERTED,395.00
```

**Field mapping to TransactionData:**
- `Completed Date` → `date` (converted `YYYY-MM-DD HH:MM:SS` → `YYYY-MM-DD`)
- `Description` → `description`
- `Amount` → `amountCents` (dot decimal → integer cents)
- Full original line → `rawLine`

**Fields NOT mapped** (available for future use): `Type` (transaction type), `Product` (account type), `Started Date` (initiation timestamp), `Fee` (transaction fee), `Currency` (ISO currency code), `State` (used for filtering), `Balance` (running balance).

#### Business Account Format (19+ columns)

| Property | Value |
|----------|-------|
| **Delimiter** | Comma (`,`) |
| **Encoding** | UTF-8 |
| **Date format** | `YYYY-MM-DD HH:MM:SS` |
| **Key columns** | `Date started (UTC)`, `Date completed (UTC)`, `ID`, `Type`, `Description`, `Reference`, `Payer`, `Card number`, `Orig currency`, `Orig amount`, `Payment currency`, `Amount`, `Fee`, `Balance`, `Account`, ... |

**Sample data (Business):**
```
Date started (UTC),Date completed (UTC),ID,Type,Description,Reference,Payer,Card number,Orig currency,Orig amount,Payment currency,Amount,Fee,Balance,Account,Beneficiary account number,Beneficiary sort code or routing number,Beneficiary IBAN,Beneficiary BIC
2023-03-15 14:30:00,2023-03-16 08:00:00,abc123,CARD_PAYMENT,Restaurant Le Provençal,,Lambert T,*1234,EUR,-55.00,EUR,-55.00,0.00,1945.00,Main,,,,
2023-03-14 09:00:00,2023-03-14 09:05:00,def456,TRANSFER,Client Payment,INV-2023-042,ACME Corp,,EUR,2500.00,EUR,2500.00,0.00,2000.00,Main,,,,
```

**Field mapping for Business to TransactionData:**
- `Date completed (UTC)` → `date` (same format conversion as Personal)
- `Description` → `description`
- `Amount` → `amountCents` (same conversion as Personal)
- Full original line → `rawLine`

**Business format note:** No `State` column — include all rows. Business exports typically only contain settled transactions.

### Architecture Patterns to Follow

**CSV Parser Strategy Pattern** [Source: _bmad-output/planning-artifacts/architecture.md#Process Patterns]:
```php
interface CsvParserInterface {
    public function canParse(string $content, string $filename): bool;
    public function parse(string $content): Collection;
}
```

- RevolutParser implements `CsvParserInterface` — same contract as BanquePopulaireParser
- `canParse()` checks for Revolut-specific header columns
- `parse()` returns `Collection<TransactionData>` with normalized data
- Adding a new bank = one new class implementing CsvParserInterface

**Amount handling** [Source: _bmad-output/planning-artifacts/architecture.md#Format Patterns]:
- ALL amounts MUST be stored as integer cents — **never floats, never pre-formatted strings**
- Parse `"-42.50"` → `floatval()` → `* 100` → `(int) round()` → `-4250`
- The `round()` before `(int)` cast is essential to avoid floating-point precision errors

**File placement** [Source: _bmad-output/planning-artifacts/architecture.md#Structure Patterns]:
- Parser goes in `app/Parsers/RevolutParser.php`
- Unit tests go in `tests/Unit/RevolutParserTest.php`
- Registration in `app/Providers/AppServiceProvider.php`
- Follow Laravel naming: PascalCase classes, camelCase methods

### Encoding — No Conversion Needed

Unlike BanquePopulaireParser (which must convert from ISO-8859-15 to UTF-8), Revolut exports are already in UTF-8. The RevolutParser does **not** need encoding conversion. This simplifies the implementation.

### Key Differences from BanquePopulaireParser

| Aspect | BanquePopulaireParser | RevolutParser |
|--------|----------------------|---------------|
| Delimiter | Semicolon (`;`) | Comma (`,`) |
| Encoding | ISO-8859-15 → UTF-8 | UTF-8 (native) |
| Date format | DD/MM/YYYY | YYYY-MM-DD HH:MM:SS |
| Decimal separator | Comma (`,`) | Dot (`.`) |
| Amount conversion | Replace `,` with `.` then parse | Direct `floatval()` |
| Status filtering | None (all rows imported) | Only `COMPLETED` rows (Personal); all rows (Business) |
| Header detection | Semicolon-separated French headers | Comma-separated English headers |
| Multiple formats | Single format | Personal (10 cols) + Business (19+ cols) |

### Implementation Notes

**Header detection strategy:**
- Personal: Check first line contains `Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance`
- Business: Check first line contains `Date started (UTC),Date completed (UTC)` and `Amount`
- `canParse()` should check for either format

**Format detection within parse():**
- After `canParse()` succeeds, `parse()` needs to determine whether the file is Personal or Business format
- Use header column inspection: if header contains `State` → Personal format; if header contains `Date started (UTC)` → Business format
- Extract column indices dynamically based on headers

**State filtering (Personal format only):**
- Only import rows where `State` column equals `COMPLETED`
- Skip `PENDING` (incomplete), `REVERTED` (cancelled), `FAILED`, `DECLINED`
- Log or silently skip — do not throw exceptions for non-COMPLETED rows

**Date parsing:**
```php
// Revolut dates: "2023-03-15 14:30:00" → "2023-03-15"
Carbon::createFromFormat('Y-m-d H:i:s', $completedDate)->format('Y-m-d');
```

**Amount parsing (simpler than BP):**
```php
// Revolut amounts: "-42.50" → -4250
$amountCents = (int) round(floatval($amount) * 100);
```

### Existing Codebase Context

**Files created in Story 2.1 that this story builds on:**
- `app/Parsers/CsvParserInterface.php` — the contract to implement
- `app/Parsers/TransactionData.php` — the DTO to return
- `app/Parsers/CsvParserFactory.php` — register parser here
- `app/Parsers/BanquePopulaireParser.php` — sibling parser to follow as pattern
- `app/Exceptions/UnrecognizedCsvFormatException.php` — used by factory
- `app/Exceptions/CsvParseException.php` — throw for parse errors
- `app/Providers/AppServiceProvider.php` — parser registration singleton

**Current test state:** 99 tests pass, 241 assertions. All new tests must maintain zero regressions.

**PHP 8.5 note from Story 2.1:** `str_getcsv()` requires explicit `$escape` parameter — always pass `''` as 4th argument to disable escaping. Example: `str_getcsv($line, ',', '"', '')`

### Testing Patterns

Follow existing patterns from `tests/Unit/BanquePopulaireParserTest.php`:
- Create parser instance in `setUp()`
- Use descriptive test method names: `test_parses_valid_revolut_personal_csv_with_correct_amounts()`
- Embed sample CSV content as PHP strings (no external fixtures)
- Use `RefreshDatabase` trait only if DB needed (parser tests are pure unit — no DB)
- PHPUnit assertions: `assertCount()`, `assertSame()`, `assertEquals()`, `assertInstanceOf()`
- Test both happy path and edge cases (empty content, invalid headers, malformed rows, non-COMPLETED states)

### Previous Story Learnings

**From Story 2.1 (direct predecessor — CRITICAL context):**
- `str_getcsv()` on PHP 8.5 requires explicit `''` escape parameter — caused initial test failures in 2.1. **Do not forget this.**
- Test CSV content must match expected encoding — in 2.1, test fixtures initially used wrong encoding. For Revolut, content is UTF-8 so this is straightforward.
- Code review found: missing validation for empty/non-numeric amounts — RevolutParser must validate amounts before conversion
- Code review found: missing error handling for malformed dates — RevolutParser must wrap Carbon parsing in try/catch
- Factory error message should use user-friendly bank names, not class names — already fixed in 2.1's factory
- 6 issues found in code review — build defensively from the start to minimize review findings

**From Story 1.4:**
- All commands run inside Sail: `sail artisan test`, `sail bun run build`
- Convention: one commit per story

### Git Intelligence

Recent commits (one per story):
- `5e990ad` Story 2.1: CSV parser framework and Banque Populaire support
- `520aee7` Story 1.4: Production deployment with HTTPS and monitoring
- `0f99855` Story 1.3: Visual foundation and app layout

**Patterns established in Story 2.1:**
- Parser classes: ~100-110 lines with EXPECTED_HEADERS constant, canParse(), parse(), and helper methods
- Tests: comprehensive coverage including format detection, data parsing, edge cases, error handling
- Registration: singleton pattern in AppServiceProvider with array of parser class names

### What This Story Does NOT Include

- **Import pipeline / deduplication** — That's Story 2.3
- **AI categorization** — That's Story 2.4
- **Dashboard UI** — That's Story 2.5
- **Import UI (drag-and-drop)** — That's Story 2.6
- **Actual file upload/storage** — This story only handles parsing content strings
- **Database persistence of transactions** — Persistence is Story 2.3's import pipeline
- **Currency conversion** — All amounts parsed as-is from the Amount column; multi-currency handling is out of scope

### Project Structure Notes

- Aligns with architecture directory structure: new file in existing `app/Parsers/` directory
- No conflicts with existing code — one new parser class, one new test file, one modification to AppServiceProvider
- No frontend changes in this story (pure backend)
- Factory already supports multiple parsers — just add to the registration array

### References

- Epic 2 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 2.2]
- CSV parser strategy pattern: [Source: _bmad-output/planning-artifacts/architecture.md#Process Patterns]
- Amount handling rules: [Source: _bmad-output/planning-artifacts/architecture.md#Format Patterns]
- File structure: [Source: _bmad-output/planning-artifacts/architecture.md#Structure Patterns]
- Naming conventions: [Source: _bmad-output/planning-artifacts/architecture.md#Naming Patterns]
- Previous story context: [Source: _bmad-output/implementation-artifacts/2-1-csv-parser-framework-and-banque-populaire-support.md]
- Revolut Personal CSV format: [Source: dativery.com/en/apps/revolut-personal-csv]
- Revolut Business CSV format: [Source: dativery.com/en/apps/revolut-csv]
- Revolut Developer Docs: [Source: developer.revolut.com/docs/guides/accept-payments/tutorials/create-csv-reports]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

None — all tests passed on first run with zero errors.

### Completion Notes List

- Implemented RevolutParser supporting both Personal (10-column) and Business (19+ column) CSV formats in a single class
- Used dynamic header mapping (`array_flip($headers)`) to resolve column indices at parse time, avoiding separate parser classes
- Personal format filters by `State === 'COMPLETED'`; Business format includes all rows (no State column)
- Applied all defensive coding patterns from Story 2.1 code review: amount validation (`is_numeric`), date parsing in try/catch, edge case handling for empty fields
- No encoding conversion needed (Revolut exports are native UTF-8, unlike BP's ISO-8859-15)
- Registered RevolutParser in AppServiceProvider alongside BanquePopulaireParser
- 25 new tests (22 RevolutParser + 4 CsvParserFactory new, 1 CsvParserFactory removed), all passing — 124 total tests, 298 assertions, zero regressions
- TypeScript compiles cleanly

### File List

- `app/Parsers/RevolutParser.php` — NEW — Revolut CSV parser (Personal + Business formats)
- `app/Providers/AppServiceProvider.php` — MODIFIED — Added RevolutParser to factory registration
- `tests/Unit/RevolutParserTest.php` — NEW — 22 unit tests for RevolutParser
- `tests/Unit/CsvParserFactoryTest.php` — MODIFIED — Added 4 tests, removed 1 (renamed), refactored with helper method (now 6 tests total)

### Change Log

| Date | Author | Change |
|------|--------|--------|
| 2026-02-20 | Code Review (AI) | Fixed 3 MEDIUM issues: added missing test for malformed rows with insufficient columns, added test for Business format partial header rejection, corrected inaccurate test counts in Dev Agent Record |

### Senior Developer Review (AI)

**Reviewer:** Lambert (via adversarial code review workflow)
**Date:** 2026-02-20
**Outcome:** Approve (after fixes applied)

**Summary:** Solid implementation. RevolutParser correctly handles both Personal and Business CSV formats with appropriate defensive coding (amount validation, date try/catch, edge case handling). All 4 Acceptance Criteria verified as fully implemented. All tasks verified as done. No security vulnerabilities, no architecture violations.

**Issues Found:** 3 MEDIUM, 3 LOW

**MEDIUM (fixed):**
- M1: Missing test for rows with insufficient columns — added `test_skips_malformed_rows_with_insufficient_columns`
- M2: Inaccurate test counts in Dev Agent Record (17 claimed vs 20 actual for RevolutParser; 7 total claimed vs 6 actual for Factory) — corrected counts
- M3: No test for Business format canParse() partial header rejection — added `test_can_parse_returns_false_for_partial_business_headers`

**LOW (noted, not fixed):**
- L1: No rawLine assertion for Business format transactions (only tested for Personal)
- L2: Silently skipped rows with no logging (pre-existing pattern from BanquePopulaireParser)
- L3: Business format detection uses minimal required headers (4 of 19+ columns)

**Test Results After Review:** 124 tests, 298 assertions, zero failures
