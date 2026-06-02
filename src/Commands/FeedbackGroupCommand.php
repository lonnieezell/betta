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

class FeedbackGroupCommand extends BaseCommand
{
    protected $group       = 'Betta';
    protected $name        = 'feedback:group';
    protected $description = 'Assign a feedback item to a cluster.';
    protected $arguments   = [
        'id'         => 'The feedback item ID',
        'cluster_id' => 'The cluster ID to assign the item to',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params)
    {
        $feedbackId = isset($params[0]) && ctype_digit((string) $params[0]) ? (int) $params[0] : null;
        $clusterId  = isset($params[1]) && ctype_digit((string) $params[1]) ? (int) $params[1] : null;

        if ($feedbackId === null || $feedbackId <= 0 || $clusterId === null || $clusterId <= 0) {
            CLI::error('Usage: feedback:group <id> <cluster_id>');

            return EXIT_ERROR;
        }

        $feedbackModel = new FeedbackModel();
        $clusterModel  = new FeedbackClusterModel();

        if ($feedbackModel->find($feedbackId) === null) {
            CLI::error("Feedback item {$feedbackId} not found.");

            return EXIT_ERROR;
        }

        if ($clusterModel->find($clusterId) === null) {
            CLI::error("Cluster {$clusterId} not found.");

            return EXIT_ERROR;
        }

        if (! $feedbackModel->update($feedbackId, [
            'cluster_id' => $clusterId,
            'status'     => StatusEnum::Grouped,
        ])) {
            CLI::error("Failed to assign feedback {$feedbackId} to cluster {$clusterId}.");

            return EXIT_ERROR;
        }

        CLI::write("Feedback {$feedbackId} assigned to cluster {$clusterId}.");
    }
}
