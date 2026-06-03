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

class AddGithubIssueUrlToFeedback extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('betta_feedback', [
            'github_issue_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'after'      => 'cluster_id',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('betta_feedback', 'github_issue_url');
    }
}
