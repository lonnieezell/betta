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
        '--priority' => 'New priority (low, medium, high, critical)',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): void
    {
        $id          = isset($params[0]) ? (int) $params[0] : null;
        $label       = $params['label'] ?? CLI::getOption('label');
        $priorityVal = $params['priority'] ?? CLI::getOption('priority');

        if ($label === null && $priorityVal === null) {
            CLI::error('Provide at least --label or --priority.');

            return;
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

        if (is_string($priorityVal)) {
            $data['priority'] = PriorityEnum::from($priorityVal);
        }

        $model->update($id, $data);
        CLI::write('Cluster updated.');
    }
}
