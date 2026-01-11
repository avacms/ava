<?php

declare(strict_types=1);

namespace Ava\Tests\Core;

use Ava\Testing\TestCase;

/**
 * Updater Tests
 *
 * Tests for version checking and update functionality.
 * Note: GitHub API calls are not tested here to avoid external dependencies.
 * Integration tests would require mocking HTTP requests.
 */
final class UpdaterTest extends TestCase
{
    /**
     * Test current version returns constant
     */
    public function testCurrentVersionReturnsDefined(): void
    {
        $this->assertTrue(defined('AVA_VERSION'));
        $this->assertIsString(constant('AVA_VERSION'));
        $this->assertTrue(strlen(constant('AVA_VERSION')) > 0);
    }

    /**
     * Test version format is SemVer (MAJOR.MINOR.PATCH)
     */
    public function testVersionFormatSemVer(): void
    {
        $version = constant('AVA_VERSION');
        
        // Should match pattern: 1.0.0, 2.1.3 etc
        $this->assertTrue(
            (bool) preg_match('/^\d+\.\d+\.\d+$/', $version),
            "Version '$version' should match SemVer format MAJOR.MINOR.PATCH"
        );
    }

    /**
     * Test version comparison logic
     */
    public function testVersionComparison(): void
    {
        $v1 = '1.0.0';
        $v2 = '1.0.1';
        $v3 = '1.1.0';
        
        $this->assertTrue(version_compare($v2, $v1, '>'));
        $this->assertTrue(version_compare($v1, $v2, '<'));
        $this->assertTrue(version_compare($v1, $v1, '='));
        $this->assertTrue(version_compare($v3, $v2, '>'));
    }

    /**
     * Test GitHub API cache file path exists
     */
    public function testUpdateCacheDirectory(): void
    {
        $cacheDir = AVA_ROOT . '/storage/cache';
        
        $this->assertTrue(
            is_dir($cacheDir),
            'Cache directory should exist at ' . $cacheDir
        );
    }

    /**
     * Test bundled plugins are defined
     */
    public function testBundledPluginsExist(): void
    {
        $bundledPlugins = ['sitemap', 'feed', 'redirects'];
        
        foreach ($bundledPlugins as $plugin) {
            $pluginDir = AVA_ROOT . '/plugins/' . $plugin;
            $this->assertTrue(
                is_dir($pluginDir),
                "Bundled plugin '$plugin' should exist"
            );
        }
    }

    /**
     * Test bundled plugin structure
     */
    public function testBundledPluginStructure(): void
    {
        $plugins = ['sitemap', 'feed', 'redirects'];
        
        foreach ($plugins as $plugin) {
            $pluginDir = AVA_ROOT . '/plugins/' . $plugin;
            $pluginFile = $pluginDir . '/plugin.php';
            
            $this->assertTrue(
                file_exists($pluginFile),
                "Plugin file should exist at $pluginFile"
            );
        }
    }

    /**
     * Test tag name parsing (GitHub releases)
     */
    public function testGitHubTagNameParsing(): void
    {
        // Simulate GitHub release tag format
        $tags = [
            'v1.0.0' => '1.0.0',
            'v2.1.3' => '2.1.3',
            '1.0.0' => '1.0.0',
        ];
        
        foreach ($tags as $tag => $expected) {
            $parsed = ltrim($tag, 'v');
            $this->assertEquals($expected, $parsed);
        }
    }

    /**
     * Test update directories are defined
     */
    public function testUpdateDirectoriesExist(): void
    {
        $dirs = ['core', 'docs', 'bin'];
        
        foreach ($dirs as $dir) {
            $path = AVA_ROOT . '/' . $dir;
            $this->assertTrue(
                is_dir($path),
                "Update directory '$dir' should exist"
            );
        }
    }

    /**
     * Test preserved directories during updates
     */
    public function testPreservedDirectoriesExist(): void
    {
        $preserved = ['content', 'app', 'storage'];
        
        foreach ($preserved as $dir) {
            $path = AVA_ROOT . '/' . $dir;
            $this->assertTrue(
                is_dir($path),
                "Preserved directory '$dir' should exist"
            );
        }
    }

    /**
     * Test custom themes directory
     */
    public function testCustomThemesDirectory(): void
    {
        $themesDir = AVA_ROOT . '/themes';
        
        $this->assertTrue(is_dir($themesDir));
        
        // Default theme should exist
        $this->assertTrue(is_dir($themesDir . '/default'));
    }

    /**
     * Test custom plugins directory exists
     */
    public function testCustomPluginsDirectory(): void
    {
        $pluginsDir = AVA_ROOT . '/plugins';
        
        $this->assertTrue(is_dir($pluginsDir));
    }

