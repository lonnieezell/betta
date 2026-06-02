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
use Myth\Betta\DTOs\FeedbackListFilters;
use Myth\Betta\Models\FeedbackModel;

class FeedbackListCommand extends BaseCommand
{
    protected $group       = 'Betta';
    protected $name        = 'feedback:list';
    protected $description = 'Browse and filter feedback submissions.';

    protected $arguments = [];

    protected $options = [
        '--category'  => 'Filter by category (bug, ux, feature, other)',
        '--status'    => 'Filter by status (default: new)',
        '--ungrouped' => 'Show only items with no cluster assignment',
        '--cluster'   => 'Filter by cluster ID',
        '--limit'     => 'Maximum rows to return (default: 20)',
    ];

    public function run(array $params): void
    {
        $category  = $params['category'] ?? CLI::getOption('category');
        $status    = $params['status'] ?? CLI::getOption('status') ?? 'new';
        $ungrouped = array_key_exists('ungrouped', $params) || CLI::getOption('ungrouped') !== null;
        $cluster   = $params['cluster'] ?? CLI::getOption('cluster');
        $limit     = $params['limit'] ?? CLI::getOption('limit');

        $filters = new FeedbackListFilters(
            category: is_string($category) ? $category : null,
            status: (string) $status,
            ungrouped: $ungrouped,
            cluster: $cluster !== null ? (int) $cluster : null,
            limit: $limit !== null ? (int) $limit : 20,
        );

        $rows = (new FeedbackModel())->forList($filters);

        if ($rows === []) {
            CLI::write('No feedback found.');

            return;
        }

        $tableData = [];

        foreach ($rows as $row) {
            $preview = mb_strlen($row->message) > 50
                ? mb_substr($row->message, 0, 50) . '…'
                : $row->message;

            $tableData[] = [
                (string) $row->id,
                $row->category,
                $row->status,
                $row->cluster_label,
                $preview,
            ];
        }

        CLI::table($tableData, ['ID', 'Category', 'Status', 'Cluster', 'Message']);
    }
}
