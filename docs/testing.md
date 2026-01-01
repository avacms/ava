# Testing

Ava includes a lightweight, zero-dependency test framework for verifying core functionality. Tests are designed for maintainers and contributors working on the CMS itself.

## Running Tests

Run the test suite from your project root:

```bash
./ava test
```

<pre><samp>  <span class="t-bold">Ava CMS Test Suite</span>
  <span class="t-dim">──────────────────────────────────────────────────</span>

  <span class="t-cyan">StrTest</span>

    <span class="t-green">✓</span> slug converts to lowercase
    <span class="t-green">✓</span> slug replaces spaces with separator
    <span class="t-green">✓</span> starts with returns true for match
    <span class="t-dim">...</span>

  <span class="t-cyan">ParserTest</span>

    <span class="t-green">✓</span> parse extracts frontmatter and content
    <span class="t-green">✓</span> parse handles multiple frontmatter fields
    <span class="t-dim">...</span>

  <span class="t-dim">──────────────────────────────────────────────────</span>
  <span class="t-bold">Tests:</span> <span class="t-green">383 passed</span> <span class="t-dim">(70ms)</span></samp></pre>

### Filtering Tests

Run only tests matching a pattern:

```bash
./ava test Str           # Run StrTest
./ava test Parser        # Run ParserTest
./ava test Request       # Run RequestTest
```

The filter matches against class names, so `Str` matches `StrTest`, `Request` matches `RequestTest`, etc.

### Quiet Mode

Run tests with minimal output (header + summary only):

```bash
./ava test --quiet
./ava test -q
```

<pre><samp>  <span class="t-bold">Ava CMS Test Suite</span>
  <span class="t-dim">──────────────────────────────────────────────────</span>
  <span class="t-bold">Tests:</span> <span class="t-green">383 passed</span> <span class="t-dim">(60ms)</span></samp></pre>

Useful for CI/CD pipelines or when you just want to know if tests pass.

---

## Test Structure

Tests live in the `tests/` directory, organised by component:

```
tests/
├── Admin/
│   └── DebugTest.php          # Debug configuration and logging
├── Config/
│   └── ConfigTest.php         # Configuration access patterns
├── Content/
│   ├── ItemTest.php           # Content item value object
│   └── ParserTest.php         # Markdown/YAML parser
├── Core/
│   └── UpdaterTest.php        # Update system
├── Http/
│   ├── HttpsEnforcementTest.php  # HTTPS/localhost detection
│   ├── RequestTest.php        # HTTP request handling
│   └── ResponseTest.php       # HTTP response building
├── Plugins/
│   └── HooksTest.php          # Action/filter hook system
├── Rendering/
│   └── MarkdownTest.php       # CommonMark rendering
├── Routing/
│   └── RouteMatchTest.php     # Route match value object
├── Shortcodes/
│   └── EngineTest.php         # Shortcode processing
└── Support/
    ├── ArrTest.php            # Array utilities
    ├── PathTest.php           # Path utilities
    ├── StrTest.php            # String utilities
    └── UlidTest.php           # ULID generation
```

---

## Writing Tests

Tests extend `Ava\Testing\TestCase` and use method naming conventions for discovery:

```php
<?php

declare(strict_types=1);

namespace Ava\Tests\Support;

use Ava\Support\Str;
use Ava\Testing\TestCase;

final class StrTest extends TestCase
{
    public function testSlugConvertsToLowercase(): void
    {
        $this->assertEquals('hello-world', Str::slug('Hello World'));
    }

    public function testSlugRemovesSpecialCharacters(): void
    {
        $this->assertEquals('hello-world', Str::slug('Hello, World!'));
    }
}
```

### Test Discovery

- Test files must end with `Test.php`
- Test classes must extend `TestCase`
- Test methods must be `public` and start with `test`
- Use `setUp()` for per-test initialisation
- Use `tearDown()` for cleanup

### Available Assertions

| Assertion | Description |
|-----------|-------------|
| `assertTrue($value)` | Assert value is `true` |
| `assertFalse($value)` | Assert value is `false` |
| `assertEquals($expected, $actual)` | Assert values are equal (`==`) |
| `assertSame($expected, $actual)` | Assert values are identical (`===`) |
| `assertNotSame($expected, $actual)` | Assert values are not identical |
| `assertNotEquals($expected, $actual)` | Assert values differ |
| `assertNull($value)` | Assert value is `null` |
| `assertNotNull($value)` | Assert value is not `null` |
| `assertInstanceOf($class, $object)` | Assert object is instance of class |
| `assertIsArray($value)` | Assert value is an array |
| `assertIsString($value)` | Assert value is a string |
| `assertArrayHasKey($key, $array)` | Assert array has key |
| `assertContains($needle, $haystack)` | Assert array contains value |
| `assertCount($expected, $array)` | Assert array has count |
| `assertEmpty($value)` | Assert value is empty |
| `assertNotEmpty($value)` | Assert value is not empty |
| `assertStringContains($needle, $haystack)` | Assert string contains substring |
| `assertStringNotContains($needle, $haystack)` | Assert string does not contain substring |
| `assertStringStartsWith($prefix, $string)` | Assert string starts with prefix |
| `assertStringEndsWith($suffix, $string)` | Assert string ends with suffix |
| `assertMatchesRegex($pattern, $string)` | Assert string matches regex |
| `assertThrows($exception, $callback)` | Assert callback throws exception |
| `assertGreaterThan($expected, $actual)` | Assert actual > expected |
| `assertLessThan($expected, $actual)` | Assert actual < expected |

### Skipping Tests

Skip a test conditionally:

```php
public function testRequiresExtension(): void
{
    if (!extension_loaded('igbinary')) {
        $this->skip('Requires igbinary extension');
    }
    
    // Test code here
}
```

---

## Test Philosophy

The test suite focuses on **unit testing core utilities** that have no external dependencies:

- **Support classes** (`Str`, `Arr`, `Path`, `Ulid`) - Pure functions
- **Value objects** (`Item`, `RouteMatch`, `Request`, `Response`) - Immutable data
- **Parsing** (`Parser`) - Frontmatter/Markdown extraction
- **Hooks system** - Filter/action registration and execution
- **Shortcodes** - Tag parsing and callback execution
- **Markdown** - CommonMark rendering behaviour
- **Config access** - Dot-notation array access patterns

Classes that require `Application` context (like `Query`, `Repository`, `PageCache`) are tested through the CLI commands and manual verification rather than unit tests.

---

## Continuous Integration

For CI pipelines, the test command returns appropriate exit codes:

```bash
./ava test
echo $?  # 0 = all passed, 1 = failures
```

Example GitHub Actions workflow:

```yaml
- name: Run tests
  run: ./ava test
```
