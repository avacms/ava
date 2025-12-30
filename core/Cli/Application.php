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

    // ANSI color codes
    private const RESET = "\033[0m";
    private const BOLD = "\033[1m";
    private const DIM = "\033[2m";
    private const ITALIC = "\033[3m";

    // Colors
    private const BLACK = "\033[30m";
    private const RED = "\033[31m";
    private const GREEN = "\033[32m";
    private const YELLOW = "\033[33m";
    private const BLUE = "\033[34m";
    private const MAGENTA = "\033[35m";
    private const CYAN = "\033[36m";
    private const WHITE = "\033[37m";

    // Bright colors
    private const BRIGHT_BLACK = "\033[90m";
    private const BRIGHT_GREEN = "\033[92m";
    private const BRIGHT_CYAN = "\033[96m";

    // Background colors
    private const BG_GREEN = "\033[42m";
    private const BG_RED = "\033[41m";
    private const BG_BLUE = "\033[44m";
    private const BG_YELLOW = "\033[43m";

    // ASCII Art banner
    private const BANNER = <<<'ASCII'

   ▄▄▄  ▄▄ ▄▄  ▄▄▄     ▄▄▄▄ ▄▄   ▄▄  ▄▄▄▄ 
  ██▀██ ██▄██ ██▀██   ██▀▀▀ ██▀▄▀██ ███▄▄ 
  ██▀██  ▀█▀  ██▀██   ▀████ ██   ██ ▄▄██▀
