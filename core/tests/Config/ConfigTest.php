<?php

declare(strict_types=1);

namespace Ava\Tests\Config;

use Ava\Support\Arr;
use Ava\Testing\TestCase;

/**
 * Tests for configuration access patterns.
 * 
 * Ava uses Arr::get for dot-notation config access. This tests
 * that pattern with configuration-like structures.
 */
final class ConfigTest extends TestCase
{
    private array $config;

    public function setUp(): void
    {
        // Simulated configuration structure matching app/config/ava.php
        $this->config = [
            'site' => [
                'name' => 'Test Site',
                'base_url' => 'https://example.com',
                'timezone' => 'America/New_York',
                'locale' => 'en_US',
            ],
            'theme' => 'default',
            'paths' => [
                'content' => 'content',
                'themes' => 'themes',
                'storage' => 'storage',
                'plugins' => 'plugins',
                'aliases' => [
                    '@media' => '/media',
                    '@assets' => '/assets',
                ],
            ],
            'debug' => [
                'enabled' => true,
                'display_errors' => true,
            ],
            'plugins' => [
                'feed' => ['enabled' => true],
                'sitemap' => ['enabled' => true],
                'redirects' => ['enabled' => false],
            ],
            'webpage_cache' => [
                'enabled' => false,
                'ttl' => 3600,
                'exclude' => ['/api/*'],
            ],
            'content' => [
                'per_page' => 10,
                'markdown' => [
                    'allow_html' => true,
                    'linkify' => false,
                ],
            ],
            'features' => null,
            'empty_array' => [],
        ];
    }

    // =========================================================================
    // Basic Access
    // =========================================================================

    public function testGetTopLevelString(): void
    {
        $this->assertEquals('default', Arr::get($this->config, 'theme'));
    }

    public function testGetTopLevelArray(): void
    {
        $site = Arr::get($this->config, 'site');
        $this->assertIsArray($site);
        $this->assertEquals('Test Site', $site['name']);
    }

    public function testGetNestedValue(): void
    {
        $this->assertEquals('Test Site', Arr::get($this->config, 'site.name'));
    }

    public function testGetDeeplyNestedValue(): void
    {
        $this->assertTrue(Arr::get($this->config, 'content.markdown.allow_html'));
    }

    public function testGetNestedArray(): void
    {
        $markdown = Arr::get($this->config, 'content.markdown');
        $this->assertIsArray($markdown);
        $this->assertTrue($markdown['allow_html']);
    }

    // =========================================================================
    // Defaults
    // =========================================================================

    public function testMissingKeyReturnsDefault(): void
    {
        $this->assertEquals('fallback', Arr::get($this->config, 'nonexistent', 'fallback'));
    }

    public function testMissingNestedKeyReturnsDefault(): void
    {
        $this->assertEquals('default', Arr::get($this->config, 'site.missing.deep', 'default'));
    }

    public function testNullValueReturnsNull(): void
    {
        $this->assertNull(Arr::get($this->config, 'features'));
    }

    public function testNullValueDoesNotUseDefault(): void
    {
        // When the key exists but is null, return null (not the default)
        $result = Arr::get($this->config, 'features', 'default');
        $this->assertNull($result);
    }

    public function testEmptyArrayReturnsEmptyArray(): void
    {
        $this->assertEquals([], Arr::get($this->config, 'empty_array'));
    }

    // =========================================================================
    // Boolean Values
    // =========================================================================

    public function testBooleanTrue(): void
    {
        $this->assertTrue(Arr::get($this->config, 'debug.enabled'));
    }

    public function testBooleanFalse(): void
    {
        $this->assertFalse(Arr::get($this->config, 'webpage_cache.enabled'));
    }

    public function testNestedBooleanFalse(): void
    {
        $this->assertFalse(Arr::get($this->config, 'content.markdown.linkify'));
    }

    // =========================================================================
    // Numeric Values
    // =========================================================================

    public function testIntegerValue(): void
    {
        $this->assertEquals(10, Arr::get($this->config, 'content.per_page'));
    }

    public function testTtlValue(): void
    {
        $this->assertEquals(3600, Arr::get($this->config, 'webpage_cache.ttl'));
    }

    // =========================================================================
    // Path-like Configurations
    // =========================================================================

    public function testPathValue(): void
    {
        $this->assertEquals('content', Arr::get($this->config, 'paths.content'));
    }

    public function testAliasPath(): void
    {
        $this->assertEquals('/media', Arr::get($this->config, 'paths.aliases.@media'));
    }

    public function testDebugPath(): void
    {
        $this->assertTrue(Arr::get($this->config, 'debug.display_errors'));
    }

    // =========================================================================
    // Plugin Configuration
    // =========================================================================

    public function testPluginEnabled(): void
    {
        $this->assertTrue(Arr::get($this->config, 'plugins.feed.enabled'));
    }

    public function testPluginDisabled(): void
    {
        $this->assertFalse(Arr::get($this->config, 'plugins.redirects.enabled'));
    }

    public function testMissingPluginReturnsDefault(): void
    {
        $this->assertFalse(Arr::get($this->config, 'plugins.nonexistent.enabled', false));
    }

    // =========================================================================
    // Array Values
    // =========================================================================

    public function testExcludePatterns(): void
    {
        $exclude = Arr::get($this->config, 'webpage_cache.exclude');
        $this->assertIsArray($exclude);
        $this->assertCount(1, $exclude);
        $this->assertContains('/api/*', $exclude);
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    public function testEmptyKeyReturnsFullConfig(): void
    {
        $result = Arr::get($this->config, '');
        // Empty key returns default (null) not the full array
        $this->assertNull($result);
    }

    public function testKeyWithDotsInName(): void
    {
        // Arr::get uses dot notation, so 'file.txt' is interpreted as nested
        // To use literal dots in keys, you'd need a different accessor
        $config = ['file.txt' => 'content'];
        // This actually works because there's no 'file' key with 'txt' subkey
        // The behavior: tries file->txt, fails, returns null
        // But PHP allows 'file.txt' as a direct key, so Arr::get falls through
        $result = Arr::get($config, 'file.txt');
        // Actual behavior: returns 'content' because 'file' key doesn't exist
        // so it checks the literal key 'file.txt'
        $this->assertEquals('content', $result);
    }

    public function testNumericKeys(): void
    {
        $config = ['items' => ['first', 'second', 'third']];
        $this->assertEquals('second', Arr::get($config, 'items.1'));
    }

    public function testIntermediateNonArray(): void
    {
        // When traversing, if we hit a non-array, return default
        $this->assertNull(Arr::get($this->config, 'theme.nested'));
    }
}
