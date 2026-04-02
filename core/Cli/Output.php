<?php

declare(strict_types=1);

namespace Ava\Cli;

use Ava\Application as AvaApp;

/**
 * CLI Output
 *
 * Handles colored terminal output and formatting for the Ava CLI.
 */
final class Output
{
    private string $themeColor;
    private ?bool $colorsSupported = null;
    private AvaApp $app;

    // ANSI formatting
    public const RESET = "\033[0m";
    public const BOLD = "\033[1m";
    public const DIM = "\033[90m";

    // Theme placeholder (replaced with actual theme color at runtime)
    public const PRIMARY = '__THEME__';

    // Standard colors
    public const BLACK = "\033[30m";
    public const RED = "\033[38;2;248;113;113m";
    public const GREEN = "\033[38;2;52;211;153m";
    public const YELLOW = "\033[38;2;251;191;36m";
    public const WHITE = "\033[37m";
    public const BLUE = "\033[38;2;96;165;250m";
    public const CYAN = "\033[38;2;34;211;238m";

    // Background colors
    public const BG_GREEN = "\033[48;2;52;211;153m";
    public const BG_RED = "\033[48;2;248;113;113m";
    public const BG_YELLOW = "\033[48;2;251;191;36m";
    public const BG_BLUE = "\033[48;2;96;165;250m";

    // Color themes (monochrome - single accent color)
    public const THEMES = [
        'cyan'     => "\033[38;2;34;211;238m",    // Cyan-400
        'pink'     => "\033[38;2;244;114;182m",   // Pink-400
        'purple'   => "\033[38;2;167;139;250m",   // Violet-400
        'green'    => "\033[38;2;74;222;128m",    // Green-400
        'blue'     => "\033[38;2;96;165;250m",    // Blue-400
        'amber'    => "\033[38;2;251;191;36m",    // Amber-400
        'disabled' => "\033[37m",                 // White (no color)
    ];

    // ASCII Art banner (3 lines)
    public const BANNER_LINES = [
        '   ▄▄▄  ▄▄ ▄▄  ▄▄▄     ▄▄▄▄ ▄▄   ▄▄  ▄▄▄▄ ',
        '  ██▀██ ██▄██ ██▀██   ██▀▀▀ ██▀▄▀██ ███▄▄ ',
        '  ██▀██  ▀█▀  ██▀██   ▀████ ██   ██ ▄▄██▀',
    ];

    public function __construct(AvaApp $app)
    {
        $this->app = $app;
        $themeName = $app->config('cli.theme', 'cyan');
        $this->themeColor = self::THEMES[$themeName] ?? self::THEMES['cyan'];
    }

    /**
     * Check if terminal supports colors.
     */
    public function supportsColors(): bool
    {
        if ($this->colorsSupported !== null) {
            return $this->colorsSupported;
        }

        if ($this->app->config('cli.colors') === false) {
            return $this->colorsSupported = false;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            return $this->colorsSupported = (
                getenv('ANSICON') !== false
                || getenv('ConEmuANSI') === 'ON'
                || getenv('TERM') === 'xterm'
            );
        }

        return $this->colorsSupported = (function_exists('posix_isatty') && @posix_isatty(STDOUT));
    }

    /**
     * Apply color formatting if supported.
     */
    public function color(string $text, string ...$codes): string
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
    public function showBanner(bool $showVersion = false): void
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
    public function sectionHeader(string $title): void
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
    public function keyValue(string $key, string $value, string $indent = '  '): void
    {
        $paddedKey = str_pad($key . ':', 12);
        echo $indent . $this->color($paddedKey, self::DIM);
        echo $value . "\n";
    }

    /**
     * Show a labeled item with icon.
     */
    public function labeledItem(string $label, string $value, string $icon = '•', string $iconColor = ''): void
    {
        $coloredIcon = $iconColor ? $this->color($icon, $iconColor) : $this->color($icon, self::DIM);
        $this->writeln("  {$coloredIcon} " . $this->color($label . ':', self::DIM) . " {$value}");
    }

    /**
     * Show a status badge.
     * @phpstan-ignore-next-line Kept for future CLI commands
     */
    public function badge(string $text, string $type = 'info'): string
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
    public function withSpinner(string $message, callable $operation): mixed
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
    public function box(string $text, string $type = 'info'): void
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
    public function progress(int $current, int $total, string $message = ''): void
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
    public function tip(string $text): void
    {
        echo "  " . $this->color('💡 Tip:', self::YELLOW) . " " . $this->color($text, self::DIM) . "\n";
    }

    /**
     * Show a hint for next steps.
     */
    public function nextStep(string $command, string $description = ''): void
    {
        echo "  " . $this->color('→', self::PRIMARY) . " ";
        echo $this->color($command, self::PRIMARY);
        if ($description) {
            echo $this->color(" — {$description}", self::DIM);
        }
        echo "\n";
    }

    /**
     * Show a formatted command item (for help display).
     */
    public function commandItem(string $command, string $description): void
    {
        $paddedCmd = str_pad($command, 30);
        echo '    ' . $this->color($paddedCmd, self::WHITE);
        echo $this->color($description, self::DIM) . "\n";
    }

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
     * Format text with primary color.
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
     * Format text with cyan color.
     */
    public function cyan(string $text): string
    {
        return $this->color($text, self::PRIMARY);
    }

    /**
     * Format text with accent color.
     */
    public function accent(string $text): string
    {
        return $this->color($text, self::PRIMARY);
    }

    /**
     * Format text with highlight color.
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

    /**
     * Format a byte count for display.
     */
    public function formatBytes(int $bytes): string
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
