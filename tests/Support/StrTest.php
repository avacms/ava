<?php

declare(strict_types=1);

namespace Ava\Tests\Support;

use Ava\Support\Str;
use Ava\Testing\TestCase;

/**
 * Tests for the Str utility class.
 */
final class StrTest extends TestCase
{
    // =========================================================================
    // slug()
    // =========================================================================

    public function testSlugConvertsToLowercase(): void
    {
        $this->assertEquals('hello-world', Str::slug('Hello World'));
    }

    public function testSlugReplacesSpacesWithSeparator(): void
    {
        $this->assertEquals('hello-world', Str::slug('hello world'));
    }

    public function testSlugRemovesSpecialCharacters(): void
    {
        $this->assertEquals('hello-world', Str::slug('Hello! World?'));
    }

    public function testSlugHandlesMultipleSpaces(): void
    {
        $this->assertEquals('hello-world', Str::slug('hello    world'));
    }

    public function testSlugTrimsLeadingAndTrailingSeparators(): void
    {
        $this->assertEquals('hello', Str::slug('--hello--'));
    }

    public function testSlugWithCustomSeparator(): void
    {
        $this->assertEquals('hello_world', Str::slug('Hello World', '_'));
    }

    public function testSlugWithNumbers(): void
    {
        $this->assertEquals('post-123', Str::slug('Post 123'));
    }

    // =========================================================================
    // startsWith() / endsWith() / contains()
    // =========================================================================

    public function testStartsWithReturnsTrueForMatch(): void
    {
        $this->assertTrue(Str::startsWith('Hello World', 'Hello'));
    }

    public function testStartsWithReturnsFalseForNoMatch(): void
    {
        $this->assertFalse(Str::startsWith('Hello World', 'World'));
    }

    public function testEndsWithReturnsTrueForMatch(): void
    {
        $this->assertTrue(Str::endsWith('Hello World', 'World'));
    }

    public function testEndsWithReturnsFalseForNoMatch(): void
    {
        $this->assertFalse(Str::endsWith('Hello World', 'Hello'));
    }

    public function testContainsReturnsTrueForMatch(): void
    {
        $this->assertTrue(Str::contains('Hello World', 'lo Wo'));
    }

    public function testContainsReturnsFalseForNoMatch(): void
    {
        $this->assertFalse(Str::contains('Hello World', 'xyz'));
    }

    // =========================================================================
    // before() / after()
    // =========================================================================

    public function testBeforeReturnsSubstringBeforeNeedle(): void
    {
        $this->assertEquals('Hello', Str::before('Hello World', ' '));
    }

    public function testBeforeReturnsFullStringIfNotFound(): void
    {
        $this->assertEquals('Hello', Str::before('Hello', ' '));
    }

    public function testBeforeReturnsFullStringForEmptySearch(): void
    {
        $this->assertEquals('Hello', Str::before('Hello', ''));
    }

    public function testAfterReturnsSubstringAfterNeedle(): void
    {
        $this->assertEquals('World', Str::after('Hello World', ' '));
    }

    public function testAfterReturnsFullStringIfNotFound(): void
    {
        $this->assertEquals('Hello', Str::after('Hello', ' '));
    }

    // =========================================================================
    // limit() / words()
    // =========================================================================

    public function testLimitTruncatesLongStrings(): void
    {
        $this->assertEquals('Hello...', Str::limit('Hello World', 5));
    }

    public function testLimitPreservesShortStrings(): void
    {
        $this->assertEquals('Hello', Str::limit('Hello', 10));
    }

    public function testLimitWithCustomEnding(): void
    {
        $this->assertEquals('Hello--', Str::limit('Hello World', 5, '--'));
    }

    public function testWordsLimitsWordCount(): void
    {
        $result = Str::words('one two three four five', 3);
        $this->assertEquals('one two three...', $result);
    }

    public function testWordsPreservesShortText(): void
    {
        $result = Str::words('one two', 5);
        $this->assertEquals('one two', $result);
    }

    // =========================================================================
    // Case conversion
    // =========================================================================

    public function testTitleConvertsToTitleCase(): void
    {
        $this->assertEquals('Hello World', Str::title('hello world'));
    }

    public function testCamelConvertsToCamelCase(): void
    {
        $this->assertEquals('helloWorld', Str::camel('hello-world'));
        $this->assertEquals('helloWorld', Str::camel('hello_world'));
    }

    public function testSnakeConvertsToSnakeCase(): void
    {
        $this->assertEquals('hello_world', Str::snake('helloWorld'));
        $this->assertEquals('hello_world', Str::snake('HelloWorld'));
    }

    public function testKebabConvertsToKebabCase(): void
    {
        $this->assertEquals('hello-world', Str::kebab('helloWorld'));
    }

    // =========================================================================
    // Utility methods
    // =========================================================================

    public function testPlainStripsHtmlAndDecodesEntities(): void
    {
        $this->assertEquals('Hello & World', Str::plain('<p>Hello &amp; World</p>'));
    }

    public function testRandomGeneratesCorrectLength(): void
    {
        $result = Str::random(20);
        $this->assertEquals(20, strlen($result));
    }

    public function testRandomGeneratesUniqueStrings(): void
    {
        $a = Str::random(16);
        $b = Str::random(16);
        $this->assertNotEquals($a, $b);
    }

    public function testEnsureLeftAddsPrefix(): void
    {
        $this->assertEquals('/path', Str::ensureLeft('path', '/'));
    }

    public function testEnsureLeftDoesNotDuplicatePrefix(): void
    {
        $this->assertEquals('/path', Str::ensureLeft('/path', '/'));
    }

    public function testEnsureRightAddsSuffix(): void
    {
        $this->assertEquals('path/', Str::ensureRight('path', '/'));
    }

    public function testEnsureRightDoesNotDuplicateSuffix(): void
    {
        $this->assertEquals('path/', Str::ensureRight('path/', '/'));
    }
}
