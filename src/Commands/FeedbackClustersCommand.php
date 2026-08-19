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

class FeedbackClustersCommand extends BaseCommand
{
    protected $group       = 'Betta';
    protected $name        = 'feedback:clusters';
    protected $description = 'List feedback clusters.';
    protected $arguments   = [];
    protected $options     = [
        '--priority' => 'Filter by priority (low, medium, high, critical)',
        '--status'   => 'Filter by status (active, resolved, dismissed, all). Dismissed are hidden by default.',
        '--sort'     => 'Sort by: updated_at (default) or count',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): void
    {
        $priorityVal = $params['priority'] ?? CLI::getOption('priority');
        $statusVal   = $params['status'] ?? CLI::getOption('status');
        $sort        = $params['sort'] ?? CLI::getOption('sort') ?? 'updated_at';

        $priority = null;

        if (is_string($priorityVal)) {
            $priority = PriorityEnum::from($priorityVal);
        }

        $status = null;

        if (is_string($statusVal)) {
            if ($statusVal !== 'all' && ClusterStatusEnum::tryFrom($statusVal) === null) {
                CLI::error("Invalid status '{$statusVal}'. Valid values: active, resolved, dismissed, all.");

                return;
            }

            $status = $statusVal;
        }

        $clusters = (new FeedbackClusterModel())->findAllWithCount(
            priority: $priority,
            sort: (string) $sort,
            status: $status,
        );

        if ($clusters === []) {
            CLI::write('No clusters found.');

            return;
        }

        $tableData = [];

        foreach ($clusters as $cluster) {
            $tableData[] = [
                (string) $cluster->id,
                $cluster->label,
                $cluster->priority->value,
                $cluster->status,
                (string) $cluster->item_count,
                $cluster->updated_at,
            ];
        }

        CLI::table($tableData, ['ID', 'Label', 'Priority', 'Status', 'Items', 'Updated']);
    }
}
