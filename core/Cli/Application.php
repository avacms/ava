<?php

declare(strict_types=1);

namespace Ava\Cli;

use Ava\Application as AvaApp;
use Ava\Support\Ulid;
use Ava\Support\Str;

/**
 * CLI Application
 *
 * Handles command-line interface for Ava CMS.
 */
final class Application
{
    private AvaApp $app;
    private array $commands = [];

    // Theme colors (set dynamically based on config)
    private string $themeColor;

    // ANSI formatting
    private const RESET = "\033[0m";
    private const BOLD = "\033[1m";
    private const DIM = "\033[90m";
    private const ITALIC = "\033[3m";

    // Theme placeholder (will be replaced with actual theme color)
    private const PRIMARY = '__THEME__';

    // Standard colors (always available)
    private const BLACK = "\033[30m";
    private const RED = "\033[38;2;248;113;113m";
    private const GREEN = "\033[38;2;52;211;153m";
    private const YELLOW = "\033[38;2;251;191;36m";
    private const WHITE = "\033[37m";

    // Color themes (monochrome - single accent color)
    private const THEMES = [
        'cyan'     => "\033[38;2;34;211;238m",    // Cyan-400
        'pink'     => "\033[38;2;244;114;182m",   // Pink-400
        'purple'   => "\033[38;2;167;139;250m",   // Violet-400
        'green'    => "\033[38;2;74;222;128m",    // Green-400
        'blue'     => "\033[38;2;96;165;250m",    // Blue-400
        'amber'    => "\033[38;2;251;191;36m",    // Amber-400
        'disabled' => "\033[37m",                 // White (no color)
    ];

    // ASCII Art banner (3 lines)
    private const BANNER_LINES = [
        '   ▄▄▄  ▄▄ ▄▄  ▄▄▄     ▄▄▄▄ ▄▄   ▄▄  ▄▄▄▄ ',
        '  ██▀██ ██▄██ ██▀██   ██▀▀▀ ██▀▄▀██ ███▄▄ ',
        '  ██▀██  ▀█▀  ██▀██   ▀████ ██   ██ ▄▄██▀',
    ];

    public function __construct(AvaApp $app)
    {
        $this->app = $app;
        $this->loadTheme();
        $this->registerCommands();
        $this->registerPluginCommands();
    }

    /**
     * Load the CLI color theme from config.
     */
    private function loadTheme(): void
    {
        $themeName = $this->app->config('cli.theme', 'cyan');
        $this->themeColor = self::THEMES[$themeName] ?? self::THEMES['cyan'];
    }

