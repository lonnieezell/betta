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

class AddIndexesToFeedbackTable extends Migration
{
    public function up(): void
    {
        // status is always filtered; cluster_id is the most common secondary filter
        $this->forge->addKey(['status', 'cluster_id'], false, false, 'idx_feedback_status_cluster');
        $this->forge->addKey('category', false, false, 'idx_feedback_category');
        $this->forge->addKey('github_issue_url', false, false, 'idx_feedback_github_issue_url');
        $this->forge->processIndexes('betta_feedback');
    }

    public function down(): void
    {
        $this->forge->dropKey('betta_feedback', 'idx_feedback_status_cluster', false);
        $this->forge->dropKey('betta_feedback', 'idx_feedback_category', false);
        $this->forge->dropKey('betta_feedback', 'idx_feedback_github_issue_url', false);
    }
}
