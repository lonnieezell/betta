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
use Myth\Betta\Enums\ClusterStatusEnum;
use Myth\Betta\Enums\PriorityEnum;
use Myth\Betta\Models\FeedbackClusterModel;

class FeedbackClusterEditCommand extends BaseCommand
{
    protected $group       = 'Betta';
    protected $name        = 'feedback:cluster:edit';
    protected $description = 'Edit an existing feedback cluster.';
    protected $arguments   = [
        'id' => 'Cluster ID',
    ];
    protected $options = [
        '--label'    => 'New label',
        '--priority' => 'New priority (low, medium, high, critical). Locks the priority against AI updates.',
        '--status'   => 'New status (active, resolved, dismissed)',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): void
    {
        $id          = isset($params[0]) ? (int) $params[0] : null;
        $label       = $params['label'] ?? CLI::getOption('label');
        $priorityVal = $params['priority'] ?? CLI::getOption('priority');
        $statusVal   = $params['status'] ?? CLI::getOption('status');

        if ($label === null && $priorityVal === null && $statusVal === null) {
            CLI::error('Provide at least --label, --priority, or --status.');

            return;
        }

        $status = null;

        if (is_string($statusVal)) {
            $status = ClusterStatusEnum::tryFrom($statusVal);

            if ($status === null) {
                CLI::error("Invalid status '{$statusVal}'. Valid values: active, resolved, dismissed.");

                return;
            }
        }

        $model   = new FeedbackClusterModel();
        $cluster = $id !== null ? $model->find($id) : null;

        if ($cluster === null) {
            CLI::error("Cluster #{$id} not found.");

            return;
        }

        $data = [];

        if (is_string($label)) {
            $data['label'] = $label;
        }

        // A priority set by hand is a deliberate judgement call, so lock it
        // against being overwritten by the next feedback:analyze run.
        if (is_string($priorityVal)) {
            $data['priority']        = PriorityEnum::from($priorityVal);
            $data['priority_locked'] = true;
        }

        if ($status !== null) {
            $data['status'] = $status->value;
        }

        $model->update($id, $data);
        CLI::write('Cluster updated.');
    }
}
