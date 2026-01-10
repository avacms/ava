<?php

declare(strict_types=1);

namespace Ava\Tests\Admin;

use Ava\Admin\ContentSecurity;
use Ava\Testing\TestCase;

/**
 * Tests for ContentSecurity slug and filename validation.
 */
final class ContentSecurityTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Slug Validation Tests
    // -------------------------------------------------------------------------

    public function testValidSlugPasses(): void
    {
        $result = ContentSecurity::validateSlug('my-post');
        $this->assertTrue($result['valid']);
        $this->assertEquals('my-post', $result['slug']);
        $this->assertNull($result['error']);
    }

    public function testValidSlugSingleChar(): void
    {
        $result = ContentSecurity::validateSlug('a');
        $this->assertTrue($result['valid']);
        $this->assertEquals('a', $result['slug']);
    }

    public function testValidSlugNumbers(): void
    {
        $result = ContentSecurity::validateSlug('post-123');
        $this->assertTrue($result['valid']);
        $this->assertEquals('post-123', $result['slug']);
    }

    public function testSlugNormalizedToLowercase(): void
    {
        $result = ContentSecurity::validateSlug('My-Post');
        $this->assertTrue($result['valid']);
        $this->assertEquals('my-post', $result['slug']);
    }

    public function testEmptySlugFails(): void
    {
        $result = ContentSecurity::validateSlug('');
        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['error']);
    }

    public function testSlugWithDotsBlocked(): void
    {
        $result = ContentSecurity::validateSlug('my.post');
        $this->assertFalse($result['valid']);
        $this->assertStringContains('dots', $result['error']);
    }

    public function testSlugWithPathTraversalBlocked(): void
    {
        $result = ContentSecurity::validateSlug('../../../etc/passwd');
        $this->assertFalse($result['valid']);
        $this->assertStringContains('path', strtolower($result['error']));
    }

    public function testSlugWithDoubleDotBlocked(): void
    {
        $result = ContentSecurity::validateSlug('blog/../../test');
        $this->assertFalse($result['valid']);
    }

    public function testSlugWithForwardSlashBlocked(): void
    {
        $result = ContentSecurity::validateSlug('blog/test');
        $this->assertFalse($result['valid']);
    }

    public function testSlugWithBackslashBlocked(): void
    {
        $result = ContentSecurity::validateSlug('blog\\test');
        $this->assertFalse($result['valid']);
    }

    public function testSlugStartingWithHyphenFails(): void
    {
        $result = ContentSecurity::validateSlug('-my-post');
        $this->assertFalse($result['valid']);
    }

    public function testSlugEndingWithHyphenFails(): void
    {
        $result = ContentSecurity::validateSlug('my-post-');
        $this->assertFalse($result['valid']);
    }

    public function testSlugWithConsecutiveHyphensFails(): void
    {
        $result = ContentSecurity::validateSlug('my--post');
        $this->assertFalse($result['valid']);
        $this->assertStringContains('consecutive', $result['error']);
    }

    public function testSlugWithSpecialCharsBlocked(): void
    {
        $result = ContentSecurity::validateSlug('my<script>post');
        $this->assertFalse($result['valid']);
    }

    public function testSlugWithSpacesFails(): void
    {
        $result = ContentSecurity::validateSlug('my post');
        $this->assertFalse($result['valid']);
    }

    // -------------------------------------------------------------------------
    // Filename Validation Tests
    // -------------------------------------------------------------------------

    public function testValidFilenamePasses(): void
    {
        $result = ContentSecurity::validateFilename('my-post');
        $this->assertTrue($result['valid']);
        $this->assertEquals('my-post', $result['filename']);
    }

    public function testValidFilenameWithDatePrefix(): void
    {
        $result = ContentSecurity::validateFilename('2025-01-10-my-post');
        $this->assertTrue($result['valid']);
        $this->assertEquals('2025-01-10-my-post', $result['filename']);
    }

    public function testFilenameNormalizedToLowercase(): void
    {
        $result = ContentSecurity::validateFilename('My-Post');
        $this->assertTrue($result['valid']);
        $this->assertEquals('my-post', $result['filename']);
    }

    public function testEmptyFilenameFails(): void
    {
        $result = ContentSecurity::validateFilename('');
        $this->assertFalse($result['valid']);
    }

    public function testFilenameWithExtensionBlocked(): void
    {
        $result = ContentSecurity::validateFilename('my-post.md');
        $this->assertFalse($result['valid']);
        $this->assertStringContains('dots', $result['error']);
    }

    public function testFilenameWithPhpExtensionBlocked(): void
    {
        $result = ContentSecurity::validateFilename('my-post.php');
        $this->assertFalse($result['valid']);
    }

    public function testFilenameWithPathTraversalBlocked(): void
    {
        $result = ContentSecurity::validateFilename('../../../etc/passwd');
        $this->assertFalse($result['valid']);
    }

    public function testFilenameWithSlashBlocked(): void
    {
        $result = ContentSecurity::validateFilename('subdir/file');
        $this->assertFalse($result['valid']);
    }

    // -------------------------------------------------------------------------
    // Filename Generation Tests
    // -------------------------------------------------------------------------

    public function testGenerateFilenameWithoutDate(): void
    {
        $filename = ContentSecurity::generateFilename('my-post', null);
        $this->assertEquals('my-post', $filename);
    }

    public function testGenerateFilenameWithDate(): void
    {
        $filename = ContentSecurity::generateFilename('my-post', '2025-01-10');
        $this->assertEquals('2025-01-10-my-post', $filename);
    }

    public function testGenerateFilenameWithInvalidDate(): void
    {
        $filename = ContentSecurity::generateFilename('my-post', 'invalid');
        $this->assertEquals('my-post', $filename);
    }

    public function testGenerateFilenameWithInvalidSlugFallback(): void
    {
        $filename = ContentSecurity::generateFilename('../../../evil', null);
        $this->assertEquals('untitled', $filename);
    }
}
