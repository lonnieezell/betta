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
use Myth\Betta\Enums\StatusEnum;
use Myth\Betta\Models\FeedbackClusterModel;
use Myth\Betta\Models\FeedbackModel;

class FeedbackReviewCommand extends BaseCommand
{
    protected $group       = 'Betta';
    protected $name        = 'feedback:review';
    protected $description = 'Interactive triage session for feedback items.';
    protected $arguments   = [
        'id' => '[Optional] Start from this feedback item ID; omit to start from the oldest new item.',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        $feedbackModel = new FeedbackModel();
        $clusterModel  = new FeedbackClusterModel();

        $startId = isset($params[0]) && ctype_digit($params[0]) ? (int) $params[0] : null;

        if ($startId !== null && $startId <= 0) {
            CLI::error('Usage: feedback:review [id]');

            return EXIT_ERROR;
        }

        if ($startId !== null) {
            $item = $feedbackModel->find($startId);
            if ($item === null) {
                CLI::error("Feedback item {$startId} not found.");

                return EXIT_ERROR;
            }
        } else {
            $item = $this->nextNew($feedbackModel);
            if ($item === null) {
                CLI::write('No more items to review.');

                return EXIT_SUCCESS;
            }
        }

        while ($item !== null) {
            $this->displayItem($item);

            if ($item->status === StatusEnum::New && ! $feedbackModel->update($item->id, ['status' => StatusEnum::Reviewed])) {
                CLI::error("Failed to mark feedback {$item->id} as reviewed.");
                return EXIT_ERROR;
            }

            $action = $this->promptAction();

            if ($action === 'q') {
                return EXIT_SUCCESS;
            }

            if ($action === 'd') {
                if (! $feedbackModel->update($item->id, ['status' => StatusEnum::Dismissed])) {
                    CLI::error("Failed to dismiss feedback {$item->id}.");

                    return EXIT_ERROR;
                }
                CLI::write("Feedback {$item->id} dismissed.");
            } elseif ($action === 'a') {
                $this->handleAssign($item, $feedbackModel, $clusterModel);
            } elseif ($action === 'n') {
                $this->handleNewCluster($item, $feedbackModel, $clusterModel);
            }

            $item = $this->nextNew($feedbackModel);
            if ($item === null) {
                CLI::write('No more items to review.');
            }
        }

        return EXIT_SUCCESS;
    }

    private function displayItem(object $item): void
    {
        CLI::write('');
        $category = $item->category->value;
        $status   = $item->status->value;
        CLI::write(CLI::color("--- Feedback #{$item->id} ({$category} / {$status}) ---", 'yellow'));
        CLI::write('Email:   ' . ($item->email ?? '—'));
        CLI::write('URL:     ' . ($item->url_context ?? '—'));
        CLI::write('Date:    ' . ($item->created_at ?? '—'));
        CLI::write('');
        CLI::write((string) $item->message);
        CLI::write('');
    }

    private function promptAction(): string
    {
        $valid = ['a', 'n', 'd', 'q'];

        do {
            $action = strtolower(CLI::prompt('Action (a=assign, n=new cluster, d=dismiss, q=quit)'));
        } while (! in_array($action, $valid, true));

        return $action;
    }

    private function handleAssign(object $item, FeedbackModel $feedbackModel, FeedbackClusterModel $clusterModel): void
    {
        $clusters = $clusterModel->findAll();

        if ($clusters === []) {
            CLI::write("No clusters found. Use 'n' to create one.");

            return;
        }

        $clusterMap = [];

        foreach ($clusters as $cluster) {
            CLI::write("[{$cluster->id}] {$cluster->label}");
            $clusterMap[$cluster->id] = true;
        }

        $clusterId = null;

        while ($clusterId === null) {
            $input   = CLI::prompt('Cluster ID');
            $inputId = ctype_digit($input) ? (int) $input : 0;

            if ($inputId > 0 && isset($clusterMap[$inputId])) {
                $clusterId = $inputId;
            } else {
                CLI::error("Cluster '{$input}' not found.");
            }
        }

        $feedbackModel->update($item->id, [
            'cluster_id' => $clusterId,
            'status'     => StatusEnum::Grouped,
        ]);
        CLI::write("Feedback {$item->id} assigned to cluster {$clusterId}.");
    }

    private function handleNewCluster(object $item, FeedbackModel $feedbackModel, FeedbackClusterModel $clusterModel): void
    {
        $label = '';

        while ($label === '') {
            $label = trim(CLI::prompt('Cluster label'));

            if ($label === '') {
                CLI::error('Cluster label cannot be empty.');
            }
        }

        $clusterId = $clusterModel->insert(['label' => $label]);

        if ($clusterId === false) {
            CLI::error('Failed to create cluster.');

            return;
        }

        $feedbackModel->update($item->id, [
            'cluster_id' => $clusterId,
            'status'     => StatusEnum::Grouped,
        ]);
        CLI::write("Created cluster '{$label}' and assigned feedback {$item->id}.");
    }

    private function nextNew(FeedbackModel $feedbackModel): ?object
    {
        return $feedbackModel
            ->where('status', StatusEnum::New->value)
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->first();
    }
}
