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

namespace Myth\Betta\Database\Migrations;

use CodeIgniter\Database\Migration;
use Myth\Betta\Enums\ClusterStatusEnum;

class AddStatusAndPriorityLockToClusters extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('feedback_clusters', [
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'default'    => ClusterStatusEnum::Active->value,
                'after'      => 'priority',
            ],
            'priority_locked' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'after'      => 'status',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('feedback_clusters', ['status', 'priority_locked']);
    }
}
