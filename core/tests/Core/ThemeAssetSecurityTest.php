<?php

declare(strict_types=1);

namespace Ava\Tests\Core;

use Ava\Http\Request;
use Ava\Testing\TestCase;

/**
 * Theme Asset Security Tests
 *
 * Tests for the /theme/ route security measures:
 * - Extension allowlist (only serve safe file types)
 * - Hidden file blocking (dotfiles return 404)
 * - Path traversal protection (already tested elsewhere, verified here)
 */
final class ThemeAssetSecurityTest extends TestCase
{
    public function setUp(): void
    {
        // Boot the app to register theme routes
        $this->app->boot();
    }

    /**
     * Get the theme assets directory for testing.
     */
    private function getThemeAssetsDir(): string
    {
        $theme = $this->app->config('theme', 'default');
        return $this->app->configPath('themes') . '/' . $theme . '/assets';
    }

    /**
     * Create a request for the /theme/ route.
     */
    private function createThemeRequest(string $assetPath): Request
    {
        return new Request('GET', '/theme/' . ltrim($assetPath, '/'));
    }

    // =========================================================================
    // Allowed Extensions (should serve with correct content type)
    // =========================================================================

    public function testCssFilesAreServed(): void
    {
        $assetsDir = $this->getThemeAssetsDir();
        
        // Skip if no CSS files exist in theme
        $cssFiles = glob($assetsDir . '/*.css');
        if (empty($cssFiles)) {
            $this->skip('No CSS files in theme assets to test');
        }

        $cssFile = basename($cssFiles[0]);
        $request = $this->createThemeRequest($cssFile);
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->status());
        $this->assertEquals('text/css', $response->header('Content-Type'));
    }

    public function testJsFilesAreServed(): void
    {
        $assetsDir = $this->getThemeAssetsDir();
        
        $jsFiles = glob($assetsDir . '/*.js');
        if (empty($jsFiles)) {
            $this->skip('No JS files in theme assets to test');
        }

        $jsFile = basename($jsFiles[0]);
        $request = $this->createThemeRequest($jsFile);
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->status());
        $this->assertEquals('application/javascript', $response->header('Content-Type'));
    }

    // =========================================================================
    // Blocked Extensions (should return 404)
    // =========================================================================

    public function testPhpFilesReturn404(): void
    {
        // Even if a .php file somehow exists in assets, it should not be served
        $request = $this->createThemeRequest('malicious.php');
        $response = $this->app->handle($request);

        // Should be 404 (not served)
        $this->assertEquals(404, $response->status());
    }

    public function testPhtmlFilesReturn404(): void
    {
        $request = $this->createThemeRequest('template.phtml');
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->status());
    }

    public function testHtaccessFilesReturn404(): void
    {
        $request = $this->createThemeRequest('.htaccess');
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->status());
    }

    public function testHtmlFilesReturn404(): void
    {
        // HTML files are not in the allowlist - should 404
        $request = $this->createThemeRequest('page.html');
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->status());
    }

    public function testTxtFilesReturn404(): void
    {
        $request = $this->createThemeRequest('readme.txt');
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->status());
    }

    // =========================================================================
    // Hidden Files (dotfiles should return 404)
    // =========================================================================

    public function testHiddenFilesReturn404(): void
    {
        $request = $this->createThemeRequest('.env');
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->status());
    }

    public function testHiddenCssFilesReturn404(): void
    {
        // Even if extension is allowed, hidden files should be blocked
        $request = $this->createThemeRequest('.hidden.css');
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->status());
    }

    public function testGitignoreReturn404(): void
    {
        $request = $this->createThemeRequest('.gitignore');
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->status());
    }

    // =========================================================================
    // Path Traversal Protection
    // =========================================================================

    public function testPathTraversalBlocked(): void
    {
        $request = $this->createThemeRequest('../../../bootstrap.php');
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->status());
    }

    public function testEncodedPathTraversalBlocked(): void
    {
        // URL-encoded ../ 
        $request = $this->createThemeRequest('..%2F..%2Fbootstrap.php');
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->status());
    }

    public function testDoubleEncodedTraversalBlocked(): void
    {
        // ....// which becomes ../ after naive replacement
        $request = $this->createThemeRequest('....//....//bootstrap.php');
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->status());
    }

    // =========================================================================
    // Allowed Extensions List
    // =========================================================================

    public function testAllowedExtensionsList(): void
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->app);
        $method = $reflection->getMethod('getAllowedAssetExtensions');
        $method->setAccessible(true);
        
        $extensions = $method->invoke($this->app);

        // Verify expected extensions are present
        $expectedExtensions = [
            'css', 'js', 'json', 'map',
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'avif',
            'woff', 'woff2', 'ttf', 'otf', 'eot',
        ];

        foreach ($expectedExtensions as $ext) {
            $this->assertArrayHasKey($ext, $extensions, "Extension '$ext' should be in allowlist");
        }

        // Verify dangerous extensions are NOT present
        $blockedExtensions = ['php', 'phtml', 'phar', 'html', 'htm', 'txt', 'md', 'yml', 'yaml', 'sh', 'env'];
        foreach ($blockedExtensions as $ext) {
            $this->assertFalse(
                array_key_exists($ext, $extensions),
                "Extension '$ext' should NOT be in allowlist"
            );
        }
    }

    // =========================================================================
    // Cache Headers
    // =========================================================================

    public function testCacheHeadersAreSet(): void
    {
        $assetsDir = $this->getThemeAssetsDir();
        
        // Find any allowed file
        $files = array_merge(
            glob($assetsDir . '/*.css') ?: [],
            glob($assetsDir . '/*.js') ?: [],
            glob($assetsDir . '/*.png') ?: []
        );

        if (empty($files)) {
            $this->skip('No testable asset files in theme');
        }

        $file = basename($files[0]);
        $request = $this->createThemeRequest($file);
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->status());
        $this->assertStringContains('max-age=', $response->header('Cache-Control'));
        $this->assertNotEmpty($response->header('ETag'));
        $this->assertNotEmpty($response->header('Last-Modified'));
    }
}
