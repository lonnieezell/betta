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
use Myth\Betta\Enums\ClusterStatusEnum;
use Myth\Betta\Enums\PriorityEnum;
use Myth\Betta\Enums\StatusEnum;
use Myth\Betta\Models\FeedbackClusterModel;
use Myth\Betta\Models\FeedbackModel;
use Myth\Betta\Prompts\ClusterFeedbackPrompt;
use Myth\Scribe\AIService;
use Myth\Scribe\Exceptions\AIException;

class FeedbackAnalyzeCommand extends BaseCommand
{
    protected $group       = 'Betta';
    protected $name        = 'feedback:analyze';
    protected $description = 'AI-assisted clustering of ungrouped feedback via lonnieezell/scribe.';
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
        if (! class_exists(AIService::class)) {
            CLI::error('lonnieezell/scribe is not installed. Add it with: composer require lonnieezell/scribe');

            return EXIT_ERROR;
        }

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
            static fn (object $c): array => [
                'id'              => $c->id,
                'label'           => $c->label,
                'priority_locked' => $c->priority_locked,
            ],
            $existingClusters,
        );

        $prompt = new ClusterFeedbackPrompt($itemsData, $clustersData);

        try {
            $scribe   = service('scribe');
            $response = $scribe->run($prompt)->toArray();
        } catch (AIException $e) {
            CLI::error('AI error: ' . $e->getMessage());

            return EXIT_ERROR;
        } catch (Exception $e) {
            CLI::error('Unexpected error from scribe: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        $suggestions = $this->normalizeSuggestions($response);

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
    /**
     * The schema asks the model for a list of cluster objects, but nothing
     * enforces that it obliges — scribe hands back whatever JSON came out of
     * the provider. Anything that isn't a usable suggestion is dropped here so
     * one malformed entry can't fatal the whole run further down.
     *
     * @param array<string, mixed> $decoded
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSuggestions(array $decoded): array
    {
        $suggestions = [];

        foreach ($decoded as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $suggestion = [];

            foreach ($entry as $key => $value) {
                $suggestion[(string) $key] = $value;
            }

            if (isset($suggestion['label'], $suggestion['ids'])) {
                $suggestions[] = $suggestion;
            }
        }

        return $suggestions;
    }

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
        $existing   = null;

        if ($existingId !== null) {
            $existingLabel = '';

            foreach ($clustersData as $c) {
                if ($c['id'] === $existingId) {
                    $existing      = $c;
                    $existingLabel = " → existing cluster #{$existingId}";
                    break;
                }
            }

            CLI::write(CLI::color("Cluster: {$suggestion['label']}{$existingLabel}", 'yellow'));
        } else {
            CLI::write(CLI::color("New cluster: {$suggestion['label']}", 'yellow'));
        }

        CLI::write("Summary: {$suggestion['summary']}");
        CLI::write('Priority: ' . $this->describePriority($suggestion, $existing));
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
     * The priority applySuggestion() would actually write, phrased for display.
     *
     * @param array<string, mixed>      $suggestion
     * @param array<string, mixed>|null $existing   The cluster being merged into, if any.
     */
    private function describePriority(array $suggestion, ?array $existing): string
    {
        $priority = $this->suggestedPriority($suggestion);

        if ($existing === null) {
            return ($priority ?? PriorityEnum::Medium)->value;
        }

        if ($existing['priority_locked'] === true) {
            return 'unchanged (locked)';
        }

        return $priority instanceof PriorityEnum ? $priority->value : 'unchanged';
    }

    /**
     * The priority the AI suggested, or null when it supplied none the enum recognises.
     *
     * @param array<string, mixed> $suggestion
     */
    private function suggestedPriority(array $suggestion): ?PriorityEnum
    {
        $priority = $suggestion['priority'] ?? null;

        return is_string($priority) ? PriorityEnum::tryFrom($priority) : null;
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
        $priority   = $this->suggestedPriority($suggestion);

        if ($existingId !== null) {
            $existingId = (int) $existingId;
            $cluster    = $clusterModel->find($existingId);

            if ($cluster === null) {
                CLI::error("Cluster {$existingId} no longer exists; skipping suggestion.");

                return;
            }

            $clusterId = $existingId;
            $changes   = [];

            // A manual priority edit locks the cluster; the AI must not overrule it.
            if (! $cluster->priority_locked && $priority !== null) {
                $changes['priority'] = $priority;
            }

            // New feedback reopens a resolved cluster, but a dismissed one stays dismissed.
            if ($cluster->status === ClusterStatusEnum::Resolved->value) {
                $changes['status'] = ClusterStatusEnum::Active->value;
            }

            if ($changes !== []) {
                $clusterModel->update($clusterId, $changes);
            }
        } else {
            $clusterId = $clusterModel->insert([
                'label'           => $suggestion['label'],
                'summary'         => $suggestion['summary'] ?? '',
                'priority'        => $priority ?? PriorityEnum::Medium,
                'status'          => ClusterStatusEnum::Active->value,
                'priority_locked' => false,
            ]);

            if ($clusterId === false) {
                CLI::error("Failed to create cluster '{$suggestion['label']}'.");

                return;
            }
        }

        $ids = (array) $suggestion['ids'];

        foreach ($ids as $feedbackId) {
            $feedbackModel->update((int) $feedbackId, [
                'cluster_id' => $clusterId,
                'status'     => StatusEnum::Grouped,
            ]);
        }

        log_message('info', sprintf(
            'betta.analyze: grouped %d item(s) into cluster #%d "%s"',
            count($ids),
            $clusterId,
            $suggestion['label'],
        ));
    }
}
