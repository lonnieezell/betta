<?php

declare(strict_types=1);

/**
 * This file is part of Myth/Betta.
 *
 * (c) Lonnie Ezell <lonnieje@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Myth\Betta\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Exception;
use Myth\Betta\Config\Betta;
use Myth\Betta\Enums\StatusEnum;
use Myth\Betta\Models\FeedbackClusterModel;
use Myth\Betta\Models\FeedbackModel;
use Myth\Betta\Prompts\ClusterFeedbackPrompt;
use Myth\Scribe\Exceptions\AIException;
use Myth\Scribe\Services\ScribeService;

class FeedbackAnalyzeCommand extends BaseCommand
{
    protected $group       = 'Betta';
    protected $name        = 'feedback:analyze';
    protected $description = 'AI-assisted clustering of ungrouped feedback via myth/scribe.';
    protected $options     = [
        '--dry-run' => 'Print suggestions without writing to the database.',
        '--apply'   => 'Write all suggestions without interactive prompts.',
        '--limit'   => 'Maximum number of ungrouped items to analyze (overrides config).',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        if (! class_exists(ScribeService::class)) {
            CLI::error('myth/scribe is not installed. Add it with: composer require myth/scribe');

            return EXIT_ERROR;
        }

        /** @var Betta $config */
        $config = config('Betta');

        $dryRun = array_key_exists('dry-run', $params) || CLI::getOption('dry-run') !== null;
        $apply  = array_key_exists('apply', $params)   || CLI::getOption('apply') !== null;
        $limit  = $params['limit'] ?? CLI::getOption('limit') ?? $config->analyzeBatchSize;

        $feedbackModel = new FeedbackModel();
        $clusterModel  = new FeedbackClusterModel();

        $items = $feedbackModel
            ->where('cluster_id IS NULL', null, false)
            ->where('status !=', StatusEnum::Dismissed->value)
            ->orderBy('created_at', 'ASC')
            ->limit((int) $limit)
            ->findAll();

        if ($items === []) {
            CLI::write('No ungrouped feedback items found.');

            return EXIT_SUCCESS;
        }

        $existingClusters = $clusterModel->findAll();

        $itemsData = array_map(
            static fn (object $i): array => ['id' => $i->id, 'message' => (string) $i->message],
            $items,
        );
        $clustersData = array_map(
            static fn (object $c): array => ['id' => $c->id, 'label' => $c->label],
            $existingClusters,
        );

        $prompt = new ClusterFeedbackPrompt($itemsData, $clustersData);

        try {
            $scribe      = service('scribe');
            $suggestions = $scribe->run($prompt)->toArray();
        } catch (AIException $e) {
            CLI::error('AI error: ' . $e->getMessage());

            return EXIT_ERROR;
        } catch (Exception $e) {
            CLI::error('Unexpected error from scribe: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        if ($dryRun) {
            $this->displaySuggestions($suggestions, $clustersData);

            return EXIT_SUCCESS;
        }

        foreach ($suggestions as $suggestion) {
            if ($apply) {
                $this->applySuggestion($suggestion, $feedbackModel, $clusterModel);
            } else {
                $this->displaySuggestion($suggestion, $clustersData);
                $action = $this->promptAction();

                if ($action === 'n') {
                    continue;
                }

                if ($action === 'e') {
                    $label               = CLI::prompt('Label', $suggestion['label']);
                    $suggestion['label'] = trim($label) !== '' ? trim($label) : (string) $suggestion['label'];
                }

                $this->applySuggestion($suggestion, $feedbackModel, $clusterModel);
            }
        }

        return EXIT_SUCCESS;
    }

    /**
     * @param array<int, array<string, mixed>> $suggestions
     * @param array<int, array<string, mixed>> $clustersData
     */
    private function displaySuggestions(array $suggestions, array $clustersData): void
    {
        foreach ($suggestions as $suggestion) {
            $this->displaySuggestion($suggestion, $clustersData);
        }
    }

    /**
     * @param array<string, mixed>             $suggestion
     * @param array<int, array<string, mixed>> $clustersData
     */
    private function displaySuggestion(array $suggestion, array $clustersData): void
    {
        CLI::write('');
        $existingId = $suggestion['existing_cluster_id'] ?? null;

        if ($existingId !== null) {
            $existingLabel = '';

            foreach ($clustersData as $c) {
                if ($c['id'] === $existingId) {
                    $existingLabel = " → existing cluster #{$existingId}";
                    break;
                }
            }

            CLI::write(CLI::color("Cluster: {$suggestion['label']}{$existingLabel}", 'yellow'));
        } else {
            CLI::write(CLI::color("New cluster: {$suggestion['label']}", 'yellow'));
        }

        CLI::write("Summary: {$suggestion['summary']}");
        CLI::write('Items: ' . implode(', ', (array) $suggestion['ids']));
    }

    private function promptAction(): string
    {
        $valid = ['y', 'n', 'e'];

        do {
            $action = strtolower(CLI::prompt('Action ([y] Accept / [n] Skip / [e] Edit label)'));
        } while (! in_array($action, $valid, true));

        return $action;
    }

    /**
     * @param array<string, mixed> $suggestion
     */
    private function applySuggestion(
        array $suggestion,
        FeedbackModel $feedbackModel,
        FeedbackClusterModel $clusterModel,
    ): void {
        $existingId = $suggestion['existing_cluster_id'] ?? null;

        if ($existingId !== null) {
            $existingId = (int) $existingId;

            if ($clusterModel->find($existingId) === null) {
                CLI::error("Cluster {$existingId} no longer exists; skipping suggestion.");

                return;
            }

            $clusterId = $existingId;
        } else {
            $clusterId = $clusterModel->insert([
                'label'   => $suggestion['label'],
                'summary' => $suggestion['summary'] ?? '',
            ]);

            if ($clusterId === false) {
                CLI::error("Failed to create cluster '{$suggestion['label']}'.");

                return;
            }
        }

        foreach ((array) $suggestion['ids'] as $feedbackId) {
            $feedbackModel->update((int) $feedbackId, [
                'cluster_id' => $clusterId,
                'status'     => StatusEnum::Grouped,
            ]);
        }
    }
}
