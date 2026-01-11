<?php

declare(strict_types=1);

namespace Ava\Tests\Release;

use Ava\Testing\TestCase;

/**
 * Release Readiness Tests
 *
 * These tests verify that the project is ready for release.
 * They are only run when using: ./ava test --release
 *
 * This ensures sensitive files are ignored, default configuration is safe,
 * and version numbers are properly set.
 */
final class ReleaseChecksTest extends TestCase
{
    // =========================================================================
    // Security & Git
    // =========================================================================

    /**
     * Test that users.php is in .gitignore
     */
    public function testUsersFileIsGitignored(): void
    {
        $gitignore = file_get_contents(AVA_ROOT . '/.gitignore');
        
        $this->assertTrue(
            str_contains($gitignore, 'app/config/users.php') || str_contains($gitignore, 'users.php'),
            'users.php should be listed in .gitignore to prevent credential leaks'
        );
    }

    /**
     * Test that .env files are gitignored
     */
    public function testEnvFilesAreGitignored(): void
    {
        $gitignore = file_get_contents(AVA_ROOT . '/.gitignore');
        
        $this->assertTrue(
            str_contains($gitignore, '.env'),
            '.env files should be listed in .gitignore'
        );
    }

    /**
     * Test that storage directories are gitignored
     */
    public function testStorageCacheIsGitignored(): void
    {
        $gitignore = file_get_contents(AVA_ROOT . '/.gitignore');
        
        $this->assertTrue(
            str_contains($gitignore, 'storage/cache') || str_contains($gitignore, '/storage/cache/'),
            'storage/cache should be gitignored'
        );
    }

    // =========================================================================
    // Configuration Defaults
    // =========================================================================

    /**
     * Test that debug is disabled by default
     */
    public function testDebugIsDisabledByDefault(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        
        $this->assertFalse(
            $config['debug']['enabled'] ?? true,
            'Debug should be disabled (debug.enabled = false) for release'
        );
    }

    /**
     * Test that theme is set to default
     */
    public function testThemeIsDefault(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        
        $this->assertEquals(
            'default',
            $config['theme'] ?? '',
            'Theme should be set to "default" for release'
        );
    }

    /**
     * Test that admin is disabled by default
     */
    public function testAdminIsDisabledByDefault(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        
        $this->assertFalse(
            $config['admin']['enabled'] ?? true,
            'Admin should be disabled (admin.enabled = false) for release'
        );
    }

    /**
     * Test that admin path is /admin
     */
    public function testAdminPathIsDefault(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        
        $this->assertEquals(
            '/admin',
            $config['admin']['path'] ?? '',
            'Admin path should be "/admin" for release'
        );
    }

    /**
     * Test that admin theme is cyan
     */
    public function testAdminThemeIsCyan(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        
        $this->assertEquals(
            'cyan',
            $config['admin']['theme'] ?? '',
            'Admin theme should be "cyan" for release'
        );
    }

    /**
     * Test that CLI theme is cyan
     */
    public function testCliThemeIsCyan(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        
        $this->assertEquals(
            'cyan',
            $config['cli']['theme'] ?? '',
            'CLI theme should be "cyan" for release'
        );
    }

    /**
     * Test that site name is "My Ava Site"
     */
    public function testSiteNameIsDefault(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        
        $this->assertEquals(
            'My Ava Site',
            $config['site']['name'] ?? '',
            'Site name should be "My Ava Site" for release'
        );
    }

    /**
     * Test that base URL is localhost
     */
    public function testBaseUrlIsLocalhost(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        $baseUrl = $config['site']['base_url'] ?? '';
        
        $this->assertTrue(
            str_contains($baseUrl, 'localhost'),
            "Base URL should contain 'localhost' for release (got: {$baseUrl})"
        );
    }

    /**
     * Test that timezone is UTC
     */
    public function testTimezoneIsUtc(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        
        $this->assertEquals(
            'UTC',
            $config['site']['timezone'] ?? '',
            'Timezone should be "UTC" for release'
        );
    }

    /**
     * Test that locale is en_GB
     */
    public function testLocaleIsEnGb(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        
        $this->assertEquals(
            'en_GB',
            $config['site']['locale'] ?? '',
            'Locale should be "en_GB" for release'
        );
    }

    // =========================================================================
    // Version Checks
    // =========================================================================

    /**
     * Test that version follows SemVer format
     */
    public function testVersionFollowsSemVer(): void
    {
        $version = AVA_VERSION;
        
        $this->assertTrue(
            (bool) preg_match('/^\d+\.\d+\.\d+$/', $version),
            "Version '{$version}' should match SemVer format MAJOR.MINOR.PATCH (e.g., 1.0.0)"
        );
    }

