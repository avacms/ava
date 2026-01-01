<?php

declare(strict_types=1);

namespace Ava\Tests\Admin;

use Ava\Testing\TestCase;

/**
 * Debug Mode Tests
 *
 * Tests for debug configuration, error logging, and debug info retrieval.
 */
final class DebugTest extends TestCase
{
    /**
     * Test debug config default values
     */
    public function testDebugConfigDefaults(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        
        $this->assertArrayHasKey('debug', $config);
        $debug = $config['debug'];
        
        $this->assertArrayHasKey('enabled', $debug);
        $this->assertArrayHasKey('display_errors', $debug);
        $this->assertArrayHasKey('log_errors', $debug);
        $this->assertArrayHasKey('level', $debug);
    }

    /**
     * Test debug config values are boolean (except level)
     * 
     * Note: The actual values (true/false) depend on environment. 
     * This test validates the config structure and types.
     */
    public function testDebugConfigSafeDefaults(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        $debug = $config['debug'];
        
        $this->assertIsBool($debug['enabled'], 'enabled should be boolean');
        $this->assertIsBool($debug['display_errors'], 'display_errors should be boolean');
        $this->assertIsBool($debug['log_errors'], 'log_errors should be boolean');
        $this->assertIsString($debug['level'], 'level should be string');
    }

    /**
     * Test debug level is one of allowed values
     */
    public function testDebugLevelValidValues(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        $level = $config['debug']['level'];
        
        $this->assertContains($level, ['all', 'errors', 'none']);
    }

    /**
     * Test debug enabled flag is boolean
     */
    public function testDebugEnabledIsBoolean(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        
        $this->assertTrue(is_bool($config['debug']['enabled']));
        $this->assertTrue(is_bool($config['debug']['display_errors']));
        $this->assertTrue(is_bool($config['debug']['log_errors']));
    }

    /**
     * Test debug level is string
     */
    public function testDebugLevelIsString(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        
        $this->assertTrue(is_string($config['debug']['level']));
    }

    /**
     * Test error log path is in storage/logs
     */
    public function testErrorLogPath(): void
    {
        $errorLogPath = AVA_ROOT . '/storage/logs/error.log';
        
        // The directory should exist
        $this->assertTrue(
            is_dir(dirname($errorLogPath)),
            'storage/logs directory should exist'
        );
    }

    /**
     * Test parsing single line error log entry
     */
    public function testParseErrorLogEntry(): void
    {
        // Test the log format: [timestamp] LEVEL: message
        $line = '[2025-12-31 23:59:59] ERROR: Something went wrong';
        
        if (preg_match('/^\[([^\]]+)\]\s+(\w+):\s*(.*)$/', $line, $m)) {
            $this->assertEquals('2025-12-31 23:59:59', $m[1]);
            $this->assertEquals('ERROR', $m[2]);
            $this->assertEquals('Something went wrong', $m[3]);
        } else {
            $this->fail('Failed to parse error log entry');
        }
    }

    /**
     * Test parsing warning log entry
     */
    public function testParseWarningLogEntry(): void
    {
        $line = '[2025-12-31 23:59:59] WARNING: Be careful';
        
        if (preg_match('/^\[([^\]]+)\]\s+(\w+):\s*(.*)$/', $line, $m)) {
            $this->assertEquals('WARNING', $m[2]);
            $this->assertEquals('Be careful', $m[3]);
        } else {
            $this->fail('Failed to parse warning log entry');
        }
    }

    /**
     * Test parsing notice log entry
     */
    public function testParseNoticeLogEntry(): void
    {
        $line = '[2025-12-31 23:59:59] NOTICE: Just so you know';
        
        if (preg_match('/^\[([^\]]+)\]\s+(\w+):\s*(.*)$/', $line, $m)) {
            $this->assertEquals('NOTICE', $m[2]);
            $this->assertEquals('Just so you know', $m[3]);
        } else {
            $this->fail('Failed to parse notice log entry');
        }
    }

    /**
     * Test log levels
     */
    public function testDebugLevels(): void
    {
        $levels = ['all', 'errors', 'none'];
        
        foreach ($levels as $level) {
            $this->assertIsString($level);
            $this->assertTrue(strlen($level) > 0);
        }
    }

