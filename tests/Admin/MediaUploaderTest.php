<?php

declare(strict_types=1);

namespace Ava\Tests\Admin;

use Ava\Admin\MediaUploader;
use Ava\Testing\TestCase;

/**
 * Tests for the MediaUploader security features.
 */
final class MediaUploaderTest extends TestCase
{
    private MediaUploader $uploader;

    public function setUp(): void
    {
        $this->uploader = new MediaUploader($this->app);
    }

    public function testSanitizeFilenameRemovesPathComponents(): void
    {
        $result = $this->uploader->sanitizeFilename('../../../etc/passwd.jpg', 'jpg');
        $this->assertFalse(str_contains($result, '..'));
        $this->assertFalse(str_contains($result, '/'));
        $this->assertTrue(str_ends_with($result, '.jpg'));
    }

    public function testSanitizeFilenameRemovesControlCharacters(): void
    {
        $result = $this->uploader->sanitizeFilename("test\x00\x1F\x7Fimage.png", 'png');
        $this->assertFalse(str_contains($result, "\x00"));
        $this->assertFalse(str_contains($result, "\x1F"));
        $this->assertFalse(str_contains($result, "\x7F"));
        $this->assertTrue(str_ends_with($result, '.png'));
    }

    public function testSanitizeFilenameRemovesUnicodeDirectionOverrides(): void
    {
        // RLO attack: malicious\u202Egpj.exe -> appears as maliciousexe.jpg
        $result = $this->uploader->sanitizeFilename("malicious\u{202E}gpj.exe", 'jpg');
        $this->assertFalse(str_contains($result, "\u{202E}"));
        $this->assertFalse(str_contains($result, 'exe'));
        $this->assertTrue(str_ends_with($result, '.jpg'));
    }

    public function testSanitizeFilenameHandlesDangerousExtensions(): void
    {
        $result = $this->uploader->sanitizeFilename('shell.php.jpg', 'jpg');
        $this->assertEquals('image.jpg', $result);
    }

    public function testSanitizeFilenameHandlesWindowsReservedNames(): void
    {
        $result = $this->uploader->sanitizeFilename('CON.jpg', 'jpg');
        $this->assertStringStartsWith('image-con', $result);
        $this->assertTrue(str_ends_with($result, '.jpg'));

        $result = $this->uploader->sanitizeFilename('AUX.png', 'png');
        $this->assertStringStartsWith('image-aux', $result);
    }

    public function testSanitizeFilenameConvertsToLowercase(): void
    {
        $result = $this->uploader->sanitizeFilename('MyImage.PNG', 'png');
        $this->assertEquals('myimage.png', $result);
    }

    public function testSanitizeFilenameConvertsSpacesToHyphens(): void
    {
        $result = $this->uploader->sanitizeFilename('my image file.jpg', 'jpg');
        $this->assertEquals('my-image-file.jpg', $result);
    }

    public function testSanitizeFilenameHandlesEmptyInput(): void
    {
        $result = $this->uploader->sanitizeFilename('', 'jpg');
        $this->assertEquals('image.jpg', $result);

        $result = $this->uploader->sanitizeFilename('....', 'png');
        $this->assertEquals('image.png', $result);
    }

    public function testSanitizeFilenameTruncatesLongNames(): void
    {
        $longName = str_repeat('a', 200) . '.jpg';
        $result = $this->uploader->sanitizeFilename($longName, 'jpg');
        $this->assertLessThanOrEqual(84, strlen($result)); // 80 chars + ".jpg"
    }

    public function testSanitizeFilenamePreservesValidCharacters(): void
    {
        $result = $this->uploader->sanitizeFilename('my_image-2024.jpg', 'jpg');
        $this->assertEquals('my_image-2024.jpg', $result);
    }

    public function testSanitizeFilenameRemovesHiddenFilePrefix(): void
    {
        // .htaccess is detected as dangerous pattern
        $result = $this->uploader->sanitizeFilename('.htaccess', 'jpg');
        $this->assertEquals('image.jpg', $result);

        // ..hidden becomes 'image.png' because after stripping dots, we get empty string
        $result = $this->uploader->sanitizeFilename('..hidden', 'png');
        $this->assertEquals('image.png', $result);
    }

    public function testSanitizeFilenameRejectsMultipleDangerousPatterns(): void
    {
        $tests = [
            'shell.phtml',
            'hack.phar',
            'config.user.ini',
            'test.asp',
            'web.config',
        ];

        foreach ($tests as $dangerous) {
            $result = $this->uploader->sanitizeFilename($dangerous, 'jpg');
            $this->assertEquals('image.jpg', $result, "Failed for: {$dangerous}");
        }
    }

    public function testUploadLimitsReturnsExpectedKeys(): void
    {
        $limits = $this->uploader->getUploadLimits();

        $this->assertArrayHasKey('max_file_size', $limits);
        $this->assertArrayHasKey('max_file_size_formatted', $limits);
        $this->assertArrayHasKey('allowed_types', $limits);
        $this->assertArrayHasKey('allowed_extensions', $limits);
        $this->assertArrayHasKey('has_imagick', $limits);
        $this->assertArrayHasKey('has_gd', $limits);
    }

    public function testGetExistingFoldersReturnsArray(): void
    {
        $folders = $this->uploader->getExistingFolders();
        $this->assertIsArray($folders);
    }

    public function testGetDateFolderReturnsCorrectFormat(): void
    {
        $dateFolder = $this->uploader->getDateFolder();
        $this->assertTrue(
            (bool) preg_match('/^\d{4}\/\d{2}$/', $dateFolder),
            "Date folder should match YYYY/MM format, got: {$dateFolder}"
        );
    }

    public function testIsEnabledReturnsBoolean(): void
    {
        $this->assertIsBool($this->uploader->isEnabled());
    }

    public function testIsWritableReturnsBoolean(): void
    {
        $this->assertIsBool($this->uploader->isWritable());
    }

    public function testMimeToExtensionMapping(): void
    {
        // Test that dangerous types are not in the mapping
        $reflection = new \ReflectionClass($this->uploader);
        $constant = $reflection->getConstant('MIME_TO_EXTENSION');

        $this->assertArrayHasKey('image/jpeg', $constant);
        $this->assertArrayHasKey('image/png', $constant);
        $this->assertArrayHasKey('image/gif', $constant);
        $this->assertArrayHasKey('image/webp', $constant);

        // Ensure no executable types
        $this->assertFalse(isset($constant['application/x-php']), 'Should not allow PHP');
        $this->assertFalse(isset($constant['text/html']), 'Should not allow HTML');
        $this->assertFalse(isset($constant['application/javascript']), 'Should not allow JavaScript');
    }
}