    /**
     * Test that version is higher than what's on GitHub
     *
     * This ensures you're releasing a new version, not an old one.
     * Requires curl extension and network access.
     */
    public function testVersionIsHigherThanGitHub(): void
    {
        if (!extension_loaded('curl')) {
            $this->skip('curl extension required for GitHub API check');
        }

        $ch = curl_init('https://api.github.com/repos/ava-cms/ava/releases/latest');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Ava-CMS-ReleaseTest/' . AVA_VERSION,
                'Accept: application/vnd.github.v3+json',
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            $this->skip('Could not fetch GitHub release info (HTTP ' . $httpCode . ')');
        }

        $release = json_decode($response, true);
        if (!isset($release['tag_name'])) {
            $this->skip('Invalid GitHub API response');
        }

        $latestGitHub = ltrim($release['tag_name'], 'v');
        $current = AVA_VERSION;

        $this->assertTrue(
            version_compare($current, $latestGitHub, '>'),
            "Local version ({$current}) should be higher than GitHub release ({$latestGitHub})"
        );
    }

    // =========================================================================
    // Content & Structure
    // =========================================================================

    /**
     * Test that default theme exists
     */
    public function testDefaultThemeExists(): void
    {
        $themePath = AVA_ROOT . '/themes/default';
        
        $this->assertTrue(
            is_dir($themePath),
            'Default theme directory should exist at themes/default'
        );
    }

    /**
     * Test that default theme has theme.php
     */
    public function testDefaultThemeHasBootstrap(): void
    {
        $themeFile = AVA_ROOT . '/themes/default/theme.php';
        
        $this->assertTrue(
            file_exists($themeFile),
            'Default theme should have theme.php bootstrap file'
        );
    }

    /**
     * Test that example content exists
     */
    public function testExampleContentExists(): void
    {
        $indexPage = AVA_ROOT . '/content/pages/index.md';
        
        $this->assertTrue(
            file_exists($indexPage),
            'Example index page should exist at content/pages/index.md'
        );
    }

    /**
     * Test that no users.php exists (fresh install)
     */
    public function testNoUsersFileExists(): void
    {
        $usersFile = AVA_ROOT . '/app/config/users.php';
        
        $this->assertFalse(
            file_exists($usersFile),
            'users.php should not exist for release (it should be created by user:add)'
        );
    }

    /**
     * Test that media directory is empty
     */
    public function testMediaDirectoryIsEmpty(): void
    {
        $mediaDir = AVA_ROOT . '/public/media';
        
        if (!is_dir($mediaDir)) {
            // Directory doesn't exist, which is fine
            $this->assertTrue(true, 'Media directory does not exist (empty)');
            return;
        }

        $files = array_diff(scandir($mediaDir), ['.', '..']);
        
        $this->assertEmpty(
            $files,
            'Media directory should be empty for release (found: ' . implode(', ', $files) . ')'
        );
    }

    /**
     * Test that preview token is placeholder
     */
    public function testPreviewTokenIsPlaceholder(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        $token = $config['security']['preview_token'] ?? '';
        
        $this->assertTrue(
            str_contains($token, 'your-preview-token') || $token === '',
            'Preview token should be a placeholder value for release'
        );
    }

    // =========================================================================
    // Documentation
    // =========================================================================

    /**
     * Test that README exists
     */
    public function testReadmeExists(): void
    {
        $this->assertTrue(
            file_exists(AVA_ROOT . '/README.md'),
            'README.md should exist'
        );
    }

    /**
     * Test that LICENSE exists
     */
    public function testLicenseExists(): void
    {
        $this->assertTrue(
            file_exists(AVA_ROOT . '/LICENSE'),
            'LICENSE file should exist'
        );
    }

    /**
     * Test that documentation exists
     */
    public function testDocumentationExists(): void
    {
        $docsPath = AVA_ROOT . '/docs';
        
        $this->assertTrue(
            is_dir($docsPath),
            'Documentation directory should exist at docs/'
        );

        $this->assertTrue(
            file_exists($docsPath . '/README.md'),
            'docs/README.md should exist'
        );
    }

    // =========================================================================
    // Composer & Dependencies
    // =========================================================================

    /**
     * Test that composer.json exists and is valid
     */
    public function testComposerJsonIsValid(): void
    {
        $composerFile = AVA_ROOT . '/composer.json';
        
        $this->assertTrue(
            file_exists($composerFile),
            'composer.json should exist'
        );

        $content = json_decode(file_get_contents($composerFile), true);
        
        $this->assertTrue(
            $content !== null,
            'composer.json should be valid JSON'
        );

        $this->assertTrue(
            isset($content['name']),
            'composer.json should have a name field'
        );
    }

    /**
     * Test that vendor directory exists
     */
    public function testVendorDirectoryExists(): void
    {
        $this->assertTrue(
            is_dir(AVA_ROOT . '/vendor'),
            'vendor/ directory should exist (run composer install)'
        );
    }

    /**
     * Test that autoloader exists
     */
    public function testAutoloaderExists(): void
    {
        $this->assertTrue(
            file_exists(AVA_ROOT . '/vendor/autoload.php'),
            'vendor/autoload.php should exist'
        );
    }
}