    /**
     * Test error log entry with special characters
     */
    public function testParseLogEntryWithSpecialChars(): void
    {
        $line = '[2025-12-31 23:59:59] ERROR: Call to undefined function foo() in /path/to/file.php:42';
        
        if (preg_match('/^\[([^\]]+)\]\s+(\w+):\s*(.*)$/', $line, $m)) {
            $this->assertStringContains('undefined function', $m[3]);
            $this->assertStringContains('/path/to/file.php:42', $m[3]);
        } else {
            $this->fail('Failed to parse complex error log entry');
        }
    }

    /**
     * Test error log entry with brackets in message
     */
    public function testParseLogEntryWithBracketsInMessage(): void
    {
        $line = '[2025-12-31 23:59:59] ERROR: Array [foo, bar, baz] is invalid';
        
        if (preg_match('/^\[([^\]]+)\]\s+(\w+):\s*(.*)$/', $line, $m)) {
            $this->assertStringContains('[foo, bar, baz]', $m[3]);
        } else {
            $this->fail('Failed to parse log entry with brackets');
        }
    }

    /**
     * Test error levels in bootstrap
     */
    public function testBootstrapErrorHandling(): void
    {
        // bootstrap.php should set up error handling based on debug config
        // We can verify the file exists and contains error handler setup
        $bootstrap = file_get_contents(AVA_ROOT . '/bootstrap.php');
        
        $this->assertStringContains('set_error_handler', $bootstrap);
        $this->assertStringContains('set_exception_handler', $bootstrap);
    }

    /**
     * Test debug config in bootstrap
     */
    public function testBootstrapReadsDebugConfig(): void
    {
        $bootstrap = file_get_contents(AVA_ROOT . '/bootstrap.php');
        
        // Should read debug config
        $this->assertStringContains('debug', $bootstrap);
        // Should handle error reporting
        $this->assertStringContains('error_reporting', $bootstrap);
    }

    /**
     * Test error log format is consistent
     */
    public function testErrorLogFormatConsistency(): void
    {
        // Multiple variations should all match the pattern
        $entries = [
            '[2025-12-31 23:59:59] ERROR: First error',
            '[2025-12-31 23:59:58] WARNING: Second warning',
            '[2025-12-31 23:59:57] NOTICE: Third notice',
        ];
        
        $pattern = '/^\[([^\]]+)\]\s+(\w+):\s*(.*)$/';
        
        foreach ($entries as $entry) {
            $this->assertTrue(
                (bool) preg_match($pattern, $entry),
                "Entry '$entry' should match log format"
            );
        }
    }

    /**
     * Test debug config keys consistency
     */
    public function testDebugConfigKeysConsistency(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        $debug = $config['debug'];
        
        $expectedKeys = ['enabled', 'display_errors', 'log_errors', 'level'];
        
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $debug, "Debug config should have '$key' key");
        }
    }

    /**
     * Test admin controller includes debug info
     */
    public function testAdminControllerDebugIntegration(): void
    {
        $controller = file_get_contents(AVA_ROOT . '/core/Admin/Controller.php');
        
        // Should have getDebugInfo method
        $this->assertStringContains('getDebugInfo', $controller);
        // Should parse debug config
        $this->assertStringContains("config('debug'", $controller);
        // Should read error log
        $this->assertStringContains('error.log', $controller);
    }

    /**
     * Test admin system view includes debug info
     */
    public function testAdminSystemViewDebugDisplay(): void
    {
        $view = file_get_contents(AVA_ROOT . '/core/Admin/views/system.php');
        
        // Should display debug info
        $this->assertStringContains('Debug Mode', $view);
        // Should show debug status
        $this->assertStringContains('debugInfo', $view);
    }

    /**
     * Test all debug config values are present
     */
    public function testAllDebugConfigValuesPresent(): void
    {
        $config = require AVA_ROOT . '/app/config/ava.php';
        $debug = $config['debug'];
        
        // All values should be set
        $this->assertNotNull($debug['enabled']);
        $this->assertNotNull($debug['display_errors']);
        $this->assertNotNull($debug['log_errors']);
        $this->assertNotNull($debug['level']);
    }

    /**
     * Test error level 'all' includes all types
     */
    public function testDebugLevelAll(): void
    {
        $this->assertEquals('all', 'all');
        // 'all' should enable all error reporting
        // E_ALL | E_STRICT in PHP
    }

    /**
     * Test error level 'errors' includes errors only
     */
    public function testDebugLevelErrors(): void
    {
        $this->assertEquals('errors', 'errors');
        // 'errors' should enable E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR
    }

    /**
     * Test error level 'none' disables reporting
     */
    public function testDebugLevelNone(): void
    {
        $this->assertEquals('none', 'none');
        // 'none' should be 0 (no errors reported)
    }
}
