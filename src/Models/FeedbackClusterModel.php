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

namespace Myth\Betta\Models;

use CodeIgniter\Model;
use Myth\Betta\Enums\PriorityEnum;

class FeedbackClusterModel extends Model
{
    protected $table      = 'feedback_clusters';
    protected $primaryKey = 'id';

    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'label',
        'summary',
        'priority',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** @var array<string, string> */
    protected array $casts = [
        'priority' => 'enum[' . PriorityEnum::class . ']',
    ];

    /**
     * Returns all clusters with a computed item_count from a COUNT() join.
     * Manually casts priority to PriorityEnum and item_count to int so the
     * return type is consistent regardless of DB driver.
     *
     * @return list<object>
     */
    public function findAllWithCount(): array
    {
        $results = $this->db->table('feedback_clusters AS fc')
            ->select('fc.*, COUNT(fb.id) AS item_count')
            ->join('betta_feedback AS fb', 'fb.cluster_id = fc.id', 'left')
            ->groupBy('fc.id')
            ->get()
            ->getResultObject();

        foreach ($results as $row) {
            $row->priority   = PriorityEnum::from($row->priority);
            $row->item_count = (int) $row->item_count;
        }

        return $results;
    }
}
