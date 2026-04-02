<?php

declare(strict_types=1);

namespace Ava\Cli\Commands;

use Ava\Application as AvaApp;
use Ava\Cli\Output;
use Ava\Support\Ulid;

/**
 * Stress testing commands: generate, clean.
 */
final class StressCommand
{
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

    public function __construct(
        private Output $output,
        private AvaApp $app,
    ) {}

    public function generate(array $args): int
    {
        if (count($args) < 2) {
            $this->output->writeln('');
            $this->output->error('Usage: ./ava stress:generate <type> <count>');
            $this->output->writeln('');
            $this->output->writeln($this->output->color('  Examples:', Output::BOLD));
            $this->output->writeln('    ' . $this->output->color('./ava stress:generate post 100', Output::PRIMARY) . $this->output->color('    # Generate 100 posts', Output::DIM));
            $this->output->writeln('    ' . $this->output->color('./ava stress:generate post 1000', Output::PRIMARY) . $this->output->color('   # Generate 1000 posts', Output::DIM));
            $this->output->writeln('');
            $this->showAvailableTypes();
            $this->output->writeln('');
            return 1;
        }

        $type = $args[0];
        $count = (int) $args[1];

        if ($count < 1 || $count > 100000) {
            $this->output->error('Count must be between 1 and 100,000');
            return 1;
        }

        // Verify type exists
        $contentTypes = require $this->app->path('app/config/content_types.php');
        if (!isset($contentTypes[$type])) {
            $this->output->error("Unknown content type: {$type}");
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

        $this->output->writeln('');
        echo $this->output->color("  🧪 Generating {$count} dummy {$type}(s)...", Output::PRIMARY) . "\n";
        $this->output->writeln('');

        $start = microtime(true);
        $created = 0;

        for ($i = 1; $i <= $count; $i++) {
            $result = $this->generateDummyContent($type, $basePath, $isDated, $taxonomies, $taxonomyTerms, $i);
            if ($result) {
                $created++;
                $this->output->progress($i, $count, "Creating {$type}s...");
            }
        }

        $elapsed = round((microtime(true) - $start) * 1000);

        $this->output->success("Generated {$created} files in {$elapsed}ms");
        $this->output->writeln('');

        $this->output->withSpinner('Rebuilding content index', function () {
            $this->app->indexer()->rebuild();
            return true;
        });

        $this->output->writeln('');
        $this->output->nextStep("./ava stress:clean {$type}", 'Remove generated content when done');
        $this->output->writeln('');

        return 0;
    }

    public function clean(array $args): int
    {
        if (count($args) < 1) {
            $this->output->writeln('');
            $this->output->error('Usage: ./ava stress:clean <type>');
            $this->output->writeln('');
            $this->output->writeln('  This will remove all files with the ' . $this->output->color('_dummy-', Output::YELLOW) . ' prefix.');
            $this->output->writeln('');
            return 1;
        }

        $type = $args[0];

        // Verify type exists
        $contentTypes = require $this->app->path('app/config/content_types.php');
        if (!isset($contentTypes[$type])) {
            $this->output->error("Unknown content type: {$type}");
            $this->showAvailableTypes();
            return 1;
        }

        $typeConfig = $contentTypes[$type];
        $contentDir = $typeConfig['content_dir'] ?? $type;
        $basePath = $this->app->configPath('content') . '/' . $contentDir;

        if (!is_dir($basePath)) {
            $this->output->writeln('');
            $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' No content directory found.');
            $this->output->writeln('');
            return 0;
        }

        // Find all dummy files
        $pattern = $basePath . '/_dummy-*.md';
        $files = glob($pattern);

        if (empty($files)) {
            $this->output->writeln('');
            $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' No dummy content files found.');
            $this->output->writeln('');
            return 0;
        }

        $count = count($files);
        $this->output->writeln('');
        $this->output->writeln('  Found ' . $this->output->color((string) $count, Output::YELLOW, Output::BOLD) . ' dummy content file(s).');
        $this->output->writeln('');
        echo '  Delete all? [' . $this->output->color('y', Output::RED) . '/N]: ';
        $answer = trim(fgets(STDIN));

