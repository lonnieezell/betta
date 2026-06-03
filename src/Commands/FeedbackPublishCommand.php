<?php

declare(strict_types=1);

/**
 * This file is part of Myth/Betta.
 *
 * (c) Your Name <you@example.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Myth\Betta\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class FeedbackPublishCommand extends BaseCommand
{
    protected $group       = 'Betta';
    protected $name        = 'feedback:publish';
    protected $description = 'Publishes package views and/or config file to the application.';
    protected $usage       = 'feedback:publish [options]';
    protected $options     = [
        '--views'    => 'Copy view files to app/Views/vendor/betta/',
        '--config'   => 'Write app/Config/Betta.php extending the package config',
        '--app-path' => '[Internal] Override APPPATH (used in tests)',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        $publishViews  = array_key_exists('views', $params);
        $publishConfig = array_key_exists('config', $params);

        if (! $publishViews && ! $publishConfig) {
            CLI::error('Specify at least one option: --views or --config');

            return EXIT_ERROR;
        }

        $appPath = isset($params['app-path']) ? rtrim((string) $params['app-path'], '/\\') . DIRECTORY_SEPARATOR : APPPATH;

        if ($publishViews) {
            $this->publishViews($appPath);
        }

        if ($publishConfig) {
            $this->publishConfig($appPath);
        }

        return EXIT_SUCCESS;
    }

    private function publishViews(string $appPath): void
    {
        $srcDir  = dirname(__DIR__) . '/Views/';
        $destDir = $appPath . 'Views' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'betta' . DIRECTORY_SEPARATOR;

        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        foreach (['form.php', 'page.php', 'closed.php'] as $file) {
            $dest = $destDir . $file;

            if (is_file($dest) && CLI::prompt("Overwrite existing {$file}?", ['y', 'n']) !== 'y') {
                CLI::write("Skipped {$file}.");

                continue;
            }

            copy($srcDir . $file, $dest);
            CLI::write("Published {$file} → " . $dest, 'green');
        }
    }

    private function publishConfig(string $appPath): void
    {
        $dest = $appPath . 'Config' . DIRECTORY_SEPARATOR . 'Betta.php';

        if (is_file($dest) && CLI::prompt('Overwrite existing Betta.php?', ['y', 'n']) !== 'y') {
            CLI::write('Skipped Betta.php.');

            return;
        }

        $content = $this->buildConfigContent();
        file_put_contents($dest, $content);
        CLI::write('Published Betta.php → ' . $dest, 'green');
    }

    private function buildConfigContent(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace Config;

            use Myth\Betta\Config\Betta as BettaConfig;

            class Betta extends BettaConfig
            {
                // The route prefix for the feedback endpoints.
                // Produces GET /{routePrefix} and POST /{routePrefix}/submit.
                // public string $routePrefix = 'feedback';

                // Whether to accept new feedback submissions.
                // When false, the closed view is shown instead of the form.
                // public bool $acceptSubmissions = true;

                // Maximum number of ungrouped items to send to the AI in a single
                // feedback:analyze batch. Override with --limit at the CLI.
                // public int $analyzeBatchSize = 50;
            }
            PHP;
    }
}