ASCII;

    public function __construct()
    {
        $this->app = AvaApp::getInstance();
        $this->registerCommands();
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
            $this->showBanner();
            echo $this->color('  v' . AVA_VERSION, self::DIM) . "\n";
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
        $this->commands['pages:clear'] = [$this, 'cmdPagesClear'];
        $this->commands['pages:stats'] = [$this, 'cmdPagesStats'];
        $this->commands['stress:generate'] = [$this, 'cmdStressGenerate'];
        $this->commands['stress:clean'] = [$this, 'cmdStressClean'];
        $this->commands['user:add'] = [$this, 'cmdUserAdd'];
        $this->commands['user:password'] = [$this, 'cmdUserPassword'];
        $this->commands['user:remove'] = [$this, 'cmdUserRemove'];
        $this->commands['user:list'] = [$this, 'cmdUserList'];
        $this->commands['update:check'] = [$this, 'cmdUpdateCheck'];
        $this->commands['update:apply'] = [$this, 'cmdUpdateApply'];
    }

    // =========================================================================
    // Commands
    // =========================================================================

    /**
     * Show site status.
     */
    private function cmdStatus(array $args): int
    {
        $this->showBanner();
        echo $this->color('  v' . AVA_VERSION, self::DIM) . "\n";

        // Site info
        $this->sectionHeader('Site');
        $this->keyValue('Name', $this->color($this->app->config('site.name'), self::BOLD));
        $this->keyValue('URL', $this->color($this->app->config('site.base_url'), self::BRIGHT_CYAN));

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
                $this->color((string) $count, self::CYAN, self::BOLD) .
                    $this->color(' terms', self::DIM),
                '◆',
                self::CYAN
            );
        }

        // Page cache stats
        $pageCache = $this->app->pageCache();
        $stats = $pageCache->stats();
        $this->sectionHeader('Page Cache');
        $status = $stats['enabled']
            ? $this->color('● Enabled', self::GREEN, self::BOLD)
            : $this->color('○ Disabled', self::DIM);
        $this->keyValue('Status', $status);

        if ($stats['enabled']) {
            $ttl = $stats['ttl'] ?? null;
            $this->keyValue('TTL', $ttl ? "{$ttl}s" : 'Forever');
            $this->keyValue('Cached', $this->color((string) $stats['count'], self::CYAN, self::BOLD) . ' pages');
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
            $this->writeln('    ' . $this->color('./ava make post "My New Post"', self::BRIGHT_CYAN));
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
            echo '    ' . $this->color('▸ ', self::CYAN);
            echo $this->color($name, self::BRIGHT_CYAN);
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
        $this->keyValue('File', $this->color($relativePath, self::BRIGHT_CYAN));
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
            $this->writeln('    ' . $this->color('./ava prefix add post', self::BRIGHT_CYAN) . $this->color('      # Add date prefix to posts', self::DIM));
            $this->writeln('    ' . $this->color('./ava prefix remove post', self::BRIGHT_CYAN) . $this->color('   # Remove date prefix', self::DIM));
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
            $this->writeln('  ' . $this->color('ℹ', self::CYAN) . ' No files needed renaming.');
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
            echo $this->color($filename, self::DIM) . " → " . $this->color($newFilename, self::BRIGHT_CYAN) . "\n";
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
            echo $this->color($filename, self::DIM) . " → " . $this->color($newFilename, self::BRIGHT_CYAN) . "\n";
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
            $this->writeln('    ' . $this->color('./ava user:add admin@example.com mypassword "Admin"', self::BRIGHT_CYAN));
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
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'name' => $userName,
            'created' => date('Y-m-d'),
        ];

        $this->saveUsers($usersFile, $users);

        $this->writeln('');
        $this->box("User created successfully!", 'success');
        $this->writeln('');
        $this->keyValue('Email', $this->color($email, self::BRIGHT_CYAN));
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

        $users[$email]['password'] = password_hash($password, PASSWORD_DEFAULT);
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

        echo $this->color('  ─── Users ', self::CYAN, self::BOLD);
        echo $this->color(str_repeat('─', 45), self::DIM) . "\n";
        $this->writeln('');

        foreach ($users as $email => $data) {
            $name = $data['name'] ?? '';
            $created = $data['created'] ?? '';

            echo "    " . $this->color('◆', self::CYAN) . " ";
            echo $this->color($email, self::BRIGHT_CYAN, self::BOLD) . "\n";
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
                    echo $this->color('  ─── Changelog ', self::CYAN, self::BOLD);
                    echo $this->color(str_repeat('─', 42), self::DIM) . "\n";
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
            $this->writeln('  ' . $this->color('ℹ', self::CYAN) . ' ' . $this->color('Cached result — use --force to refresh', self::DIM));
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

        echo $this->color('  ─── Update Available ', self::CYAN, self::BOLD);
        echo $this->color(str_repeat('─', 35), self::DIM) . "\n";
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
                $this->writeln('  ' . $this->color('ℹ', self::CYAN) . ' Please backup your site before updating.');
                $this->writeln('');
                return 0;
            }
            $this->writeln('');

            echo '  Continue with update? [' . $this->color('y', self::GREEN) . '/N]: ';
            $answer = trim(fgets(STDIN));
            if (strtolower($answer) !== 'y') {
                $this->writeln('');
                $this->writeln('  ' . $this->color('ℹ', self::CYAN) . ' Update cancelled.');
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
                echo "    " . $this->color('•', self::CYAN) . " {$plugin}\n";
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
            $this->writeln('    ' . $this->color('./ava stress:generate post 100', self::BRIGHT_CYAN) . $this->color('    # Generate 100 posts', self::DIM));
            $this->writeln('    ' . $this->color('./ava stress:generate post 1000', self::BRIGHT_CYAN) . $this->color('   # Generate 1000 posts', self::DIM));
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
        echo $this->color("  🧪 Generating {$count} dummy {$type}(s)...", self::CYAN) . "\n";
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
            $this->writeln('  ' . $this->color('ℹ', self::CYAN) . ' No content directory found.');
            $this->writeln('');
            return 0;
        }

        // Find all dummy files
        $pattern = $basePath . '/_dummy-*.md';
        $files = glob($pattern);

        if (empty($files)) {
            $this->writeln('');
            $this->writeln('  ' . $this->color('ℹ', self::CYAN) . ' No dummy content files found.');
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
            $this->writeln('  ' . $this->color('ℹ', self::CYAN) . ' Cancelled.');
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
     * Clear page cache.
     */
    private function cmdPagesClear(array $args): int
    {
        $pageCache = $this->app->pageCache();

        $this->writeln('');

        if (!$pageCache->isEnabled()) {
            $this->box("Page cache is not enabled", 'warning');
            $this->writeln('');
            $this->tip("Enable it in app/config/ava.php with 'page_cache' => ['enabled' => true]");
            $this->writeln('');
            return 0;
        }

        $stats = $pageCache->stats();
        if ($stats['count'] === 0) {
            $this->writeln('  ' . $this->color('ℹ', self::CYAN) . ' Page cache is empty.');
            $this->writeln('');
            return 0;
        }

        $this->writeln('  Found ' . $this->color((string) $stats['count'], self::CYAN, self::BOLD) . ' cached page(s).');
        $this->writeln('');

        // Check for pattern argument
        if (isset($args[0])) {
            $pattern = $args[0];
            $count = $pageCache->clearPattern($pattern);
            $this->success("Cleared {$count} page(s) matching: {$pattern}");
        } else {
            echo '  Clear all cached pages? [' . $this->color('y', self::RED) . '/N]: ';
            $answer = trim(fgets(STDIN));

            if (strtolower($answer) !== 'y') {
                $this->writeln('');
                $this->writeln('  ' . $this->color('ℹ', self::CYAN) . ' Cancelled.');
                $this->writeln('');
                return 0;
            }

            $count = $pageCache->clear();
            $this->success("Cleared {$count} cached page(s)");
        }

        $this->writeln('');
        return 0;
    }

    /**
     * Show page cache statistics.
     */
    private function cmdPagesStats(array $args): int
    {
        $pageCache = $this->app->pageCache();
        $stats = $pageCache->stats();

        $this->writeln('');
        echo $this->color('  ─── Page Cache ', self::CYAN, self::BOLD);
        echo $this->color(str_repeat('─', 41), self::DIM) . "\n";
        $this->writeln('');

        $status = $stats['enabled']
            ? $this->color('● Enabled', self::GREEN, self::BOLD)
            : $this->color('○ Disabled', self::DIM);
        $this->keyValue('Status', $status);

        if (!$stats['enabled']) {
            $this->writeln('');
            $this->tip("Enable page caching in app/config/ava.php: 'page_cache' => ['enabled' => true]");
            $this->writeln('');
            return 0;
        }

        $this->keyValue('TTL', $stats['ttl'] ? $stats['ttl'] . ' seconds' : 'Forever (until cleared)');
        $this->writeln('');
        $this->keyValue('Cached', $this->color((string) $stats['count'], self::CYAN, self::BOLD) . ' pages');
        $this->keyValue('Size', $this->formatBytes($stats['size']));

        if ($stats['oldest']) {
            $this->keyValue('Oldest', $stats['oldest']);
            $this->keyValue('Newest', $stats['newest']);
        }

        $this->writeln('');

        return 0;
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
        return implode('', $codes) . $text . self::RESET;
    }

    /**
     * Show the banner.
     */
    private function showBanner(): void
    {
        echo $this->color(self::BANNER, self::CYAN, self::BOLD);
    }

    /**
     * Show a section header.
     */
    private function sectionHeader(string $title): void
    {
        $this->writeln('');
        echo $this->color("  ─── {$title} ", self::CYAN, self::BOLD);
        echo $this->color(str_repeat('─', max(0, 50 - strlen($title))), self::DIM);
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
        echo "  " . $this->color($frames[0], self::CYAN) . " {$message}...";

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
            default => self::CYAN,
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
     * Show a table.
     */
    private function table(array $headers, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        // Calculate column widths
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen((string) $cell));
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

        // Rows
        foreach ($rows as $row) {
            $rowText = '  ';
            foreach ($row as $i => $cell) {
                $rowText .= str_pad((string) $cell, ($widths[$i] ?? 0) + 2);
            }
            $this->writeln($rowText);
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
        echo "  " . $this->color('→', self::CYAN) . " ";
        echo $this->color($command, self::BRIGHT_CYAN);
        if ($description) {
            echo $this->color(" — {$description}", self::DIM);
        }
        echo "\n";
    }

    private function showHelp(): void
    {
        $this->showBanner();
        $this->writeln('');

        $this->sectionHeader('Usage');
        $this->writeln('  ' . $this->color('./ava', self::BRIGHT_CYAN) . ' <command> [options]');

        $this->sectionHeader('Site Management');
        $this->commandItem('status', 'Show site health and overview');
        $this->commandItem('rebuild', 'Rebuild the content index');
        $this->commandItem('lint', 'Validate all content files');

        $this->sectionHeader('Content');
        $this->commandItem('make <type> "Title"', 'Create new content');
        $this->commandItem('prefix <add|remove> [type]', 'Toggle date prefixes');

        $this->sectionHeader('Page Cache');
        $this->commandItem('pages:stats', 'View cache statistics');
        $this->commandItem('pages:clear [pattern]', 'Clear cached pages');

        $this->sectionHeader('Users');
        $this->commandItem('user:add <email> <pass>', 'Create admin user');
        $this->commandItem('user:password <email> <pass>', 'Update password');
        $this->commandItem('user:remove <email>', 'Remove user');
        $this->commandItem('user:list', 'List all users');

        $this->sectionHeader('Updates');
        $this->commandItem('update:check', 'Check for updates');
        $this->commandItem('update:apply', 'Apply available update');

        $this->sectionHeader('Testing');
        $this->commandItem('stress:generate <type> <n>', 'Generate test content');
        $this->commandItem('stress:clean <type>', 'Remove test content');

        $this->writeln('');
        echo $this->color('  Examples:', self::BOLD) . "\n";
        $this->writeln('');
        $this->writeln('    ' . $this->color('./ava status', self::BRIGHT_CYAN));
        $this->writeln('    ' . $this->color('./ava make post "Hello World"', self::BRIGHT_CYAN));
        $this->writeln('    ' . $this->color('./ava lint', self::BRIGHT_CYAN));
        $this->writeln('');
    }

    private function commandItem(string $command, string $description): void
    {
        $paddedCmd = str_pad($command, 30);
        echo '    ' . $this->color($paddedCmd, self::BRIGHT_CYAN);
        echo $this->color($description, self::DIM) . "\n";
    }

    private function writeln(string $message): void
    {
        echo $message . "\n";
    }

    private function success(string $message): void
    {
        echo "\n  " . $this->color('✓', self::GREEN, self::BOLD) . " " . $this->color($message, self::GREEN) . "\n";
    }

    private function error(string $message): void
    {
        echo "\n  " . $this->color('✗', self::RED, self::BOLD) . " " . $this->color($message, self::RED) . "\n";
    }

    private function warning(string $message): void
    {
        echo "  " . $this->color('⚠', self::YELLOW) . " " . $this->color($message, self::YELLOW) . "\n";
    }

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
