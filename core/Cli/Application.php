<?php

declare(strict_types=1);

namespace Ava\Cli;

use Ava\Application as AvaApp;
use Ava\Cli\Commands\BenchmarkCommand;
use Ava\Cli\Commands\CacheCommand;
use Ava\Cli\Commands\ContentCommand;
use Ava\Cli\Commands\IndexCommand;
use Ava\Cli\Commands\LogsCommand;
use Ava\Cli\Commands\StatusCommand;
use Ava\Cli\Commands\StressCommand;
use Ava\Cli\Commands\TestCommand;
use Ava\Cli\Commands\UpdateCommand;

/**
 * CLI Application
 *
 * Thin dispatcher that routes commands to their handler classes.
 */
final class Application
{
    private AvaApp $app;
    private Output $output;
    private array $commands = [];

    /** @var array<string, array> Plugin command metadata for help display */
    private array $pluginCommands = [];

    public function __construct(AvaApp $app)
    {
        $this->app = $app;
        $this->output = new Output($app);
        $this->registerCommands();
        $this->registerPluginCommands();
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
            $this->output->showBanner(showVersion: true);
            $this->output->writeln('');
            return 0;
        }

        // Find and run command
        if (!isset($this->commands[$command])) {
            $this->output->error("Unknown command: {$command}");
            $this->showHelp();
            return 1;
        }

        try {
            return $this->commands[$command]($argv);
        } catch (\Throwable $e) {
            $this->output->error($e->getMessage());
            return 1;
        }
    }

    /**
     * Register available commands.
     */
    private function registerCommands(): void
    {
        $status = new StatusCommand($this->output, $this->app);
        $index = new IndexCommand($this->output, $this->app);
        $content = new ContentCommand($this->output, $this->app);
        $update = new UpdateCommand($this->output, $this->app);
        $cache = new CacheCommand($this->output, $this->app);
        $logs = new LogsCommand($this->output, $this->app);
        $test = new TestCommand($this->output, $this->app);
        $stress = new StressCommand($this->output, $this->app);
        $benchmark = new BenchmarkCommand($this->output, $this->app);

        $this->commands['status'] = [$status, 'execute'];
        $this->commands['rebuild'] = [$index, 'rebuild'];
        $this->commands['lint'] = [$index, 'lint'];
        $this->commands['make'] = [$content, 'make'];
        $this->commands['prefix'] = [$content, 'prefix'];
        $this->commands['cache'] = [$cache, 'stats'];
        $this->commands['cache:clear'] = [$cache, 'clear'];
        $this->commands['cache:stats'] = [$cache, 'stats'];
        $this->commands['logs'] = [$logs, 'stats'];
        $this->commands['logs:stats'] = [$logs, 'stats'];
        $this->commands['logs:clear'] = [$logs, 'clear'];
        $this->commands['logs:tail'] = [$logs, 'tail'];
        $this->commands['benchmark'] = [$benchmark, 'execute'];
        $this->commands['test'] = [$test, 'execute'];
        $this->commands['stress:generate'] = [$stress, 'generate'];
        $this->commands['stress:clean'] = [$stress, 'clean'];
        $this->commands['stress:benchmark'] = [$benchmark, 'execute'];
        $this->commands['update'] = [$update, 'check'];
        $this->commands['update:check'] = [$update, 'check'];
        $this->commands['update:apply'] = [$update, 'apply'];
        $this->commands['update:stale'] = [$update, 'stale'];
    }

    /**
     * Register commands from enabled plugins.
     *
     * Plugins can register CLI commands by including a 'commands' key in their
     * plugin.php return array. Each command should be an array with:
     * - 'name': Command name (e.g., 'sitemap:stats')
     * - 'description': Command description for help
     * - 'handler': Callable that receives ($args, $output, $app) and returns exit code
     *
     * @example
     * return [
     *     'name' => 'My Plugin',
     *     'commands' => [
     *         [
     *             'name' => 'myplugin:status',
     *             'description' => 'Show plugin status',
     *             'handler' => function($args, $output, $app) {
     *                 $output->info("Plugin is running!");
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

                // Pass Output instance instead of CLI Application
                $this->commands[$cmdName] = function ($args) use ($cmdConfig) {
                    return $cmdConfig['handler']($args, $this->output, $this->app);
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


    private function showHelp(): void
    {
        $this->output->showBanner(showVersion: true);

        $this->output->sectionHeader('Usage');
        $this->output->writeln('    ' . $this->output->color('./ava', Output::PRIMARY) . ' ' . $this->output->color('<command>', Output::WHITE) . ' ' . $this->output->color('[options]', Output::DIM));

        $this->output->sectionHeader('Site Management');
        $this->output->commandItem('status', 'Show site health and overview');
        $this->output->commandItem('rebuild [--keep-webpage-cache]', 'Rebuild the content index');
        $this->output->commandItem('lint', 'Validate all content files');

        $this->output->sectionHeader('Content');
        $this->output->commandItem('make <type> "Title"', 'Create new content');
        $this->output->commandItem('prefix <add|remove> [type]', 'Toggle date prefixes');

        $this->output->sectionHeader('Webpage Cache');
        $this->output->commandItem('cache:stats (or cache)', 'View cache statistics');
        $this->output->commandItem('cache:clear [pattern]', 'Clear cached webpages');

        $this->output->sectionHeader('Logs');
        $this->output->commandItem('logs:stats (or logs)', 'View log file statistics');
        $this->output->commandItem('logs:tail [name] [-n N]', 'Show last N lines of a log');
        $this->output->commandItem('logs:clear [name]', 'Clear log files');

        $this->output->sectionHeader('Updates');
        $this->output->commandItem('update:check (or update)', 'Check for updates');
        $this->output->commandItem('update:apply', 'Apply available update');
        $this->output->commandItem('update:stale', 'Detect stale files from older releases');

        $this->output->sectionHeader('Testing');
        $this->output->commandItem('test [filter]', 'Run the test suite');
        $this->output->commandItem('stress:generate <type> <n>', 'Generate test content');
        $this->output->commandItem('stress:clean <type>', 'Remove test content');
        $this->output->commandItem('stress:benchmark', 'Benchmark index backends');

        // Show plugin commands if any are registered
        if (!empty($this->pluginCommands)) {
            $this->output->sectionHeader('Plugins');
            foreach ($this->pluginCommands as $cmd) {
                $this->output->commandItem($cmd['name'], $cmd['description']);
            }
        }

        $this->output->sectionHeader('Examples');
        $this->output->writeln('    ' . $this->output->color('./ava status', Output::WHITE));
        $this->output->writeln('    ' . $this->output->color('./ava make post "Hello World"', Output::WHITE));
        $this->output->writeln('    ' . $this->output->color('./ava lint', Output::WHITE));
        $this->output->writeln('');
    }
}
