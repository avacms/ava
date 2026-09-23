<?php

declare(strict_types=1);

namespace Ava\Tests\Core;

use Ava\Updater;
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
    public function testUpdaterPreventsConcurrentInstallations(): void
    {
        $directory = $this->app->configPath('storage') . '/tmp/test-updater-lock-'
            . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);
        $lockFile = $directory . '/update.lock';
        $firstLock = null;

        try {
            $updater = new Updater($this->app);
            $method = new \ReflectionMethod($updater, 'acquireUpdateLock');
            $method->setAccessible(true);
            $firstLock = $method->invoke($updater, $lockFile);

            $this->assertThrows(
                \RuntimeException::class,
                fn() => $method->invoke($updater, $lockFile)
            );
        } finally {
            if (is_resource($firstLock)) {
                flock($firstLock, LOCK_UN);
                fclose($firstLock);
            }
            if (is_file($lockFile)) {
                unlink($lockFile);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testUpdaterRestoresOriginalFilesAfterActivationFailure(): void
    {
        $directory = $this->app->configPath('storage') . '/tmp/test-updater-rollback-'
            . bin2hex(random_bytes(6));
        $root = $directory . '/root';
        $backup = $directory . '/backup';
        mkdir($root . '/core', 0700, true);
        mkdir($backup . '/core', 0700, true);
        file_put_contents($root . '/core/version.txt', 'new');
        file_put_contents($root . '/bootstrap.php', 'new bootstrap');
        file_put_contents($backup . '/core/version.txt', 'old');
        file_put_contents($backup . '/bootstrap.php', 'old bootstrap');

        try {
            $updater = new Updater($this->app);
            $method = new \ReflectionMethod($updater, 'restoreUpdateTargets');
            $method->setAccessible(true);
            $errors = $method->invoke(
                $updater,
                $root,
                $backup,
                ['core', 'bootstrap.php'],
                ['core', 'bootstrap.php']
            );

            $this->assertEquals([], $errors);
            $this->assertEquals('old', file_get_contents($root . '/core/version.txt'));
            $this->assertEquals('old bootstrap', file_get_contents($root . '/bootstrap.php'));
            $this->assertFalse(file_exists($backup . '/core'));
            $this->assertFalse(file_exists($backup . '/bootstrap.php'));
        } finally {
            if (is_dir($directory)) {
                $this->removeTestDirectory($directory);
            }
        }
    }

    public function testUpdaterRejectsIncompletePackageBeforeChangingFiles(): void
    {
        $directory = $this->app->configPath('storage') . '/tmp/test-updater-incomplete-'
            . bin2hex(random_bytes(6));
        $source = $directory . '/source';
        $root = $directory . '/root';
        mkdir($source, 0700, true);
        mkdir($root, 0700, true);
        file_put_contents($root . '/required.php', 'old');

        try {
            $updater = new Updater($this->app);
            $updateDirs = new \ReflectionProperty($updater, 'updateDirs');
            $updateDirs->setAccessible(true);
            $updateDirs->setValue($updater, ['required.php']);
            $bundledPlugins = new \ReflectionProperty($updater, 'bundledPlugins');
            $bundledPlugins->setAccessible(true);
            $bundledPlugins->setValue($updater, []);

            $method = new \ReflectionMethod($updater, 'applyUpdates');
            $method->setAccessible(true);
            $this->assertThrows(
                \RuntimeException::class,
                fn() => $method->invoke($updater, $source, $root)
            );

            $this->assertEquals('old', file_get_contents($root . '/required.php'));
        } finally {
            if (is_dir($directory)) {
                $this->removeTestDirectory($directory);
            }
        }
    }

    public function testUpdaterOnlyAllowsHttpsGitHubDownloads(): void
    {
        $updater = new Updater($this->app);
        $method = new \ReflectionMethod($updater, 'validateDownloadUrl');
        $method->setAccessible(true);

        $method->invoke($updater, 'https://api.github.com/repos/avacms/ava/zipball/v1.0.0');
        $method->invoke($updater, 'https://codeload.github.com/avacms/ava/legacy.zip/v1.0.0');

        foreach ([
            'http://api.github.com/repos/avacms/ava/zipball/v1.0.0',
            'https://api.github.com.evil.example/update.zip',
            'https://user@example.com/update.zip',
            'https://github.com/attacker/repository/archive/v1.0.0.zip',
        ] as $url) {
            $this->assertThrows(
                \RuntimeException::class,
                fn() => $method->invoke($updater, $url)
            );
        }
    }

    public function testUpdaterRejectsArchiveTraversal(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markSkipped('ZipArchive extension is not available');
        }

        $directory = $this->app->configPath('storage') . '/tmp/test-updater-zip-'
            . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);
        $zipPath = $directory . '/malicious.zip';
        $destination = $directory . '/extract';

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath, \ZipArchive::CREATE) === true);
        $this->assertTrue($zip->addFromString('release/../../escaped.php', 'unsafe'));
        $zip->close();

        try {
            $updater = new Updater($this->app);
            $method = new \ReflectionMethod($updater, 'extract');
            $method->setAccessible(true);

            $this->assertThrows(
                \RuntimeException::class,
                fn() => $method->invoke($updater, $zipPath, $destination)
            );
            $this->assertFalse(file_exists($directory . '/escaped.php'));
            $this->assertFalse(is_dir($destination));
        } finally {
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
            if (is_dir($destination)) {
                rmdir($destination);
            }
            rmdir($directory);
        }
    }

    public function testUpdaterWorkspacesAreUniqueAndPrivate(): void
    {
        $updater = new Updater($this->app);
        $method = new \ReflectionMethod($updater, 'createTemporaryWorkspace');
        $method->setAccessible(true);

        $first = $method->invoke($updater, 'test');
        $second = null;

        try {
            $second = $method->invoke($updater, 'test');
            $tempRoot = realpath($this->app->configPath('storage') . '/tmp');
            if ($tempRoot === false) {
                $this->fail('Updater temporary root could not be resolved.');
            }
            $this->assertNotEquals($first, $second);
            $this->assertTrue(is_dir($first));
            $this->assertTrue(is_dir($second));
            $this->assertStringStartsWith($tempRoot . DIRECTORY_SEPARATOR, $first);
            $this->assertStringStartsWith($tempRoot . DIRECTORY_SEPARATOR, $second);

            if (DIRECTORY_SEPARATOR === '/') {
                $this->assertEquals(0700, fileperms($first) & 0777);
                $this->assertEquals(0700, fileperms($second) & 0777);
            }
        } finally {
            if (is_dir($first)) {
                rmdir($first);
            }
            if (is_string($second) && is_dir($second)) {
                rmdir($second);
            }
        }
    }

    /**
     * The updater replaces its target paths wholesale and re-copies bundled
     * plugins, so a missing target would silently drop working files.
     */
    public function testUpdateTargetsAndPreservedPathsExistInTheShippedTree(): void
    {
        $updater = new Updater($this->app);
        $property = new \ReflectionProperty($updater, 'updateDirs');
        $property->setAccessible(true);

        foreach ($property->getValue($updater) as $target) {
            $this->assertTrue(
                file_exists(AVA_ROOT . '/' . $target),
                "Update target '$target' should exist"
            );
        }

        // These hold user data and must survive an update untouched.
        foreach (['content', 'app', 'storage'] as $preserved) {
            $this->assertTrue(is_dir(AVA_ROOT . '/' . $preserved), "'$preserved' should exist");
        }

        $this->assertTrue(is_dir($this->app->configPath('themes') . '/' . $this->app->config('theme', 'default')));
    }

    public function testBundledPluginsAreShippedWithAnEntryPoint(): void
    {
        $updater = new Updater($this->app);
        $property = new \ReflectionProperty($updater, 'bundledPlugins');
        $property->setAccessible(true);
        $plugins = $property->getValue($updater);

        $this->assertNotEmpty($plugins);
        foreach ($plugins as $plugin) {
            $this->assertTrue(
                is_file(AVA_ROOT . '/app/plugins/' . $plugin . '/plugin.php'),
                "Bundled plugin '$plugin' should ship a plugin.php"
            );
        }
    }

    private function removeTestDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
    public function testDevUpdatesDownloadTheExactCommitReported(): void
    {
        $updater = new \Ava\Updater($this->app);
        $method = new \ReflectionMethod($updater, 'getCommitZipUrl');
        $sha = str_repeat('a1', 20);

        $this->assertEquals(
            'https://api.github.com/repos/avacms/ava/zipball/' . $sha,
            $method->invoke($updater, ['sha' => $sha])
        );
        foreach ([[], ['sha' => 'main'], ['sha' => '../../evil']] as $commit) {
            $this->assertThrows(\RuntimeException::class, fn() => $method->invoke($updater, $commit));
        }
    }
}
