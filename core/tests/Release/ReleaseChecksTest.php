<?php

declare(strict_types=1);

namespace Ava\Tests\Release;

use Ava\Support\Arr;
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
    public function testSensitiveRuntimePathsAreGitignored(): void
    {
        $gitignore = file_get_contents(AVA_ROOT . '/.gitignore');

        foreach (['.env', 'storage/cache'] as $entry) {
            $this->assertStringContains($entry, $gitignore, "$entry should be gitignored");
        }
    }

    public function testShippedConfigurationUsesSafeDefaults(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';

        $expected = [
            'debug.enabled'        => false,
            'theme'                => 'default',
            'cli.theme'            => 'cyan',
            'site.name'            => 'My Ava Site',
            'site.base_url'        => 'http://localhost:8000',
            'site.timezone'        => 'UTC',
            'site.locale'          => 'en_GB',
            'content_index.mode'   => 'auto',
            // A shipped token would be a shared secret across every install.
            'security.preview_token' => null,
            // Publishing the exact version on every page makes unpatched
            // installs searchable in bulk after a security release.
            'generator_comment'    => false,
        ];

        foreach ($expected as $key => $value) {
            $this->assertSame($value, Arr::get($config, $key), "$key should be " . $this->export($value));
        }

        $this->assertContains("script-src 'self'", Arr::get($config, 'security.headers.content_security_policy'));
    }

    public function testVersionFollowsCalVer(): void
    {
        $this->assertMatchesRegex(
            '/^\d+\.\d+\.\d+$/',
            AVA_VERSION,
            "Version '" . AVA_VERSION . "' should match YEAR.MONTH.PATCH (e.g. 26.9.0)"
        );
    }

    /** Guards against tagging a release that GitHub already has. */
    public function testVersionLeadsTheLatestGitHubRelease(): void
    {
        $current = AVA_VERSION;

        if (!extension_loaded('curl')) {
            $this->skip('curl extension required for GitHub API check');
        }

        $ch = curl_init('https://api.github.com/repos/avacms/ava/releases/latest');
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

        $latest = ltrim($release['tag_name'], 'v');
        $this->assertTrue(
            version_compare($current, $latest, '>'),
            "Local version ({$current}) should be higher than GitHub release ({$latest})"
        );
    }

    public function testRequiredFilesAreShipped(): void
    {
        $required = [
            'app/themes/default'             => 'default theme',
            'app/themes/default/theme.php'   => 'default theme bootstrap',
            'content/pages/index.md'         => 'example index page',
            'README.md'                      => 'readme',
            'LICENSE'                        => 'licence',
            'composer.json'                  => 'composer manifest',
            'vendor/autoload.php'            => 'composer autoloader (run composer install)',
        ];

        foreach ($required as $path => $label) {
            $this->assertTrue(file_exists(AVA_ROOT . '/' . $path), "Missing $label at $path");
        }

        $composer = json_decode(file_get_contents(AVA_ROOT . '/composer.json'), true);
        $this->assertIsArray($composer, 'composer.json should be valid JSON');
        $this->assertArrayHasKey('name', $composer);
    }

    public function testMediaDirectoryShipsEmpty(): void
    {
        $mediaDir = AVA_ROOT . '/public/media';
        if (!is_dir($mediaDir)) {
            return;
        }

        $files = array_diff(scandir($mediaDir), ['.', '..', '.gitkeep']);
        $this->assertEmpty(
            $files,
            'Media directory should be empty for release (found: ' . implode(', ', $files) . ')'
        );
    }
}
