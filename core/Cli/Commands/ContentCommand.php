<?php

declare(strict_types=1);

namespace Ava\Cli\Commands;

use Ava\Application as AvaApp;
use Ava\Cli\Output;
use Ava\Support\Str;
use Ava\Support\Ulid;

/**
 * Content management commands: make, prefix.
 */
final class ContentCommand
{
    public function __construct(
        private Output $output,
        private AvaApp $app,
    ) {}

    public function make(array $args): int
    {
        if (count($args) < 2) {
            $this->output->writeln('');
            $this->output->error('Usage: ./ava make <type> "Title"');
            $this->output->writeln('');
            $this->showAvailableTypes();
            $this->output->writeln('');
            $this->output->writeln($this->output->color('  Example:', Output::BOLD));
            $this->output->writeln('    ' . $this->output->color('./ava make post "My New Post"', Output::PRIMARY));
            $this->output->writeln('');
            return 1;
        }

        $type = array_shift($args);
        $title = implode(' ', $args);

        // Verify type exists
        $contentTypes = require $this->app->path('app/config/content_types.php');
        if (!isset($contentTypes[$type])) {
            $this->output->error("Unknown content type: {$type}");
            $this->output->writeln('');
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

    public function prefix(array $args): int
    {
        $action = $args[0] ?? null;
        $typeFilter = $args[1] ?? null;

        if (!in_array($action, ['add', 'remove'], true)) {
            $this->output->writeln('');
            $this->output->error('Usage: ./ava prefix <add|remove> [type]');
            $this->output->writeln('');
            $this->output->writeln($this->output->color('  Examples:', Output::BOLD));
            $this->output->writeln('    ' . $this->output->color('./ava prefix add post', Output::PRIMARY) . $this->output->color('      # Add date prefix to posts', Output::DIM));
            $this->output->writeln('    ' . $this->output->color('./ava prefix remove post', Output::PRIMARY) . $this->output->color('   # Remove date prefix', Output::DIM));
            $this->output->writeln('');
            return 1;
        }

        $contentTypes = require $this->app->path('app/config/content_types.php');
        $parser = new \Ava\Content\Parser();
        $renamed = 0;
        $skipped = 0;

        $this->output->writeln('');
        $actionLabel = $action === 'add' ? 'Adding' : 'Removing';
        echo $this->output->color("  {$actionLabel} date prefixes...", Output::DIM) . "\n";
        $this->output->writeln('');

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
            $this->output->success("Renamed {$renamed} file(s)");
            $this->output->writeln('');
            $this->output->nextStep('./ava rebuild', 'Update the content index');
        } else {
            $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' No files needed renaming.');
        }

        $this->output->writeln('');
        return 0;
    }

    public function showAvailableTypes(): void
    {
        $contentTypes = require $this->app->path('app/config/content_types.php');
        $this->output->writeln($this->output->color('  Available types:', Output::BOLD));
        $this->output->writeln('');
        foreach ($contentTypes as $name => $config) {
            $label = $config['label'] ?? ucfirst($name);
            echo '    ' . $this->output->color('▸ ', Output::PRIMARY);
            echo $this->output->color($name, Output::PRIMARY);
            echo $this->output->color(" — {$label}", Output::DIM) . "\n";
        }
    }

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
            $this->output->error("File already exists: {$filePath}");
            return 1;
        }

        // Write file with exclusive lock for concurrent safety
        file_put_contents($filePath, $yaml, LOCK_EX);

        $relativePath = str_replace($this->app->path('') . '/', '', $filePath);

        $this->output->writeln('');
        $this->output->box("Created new {$type}!", 'success');
        $this->output->writeln('');
        $this->output->keyValue('File', $this->output->color($relativePath, Output::PRIMARY));
        $this->output->keyValue('ID', $this->output->color($id, Output::DIM));
        $this->output->keyValue('Slug', $slug);
        $this->output->keyValue('Status', $this->output->color('draft', Output::YELLOW));
        $this->output->writeln('');
        $this->output->tip("Edit your content, then set status: published when ready");
        $this->output->writeln('');

        return 0;
    }

    /**
     * @return bool|null true=renamed, false=skipped, null=no action needed
     */
    private function processFilePrefix(string $filePath, string $type, \Ava\Content\Parser $parser, string $action): ?bool
    {
        try {
            $item = $parser->parseFile($filePath, $type);
        } catch (\Exception $e) {
            $this->output->warning("Skipping: " . basename($filePath) . " — " . $e->getMessage());
            return false;
        }

        $date = $item->date();
        if ($date === null) {
            return null;
        }

        $dir = dirname($filePath);
        $filename = basename($filePath);
        $datePrefix = $date->format('Y-m-d') . '-';

        $hasPrefix = preg_match('/^\d{4}-\d{2}-\d{2}-/', $filename);

        if ($action === 'add' && !$hasPrefix) {
            $newFilename = $datePrefix . $filename;
            $newPath = $dir . '/' . $newFilename;

            if (file_exists($newPath)) {
                $this->output->warning("Cannot rename: {$newFilename} already exists");
                return false;
            }

            rename($filePath, $newPath);
            echo "    " . $this->output->color('→', Output::GREEN) . " ";
            echo $this->output->color($filename, Output::DIM) . " → " . $this->output->color($newFilename, Output::PRIMARY) . "\n";
            return true;

        } elseif ($action === 'remove' && $hasPrefix) {
            $newFilename = preg_replace('/^\d{4}-\d{2}-\d{2}-/', '', $filename);
            $newPath = $dir . '/' . $newFilename;

            if (file_exists($newPath)) {
                $this->output->warning("Cannot rename: {$newFilename} already exists");
                return false;
            }

            rename($filePath, $newPath);
            echo "    " . $this->output->color('→', Output::GREEN) . " ";
            echo $this->output->color($filename, Output::DIM) . " → " . $this->output->color($newFilename, Output::PRIMARY) . "\n";
            return true;
        }

        return null;
    }

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
}
