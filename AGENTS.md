# AGENTS.md

Standalone PHP Composer library for scraping vehicle listings from viabovag.nl.
No framework — pure PSR-4 package built on Guzzle + PSR-16 caching.

## Build / Lint / Test Commands

```bash
# Run unit tests (excludes Integration suite)
composer test

# Run a single test class
vendor/bin/phpunit tests/ViaBOVAGTest.php

# Run a single test method
vendor/bin/phpunit --filter test_extracts_build_id_from_homepage

# Run a single test method in a specific class
vendor/bin/phpunit --filter 'ViaBOVAGTest::test_extracts_build_id_from_homepage'

# Run integration tests (hits live site — separate suite)
vendor/bin/phpunit --testsuite Integration

# Run tests with coverage
composer test-coverage

# Code formatting (Laravel Pint — default Laravel preset)
composer format

# Static analysis (PHPStan level 8)
composer analyse
```

CI runs PHPUnit on PHP 8.4 across ubuntu + windows with both prefer-lowest and prefer-stable.
A GitHub Action auto-runs Pint and commits style fixes on push.

## Project Structure

```
src/
  ViaBOVAG.php              # Main client — HTTP, build ID, caching, retry
  ViaBOVAGInterface.php     # Public API contract (interface)
  Parser/JsonParser.php     # Build ID extraction + JSON-to-DTO mapping
  Data/                     # DTOs (readonly classes), enums, search criteria
    Concerns/               # Traits (HasSharedFilterSlugs)
  Exception/                # ViaBOVAGException, NotFoundException
  Testing/                  # FakeViaBOVAG, factories — shipped for downstream consumers
tests/
  ViaBOVAGTest.php          # Main unit tests (Guzzle MockHandler)
  Fixtures/                 # HTML/JSON response fixtures
  Integration/              # Live site tests (separate PHPUnit suite)
  Testing/                  # Tests for fake client and factories
```

## Code Style

### Strict Types and PHP Version

- **PHP ^8.4** — uses readonly classes, typed constants, constructor promotion, enums
- Every file starts with `declare(strict_types=1)` — no exceptions

### Formatting (Laravel Pint defaults)

- 4-space indentation, LF line endings, UTF-8
- Opening brace on same line (PSR-12 / PER style)
- Trailing commas on multi-line arrays, parameters, and arguments
- Space after negation: `! $foo` (not `!$foo`)
- No parentheses on `new` without arguments: `new Client` (not `new Client()`)
- Single quotes for strings; double quotes only for interpolation
- No spaces around concatenation: `'prefix'.$var.'suffix'`

### Naming Conventions

| Element         | Convention                   | Example                              |
|-----------------|------------------------------|--------------------------------------|
| Classes         | PascalCase                   | `ListingDetail`, `FakeViaBOVAG`      |
| Interfaces      | PascalCase + `Interface`     | `ViaBOVAGInterface`, `SearchQuery`   |
| Traits          | `Has*` in `Concerns/` dir   | `HasSharedFilterSlugs`               |
| Enums           | PascalCase, string-backed    | `MobilityType`, `CarBodyType`        |
| Enum cases      | PascalCase                   | `case Benzine = 'Benzine'`           |
| Methods         | camelCase                    | `getDetail()`, `toFilterSlugs()`     |
| Private methods | verb prefix                  | `fetchJson()`, `buildSearchUrl()`    |
| Properties      | camelCase                    | `$buildId`, `$cacheTtl`              |
| Bool properties | `is*` / `has*` prefix        | `$isFinanceable`, `$hasBovagChecklist`|
| Constants       | Typed UPPER_SNAKE_CASE       | `private const string BASE_URL = ...`|

### Imports

- Fully qualified `use` statements — no inline `\Namespace\Class` references
- Alphabetically ordered within groups

### Type System

- Native types on all parameters, return types, and properties — no exceptions
- PHPDoc only for array shapes PHP cannot express: `@param array<string, mixed>`, `@param Listing[]`
- Nullable types: `?string`, `?int`, `?CacheInterface`
- Union types where appropriate: `array|string|null`

### DTOs and Data Classes

- All DTOs are `readonly class` with promoted constructor properties
- No behavior — pure data containers
- Service classes use `private readonly` promoted properties with default values

### Enums

- Always string-backed (`enum X: string`)
- PascalCase case names
- Include `slug(): string` method for URL-friendly values
- Complex mappings via `match` expressions

### Error Handling

- Custom exception hierarchy: `ViaBOVAGException extends RuntimeException`
- Specific exceptions: `NotFoundException extends ViaBOVAGException`
- Always chain previous exception: `throw new ViaBOVAGException('msg', 0, $e)`
- Throw expressions used inline: `$val ?? throw new ViaBOVAGException('...')`
- Catch specific types (`ClientExceptionInterface`, `\JsonException`), wrap into package exceptions

### Comments

- PHPDoc for: array type annotations, interface method docs, `@throws` declarations
- Inline comments explain "why" not "what": `// Stale build ID — invalidate and retry`
- Section separators in tests: `// --- Build ID Extraction ---`
- No redundant docblocks on methods with clear native type hints

## Testing Conventions

### Structure

- PHPUnit 10.x with strict mode (`failOnWarning`, `failOnRisky`, random order)
- Two suites: `Unit` (default) and `Integration` (excluded from default run)
- Test classes extend `PHPUnit\Framework\TestCase`

### Naming

- Method names: `test_` prefix with snake_case: `test_parses_search_results`
- No `@test` annotations — always use the `test_` prefix
- All test methods return `void`

### Mocking

- HTTP mocked via Guzzle `MockHandler` + `HandlerStack` — no mocking frameworks
- Response fixtures stored in `tests/Fixtures/` (HTML and JSON files)
- Private helper methods for setup: `createClient()`, `fixture()`

### Assertions

- Prefer `assertSame()` (strict) over `assertEquals()`
- Use `expectException()` + `expectExceptionMessage()` for error cases
- Common: `assertNotEmpty`, `assertCount`, `assertContains`, `assertInstanceOf`

### Shipped Test Utilities (in `src/Testing/`)

These live in `src/` so downstream consumers can use them:
- `FakeViaBOVAG` — in-memory fake with call recording + assertions
- Factories: `ListingFactory::make()`, `::makeMany()`, `SearchResultFactory::make()`, etc.
- All factories accept `array $overrides` for customization

## Architecture Notes

- **Interface-first**: `ViaBOVAGInterface` defines public API; real + fake both implement it
- **JSON data extraction**: Site is Next.js — extracts build ID from homepage, then uses `/_next/data/{buildId}/...` routes for JSON
- **Stale build ID retry**: On 404, invalidates build ID, fetches fresh one, retries once
- **Optional PSR-16 caching**: Build ID cached to avoid refetching homepage each request
- **Separation of concerns**: Client (HTTP) → Parser (JSON mapping) → DTOs (data)
