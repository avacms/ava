<?php

declare(strict_types=1);

namespace Ava\Tests\Support;

use Ava\Support\Path;
use Ava\Testing\TestCase;

/**
 * Tests for the Path utility class.
 */
final class PathTest extends TestCase
{
    // =========================================================================
    // normalize()
    // =========================================================================

    public function testNormalizeConvertsBackslashes(): void
    {
        $this->assertEquals('/path/to/file', Path::normalize('\\path\\to\\file'));
    }

    public function testNormalizeCollapsesMultipleSlashes(): void
    {
        $this->assertEquals('/path/to/file', Path::normalize('/path//to///file'));
    }

    public function testNormalizeResolvesDots(): void
    {
        $this->assertEquals('/path/file', Path::normalize('/path/./file'));
    }

    public function testNormalizeResolvesDoubleDots(): void
    {
        $this->assertEquals('/path/file', Path::normalize('/path/to/../file'));
    }

    public function testNormalizeHandlesComplexPaths(): void
    {
        $this->assertEquals('/a/d', Path::normalize('/a/b/c/../../d'));
    }

    public function testNormalizePreservesLeadingSlash(): void
    {
        $this->assertEquals('/path', Path::normalize('/path'));
    }

    public function testNormalizeHandlesRelativePaths(): void
    {
        $this->assertEquals('path/to/file', Path::normalize('path/to/file'));
    }

    public function testNormalizeReturnsSlashForEmpty(): void
    {
        $this->assertEquals('/', Path::normalize('/'));
    }

    // =========================================================================
    // join()
    // =========================================================================

    public function testJoinCombinesPaths(): void
    {
        $this->assertEquals('/path/to/file', Path::join('/path', 'to', 'file'));
    }

    public function testJoinHandlesLeadingSlashes(): void
    {
        $this->assertEquals('/path/to', Path::join('/path', '/to'));
    }

    public function testJoinFiltersEmptyParts(): void
    {
        $this->assertEquals('/path/file', Path::join('/path', '', 'file'));
    }

    public function testJoinNormalizesResult(): void
    {
        $this->assertEquals('/path/file', Path::join('/path', './file'));
    }

    // =========================================================================
    // relative()
    // =========================================================================

    public function testRelativeReturnsRelativePath(): void
    {
        $this->assertEquals('../other', Path::relative('/path/to', '/path/other'));
    }

    public function testRelativeHandlesSamePath(): void
    {
        $this->assertEquals('.', Path::relative('/path', '/path'));
    }

    public function testRelativeHandlesDeeperPaths(): void
    {
        $this->assertEquals('to/file', Path::relative('/path', '/path/to/file'));
    }

    public function testRelativeHandlesMultipleLevelsUp(): void
    {
        $this->assertEquals('../../other/file', Path::relative('/path/to/deep', '/path/other/file'));
    }

    // =========================================================================
    // dirname() / basename() / extension() / filename()
    // =========================================================================

    public function testDirnameReturnsDirectory(): void
    {
        $this->assertEquals('/path/to', Path::dirname('/path/to/file.txt'));
    }

    public function testDirnameReturnsSlashForRootLevelFile(): void
    {
        $this->assertEquals('/', Path::dirname('/file.txt'));
    }

    public function testBasenameReturnsFilename(): void
    {
        $this->assertEquals('file.txt', Path::basename('/path/to/file.txt'));
    }

    public function testBasenameRemovesSuffix(): void
    {
        $this->assertEquals('file', Path::basename('/path/to/file.txt', '.txt'));
    }

    public function testExtensionReturnsExtension(): void
    {
        $this->assertEquals('txt', Path::extension('/path/to/file.txt'));
    }

    public function testExtensionReturnsEmptyForNoExtension(): void
    {
        $this->assertEquals('', Path::extension('/path/to/file'));
    }

    public function testFilenameReturnsNameWithoutExtension(): void
    {
        $this->assertEquals('file', Path::filename('/path/to/file.txt'));
    }

    // =========================================================================
    // isAbsolute()
    // =========================================================================

    public function testIsAbsoluteReturnsTrueForUnixPath(): void
    {
        $this->assertTrue(Path::isAbsolute('/path/to/file'));
    }

    public function testIsAbsoluteReturnsTrueForWindowsPath(): void
    {
        $this->assertTrue(Path::isAbsolute('C:/path/to/file'));
    }

    public function testIsAbsoluteReturnsFalseForRelativePath(): void
    {
        $this->assertFalse(Path::isAbsolute('path/to/file'));
    }

    public function testIsAbsoluteReturnsFalseForDotPath(): void
    {
        $this->assertFalse(Path::isAbsolute('./file'));
    }

    // =========================================================================
    // makeAbsolute()
    // =========================================================================

    public function testMakeAbsoluteConvertsRelativePath(): void
    {
        $this->assertEquals('/base/path/file', Path::makeAbsolute('path/file', '/base'));
    }

    public function testMakeAbsoluteKeepsAbsolutePath(): void
    {
        $this->assertEquals('/path/file', Path::makeAbsolute('/path/file', '/base'));
    }

    // =========================================================================
    // isInside()
    // =========================================================================

    public function testIsInsideReturnsTrueForNestedPath(): void
    {
        $this->assertTrue(Path::isInside('/base/path/file', '/base'));
    }

    public function testIsInsideReturnsTrueForSamePath(): void
    {
        $this->assertTrue(Path::isInside('/base', '/base'));
    }

    public function testIsInsideReturnsFalseForOutsidePath(): void
    {
        $this->assertFalse(Path::isInside('/other/path', '/base'));
    }

    public function testIsInsideReturnsFalseForSimilarPrefix(): void
    {
        // /base-other is not inside /base
        $this->assertFalse(Path::isInside('/base-other/file', '/base'));
    }

    // =========================================================================
    // toUrl()
    // =========================================================================

    public function testToUrlConvertsPathToUrl(): void
    {
        $this->assertEquals('/path/to/file', Path::toUrl('/path/to/file'));
    }

    public function testToUrlRemovesMdExtension(): void
    {
        $this->assertEquals('/path/to/file', Path::toUrl('/path/to/file.md'));
    }

    public function testToUrlHandlesIndexFiles(): void
    {
        $this->assertEquals('/path/to/', Path::toUrl('/path/to/index.md'));
    }

    public function testToUrlRemovesBasePath(): void
    {
        $this->assertEquals('/file', Path::toUrl('/base/path/file', '/base/path'));
    }
}