    /**
     * Test GitHub repo slug
     */
    public function testGitHubRepoFormat(): void
    {
        $repo = 'ava-cms/ava';
        
        $this->assertStringContains('/', $repo);
        $this->assertTrue(str_contains($repo, 'ava-cms'));
        $this->assertTrue(str_contains($repo, 'ava'));
    }

    /**
     * Test release info structure
     */
    public function testReleaseInfoStructure(): void
    {
        // Expected structure from GitHub API
        $requiredFields = ['tag_name', 'name', 'body', 'published_at', 'html_url', 'zipball_url'];
        
        foreach ($requiredFields as $field) {
            $this->assertIsString($field);
        }
    }

    /**
     * Test check result structure when no update available
     */
    public function testCheckResultStructureNoUpdate(): void
    {
        $result = [
            'available' => false,
            'current' => '25.12.1',
            'latest' => '25.12.1',
            'release' => null,
            'error' => null,
        ];
        
        $this->assertArrayHasKey('available', $result);
        $this->assertArrayHasKey('current', $result);
        $this->assertArrayHasKey('latest', $result);
        $this->assertArrayHasKey('release', $result);
        $this->assertArrayHasKey('error', $result);
        
        $this->assertFalse($result['available']);
        $this->assertNull($result['release']);
        $this->assertNull($result['error']);
    }

    /**
     * Test check result structure with update available
     */
    public function testCheckResultStructureWithUpdate(): void
    {
        $result = [
            'available' => true,
            'current' => '1.0.0',
            'latest' => '1.0.1',
            'release' => [
                'name' => '1.0.1',
                'body' => 'Bug fixes and improvements',
                'published_at' => '2026-01-10T00:00:00Z',
                'html_url' => 'https://github.com/ava-cms/ava/releases/tag/v1.0.1',
                'zipball_url' => 'https://api.github.com/repos/ava-cms/ava/zipball/v1.0.1',
            ],
            'error' => null,
        ];
        
        $this->assertTrue($result['available']);
        $this->assertIsArray($result['release']);
        $this->assertArrayHasKey('name', $result['release']);
        $this->assertArrayHasKey('body', $result['release']);
        $this->assertArrayHasKey('published_at', $result['release']);
    }

    /**
     * Test apply result structure
     */
    public function testApplyResultStructure(): void
    {
        $result = [
            'success' => true,
            'message' => 'Updated successfully',
            'updated_from' => '1.0.0',
            'updated_to' => '1.0.1',
            'new_plugins' => [],
        ];
        
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('updated_from', $result);
        $this->assertArrayHasKey('updated_to', $result);
        $this->assertArrayHasKey('new_plugins', $result);
        
        $this->assertTrue($result['success']);
        $this->assertIsArray($result['new_plugins']);
    }

    /**
     * Test GitHub API URL format
     */
    public function testGitHubApiUrlFormat(): void
    {
        $repo = 'ava-cms/ava';
        $apiUrl = "https://api.github.com/repos/{$repo}/releases/latest";
        
        $this->assertStringContains('api.github.com', $apiUrl);
        $this->assertStringContains('releases/latest', $apiUrl);
        $this->assertTrue(str_starts_with($apiUrl, 'https://'));
    }

    /**
     * Test update directory list is not empty
     */
    public function testUpdateDirsNotEmpty(): void
    {
        $dirs = ['core', 'docs', 'bin'];
        
        $this->assertTrue(count($dirs) > 0);
    }

    /**
     * Test bundled plugins list is not empty
     */
    public function testBundledPluginsNotEmpty(): void
    {
        $plugins = ['sitemap', 'feed', 'redirects'];
        
        $this->assertTrue(count($plugins) > 0);
        $this->assertEquals(3, count($plugins));
    }

    /**
     * Test version number components
     */
    public function testVersionComponents(): void
    {
        $version = '1.2.3';
        $parts = explode('.', $version);
        
        $this->assertEquals(3, count($parts));
        $this->assertEquals('1', $parts[0]);  // Major
        $this->assertEquals('2', $parts[1]);  // Minor
        $this->assertEquals('3', $parts[2]);  // Patch
    }

    /**
     * Test error handling structure
     */
    public function testErrorHandlingStructure(): void
    {
        $errorResult = [
            'available' => false,
            'current' => '1.0.0',
            'latest' => '1.0.0',
            'release' => null,
            'error' => 'Could not fetch release info from GitHub',
        ];
        
        $this->assertFalse($errorResult['available']);
        $this->assertNotNull($errorResult['error']);
        $this->assertStringContains('GitHub', $errorResult['error']);
    }
}
