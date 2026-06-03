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

class DropSentimentFromFeedbackTable extends Migration
{
    public function up(): void
    {
        $this->forge->dropColumn('betta_feedback', 'sentiment');
    }

    public function down(): void
    {
        $this->forge->addColumn('betta_feedback', [
            'sentiment' => [
                'type' => 'TINYINT',
                'null' => true,
                'after' => 'url_context',
            ],
        ]);
    }
}