        if (strtolower($answer) !== 'y') {
            $this->output->writeln('');
            $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' Cancelled.');
            $this->output->writeln('');
            return 0;
        }

        $this->output->writeln('');
        $deleted = 0;
        foreach ($files as $i => $file) {
            if (unlink($file)) {
                $deleted++;
            }
            $this->output->progress($i + 1, $count, 'Deleting files...');
        }

        $this->output->success("Deleted {$deleted} file(s)");
        $this->output->writeln('');

        $this->output->withSpinner('Rebuilding content index', function () {
            $this->app->indexer()->rebuild();
            return true;
        });
        $this->output->success('Done!');
        $this->output->writeln('');

        return 0;
    }

    private function showAvailableTypes(): void
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

    private function generateDummyContent(
        string $type,
        string $basePath,
        bool $isDated,
        array $taxonomies,
        array $taxonomyTerms,
        int $index
    ): bool {
        $uniqueId = bin2hex(random_bytes(4));
        $slug = "_dummy-{$index}-{$uniqueId}";
        $filePath = $basePath . '/' . $slug . '.md';

        if (file_exists($filePath)) {
            return false;
        }

        $titleWords = array_map('ucfirst', $this->randomWords(rand(3, 8)));
        $title = implode(' ', $titleWords);

        $frontmatter = [
            'id' => Ulid::generate(),
            'title' => $title,
            'slug' => $slug,
            'status' => $this->randomStatus(),
        ];

        if ($isDated) {
            $daysAgo = rand(0, 730);
            $date = date('Y-m-d', strtotime("-{$daysAgo} days"));
            $frontmatter['date'] = $date;
        }

        $frontmatter['excerpt'] = ucfirst(implode(' ', $this->randomWords(rand(10, 25)))) . '.';

        foreach ($taxonomies as $taxonomy) {
            if (isset($taxonomyTerms[$taxonomy]) && !empty($taxonomyTerms[$taxonomy])) {
                $terms = $taxonomyTerms[$taxonomy];
                $numTerms = min(count($terms), rand(1, 3));
                shuffle($terms);
                $selectedTerms = array_slice($terms, 0, $numTerms);
                $frontmatter[$taxonomy] = $selectedTerms;
            }
        }

        $yaml = "---\n";
        foreach ($frontmatter as $key => $value) {
            if (is_array($value)) {
                $yaml .= "{$key}:\n";
                foreach ($value as $item) {
                    $yaml .= "  - {$item}\n";
                }
            } else {
                $stringValue = (string) $value;
                if (str_contains($stringValue, ':') || str_contains($stringValue, '#')) {
                    $stringValue = '"' . addslashes($stringValue) . '"';
                }
                $yaml .= "{$key}: {$stringValue}\n";
            }
        }
        $yaml .= "---\n\n";

        $numParagraphs = rand(3, 10);
        $content = '';
        for ($p = 0; $p < $numParagraphs; $p++) {
            $numSentences = rand(3, 8);
            $sentences = [];
            for ($s = 0; $s < $numSentences; $s++) {
                $sentence = ucfirst(implode(' ', $this->randomWords(rand(8, 20)))) . '.';
                $sentences[] = $sentence;
            }
            $content .= implode(' ', $sentences) . "\n\n";
        }

        if (rand(0, 2) === 0) {
            $headingWords = array_map('ucfirst', $this->randomWords(rand(2, 5)));
            $content = "## " . implode(' ', $headingWords) . "\n\n" . $content;
        }

        return file_put_contents($filePath, $yaml . $content, LOCK_EX) !== false;
    }

    private function randomWords(int $count): array
    {
        $words = [];
        for ($i = 0; $i < $count; $i++) {
            $words[] = self::LOREM_WORDS[array_rand(self::LOREM_WORDS)];
        }
        return $words;
    }

    private function randomStatus(): string
    {
        return rand(1, 10) <= 8 ? 'published' : 'draft';
    }

    private function loadTaxonomyTerms(array $taxonomies): array
    {
        $result = [];
        $taxPath = $this->app->configPath('content') . '/_taxonomies';

        foreach ($taxonomies as $taxonomy) {
            $result[$taxonomy] = [];
            $file = $taxPath . '/' . $taxonomy . '.yml';

            if (file_exists($file)) {
                $content = file_get_contents($file);
                if (preg_match_all('/^\s*-?\s*slug:\s*(\S+)/m', $content, $matches)) {
                    $result[$taxonomy] = $matches[1];
                }
            }

            if (empty($result[$taxonomy])) {
                $result[$taxonomy] = ['general', 'misc', 'other'];
            }
        }

        return $result;
    }
}