    /**
     * Run the CLI application.
     */
    public function run(array $argv): int
    {
        $script = array_shift($argv);
        $command = array_shift($argv) ?? 'help';

        // Handle help
        if ($command === 'help' || $command === '--help' || $command === '-h') {
            $this->showHelp();
            return 0;
        }

        // Handle version
        if ($command === 'version' || $command === '--version' || $command === '-v') {
            $this->showBanner(showVersion: true);
            $this->writeln('');
            return 0;
        }

        // Find and run command
        if (!isset($this->commands[$command])) {
            $this->error("Unknown command: {$command}");
            $this->showHelp();
            return 1;
        }

        try {
            return $this->commands[$command]($argv);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }

    /**
     * Register available commands.
     */
    private function registerCommands(): void
    {
        $this->commands['status'] = [$this, 'cmdStatus'];
        $this->commands['rebuild'] = [$this, 'cmdRebuild'];
        $this->commands['lint'] = [$this, 'cmdLint'];
        $this->commands['make'] = [$this, 'cmdMake'];
        $this->commands['prefix'] = [$this, 'cmdPrefix'];
        $this->commands['cache'] = [$this, 'cmdCacheStats']; // Alias for cache:stats
        $this->commands['cache:clear'] = [$this, 'cmdCacheClear'];
        $this->commands['cache:stats'] = [$this, 'cmdCacheStats'];
        $this->commands['logs'] = [$this, 'cmdLogsStats']; // Alias for logs:stats
        $this->commands['logs:stats'] = [$this, 'cmdLogsStats'];
        $this->commands['logs:clear'] = [$this, 'cmdLogsClear'];
        $this->commands['logs:tail'] = [$this, 'cmdLogsTail'];
        $this->commands['benchmark'] = [$this, 'cmdBenchmark'];
        $this->commands['test'] = [$this, 'cmdTest'];
        $this->commands['stress:generate'] = [$this, 'cmdStressGenerate'];
        $this->commands['stress:clean'] = [$this, 'cmdStressClean'];
        $this->commands['stress:benchmark'] = [$this, 'cmdBenchmark']; // Alias for benchmark
        $this->commands['user'] = [$this, 'cmdUserList']; // Alias for user:list
        $this->commands['user:add'] = [$this, 'cmdUserAdd'];
        $this->commands['user:password'] = [$this, 'cmdUserPassword'];
        $this->commands['user:remove'] = [$this, 'cmdUserRemove'];
        $this->commands['user:list'] = [$this, 'cmdUserList'];
        $this->commands['update'] = [$this, 'cmdUpdateCheck']; // Alias for update:check
        $this->commands['update:check'] = [$this, 'cmdUpdateCheck'];
        $this->commands['update:apply'] = [$this, 'cmdUpdateApply'];
    }

    /**
     * Register commands from enabled plugins.
     * 
     * Plugins can register CLI commands by including a 'commands' key in their
     * plugin.php return array. Each command should be an array with:
     * - 'name': Command name (e.g., 'sitemap:stats')
     * - 'description': Command description for help
     * - 'handler': Callable that receives ($args, $cli, $app) and returns exit code
     * 
     * @example
     * return [
     *     'name' => 'My Plugin',
     *     'commands' => [
     *         [
     *             'name' => 'myplugin:status',
     *             'description' => 'Show plugin status',
     *             'handler' => function($args, $cli, $app) {
     *                 $cli->info("Plugin is running!");
     *                 return 0;
     *             },
     *         ],
     *     ],
     * ];
     */
    private function registerPluginCommands(): void
    {
        $enabledPlugins = $this->app->config('plugins', []);
        $pluginsPath = $this->app->configPath('plugins');

        foreach ($enabledPlugins as $pluginName) {
            $pluginFile = $pluginsPath . '/' . $pluginName . '/plugin.php';
            if (!file_exists($pluginFile)) {
                continue;
            }

            $plugin = require $pluginFile;
            if (!is_array($plugin) || empty($plugin['commands'])) {
                continue;
            }

            foreach ($plugin['commands'] as $cmdConfig) {
                $cmdName = $cmdConfig['name'] ?? null;
                if (!$cmdName || !isset($cmdConfig['handler']) || !is_callable($cmdConfig['handler'])) {
                    continue;
                }

                // Store the command with plugin context - pass $app as third argument
                $this->commands[$cmdName] = function ($args) use ($cmdConfig) {
                    return $cmdConfig['handler']($args, $this, $this->app);
                };

                // Store command metadata for help display
                $this->pluginCommands[] = [
                    'name' => $cmdName,
                    'description' => $cmdConfig['description'] ?? 'Plugin command',
                    'plugin' => $pluginName,
                ];
            }
        }
    }

    /** @var array<string, array> Plugin command metadata for help display */
    private array $pluginCommands = [];

    // =========================================================================
    // Commands
    // =========================================================================

    /**
     * Show site status.
     */
    private function cmdStatus(array $args): int
    {
        $this->showBanner(showVersion: true);

        // Site info
        $this->sectionHeader('Site');
        $this->keyValue('Name', $this->color($this->app->config('site.name'), self::BOLD));
        $this->keyValue('URL', $this->color($this->app->config('site.base_url'), self::PRIMARY));

        // PHP environment
        $this->sectionHeader('Environment');
        $this->keyValue('PHP', PHP_VERSION);
        $extensions = [];
        if (extension_loaded('igbinary')) {
            $extensions[] = $this->color('igbinary', self::GREEN);
        }
        if (extension_loaded('opcache') && ini_get('opcache.enable')) {
            $extensions[] = $this->color('opcache', self::GREEN);
        }
        if (!empty($extensions)) {
            $this->keyValue('Extensions', implode(', ', $extensions));
        }

        // Content Index status
        $this->sectionHeader('Content Index');
        $cachePath = $this->app->configPath('storage') . '/cache';
        $fingerprintPath = $cachePath . '/fingerprint.json';

        if (file_exists($fingerprintPath)) {
            $fresh = $this->app->indexer()->isCacheFresh();
            $status = $fresh
                ? $this->color('● Fresh', self::GREEN, self::BOLD)
                : $this->color('○ Stale', self::YELLOW, self::BOLD);
            $this->keyValue('Status', $status);
            $this->keyValue('Mode', $this->app->config('content_index.mode', 'auto'));

            // Show backend info
            $repository = $this->app->repository();
            $backendName = ucfirst($repository->backendName());
            $configBackend = $this->app->config('content_index.backend', 'auto');
            $backendInfo = $this->color($backendName, self::PRIMARY);
            if ($configBackend === 'auto') {
                $backendInfo .= $this->color(' (auto-detected)', self::DIM);
            }
            $this->keyValue('Backend', $backendInfo);

            // Show cache file sizes
            $cacheFiles = [
                'content_index.bin' => 'Full index',
                'slug_lookup.bin' => 'Slug lookup',
                'recent_cache.bin' => 'Recent cache',
                'routes.bin' => 'Routes',
                'tax_index.bin' => 'Taxonomies',
            ];
            
            $sizes = [];
            foreach ($cacheFiles as $file => $label) {
                $path = $cachePath . '/' . $file;
                if (file_exists($path)) {
                    $sizes[] = $this->color($label, self::DIM) . ' ' . $this->formatBytes(filesize($path));
                }
            }

            // Add SQLite size if available
            $sqlitePath = $cachePath . '/content_index.sqlite';
            if (file_exists($sqlitePath)) {
                $sizes[] = $this->color('SQLite', self::DIM) . ' ' . $this->formatBytes(filesize($sqlitePath));
            }

            if (!empty($sizes)) {
                $this->keyValue('Cache', implode(', ', $sizes));
            }

            // Show build time
            $indexPath = $cachePath . '/content_index.bin';
            if (file_exists($indexPath)) {
                $mtime = filemtime($indexPath);
                $this->keyValue('Built', $this->color(date('Y-m-d H:i:s', $mtime), self::DIM));
            }
        } else {
            $this->keyValue('Status', $this->color('○ Not built', self::YELLOW));
            $this->tip('Run ./ava rebuild to build the index');
        }

        // Content counts
        $this->sectionHeader('Content');
        $repository = $this->app->repository();

        $rows = [];
        foreach ($repository->types() as $type) {
            $total = $repository->count($type);
            $published = $repository->count($type, 'published');
            $drafts = $repository->count($type, 'draft');

            $draftBadge = $drafts > 0 ? $this->color(" ({$drafts} drafts)", self::YELLOW) : '';
            $this->labeledItem(
                ucfirst($type),
                $this->color((string) $published, self::GREEN, self::BOLD) .
                    $this->color(' published', self::DIM) . $draftBadge,
                $total > 0 ? '◆' : '◇',
                $total > 0 ? self::GREEN : self::DIM
            );
        }

        // Taxonomies
        $this->sectionHeader('Taxonomies');
        foreach ($repository->taxonomies() as $taxonomy) {
            $terms = $repository->terms($taxonomy);
            $count = count($terms);
            $this->labeledItem(
                ucfirst($taxonomy),
                $this->color((string) $count, self::PRIMARY, self::BOLD) .
                    $this->color(' terms', self::DIM),
                '◆',
                self::PRIMARY
            );
        }

        // Webpage cache stats
        $webpageCache = $this->app->webpageCache();
        $stats = $webpageCache->stats();
        $this->sectionHeader('Webpage Cache');
        $status = $stats['enabled']
            ? $this->color('● Enabled', self::GREEN, self::BOLD)
            : $this->color('○ Disabled', self::DIM);
        $this->keyValue('Status', $status);

        if ($stats['enabled']) {
            $ttl = $stats['ttl'] ?? null;
            $this->keyValue('TTL', $ttl ? "{$ttl}s" : 'Forever');
            $this->keyValue('Cached', $this->color((string) $stats['count'], self::PRIMARY, self::BOLD) . ' webpages');
            if ($stats['count'] > 0) {
                $this->keyValue('Size', $this->formatBytes($stats['size']));
            }
        }

        $this->writeln('');
        return 0;
    }

    /**
     * Rebuild cache.
     */
    private function cmdRebuild(array $args): int
    {
        $this->writeln('');
        $this->withSpinner('Rebuilding content index', function () {
            $this->app->indexer()->rebuild();
            return true;
        });

        // Reset OPcache if available (clears cached PHP bytecode)
        if (function_exists('opcache_reset')) {
            try {
                @opcache_reset();
            } catch (\Throwable $e) {
                // OPcache reset may fail in CLI mode, ignore silently
            }
        }

        $this->success('Content index rebuilt!');
        $this->writeln('');
        return 0;
    }

    /**
     * Lint content files.
     */
    private function cmdLint(array $args): int
    {
        $this->writeln('');
        echo $this->color('  🔍 Validating content files...', self::DIM) . "\n";
        $this->writeln('');

        $errors = $this->app->indexer()->lint();

        if (empty($errors)) {
            $this->box("All content files are valid!\nNo issues found.", 'success');
            $this->writeln('');
            return 0;
        }

        $this->error("Found " . count($errors) . " issue(s):");
        $this->writeln('');
        foreach ($errors as $error) {
            echo "    " . $this->color('•', self::RED) . " {$error}\n";
        }

        $this->writeln('');
        $this->tip('Fix the issues above and run lint again');
        $this->writeln('');

        return 1;
    }

    /**
     * Create content of a specific type.
     */
    private function cmdMake(array $args): int
    {
        if (count($args) < 2) {
            $this->writeln('');
            $this->error('Usage: ./ava make <type> "Title"');
            $this->writeln('');
            $this->showAvailableTypes();
            $this->writeln('');
            $this->writeln($this->color('  Example:', self::BOLD));
            $this->writeln('    ' . $this->color('./ava make post "My New Post"', self::PRIMARY));
            $this->writeln('');
            return 1;
        }

        $type = array_shift($args);
        $title = implode(' ', $args);

        // Verify type exists
        $contentTypes = require $this->app->path('app/config/content_types.php');
        if (!isset($contentTypes[$type])) {
            $this->error("Unknown content type: {$type}");
            $this->writeln('');
            $this->showAvailableTypes();
            return 1;
        }

        $typeConfig = $contentTypes[$type];
        $extra = ['status' => 'draft'];

        // Add date for dated content types
        if (($typeConfig['sorting'] ?? 'manual') === 'date_desc') {
            $extra['date'] = date('Y-m-d');
        }

        return $this->createContent($type, $title, $extra);
    }

    /**
     * Show available content types.
     */
    private function showAvailableTypes(): void
    {
        $contentTypes = require $this->app->path('app/config/content_types.php');
        $this->writeln($this->color('  Available types:', self::BOLD));
        $this->writeln('');
        foreach ($contentTypes as $name => $config) {
            $label = $config['label'] ?? ucfirst($name);
            echo '    ' . $this->color('▸ ', self::PRIMARY);
            echo $this->color($name, self::PRIMARY);
            echo $this->color(" — {$label}", self::DIM) . "\n";
        }
    }

    /**
     * Create a content file.
     */
    private function createContent(string $type, string $title, array $extra = []): int
    {
        // Load content type config
        $contentTypes = require $this->app->path('app/config/content_types.php');
        $typeConfig = $contentTypes[$type] ?? [];
        $contentDir = $typeConfig['content_dir'] ?? $type;

        // Generate slug and ID
        $slug = Str::slug($title);
        $id = Ulid::generate();

        // Build frontmatter
        $frontmatter = array_merge([
            'id' => $id,
            'title' => $title,
            'slug' => $slug,
        ], $extra);

        // Generate YAML
        $yaml = "---\n";
        foreach ($frontmatter as $key => $value) {
            if (is_array($value)) {
                $yaml .= "{$key}:\n";
                foreach ($value as $item) {
                    $yaml .= "  - {$item}\n";
                }
            } else {
                $yaml .= "{$key}: {$value}\n";
            }
        }
        $yaml .= "---\n\n";
        $yaml .= "Your content here.\n";

        // Determine file path
        $basePath = $this->app->configPath('content') . '/' . $contentDir;
        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        $filePath = $basePath . '/' . $slug . '.md';

        // Check if file exists
        if (file_exists($filePath)) {
            $this->error("File already exists: {$filePath}");
            return 1;
        }

        // Write file
        file_put_contents($filePath, $yaml);

        $relativePath = str_replace($this->app->path('') . '/', '', $filePath);

        $this->writeln('');
        $this->box("Created new {$type}!", 'success');
        $this->writeln('');
        $this->keyValue('File', $this->color($relativePath, self::PRIMARY));
        $this->keyValue('ID', $this->color($id, self::DIM));
        $this->keyValue('Slug', $slug);
        $this->keyValue('Status', $this->color('draft', self::YELLOW));
        $this->writeln('');
        $this->tip("Edit your content, then set status: published when ready");
        $this->writeln('');

        return 0;
    }

    /**
     * Toggle date prefix on content filenames.
     */
    private function cmdPrefix(array $args): int
    {
        $action = $args[0] ?? null;
        $typeFilter = $args[1] ?? null;

        if (!in_array($action, ['add', 'remove'], true)) {
            $this->writeln('');
            $this->error('Usage: ./ava prefix <add|remove> [type]');
            $this->writeln('');
            $this->writeln($this->color('  Examples:', self::BOLD));
            $this->writeln('    ' . $this->color('./ava prefix add post', self::PRIMARY) . $this->color('      # Add date prefix to posts', self::DIM));
            $this->writeln('    ' . $this->color('./ava prefix remove post', self::PRIMARY) . $this->color('   # Remove date prefix', self::DIM));
            $this->writeln('');
            return 1;
        }

        $contentTypes = require $this->app->path('app/config/content_types.php');
        $parser = new \Ava\Content\Parser();
        $renamed = 0;
        $skipped = 0;

        $this->writeln('');
        $actionLabel = $action === 'add' ? 'Adding' : 'Removing';
        echo $this->color("  {$actionLabel} date prefixes...", self::DIM) . "\n";
        $this->writeln('');

        foreach ($contentTypes as $typeName => $typeConfig) {
            // Filter by type if specified
            if ($typeFilter !== null && $typeName !== $typeFilter) {
                continue;
            }

            $contentDir = $this->app->path('content/' . ($typeConfig['content_dir'] ?? $typeName));
            if (!is_dir($contentDir)) {
                continue;
            }

            $files = $this->findMarkdownFiles($contentDir);

            foreach ($files as $filePath) {
                $result = $this->processFilePrefix($filePath, $typeName, $parser, $action);
                if ($result === true) {
                    $renamed++;
                } elseif ($result === false) {
                    $skipped++;
                }
            }
        }

        if ($renamed > 0) {
            $this->success("Renamed {$renamed} file(s)");
            $this->writeln('');
            $this->nextStep('./ava rebuild', 'Update the content index');
        } else {
            $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . ' No files needed renaming.');
        }

        $this->writeln('');
        return 0;
    }

    /**
     * Process a single file for prefix add/remove.
     *
     * @return bool|null true=renamed, false=skipped, null=no action needed
     */
    private function processFilePrefix(string $filePath, string $type, \Ava\Content\Parser $parser, string $action): ?bool
    {
        try {
            $item = $parser->parseFile($filePath, $type);
        } catch (\Exception $e) {
            $this->warning("Skipping: " . basename($filePath) . " — " . $e->getMessage());
            return false;
        }

        $date = $item->date();
        if ($date === null) {
            // No date field, skip
            return null;
        }

        $dir = dirname($filePath);
        $filename = basename($filePath);
        $datePrefix = $date->format('Y-m-d') . '-';

        // Check current state
        $hasPrefix = preg_match('/^\d{4}-\d{2}-\d{2}-/', $filename);

        if ($action === 'add' && !$hasPrefix) {
            // Add date prefix
            $newFilename = $datePrefix . $filename;
            $newPath = $dir . '/' . $newFilename;

            if (file_exists($newPath)) {
                $this->warning("Cannot rename: {$newFilename} already exists");
                return false;
            }

            rename($filePath, $newPath);
            echo "    " . $this->color('→', self::GREEN) . " ";
            echo $this->color($filename, self::DIM) . " → " . $this->color($newFilename, self::PRIMARY) . "\n";
            return true;

        } elseif ($action === 'remove' && $hasPrefix) {
            // Remove date prefix
            $newFilename = preg_replace('/^\d{4}-\d{2}-\d{2}-/', '', $filename);
            $newPath = $dir . '/' . $newFilename;

            if (file_exists($newPath)) {
                $this->warning("Cannot rename: {$newFilename} already exists");
                return false;
            }

            rename($filePath, $newPath);
            echo "    " . $this->color('→', self::GREEN) . " ";
            echo $this->color($filename, self::DIM) . " → " . $this->color($newFilename, self::PRIMARY) . "\n";
            return true;
        }

        return null;
    }

