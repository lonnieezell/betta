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
use Myth\Betta\Enums\PriorityEnum;
use Myth\Betta\Models\FeedbackClusterModel;

class FeedbackClusterCreateCommand extends BaseCommand
{
    protected $group       = 'Betta';
    protected $name        = 'feedback:cluster:create';
    protected $description = 'Create a new feedback cluster.';
    protected $arguments   = [
        'label' => 'The cluster label',
    ];
    protected $options = [
        '--priority' => 'Priority (low, medium, high, critical). Default: medium',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): void
    {
        $label       = $params[0] ?? null;
        $priorityVal = $params['priority'] ?? CLI::getOption('priority') ?? 'medium';

        if (! is_string($label) || trim($label) === '') {
            CLI::error('A label argument is required.');

            return;
        }

        $priority = PriorityEnum::from((string) $priorityVal);

        $id = (new FeedbackClusterModel())->insert([
            'label'    => trim($label),
            'priority' => $priority,
        ]);

        CLI::write((string) $id);
    }
}
