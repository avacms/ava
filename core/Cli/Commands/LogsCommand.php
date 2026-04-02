<?php

declare(strict_types=1);

namespace Ava\Cli\Commands;

use Ava\Application as AvaApp;
use Ava\Cli\Output;

/**
 * Log management commands: stats, clear, tail.
 */
final class LogsCommand
{
    public function __construct(
        private Output $output,
        private AvaApp $app,
    ) {}

    public function stats(array $args): int
    {
        $logsPath = $this->app->configPath('storage') . '/logs';

        $this->output->writeln('');
        echo $this->output->color('  ─── Logs ', Output::PRIMARY, Output::BOLD);
        echo $this->output->color(str_repeat('─', 47), Output::PRIMARY, Output::BOLD) . "\n";
        $this->output->writeln('');

        if (!is_dir($logsPath)) {
            $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' No logs directory found.');
            $this->output->writeln('');
            return 0;
        }

        $logFiles = glob($logsPath . '/*.log*') ?: [];

        if (empty($logFiles)) {
            $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' No log files found.');
            $this->output->writeln('');
            return 0;
        }

        // Group by base name (e.g., indexer.log, indexer.log.1, etc.)
        $grouped = [];
        foreach ($logFiles as $file) {
            $basename = basename($file);
            if (preg_match('/^(.+\.log)(\.\d+)?$/', $basename, $m)) {
                $base = $m[1];
                $grouped[$base][] = $file;
            }
        }

        $totalSize = 0;
        $totalFiles = 0;

        foreach ($grouped as $baseName => $files) {
            $size = 0;

            foreach ($files as $file) {
                $size += filesize($file);
            }

            // Count lines in main log file only
            $mainLog = $logsPath . '/' . $baseName;
            $lines = 0;
            if (file_exists($mainLog)) {
                $lines = $this->countLines($mainLog);
            }

            $totalSize += $size;
            $totalFiles += count($files);

            $this->output->keyValue($baseName, $this->output->formatBytes($size) .
                (count($files) > 1 ? $this->output->color(' (' . count($files) . ' files)', Output::DIM) : '') .
                ($lines > 0 ? $this->output->color(" · {$lines} lines", Output::DIM) : ''));
        }

        $this->output->writeln('');
        $this->output->keyValue('Total', $this->output->color($this->output->formatBytes($totalSize), Output::PRIMARY, Output::BOLD) .
            $this->output->color(" ({$totalFiles} files)", Output::DIM));

        // Show config
        $maxSize = $this->app->config('logs.max_size', 10 * 1024 * 1024);
        $maxFiles = $this->app->config('logs.max_files', 3);
        $this->output->writeln('');
        $this->output->keyValue('Max Size', $this->output->formatBytes($maxSize) . $this->output->color(' per log', Output::DIM));
        $this->output->keyValue('Max Files', $maxFiles . $this->output->color(' rotated copies', Output::DIM));

        $this->output->writeln('');
        return 0;
    }

    public function clear(array $args): int
    {
        $logsPath = $this->app->configPath('storage') . '/logs';

        $this->output->writeln('');

        if (!is_dir($logsPath)) {
            $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' No logs directory found.');
            $this->output->writeln('');
            return 0;
        }

        $logName = $args[0] ?? null;

        if ($logName) {
            $pattern = $logsPath . '/' . $logName . '*';
            $files = glob($pattern) ?: [];

            if (empty($files)) {
                $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . " No logs matching: {$logName}");
                $this->output->writeln('');
                return 0;
            }

            $count = 0;
            $size = 0;
            foreach ($files as $file) {
                $size += filesize($file);
                unlink($file);
                $count++;
            }

            $this->output->success("Cleared {$count} log file(s) ({$this->output->formatBytes($size)})");
        } else {
            $logFiles = glob($logsPath . '/*.log*') ?: [];

            if (empty($logFiles)) {
                $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' No log files found.');
                $this->output->writeln('');
                return 0;
            }

            $totalSize = array_sum(array_map('filesize', $logFiles));
            $this->output->writeln('  Found ' . $this->output->color((string) count($logFiles), Output::PRIMARY, Output::BOLD) .
                ' log file(s) (' . $this->output->formatBytes($totalSize) . ').');
            $this->output->writeln('');

            echo '  Clear all log files? [' . $this->output->color('y', Output::RED) . '/N]: ';
            $answer = trim(fgets(STDIN));

            if (strtolower($answer) !== 'y') {
                $this->output->writeln('');
                $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' Cancelled.');
                $this->output->writeln('');
                return 0;
            }

            $count = 0;
            foreach ($logFiles as $file) {
                unlink($file);
                $count++;
            }

            $this->output->success("Cleared {$count} log file(s) ({$this->output->formatBytes($totalSize)})");
        }

        $this->output->writeln('');
        return 0;
    }

    public function tail(array $args): int
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

        $this->output->writeln('');

        if (!file_exists($logFile)) {
            if (!str_ends_with($logName, '.log')) {
                $logFile = $logsPath . '/' . $logName . '.log';
            }

            if (!file_exists($logFile)) {
                $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . " Log file not found: {$logName}");
                $this->output->writeln('');

                $available = glob($logsPath . '/*.log') ?: [];
                if (!empty($available)) {
                    $this->output->writeln($this->output->color('  Available logs:', Output::BOLD));
                    foreach ($available as $file) {
                        $this->output->writeln('    ' . $this->output->color('▸ ', Output::PRIMARY) . basename($file));
                    }
                    $this->output->writeln('');
                }
                return 1;
            }
        }

        echo $this->output->color('  ─── ', Output::PRIMARY, Output::BOLD);
        echo $this->output->color(basename($logFile), Output::PRIMARY, Output::BOLD);
        echo $this->output->color(' (last ' . $lines . ' lines) ', Output::PRIMARY, Output::BOLD);
        echo $this->output->color(str_repeat('─', max(1, 40 - strlen(basename($logFile)))), Output::PRIMARY, Output::BOLD) . "\n";
        $this->output->writeln('');

        $content = $this->tailFile($logFile, $lines);

        if (empty(trim($content))) {
            $this->output->writeln('  ' . $this->output->color('(empty)', Output::DIM));
        } else {
            foreach (explode("\n", rtrim($content)) as $line) {
                if (preg_match('/^\[([^\]]+)\]/', $line, $m)) {
                    $line = $this->output->color('[' . $m[1] . ']', Output::DIM) . substr($line, strlen($m[0]));
                }
                $this->output->writeln('  ' . $line);
            }
        }

        $this->output->writeln('');
        return 0;
    }

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

        $allLines = explode("\n", $output);
        $allLines = array_slice($allLines, -$lines - 1);

        return implode("\n", $allLines);
    }

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
}
