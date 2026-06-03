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
use Myth\Betta\Models\FeedbackClusterModel;

class FeedbackClusterDeleteCommand extends BaseCommand
{
    protected $group       = 'Betta';
    protected $name        = 'feedback:cluster:delete';
    protected $description = 'Delete a feedback cluster and ungroup its items.';
    protected $arguments   = [
        'id' => 'Cluster ID',
    ];
    protected $options = [];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): void
    {
        $id = isset($params[0]) ? (int) $params[0] : null;

        $model   = new FeedbackClusterModel();
        $cluster = $id !== null ? $model->find($id) : null;

        if ($cluster === null) {
            CLI::error("Cluster #{$id} not found.");

            return;
        }

        $confirm = CLI::prompt("Delete cluster #{$id} \"{$cluster->label}\" and ungroup all its items?", ['yes', 'no']);

        if ($confirm !== 'yes') {
            CLI::write('Aborted.');

            return;
        }

        $model->deleteWithUngroup($id);
        CLI::write("Cluster #{$id} deleted.");
    }
}