    /**
     * Find all markdown files in a directory recursively.
     */
    private function findMarkdownFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    // =========================================================================
    // User commands
    // =========================================================================

    /**
     * Add a new user.
     */
    private function cmdUserAdd(array $args): int
    {
        if (count($args) < 2) {
            $this->writeln('');
            $this->error('Usage: ./ava user:add <email> <password> [name]');
            $this->writeln('');
            $this->writeln($this->color('  Example:', self::BOLD));
            $this->writeln('    ' . $this->color('./ava user:add admin@example.com mypassword "Admin"', self::PRIMARY));
            $this->writeln('');
            return 1;
        }

        $email = $args[0];
        $password = $args[1];
        $name = $args[2] ?? null;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');
            return 1;
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return 1;
        }

        $usersFile = $this->app->path('app/config/users.php');
        $users = $this->loadUsers($usersFile);

        if (isset($users[$email])) {
            $this->error("User already exists: {$email}");
            return 1;
        }

        $userName = $name ?? explode('@', $email)[0];
        $users[$email] = [
            'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'name' => $userName,
            'created' => date('Y-m-d'),
        ];

        $this->saveUsers($usersFile, $users);

        $this->writeln('');
        $this->box("User created successfully!", 'success');
        $this->writeln('');
        $this->keyValue('Email', $this->color($email, self::PRIMARY));
        $this->keyValue('Name', $userName);
        $this->writeln('');
        $this->nextStep('/admin', 'Login at your admin dashboard');
        $this->writeln('');

