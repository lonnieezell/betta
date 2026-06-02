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
            $feedbackModel->update($item->id, ['status' => StatusEnum::Reviewed]);

            $action = $this->promptAction();

            if ($action === 'q') {
                return EXIT_SUCCESS;
            }

            if ($action === 'd') {
                $feedbackModel->update($item->id, ['status' => StatusEnum::Dismissed]);
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
        $category = $item->category instanceof \BackedEnum ? $item->category->value : (string) $item->category;
        $status   = $item->status instanceof \BackedEnum ? $item->status->value : (string) $item->status;
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
            $action = strtolower((string) CLI::prompt('Action (a=assign, n=new cluster, d=dismiss, q=quit)'));
        } while (! in_array($action, $valid, true));

        return $action;
    }

    /**
     * @param FeedbackModel        $feedbackModel
     * @param FeedbackClusterModel $clusterModel
     */
    private function handleAssign(object $item, FeedbackModel $feedbackModel, FeedbackClusterModel $clusterModel): void
    {
        $clusters = $clusterModel->findAll();

        if ($clusters === []) {
            CLI::write("No clusters found. Use 'n' to create one.");

            return;
        }

        foreach ($clusters as $cluster) {
            CLI::write("[{$cluster->id}] {$cluster->label}");
        }

        $clusterId = null;

        while ($clusterId === null) {
            $input = (string) CLI::prompt('Cluster ID');

            if (ctype_digit($input) && $clusterModel->find((int) $input) !== null) {
                $clusterId = (int) $input;
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

    /**
     * @param FeedbackModel        $feedbackModel
     * @param FeedbackClusterModel $clusterModel
     */
    private function handleNewCluster(object $item, FeedbackModel $feedbackModel, FeedbackClusterModel $clusterModel): void
    {
        $label     = (string) CLI::prompt('Cluster label');
        $clusterId = $clusterModel->insert(['label' => $label]);

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