        return 0;
    }

    /**
     * Update a user's password.
     */
    private function cmdUserPassword(array $args): int
    {
        if (count($args) < 2) {
            $this->writeln('');
            $this->error('Usage: ./ava user:password <email> <new-password>');
            $this->writeln('');
            return 1;
        }

        $email = $args[0];
        $password = $args[1];

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return 1;
        }

        $usersFile = $this->app->path('app/config/users.php');
        $users = $this->loadUsers($usersFile);

        if (!isset($users[$email])) {
            $this->error("User not found: {$email}");
            return 1;
        }

        $users[$email]['password'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $users[$email]['updated'] = date('Y-m-d');

        $this->saveUsers($usersFile, $users);

        $this->success("Password updated for: {$email}");
        $this->writeln('');

        return 0;
    }

    /**
     * Remove a user.
     */
    private function cmdUserRemove(array $args): int
    {
        if (count($args) < 1) {
            $this->writeln('');
            $this->error('Usage: ./ava user:remove <email>');
            $this->writeln('');
            return 1;
        }

        $email = $args[0];

        $usersFile = $this->app->path('app/config/users.php');
        $users = $this->loadUsers($usersFile);

        if (!isset($users[$email])) {
            $this->error("User not found: {$email}");
            return 1;
        }

        unset($users[$email]);

        $this->saveUsers($usersFile, $users);

        $this->success("User removed: {$email}");
        $this->writeln('');

        return 0;
    }

    /**
     * List all users.
     */
    private function cmdUserList(array $args): int
    {
        $usersFile = $this->app->path('app/config/users.php');
        $users = $this->loadUsers($usersFile);

        $this->writeln('');

        if (empty($users)) {
            $this->box("No users configured yet", 'warning');
            $this->writeln('');
            $this->nextStep('./ava user:add <email> <password>', 'Create your first user');
            $this->writeln('');
            return 0;
        }

        echo $this->color('  ─── Users ', self::PRIMARY, self::BOLD);
        echo $this->color(str_repeat('─', 45), self::PRIMARY, self::BOLD) . "\n";
        $this->writeln('');

        foreach ($users as $email => $data) {
            $name = $data['name'] ?? '';
            $created = $data['created'] ?? '';

            echo "    " . $this->color('◆', self::PRIMARY) . " ";
            echo $this->color($email, self::PRIMARY, self::BOLD) . "\n";
            echo "      " . $this->color("Name: {$name}", self::DIM) . "\n";
            echo "      " . $this->color("Created: {$created}", self::DIM) . "\n";
            $this->writeln('');
        }

        return 0;
    }

    /**
     * Load users from file.
     */
    private function loadUsers(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }
        return require $file;
    }

    /**
     * Save users to file.
     */
    private function saveUsers(string $file, array $users): void
    {
        $content = "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Users Configuration\n *\n * Managed by CLI. Do not edit manually.\n */\n\nreturn " . var_export($users, true) . ";\n";
        file_put_contents($file, $content);
    }

    // =========================================================================
    // Update Commands
    // =========================================================================

    /**
     * Check for updates.
     */
    private function cmdUpdateCheck(array $args): int
    {
        $force = in_array('--force', $args) || in_array('-f', $args);

        $this->writeln('');
        echo $this->color('  🔍 Checking for updates...', self::DIM) . "\n";
        $this->writeln('');

        $updater = new \Ava\Updater($this->app);
        $result = $updater->check($force);

        $this->keyValue('Current', $this->color($result['current'], self::BOLD));
        $this->keyValue('Latest', $this->color($result['latest'], self::BOLD));

        if ($result['error']) {
            $this->error($result['error']);
            return 1;
        }

        if ($result['available']) {
            $this->writeln('');
            $this->box("Update available!", 'success');
            $this->writeln('');

            if ($result['release']) {
                if ($result['release']['name']) {
                    $this->keyValue('Release', $result['release']['name']);
                }
                if ($result['release']['published_at']) {
                    $date = date('Y-m-d', strtotime($result['release']['published_at']));
                    $this->keyValue('Published', $date);
                }
                if ($result['release']['body']) {
                    $this->writeln('');
                    echo $this->color('  ─── Changelog ', self::PRIMARY, self::BOLD);
                    echo $this->color(str_repeat('─', 42), self::PRIMARY, self::BOLD) . "\n";
                    $this->writeln('');
                    // Show first 15 lines of changelog
                    $lines = explode("\n", $result['release']['body']);
                    foreach (array_slice($lines, 0, 15) as $line) {
                        $this->writeln('  ' . $line);
                    }
                    if (count($lines) > 15) {
                        $this->writeln('  ' . $this->color('... (truncated)', self::DIM));
                    }
                }
            }

            $this->writeln('');
            $this->nextStep('./ava update:apply', 'Download and apply the update');

        } else {
            $this->writeln('');
            $this->box("You're up to date!", 'success');
        }

        if (isset($result['from_cache']) && $result['from_cache']) {
            $this->writeln('');
            $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . ' ' . $this->color('Cached result — use --force to refresh', self::DIM));
        }

        $this->writeln('');
        return 0;
    }

    /**
     * Apply update.
     */
    private function cmdUpdateApply(array $args): int
    {
        $this->writeln('');

        // Check for available update first
        $updater = new \Ava\Updater($this->app);
        $check = $updater->check(true);

        if ($check['error']) {
            $this->error('Could not check for updates: ' . $check['error']);
            return 1;
        }

        if (!$check['available']) {
            $this->box("Already running the latest version ({$check['current']})", 'success');
            $this->writeln('');
            return 0;
        }

        echo $this->color('  ─── Update Available ', self::PRIMARY, self::BOLD);
        echo $this->color(str_repeat('─', 35), self::PRIMARY, self::BOLD) . "\n";
        $this->writeln('');
        $this->keyValue('From', $check['current']);
        $this->keyValue('To', $this->color($check['latest'], self::GREEN, self::BOLD));
        $this->writeln('');

        // Confirm unless --yes flag
        if (!in_array('--yes', $args) && !in_array('-y', $args)) {
            $this->writeln($this->color('  Will be updated:', self::BOLD));
            echo "    " . $this->color('▸', self::GREEN) . " Core files (core/, bin/, bootstrap.php)\n";
            echo "    " . $this->color('▸', self::GREEN) . " Default theme (themes/default/)\n";
            echo "    " . $this->color('▸', self::GREEN) . " Bundled plugins (sitemap, feed, redirects)\n";
            echo "    " . $this->color('▸', self::GREEN) . " Documentation (docs/)\n";
            $this->writeln('');
            $this->writeln($this->color('  Will NOT be modified:', self::BOLD));
            echo "    " . $this->color('•', self::DIM) . " Your content (content/)\n";
            echo "    " . $this->color('•', self::DIM) . " Your configuration (app/)\n";
            echo "    " . $this->color('•', self::DIM) . " Custom themes and plugins\n";
            echo "    " . $this->color('•', self::DIM) . " Storage and cache files\n";
            $this->writeln('');

            // Backup check
            $this->writeln($this->color('  ⚠️  Have you backed up your site and have a secure copy saved off-site?', self::YELLOW, self::BOLD));
            echo '  [' . $this->color('y', self::GREEN) . '/N]: ';
            $backupAnswer = trim(fgets(STDIN));
            if (strtolower($backupAnswer) !== 'y') {
                $this->writeln('');
                $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . ' Please backup your site before updating.');
                $this->writeln('');
                return 0;
            }
            $this->writeln('');

            echo '  Continue with update? [' . $this->color('y', self::GREEN) . '/N]: ';
            $answer = trim(fgets(STDIN));
            if (strtolower($answer) !== 'y') {
                $this->writeln('');
                $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . ' Update cancelled.');
                $this->writeln('');
                return 0;
            }
            $this->writeln('');
        }

        $result = $this->withSpinner('Downloading update', function () use ($updater) {
            return $updater->apply();
        });

        if (!$result['success']) {
            $this->error($result['message']);
            return 1;
        }

        $this->success($result['message']);

        if (!empty($result['new_plugins'])) {
            $this->writeln('');
            echo $this->color('  New bundled plugins available:', self::BOLD) . "\n";
            foreach ($result['new_plugins'] as $plugin) {
                echo "    " . $this->color('•', self::PRIMARY) . " {$plugin}\n";
            }
            $this->writeln('');
            $this->tip('Add them to your plugins array in app/config/ava.php to activate');
        }

        $this->writeln('');
        $this->withSpinner('Rebuilding content index', function () {
            $this->app->indexer()->rebuild();
            return true;
        });
        $this->success('Content index rebuilt.');

        $this->writeln('');
        return 0;
    }

    // =========================================================================
    // Test Suite
    // =========================================================================

    /**
     * Run the automated test suite.
     */
    private function cmdTest(array $args): int
    {
        // Parse arguments
        $verbose = in_array('-v', $args, true) || in_array('--verbose', $args, true);
        $quiet = in_array('-q', $args, true) || in_array('--quiet', $args, true);
        $filter = null;

        // Get filter (first non-flag argument)
        foreach ($args as $arg) {
            if (!str_starts_with($arg, '-')) {
                $filter = $arg;
                break;
            }
        }

        $testsPath = $this->app->path('tests');

        if (!is_dir($testsPath)) {
            $this->error("Tests directory not found: {$testsPath}");
            $this->tip('Create the tests/ directory and add test files ending in Test.php');
            return 1;
        }

        // Load test framework classes
        require_once $this->app->path('core/Testing/AssertionFailedException.php');
        require_once $this->app->path('core/Testing/SkippedException.php');
        require_once $this->app->path('core/Testing/TestCase.php');
        require_once $this->app->path('core/Testing/TestRunner.php');

        $runner = new \Ava\Testing\TestRunner($this->app, $verbose, $filter, $quiet);
        return $runner->run($testsPath);
    }

    // =========================================================================
    // Stress Testing
    // =========================================================================

    /**
     * Lorem ipsum words for generating dummy content.
     */
    private const LOREM_WORDS = [
        'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit',
        'sed', 'do', 'eiusmod', 'tempor', 'incididunt', 'ut', 'labore', 'et', 'dolore',
        'magna', 'aliqua', 'enim', 'ad', 'minim', 'veniam', 'quis', 'nostrud',
        'exercitation', 'ullamco', 'laboris', 'nisi', 'aliquip', 'ex', 'ea', 'commodo',
        'consequat', 'duis', 'aute', 'irure', 'in', 'reprehenderit', 'voluptate',
        'velit', 'esse', 'cillum', 'fugiat', 'nulla', 'pariatur', 'excepteur', 'sint',
        'occaecat', 'cupidatat', 'non', 'proident', 'sunt', 'culpa', 'qui', 'officia',
        'deserunt', 'mollit', 'anim', 'id', 'est', 'laborum', 'perspiciatis', 'unde',
        'omnis', 'iste', 'natus', 'error', 'voluptatem', 'accusantium', 'doloremque',
        'laudantium', 'totam', 'rem', 'aperiam', 'eaque', 'ipsa', 'quae', 'ab', 'illo',
        'inventore', 'veritatis', 'quasi', 'architecto', 'beatae', 'vitae', 'dicta',
    ];

    /**
     * Generate dummy content for stress testing.
     */
    private function cmdStressGenerate(array $args): int
    {
        if (count($args) < 2) {
            $this->writeln('');
            $this->error('Usage: ./ava stress:generate <type> <count>');
            $this->writeln('');
            $this->writeln($this->color('  Examples:', self::BOLD));
            $this->writeln('    ' . $this->color('./ava stress:generate post 100', self::PRIMARY) . $this->color('    # Generate 100 posts', self::DIM));
            $this->writeln('    ' . $this->color('./ava stress:generate post 1000', self::PRIMARY) . $this->color('   # Generate 1000 posts', self::DIM));
            $this->writeln('');
            $this->showAvailableTypes();
            $this->writeln('');
            return 1;
        }

        $type = $args[0];
        $count = (int) $args[1];

        if ($count < 1 || $count > 100000) {
            $this->error('Count must be between 1 and 100,000');
            return 1;
        }

        // Verify type exists
        $contentTypes = require $this->app->path('app/config/content_types.php');
        if (!isset($contentTypes[$type])) {
            $this->error("Unknown content type: {$type}");
            $this->showAvailableTypes();
            return 1;
        }

        $typeConfig = $contentTypes[$type];
        $contentDir = $typeConfig['content_dir'] ?? $type;
        $basePath = $this->app->configPath('content') . '/' . $contentDir;

        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        // Get taxonomies for this type
        $taxonomies = $typeConfig['taxonomies'] ?? [];
        $taxonomyTerms = $this->loadTaxonomyTerms($taxonomies);

        // Determine if content is dated
        $isDated = ($typeConfig['sorting'] ?? 'manual') === 'date_desc';

        $this->writeln('');
        echo $this->color("  🧪 Generating {$count} dummy {$type}(s)...", self::PRIMARY) . "\n";
        $this->writeln('');

        $start = microtime(true);
        $created = 0;

        for ($i = 1; $i <= $count; $i++) {
            $result = $this->generateDummyContent($type, $basePath, $isDated, $taxonomies, $taxonomyTerms, $i);
            if ($result) {
                $created++;
                // Progress indicator
                $this->progress($i, $count, "Creating {$type}s...");
            }
        }

        $elapsed = round((microtime(true) - $start) * 1000);

        $this->success("Generated {$created} files in {$elapsed}ms");
        $this->writeln('');

        $this->withSpinner('Rebuilding content index', function () {
            $this->app->indexer()->rebuild();
            return true;
        });

        $this->writeln('');
        $this->nextStep("./ava stress:clean {$type}", 'Remove generated content when done');
        $this->writeln('');

        return 0;
    }

    /**
     * Clean up generated dummy content.
     */
    private function cmdStressClean(array $args): int
    {
        if (count($args) < 1) {
            $this->writeln('');
            $this->error('Usage: ./ava stress:clean <type>');
            $this->writeln('');
            $this->writeln('  This will remove all files with the ' . $this->color('_dummy-', self::YELLOW) . ' prefix.');
            $this->writeln('');
            return 1;
        }

        $type = $args[0];

        // Verify type exists
        $contentTypes = require $this->app->path('app/config/content_types.php');
        if (!isset($contentTypes[$type])) {
            $this->error("Unknown content type: {$type}");
            $this->showAvailableTypes();
            return 1;
        }

        $typeConfig = $contentTypes[$type];
        $contentDir = $typeConfig['content_dir'] ?? $type;
        $basePath = $this->app->configPath('content') . '/' . $contentDir;

        if (!is_dir($basePath)) {
            $this->writeln('');
            $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . ' No content directory found.');
            $this->writeln('');
            return 0;
        }

        // Find all dummy files
        $pattern = $basePath . '/_dummy-*.md';
        $files = glob($pattern);

        if (empty($files)) {
            $this->writeln('');
            $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . ' No dummy content files found.');
            $this->writeln('');
            return 0;
        }

        $count = count($files);
        $this->writeln('');
        $this->writeln('  Found ' . $this->color((string) $count, self::YELLOW, self::BOLD) . ' dummy content file(s).');
        $this->writeln('');
        echo '  Delete all? [' . $this->color('y', self::RED) . '/N]: ';
        $answer = trim(fgets(STDIN));

        if (strtolower($answer) !== 'y') {
            $this->writeln('');
            $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . ' Cancelled.');
            $this->writeln('');
            return 0;
        }

        $this->writeln('');
        $deleted = 0;
        foreach ($files as $i => $file) {
            if (unlink($file)) {
                $deleted++;
            }
            $this->progress($i + 1, $count, 'Deleting files...');
        }

        $this->success("Deleted {$deleted} file(s)");
        $this->writeln('');

        $this->withSpinner('Rebuilding content index', function () {
            $this->app->indexer()->rebuild();
            return true;
        });
        $this->success('Done!');
        $this->writeln('');

        return 0;
    }

    /**
     * Benchmark content index performance.
     *
     * Tests current backend or compares all available backends.
     * Use --compare to test all backends side-by-side.
     */
    private function cmdBenchmark(array $args): int
    {
        $this->showBanner();
        $this->writeln('');
        echo $this->color('  v' . AVA_VERSION, self::DIM) . "\n";
        $this->sectionHeader('Performance Benchmark');

        // Parse arguments
        $iterations = 5;
        $compareMode = false;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--iterations=')) {
                $iterations = max(1, (int) substr($arg, 13));
            }
            if ($arg === '--compare') {
                $compareMode = true;
            }
            if ($arg === '--help' || $arg === '-h') {
                $this->writeln('  ' . $this->color('Usage:', self::BOLD) . ' ./ava benchmark [options]');
                $this->writeln('');
                $this->writeln('  ' . $this->color('Options:', self::BOLD));
                $this->writeln('    --compare         Compare all available backends');
                $this->writeln('    --iterations=N    Number of test iterations (default: 5)');
                $this->writeln('');
                $this->writeln('  ' . $this->color('Examples:', self::BOLD));
                $this->writeln('    ./ava benchmark              Test current backend');
                $this->writeln('    ./ava benchmark --compare    Compare array vs sqlite');
                $this->writeln('');
                return 0;
            }
        }

        // Get current configuration
        $repository = $this->app->repository();
        $currentBackend = $this->app->config('content_index.backend', 'array');
        $useIgbinary = $this->app->config('content_index.use_igbinary', true);
        $igbinaryAvailable = extension_loaded('igbinary');
        $igbinaryActive = $useIgbinary && $igbinaryAvailable && $currentBackend === 'array';

        // Get content counts
        $totalItems = 0;
        $itemsByType = [];
        foreach ($repository->types() as $type) {
            $count = $repository->count($type);
            $totalItems += $count;
            $itemsByType[$type] = $count;
        }

        if ($totalItems === 0) {
            $this->error('No content found. Generate test content with:');
            $this->writeln('');
            $this->writeln('    ' . $this->color('./ava stress:generate post 1000', self::PRIMARY));
            $this->writeln('');
            return 1;
        }

        // Display configuration
        $this->keyValue('Content', $this->color(number_format($totalItems), self::BOLD) . ' items');
        foreach ($itemsByType as $type => $count) {
            $this->writeln('              ' . $type . ': ' . $count);
        }
        $this->writeln('');

        $backendDisplay = $currentBackend;
        if ($currentBackend === 'array') {
            if ($igbinaryActive) {
                $backendDisplay .= ' + igbinary';
            } else {
                $backendDisplay .= ' + serialize';
            }
        }
        $this->keyValue('Backend', $this->color($backendDisplay, self::PRIMARY, self::BOLD));

        if ($currentBackend === 'array') {
            $igStatus = $igbinaryAvailable
                ? ($useIgbinary ? $this->color('enabled', self::GREEN) : $this->color('disabled in config', self::YELLOW))
                : $this->color('not installed', self::DIM);
            $this->keyValue('igbinary', $igStatus);
        }

        $this->keyValue('Iterations', (string) $iterations);
        $this->writeln('');

        // Determine backends to test
        $backends = [];
        if ($compareMode) {
            $backends[] = ['name' => 'array', 'igbinary' => true, 'label' => 'Array+igbinary'];
            $backends[] = ['name' => 'array', 'igbinary' => false, 'label' => 'Array+serialize'];
            if (extension_loaded('pdo_sqlite')) {
                $backends[] = ['name' => 'sqlite', 'igbinary' => null, 'label' => 'SQLite'];
            }
        } else {
            $backends[] = [
                'name' => $currentBackend,
                'igbinary' => $useIgbinary,
                'label' => $backendDisplay,
            ];
        }

        // Check if we need to rebuild for comparison
        if ($compareMode) {
            $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . ' Comparison mode requires rebuilding indexes for each backend.');
            $this->writeln('    This may take a moment for large sites.');
            $this->writeln('');
        }

        $results = [];

        foreach ($backends as $backendConfig) {
            $backendName = $backendConfig['name'];
            $igbinaryEnabled = $backendConfig['igbinary'];
            $label = $backendConfig['label'];

            $this->writeln('  Testing ' . $this->color($label, self::BOLD) . '...');

            // Measure index build time
            $buildTime = 0;
            if ($compareMode) {
                // Temporarily override config for rebuild
                $buildStart = hrtime(true);
                if ($backendName === 'array') {
                    // We need to rebuild with specific igbinary setting
                    // This is a bit hacky but necessary for fair comparison
                    $this->rebuildWithConfig($backendName, $igbinaryEnabled);
                } elseif ($backendName === 'sqlite') {
                    $this->rebuildWithConfig($backendName, null);
                }
                $buildEnd = hrtime(true);
                $buildTime = ($buildEnd - $buildStart) / 1_000_000;
            } else {
                // For non-compare mode, measure rebuild with current config
                $buildStart = hrtime(true);
                $this->rebuildWithConfig($currentBackend, $useIgbinary);
                $buildEnd = hrtime(true);
                $buildTime = ($buildEnd - $buildStart) / 1_000_000;
            }
            $results[$label]['_build_time'] = $buildTime;

            // Force backend selection
            $repository->setBackendOverride($backendName);
            $repository->clearCache();

            // Get a sample slug for testing
            $sampleSlug = 'test';
            try {
                $allItems = $repository->allRaw('post');
                if (!empty($allItems)) {
                    $firstItem = reset($allItems);
                    $sampleSlug = $firstItem['slug'] ?? 'test';
                }
            } catch (\Throwable $e) {
                // Ignore errors if content not found
            }

            $tests = [
                'Count' => fn() => $repository->count('post'),
                'Get by slug' => fn() => $repository->getFromIndex('post', $sampleSlug),
                'Recent (page 1)' => fn() => $repository->getRecentItems('post', 1, 10),
                'Archive (page 50)' => fn() => (new \Ava\Content\Query($this->app))->type('post')->orderBy('date', 'desc')->perPage(10)->page(50)->get(),
                'Sort by date' => fn() => (new \Ava\Content\Query($this->app))->type('post')->orderBy('date', 'asc')->perPage(10)->get(),
                'Sort by title' => fn() => (new \Ava\Content\Query($this->app))->type('post')->orderBy('title', 'asc')->perPage(10)->get(),
                'Search' => fn() => (new \Ava\Content\Query($this->app))->type('post')->search('lorem')->perPage(10)->get(),
            ];

            foreach ($tests as $testName => $testFn) {
                $times = [];

                // Warm up
                try {
                    $testFn();
                } catch (\Throwable $e) {
                    // Skip if test fails (e.g., no content)
                    continue;
                }
                $repository->clearCache();

                for ($i = 0; $i < $iterations; $i++) {
                    $start = hrtime(true);
                    $testFn();
                    $end = hrtime(true);
                    $times[] = ($end - $start) / 1_000_000;
                    $repository->clearCache();
                }

                $avg = array_sum($times) / count($times);
                $results[$label][$testName] = $avg;
            }

            // Memory test
            $repository->clearCache();
            $memBefore = memory_get_usage(false);
            $repository->allRaw('post');
            $memAfter = memory_get_usage(false);
            $results[$label]['_memory'] = max(0, $memAfter - $memBefore);

            // Cache size
            $cachePath = $this->app->configPath('storage') . '/cache';
            if ($backendName === 'sqlite') {
                $sqlitePath = $cachePath . '/content_index.sqlite';
                $results[$label]['_cache_size'] = file_exists($sqlitePath) ? filesize($sqlitePath) : 0;
            } else {
                $size = 0;
                foreach (['content_index.bin', 'slug_lookup.bin', 'recent_cache.bin'] as $file) {
                    $path = $cachePath . '/' . $file;
                    if (file_exists($path)) {
                        $size += filesize($path);
                    }
                }
                $results[$label]['_cache_size'] = $size;
            }
        }

        // Reset
        $repository->setBackendOverride(null);
        $repository->clearCache();

        // Restore original config if we were comparing
        if ($compareMode) {
            $this->rebuildWithConfig($currentBackend, $useIgbinary);
        }

        // Display results
        $this->writeln('');
        echo $this->color('  ─── Results ', self::PRIMARY, self::BOLD);
        echo $this->color(str_repeat('─', 45), self::PRIMARY, self::BOLD) . "\n";
        $this->writeln('');

        $backendLabels = array_keys($results);
        $testNames = ['Count', 'Get by slug', 'Recent (page 1)', 'Archive (page 50)', 'Sort by date', 'Sort by title', 'Search'];

        // Header
        $header = str_pad('Test', 20);
        foreach ($backendLabels as $label) {
            $header .= str_pad($label, 18);
        }
        $this->writeln('  ' . $this->color($header, self::BOLD));
        $this->writeln('  ' . $this->color(str_repeat('─', 20 + 18 * count($backendLabels)), self::DIM));

        // Rows
        foreach ($testNames as $testName) {
            $row = str_pad($testName, 20);
            $values = [];

            foreach ($backendLabels as $label) {
                $avg = $results[$label][$testName] ?? 0;
                $values[$label] = $avg;
                $formatted = $avg < 1 ? sprintf('%.2fms', $avg) : sprintf('%.1fms', $avg);
                $row .= str_pad($formatted, 18);
            }

            $this->writeln('  ' . $row);
        }

        // Memory and cache size
        $this->writeln('  ' . $this->color(str_repeat('─', 20 + 18 * count($backendLabels)), self::DIM));

        // Build index time
        $buildRow = str_pad('Build index', 20);
        foreach ($backendLabels as $label) {
            $buildTime = $results[$label]['_build_time'] ?? 0;
            $formatted = $buildTime >= 1000 ? sprintf('%.1fs', $buildTime / 1000) : sprintf('%.0fms', $buildTime);
            $buildRow .= str_pad($formatted, 18);
        }
        $this->writeln('  ' . $buildRow);

        $memRow = str_pad('Memory', 20);
        foreach ($backendLabels as $label) {
            $memRow .= str_pad($this->formatBytes($results[$label]['_memory']), 18);
        }
        $this->writeln('  ' . $memRow);

        $cacheRow = str_pad('Cache size', 20);
        foreach ($backendLabels as $label) {
            $cacheRow .= str_pad($this->formatBytes($results[$label]['_cache_size']), 18);
        }
        $this->writeln('  ' . $cacheRow);

        // Webpage rendering benchmarks (backend-independent)
        $this->writeln('');
        echo $this->color('  ─── Webpage Rendering ', self::PRIMARY, self::BOLD);
        echo $this->color(str_repeat('─', 36), self::PRIMARY, self::BOLD) . "\n";
        $this->writeln('');

        $webpageResults = $this->benchmarkWebpageRendering($iterations);

        $this->writeln('  ' . $this->color(str_pad('Operation', 30) . str_pad('Time', 15), self::BOLD));
        $this->writeln('  ' . $this->color(str_repeat('─', 45), self::DIM));

        foreach ($webpageResults as $testName => $avgTime) {
            $formatted = $avgTime < 1 ? sprintf('%.2fms', $avgTime) : sprintf('%.1fms', $avgTime);
            $this->writeln('  ' . str_pad($testName, 30) . str_pad($formatted, 15));
        }

        $this->writeln('');

        if (!$compareMode) {
            $this->writeln('  ' . $this->color('💡 Tip:', self::YELLOW) . ' Run with ' . $this->color('--compare', self::PRIMARY) . ' to test all backends.');
        }

        $this->writeln('  ' . $this->color('📚 Docs:', self::BLUE) . ' https://ava.addy.zone/#/performance');
        $this->writeln('');

        return 0;
    }

    /**
     * Benchmark webpage rendering operations.
     */
    private function benchmarkWebpageRendering(int $iterations): array
    {
        $results = [];
        $repository = $this->app->repository();
        $webpageCache = $this->app->webpageCache();
        $renderer = $this->app->renderer();

        // Get a sample post for testing
        $sampleSlug = null;
        try {
            $recentResult = $repository->getRecentItems('post', 1, 1);
            if (!empty($recentResult['items'])) {
                $sampleSlug = $recentResult['items'][0]['slug'] ?? null;
            }
        } catch (\Throwable $e) {
            // No posts available
        }

        if ($sampleSlug === null) {
            return ['No posts available for test' => 0];
        }

        // Clear any existing webpage cache
        $webpageCache->clear();

        // Test 1: Render uncached (full pipeline)
        $times = [];
        $output = '';
        for ($i = 0; $i < $iterations; $i++) {
            // Load fresh item each time
            $item = $repository->get('post', $sampleSlug);
            if ($item === null) {
                continue;
            }

            $start = hrtime(true);
            // Render markdown content
            $html = $renderer->renderMarkdown($item->rawContent());
            // Render full template
            $output = $renderer->render('single', [
                'item' => $item->withHtml($html),
                'content_type' => 'post',
            ]);
            $end = hrtime(true);
            $times[] = ($end - $start) / 1_000_000;
        }
        if (!empty($times)) {
            $results['Render post (uncached)'] = array_sum($times) / count($times);
        }

        // Test 2: Write to webpage cache
        $cachePath = $this->app->configPath('storage') . '/cache/pages';
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $cacheFile = $cachePath . '/benchmark_test_' . $i . '.html';
            $testHtml = $output ?? '<html><body>Test content</body></html>';

            $start = hrtime(true);
            file_put_contents($cacheFile, $testHtml, LOCK_EX);
            $end = hrtime(true);
            $times[] = ($end - $start) / 1_000_000;

            // Clean up
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        }
        if (!empty($times)) {
            $results['Cache write'] = array_sum($times) / count($times);
        }

        // Test 3: Read from webpage cache
        // First, write a cache file
        $cacheFile = $cachePath . '/benchmark_test.html';
        $testHtml = $output ?? '<html><body>Test content</body></html>';
        file_put_contents($cacheFile, $testHtml, LOCK_EX);

        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            // Clear filesystem cache
            clearstatcache(true, $cacheFile);

            $start = hrtime(true);
            $content = file_get_contents($cacheFile);
            $end = hrtime(true);
            $times[] = ($end - $start) / 1_000_000;
        }
        if (!empty($times)) {
            $results['Cache read (HIT)'] = array_sum($times) / count($times);
        }

        // Clean up
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        return $results;
    }

    /**
     * Rebuild index with specific backend configuration.
     */
    private function rebuildWithConfig(string $backend, ?bool $useIgbinary): void
    {
        // This is a helper for benchmark comparison mode
        // We temporarily override the config for the indexer
        $configPath = $this->app->configPath('storage') . '/cache';

        // Create a temporary indexer with overridden settings
        $indexer = new \Ava\Content\Indexer($this->app, $backend, $useIgbinary);
        $indexer->rebuild();
    }

    /**
     * Clear webpage cache.
     */
    private function cmdCacheClear(array $args): int
    {
        $webpageCache = $this->app->webpageCache();

        $this->writeln('');

        if (!$webpageCache->isEnabled()) {
            $this->box("Webpage cache is not enabled", 'warning');
            $this->writeln('');
            $this->tip("Enable it in app/config/ava.php with 'webpage_cache' => ['enabled' => true]");
            $this->writeln('');
            return 0;
        }

        $stats = $webpageCache->stats();
        if ($stats['count'] === 0) {
            $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . ' Webpage cache is empty.');
            $this->writeln('');
            return 0;
        }

        $this->writeln('  Found ' . $this->color((string) $stats['count'], self::PRIMARY, self::BOLD) . ' cached webpage(s).');
        $this->writeln('');

        // Check for pattern argument
        if (isset($args[0])) {
            $pattern = $args[0];
            $count = $webpageCache->clearPattern($pattern);
            $this->success("Cleared {$count} webpage(s) matching: {$pattern}");
        } else {
            echo '  Clear all cached webpages? [' . $this->color('y', self::RED) . '/N]: ';
            $answer = trim(fgets(STDIN));

            if (strtolower($answer) !== 'y') {
                $this->writeln('');
                $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . ' Cancelled.');
                $this->writeln('');
                return 0;
            }

            $count = $webpageCache->clear();
            $this->success("Cleared {$count} cached webpage(s)");
        }

        $this->writeln('');
        return 0;
    }

    /**
     * Show webpage cache statistics.
     */
    private function cmdCacheStats(array $args): int
    {
        $webpageCache = $this->app->webpageCache();
        $stats = $webpageCache->stats();

        $this->writeln('');
        echo $this->color('  ─── Webpage Cache ', self::PRIMARY, self::BOLD);
        echo $this->color(str_repeat('─', 38), self::PRIMARY, self::BOLD) . "\n";
        $this->writeln('');

        $status = $stats['enabled']
            ? $this->color('● Enabled', self::GREEN, self::BOLD)
            : $this->color('○ Disabled', self::DIM);
        $this->keyValue('Status', $status);

        if (!$stats['enabled']) {
            $this->writeln('');
            $this->tip("Enable webpage caching in app/config/ava.php: 'webpage_cache' => ['enabled' => true]");
            $this->writeln('');
            return 0;
        }

        $this->keyValue('TTL', $stats['ttl'] ? $stats['ttl'] . ' seconds' : 'Forever (until cleared)');
        $this->writeln('');
        $this->keyValue('Cached', $this->color((string) $stats['count'], self::PRIMARY, self::BOLD) . ' webpages');
        $this->keyValue('Size', $this->formatBytes($stats['size']));

        if ($stats['oldest']) {
            $this->keyValue('Oldest', $stats['oldest']);
            $this->keyValue('Newest', $stats['newest']);
        }

        $this->writeln('');

        return 0;
    }

    /**
     * Show log file statistics.
     */
    private function cmdLogsStats(array $args): int
    {
        $logsPath = $this->app->configPath('storage') . '/logs';

        $this->writeln('');
        echo $this->color('  ─── Logs ', self::PRIMARY, self::BOLD);
        echo $this->color(str_repeat('─', 47), self::PRIMARY, self::BOLD) . "\n";
        $this->writeln('');

        if (!is_dir($logsPath)) {
            $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . ' No logs directory found.');
            $this->writeln('');
            return 0;
        }

        $logFiles = glob($logsPath . '/*.log*') ?: [];

        if (empty($logFiles)) {
            $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . ' No log files found.');
            $this->writeln('');
            return 0;
        }

        // Group by base name (e.g., indexer.log, indexer.log.1, etc.)
        $grouped = [];
        foreach ($logFiles as $file) {
            $basename = basename($file);
            // Extract the base log name (e.g., indexer.log from indexer.log.1)
            if (preg_match('/^(.+\.log)(\.\d+)?$/', $basename, $m)) {
                $base = $m[1];
                $grouped[$base][] = $file;
            }
        }

        $totalSize = 0;
        $totalFiles = 0;

        foreach ($grouped as $baseName => $files) {
            $size = 0;
            $lines = 0;
            $oldest = null;
            $newest = null;

            foreach ($files as $file) {
                $size += filesize($file);
                $mtime = filemtime($file);
                if ($oldest === null || $mtime < $oldest) {
                    $oldest = $mtime;
                }
                if ($newest === null || $mtime > $newest) {
                    $newest = $mtime;
                }
            }

            // Count lines in main log file only
            $mainLog = $logsPath . '/' . $baseName;
            if (file_exists($mainLog)) {
                $lines = $this->countLines($mainLog);
            }

            $totalSize += $size;
            $totalFiles += count($files);

            $this->keyValue($baseName, $this->formatBytes($size) . 
                (count($files) > 1 ? $this->color(' (' . count($files) . ' files)', self::DIM) : '') .
                ($lines > 0 ? $this->color(" · {$lines} lines", self::DIM) : ''));
        }

        $this->writeln('');
        $this->keyValue('Total', $this->color($this->formatBytes($totalSize), self::PRIMARY, self::BOLD) . 
            $this->color(" ({$totalFiles} files)", self::DIM));

        // Show config
        $maxSize = $this->app->config('logs.max_size', 10 * 1024 * 1024);
        $maxFiles = $this->app->config('logs.max_files', 3);
        $this->writeln('');
        $this->keyValue('Max Size', $this->formatBytes($maxSize) . $this->color(' per log', self::DIM));
        $this->keyValue('Max Files', $maxFiles . $this->color(' rotated copies', self::DIM));

        $this->writeln('');
        return 0;
    }

    /**
     * Clear log files.
     */
    private function cmdLogsClear(array $args): int
    {
        $logsPath = $this->app->configPath('storage') . '/logs';

        $this->writeln('');

        if (!is_dir($logsPath)) {
            $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . ' No logs directory found.');
            $this->writeln('');
            return 0;
        }

        // Check for specific log name argument
        $logName = $args[0] ?? null;

        if ($logName) {
            // Clear specific log and its rotated copies
            $pattern = $logsPath . '/' . $logName . '*';
            $files = glob($pattern) ?: [];

            if (empty($files)) {
                $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . " No logs matching: {$logName}");
                $this->writeln('');
                return 0;
            }

            $count = 0;
            $size = 0;
            foreach ($files as $file) {
                $size += filesize($file);
                unlink($file);
                $count++;
            }

            $this->success("Cleared {$count} log file(s) ({$this->formatBytes($size)})");
        } else {
            // List all logs and ask for confirmation
            $logFiles = glob($logsPath . '/*.log*') ?: [];

            if (empty($logFiles)) {
                $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . ' No log files found.');
                $this->writeln('');
                return 0;
            }

            $totalSize = array_sum(array_map('filesize', $logFiles));
            $this->writeln('  Found ' . $this->color((string) count($logFiles), self::PRIMARY, self::BOLD) . 
                ' log file(s) (' . $this->formatBytes($totalSize) . ').');
            $this->writeln('');

            echo '  Clear all log files? [' . $this->color('y', self::RED) . '/N]: ';
            $answer = trim(fgets(STDIN));

            if (strtolower($answer) !== 'y') {
                $this->writeln('');
                $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . ' Cancelled.');
                $this->writeln('');
                return 0;
            }

            $count = 0;
            foreach ($logFiles as $file) {
                unlink($file);
                $count++;
            }

            $this->success("Cleared {$count} log file(s) ({$this->formatBytes($totalSize)})");
        }

        $this->writeln('');
        return 0;
    }

    /**
     * Show the last N lines of a log file.
     */
    private function cmdLogsTail(array $args): int
    {
        $logsPath = $this->app->configPath('storage') . '/logs';
        $logName = $args[0] ?? 'indexer.log';
        $lines = 20;

        // Check for -n flag
        foreach ($args as $i => $arg) {
            if ($arg === '-n' && isset($args[$i + 1])) {
                $lines = (int) $args[$i + 1];
                break;
            }
            if (preg_match('/^-n(\d+)$/', $arg, $m)) {
                $lines = (int) $m[1];
                break;
            }
        }

        $logFile = $logsPath . '/' . $logName;

        $this->writeln('');

        if (!file_exists($logFile)) {
            // Try with .log extension
            if (!str_ends_with($logName, '.log')) {
                $logFile = $logsPath . '/' . $logName . '.log';
            }

            if (!file_exists($logFile)) {
                $this->writeln('  ' . $this->color('ℹ', self::PRIMARY) . " Log file not found: {$logName}");
                $this->writeln('');

                // List available logs
                $available = glob($logsPath . '/*.log') ?: [];
                if (!empty($available)) {
                    $this->writeln($this->color('  Available logs:', self::BOLD));
                    foreach ($available as $file) {
                        $this->writeln('    ' . $this->color('▸ ', self::PRIMARY) . basename($file));
                    }
                    $this->writeln('');
                }
                return 1;
            }
        }

        echo $this->color('  ─── ', self::PRIMARY, self::BOLD);
        echo $this->color(basename($logFile), self::PRIMARY, self::BOLD);
        echo $this->color(' (last ' . $lines . ' lines) ', self::PRIMARY, self::BOLD);
        echo $this->color(str_repeat('─', max(1, 40 - strlen(basename($logFile)))), self::PRIMARY, self::BOLD) . "\n";
        $this->writeln('');

        // Read last N lines efficiently
        $content = $this->tailFile($logFile, $lines);
        
        if (empty(trim($content))) {
            $this->writeln('  ' . $this->color('(empty)', self::DIM));
        } else {
            // Indent and colorize timestamps
            foreach (explode("\n", rtrim($content)) as $line) {
                if (preg_match('/^\[([^\]]+)\]/', $line, $m)) {
                    $line = $this->color('[' . $m[1] . ']', self::DIM) . substr($line, strlen($m[0]));
                }
                $this->writeln('  ' . $line);
            }
        }

        $this->writeln('');
        return 0;
    }

    /**
     * Read the last N lines from a file.
     */
    private function tailFile(string $file, int $lines): string
    {
        $buffer = 4096;
        $output = '';
        $lineCount = 0;

        $f = fopen($file, 'rb');
        if (!$f) {
            return '';
        }

        fseek($f, 0, SEEK_END);
        $pos = ftell($f);

        while ($lineCount < $lines && $pos > 0) {
            $toRead = min($buffer, $pos);
            $pos -= $toRead;
            fseek($f, $pos);
            $chunk = fread($f, $toRead);
            $output = $chunk . $output;
            $lineCount = substr_count($output, "\n");
        }

        fclose($f);

        // Trim to exact line count
        $allLines = explode("\n", $output);
        $allLines = array_slice($allLines, -$lines - 1);

        return implode("\n", $allLines);
    }

    /**
     * Count lines in a file.
     */
    private function countLines(string $file): int
    {
        $count = 0;
        $f = fopen($file, 'rb');
        if ($f) {
            while (!feof($f)) {
                $count += substr_count(fread($f, 8192), "\n");
            }
            fclose($f);
        }
        return $count;
    }

    /**
     * Generate a single dummy content file.
     */
    private function generateDummyContent(
        string $type,
        string $basePath,
        bool $isDated,
        array $taxonomies,
        array $taxonomyTerms,
        int $index
    ): bool {
        // Generate unique slug with _dummy- prefix for easy cleanup
        $uniqueId = bin2hex(random_bytes(4));
        $slug = "_dummy-{$index}-{$uniqueId}";
        $filePath = $basePath . '/' . $slug . '.md';

        // Skip if somehow exists
        if (file_exists($filePath)) {
            return false;
        }

        // Generate random title
        $titleWords = array_map('ucfirst', $this->randomWords(rand(3, 8)));
        $title = implode(' ', $titleWords);

        // Build frontmatter
        $frontmatter = [
            'id' => Ulid::generate(),
            'title' => $title,
            'slug' => $slug,
            'status' => $this->randomStatus(),
        ];

        // Add date for dated content (random date within last 2 years)
        if ($isDated) {
            $daysAgo = rand(0, 730);
            $date = date('Y-m-d', strtotime("-{$daysAgo} days"));
            $frontmatter['date'] = $date;
        }

        // Add random excerpt
        $frontmatter['excerpt'] = ucfirst(implode(' ', $this->randomWords(rand(10, 25)))) . '.';

        // Add random taxonomy terms
        foreach ($taxonomies as $taxonomy) {
            if (isset($taxonomyTerms[$taxonomy]) && !empty($taxonomyTerms[$taxonomy])) {
                $terms = $taxonomyTerms[$taxonomy];
                // Pick 1-3 random terms
                $numTerms = min(count($terms), rand(1, 3));
                shuffle($terms);
                $selectedTerms = array_slice($terms, 0, $numTerms);
                $frontmatter[$taxonomy] = $selectedTerms;
            }
        }

        // Generate YAML frontmatter
        $yaml = "---\n";
        foreach ($frontmatter as $key => $value) {
            if (is_array($value)) {
                $yaml .= "{$key}:\n";
                foreach ($value as $item) {
                    $yaml .= "  - {$item}\n";
                }
            } else {
                // Escape values that might need quoting
                if (is_string($value) && (str_contains($value, ':') || str_contains($value, '#'))) {
                    $value = '"' . addslashes($value) . '"';
                }
                $yaml .= "{$key}: {$value}\n";
            }
        }
        $yaml .= "---\n\n";

        // Generate random content (3-10 paragraphs)
        $numParagraphs = rand(3, 10);
        $content = '';
        for ($p = 0; $p < $numParagraphs; $p++) {
            // 3-8 sentences per paragraph
            $numSentences = rand(3, 8);
            $sentences = [];
            for ($s = 0; $s < $numSentences; $s++) {
                $sentence = ucfirst(implode(' ', $this->randomWords(rand(8, 20)))) . '.';
                $sentences[] = $sentence;
            }
            $content .= implode(' ', $sentences) . "\n\n";
        }

        // Add a heading occasionally
        if (rand(0, 2) === 0) {
            $headingWords = array_map('ucfirst', $this->randomWords(rand(2, 5)));
            $content = "## " . implode(' ', $headingWords) . "\n\n" . $content;
        }

        // Write file
        return file_put_contents($filePath, $yaml . $content) !== false;
    }

    /**
     * Get random words from lorem ipsum.
     */
    private function randomWords(int $count): array
    {
        $words = [];
        for ($i = 0; $i < $count; $i++) {
            $words[] = self::LOREM_WORDS[array_rand(self::LOREM_WORDS)];
        }
        return $words;
    }

    /**
     * Get random status (weighted towards published).
     */
    private function randomStatus(): string
    {
        return rand(1, 10) <= 8 ? 'published' : 'draft';
    }

    /**
     * Load taxonomy terms from definition files.
     */
    private function loadTaxonomyTerms(array $taxonomies): array
    {
        $result = [];
        $taxPath = $this->app->configPath('content') . '/_taxonomies';

        foreach ($taxonomies as $taxonomy) {
            $result[$taxonomy] = [];
            $file = $taxPath . '/' . $taxonomy . '.yml';

            if (file_exists($file)) {
                $content = file_get_contents($file);
                // Simple YAML parsing for term slugs
                if (preg_match_all('/^\s*-?\s*slug:\s*(\S+)/m', $content, $matches)) {
                    $result[$taxonomy] = $matches[1];
                }
            }

            // If no terms found, add some defaults
            if (empty($result[$taxonomy])) {
                $result[$taxonomy] = ['general', 'misc', 'other'];
            }
        }

        return $result;
    }

    // =========================================================================
    // Output helpers
    // =========================================================================

    /**
     * Check if terminal supports colors.
     */
    private function supportsColors(): bool
    {
        // Check config first
        if ($this->app->config('cli.colors') === false) {
            return false;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            return getenv('ANSICON') !== false
                || getenv('ConEmuANSI') === 'ON'
                || getenv('TERM') === 'xterm';
        }

        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }

    /**
     * Apply color formatting if supported.
     */
    private function color(string $text, string ...$codes): string
    {
        if (!$this->supportsColors()) {
            return $text;
        }
        
        // Replace theme placeholder with actual theme color
        $codes = array_map(
            fn($code) => $code === self::PRIMARY ? $this->themeColor : $code,
            $codes
        );
        
        return implode('', $codes) . $text . self::RESET;
    }

    /**
     * Show the banner with optional version on the last line.
     */
    private function showBanner(bool $showVersion = false): void
    {
        $this->writeln('');
        
        // Display banner in primary brand color
        foreach (self::BANNER_LINES as $i => $line) {
            $coloredLine = $this->color($line, self::PRIMARY, self::BOLD);
            
            // Add version after the last line if requested
            if ($showVersion && $i === count(self::BANNER_LINES) - 1) {
                echo $coloredLine . '   ' . $this->color('v' . AVA_VERSION, self::DIM) . "\n";
            } else {
                echo $coloredLine . "\n";
            }
        }
    }

    /**
     * Show a section header.
     */
    private function sectionHeader(string $title): void
    {
        $this->writeln('');
        echo $this->color("  ─── ", self::DIM);
        echo $this->color($title, self::PRIMARY, self::BOLD);
        echo $this->color(" " . str_repeat('─', max(0, 50 - strlen($title))), self::DIM);
        $this->writeln('');
        $this->writeln('');
    }

    /**
     * Show a key-value pair.
     */
    private function keyValue(string $key, string $value, string $indent = '  '): void
    {
        $paddedKey = str_pad($key . ':', 12);
        echo $indent . $this->color($paddedKey, self::DIM);
        echo $value . "\n";
    }

    /**
     * Show a labeled item with icon.
     */
    private function labeledItem(string $label, string $value, string $icon = '•', string $iconColor = ''): void
    {
        $coloredIcon = $iconColor ? $this->color($icon, $iconColor) : $this->color($icon, self::DIM);
        $this->writeln("  {$coloredIcon} " . $this->color($label . ':', self::DIM) . " {$value}");
    }

    /**
     * Show a status badge.
     */
    private function badge(string $text, string $type = 'info'): string
    {
        return match ($type) {
            'success' => $this->color(" {$text} ", self::BLACK, self::BG_GREEN),
            'error' => $this->color(" {$text} ", self::WHITE, self::BG_RED),
            'warning' => $this->color(" {$text} ", self::BLACK, self::BG_YELLOW),
            'info' => $this->color(" {$text} ", self::WHITE, self::BG_BLUE),
            default => $text,
        };
    }

    /**
     * Show a simple spinner animation for an operation.
     */
    private function withSpinner(string $message, callable $operation): mixed
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

        if (!$this->supportsColors()) {
            echo "  {$message}...";
            $result = $operation();
            echo " done!\n";
            return $result;
        }

        // Show initial frame
        echo "  " . $this->color($frames[0], self::PRIMARY) . " {$message}...";

        // Run operation (synchronous, but gives visual feedback)
        $start = microtime(true);
        $result = $operation();
        $elapsed = round((microtime(true) - $start) * 1000);

        // Clear line and show completion
        echo "\r\033[K";
        echo "  " . $this->color('✓', self::GREEN) . " {$message} ";
        echo $this->color("({$elapsed}ms)", self::DIM) . "\n";

        return $result;
    }

    /**
     * Draw a simple box around text.
     */
    private function box(string $text, string $type = 'info'): void
    {
        $lines = explode("\n", $text);
        $maxLen = max(array_map('strlen', $lines));
        $padding = 2;
        $width = $maxLen + ($padding * 2);

        $borderColor = match ($type) {
            'success' => self::GREEN,
            'error' => self::RED,
            'warning' => self::YELLOW,
            default => self::PRIMARY,
        };

        // Top border
        echo $this->color('  ╭' . str_repeat('─', $width) . '╮', $borderColor) . "\n";

        // Content
        foreach ($lines as $line) {
            $paddedLine = str_pad($line, $maxLen);
            echo $this->color('  │', $borderColor);
            echo str_repeat(' ', $padding) . $paddedLine . str_repeat(' ', $padding);
            echo $this->color('│', $borderColor) . "\n";
        }

        // Bottom border
        echo $this->color('  ╰' . str_repeat('─', $width) . '╯', $borderColor) . "\n";
    }

    /**
     * Show a progress message.
     */
    private function progress(int $current, int $total, string $message = ''): void
    {
        if (!$this->supportsColors()) {
            if ($current % 100 === 0 || $current === $total) {
                echo "  {$current}/{$total} {$message}\n";
            }
            return;
        }

        $width = 30;
        $percent = $total > 0 ? ($current / $total) : 0;
        $filled = (int) round($width * $percent);
        $empty = $width - $filled;

        $bar = $this->color(str_repeat('█', $filled), self::GREEN);
        $bar .= $this->color(str_repeat('░', $empty), self::DIM);

        $percentText = str_pad((int) ($percent * 100) . '%', 4, ' ', STR_PAD_LEFT);

        echo "\r  [{$bar}] {$percentText} {$message}   ";

        if ($current === $total) {
            echo "\n";
        }
    }

    /**
     * Show tip text.
     */
    private function tip(string $text): void
    {
        echo "  " . $this->color('💡 Tip:', self::YELLOW) . " " . $this->color($text, self::DIM) . "\n";
    }

    /**
     * Show a hint for next steps.
     */
    private function nextStep(string $command, string $description = ''): void
    {
        echo "  " . $this->color('→', self::PRIMARY) . " ";
        echo $this->color($command, self::PRIMARY);
        if ($description) {
            echo $this->color(" — {$description}", self::DIM);
        }
        echo "\n";
    }

    private function showHelp(): void
    {
        $this->showBanner(showVersion: true);

        $this->sectionHeader('Usage');
        $this->writeln('    ' . $this->color('./ava', self::PRIMARY) . ' ' . $this->color('<command>', self::WHITE) . ' ' . $this->color('[options]', self::DIM));

        $this->sectionHeader('Site Management');
        $this->commandItem('status', 'Show site health and overview');
        $this->commandItem('rebuild', 'Rebuild the content index');
        $this->commandItem('lint', 'Validate all content files');

        $this->sectionHeader('Content');
        $this->commandItem('make <type> "Title"', 'Create new content');
        $this->commandItem('prefix <add|remove> [type]', 'Toggle date prefixes');

        $this->sectionHeader('Webpage Cache');
        $this->commandItem('cache:stats (or cache)', 'View cache statistics');
        $this->commandItem('cache:clear [pattern]', 'Clear cached webpages');

        $this->sectionHeader('Logs');
        $this->commandItem('logs:stats (or logs)', 'View log file statistics');
        $this->commandItem('logs:tail [name] [-n N]', 'Show last N lines of a log');
        $this->commandItem('logs:clear [name]', 'Clear log files');

        $this->sectionHeader('Users');
        $this->commandItem('user:add <email> <pass>', 'Create admin user');
        $this->commandItem('user:password <email> <pass>', 'Update password');
        $this->commandItem('user:remove <email>', 'Remove user');
        $this->commandItem('user:list (or user)', 'List all users');

        $this->sectionHeader('Updates');
        $this->commandItem('update:check (or update)', 'Check for updates');
        $this->commandItem('update:apply', 'Apply available update');

        $this->sectionHeader('Testing');
        $this->commandItem('test [filter]', 'Run the test suite');
        $this->commandItem('stress:generate <type> <n>', 'Generate test content');
        $this->commandItem('stress:clean <type>', 'Remove test content');
        $this->commandItem('stress:benchmark', 'Benchmark index backends');

        // Show plugin commands if any are registered
        if (!empty($this->pluginCommands)) {
            $this->sectionHeader('Plugins');
            foreach ($this->pluginCommands as $cmd) {
                $this->commandItem($cmd['name'], $cmd['description']);
            }
        }

        $this->sectionHeader('Examples');
        $this->writeln('    ' . $this->color('./ava status', self::WHITE));
        $this->writeln('    ' . $this->color('./ava make post "Hello World"', self::WHITE));
        $this->writeln('    ' . $this->color('./ava lint', self::WHITE));
        $this->writeln('');
    }

    private function commandItem(string $command, string $description): void
    {
        $paddedCmd = str_pad($command, 30);
        echo '    ' . $this->color($paddedCmd, self::WHITE);
        echo $this->color($description, self::DIM) . "\n";
    }

    // =========================================================================
    // Public Output Helpers (for use by plugins)
    // =========================================================================

    /**
     * Write a line to stdout.
     */
    public function writeln(string $message): void
    {
        echo $message . "\n";
    }

    /**
     * Display a section header.
     */
    public function header(string $title): void
    {
        $this->writeln('');
        echo '  ' . $this->color($title, self::PRIMARY, self::BOLD) . "\n";
        echo '  ' . $this->color(str_repeat('─', strlen($title)), self::PRIMARY) . "\n";
    }

    /**
     * Display an informational message.
     */
    public function info(string $message): void
    {
        echo '  ' . $this->color('ℹ', self::CYAN) . ' ' . $message . "\n";
    }

    /**
     * Display a success message.
     */
    public function success(string $message): void
    {
        echo "\n  " . $this->color('✓', self::GREEN, self::BOLD) . " " . $this->color($message, self::GREEN) . "\n";
    }

    /**
     * Display an error message.
     */
    public function error(string $message): void
    {
        echo "\n  " . $this->color('✗', self::RED, self::BOLD) . " " . $this->color($message, self::RED) . "\n";
    }

    /**
     * Display a warning message.
     */
    public function warning(string $message): void
    {
        echo "  " . $this->color('⚠', self::YELLOW) . " " . $this->color($message, self::YELLOW) . "\n";
    }

    /**
     * Format text with primary color (purple).
     */
    public function primary(string $text): string
    {
        return $this->color($text, self::PRIMARY);
    }

    /**
     * Format text as bold.
     */
    public function bold(string $text): string
    {
        return $this->color($text, self::BOLD);
    }

    /**
     * Format text as dim/muted.
     */
    public function dim(string $text): string
    {
        return $this->color($text, self::DIM);
    }

    /**
     * Format text with green color.
     */
    public function green(string $text): string
    {
        return $this->color($text, self::GREEN);
    }

    /**
     * Format text with yellow color.
     */
    public function yellow(string $text): string
    {
        return $this->color($text, self::YELLOW);
    }

    /**
     * Format text with red color.
     */
    public function red(string $text): string
    {
        return $this->color($text, self::RED);
    }

    /**
     * Format text with cyan color (accent).
     */
    public function cyan(string $text): string
    {
        return $this->color($text, self::PRIMARY);
    }

    /**
     * Format text with accent color (neon cyan).
     */
    public function accent(string $text): string
    {
        return $this->color($text, self::PRIMARY);
    }

    /**
     * Format text with highlight color (cyberpunk pink).
     */
    public function highlight(string $text): string
    {
        return $this->color($text, self::PRIMARY);
    }

    /**
     * Display a formatted table.
     */
    public function table(array $headers, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        // Helper to strip ANSI codes for length calculation
        $stripAnsi = fn(string $text): string => preg_replace('/\033\[[0-9;]*m/', '', $text);

        // Calculate column widths (strip ANSI codes for accurate length)
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($stripAnsi($header));
        }
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen($stripAnsi((string) $cell)));
            }
        }

        // Header
        $headerRow = '  ';
        foreach ($headers as $i => $header) {
            $headerRow .= $this->color(str_pad($header, $widths[$i] + 2), self::BOLD);
        }
        $this->writeln($headerRow);

        // Separator
        $sep = '  ';
        foreach ($widths as $w) {
            $sep .= $this->color(str_repeat('─', $w + 2), self::DIM);
        }
        $this->writeln($sep);

        // Rows - need to account for ANSI codes in padding
        foreach ($rows as $row) {
            $rowText = '  ';
            foreach ($row as $i => $cell) {
                $cellStr = (string) $cell;
                $visibleLen = strlen($stripAnsi($cellStr));
                $targetLen = ($widths[$i] ?? 0) + 2;
                $padding = max(0, $targetLen - $visibleLen);
                $rowText .= $cellStr . str_repeat(' ', $padding);
            }
            $this->writeln($rowText);
        }
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $exp = floor(log($bytes) / log(1024));
        $exp = min($exp, count($units) - 1);

        return round($bytes / pow(1024, $exp), 1) . ' ' . $units[$exp];
    }
}
