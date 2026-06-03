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

namespace Myth\Betta\Models;

use CodeIgniter\Model;
use Myth\Betta\DTOs\FeedbackListFilters;
use Myth\Betta\Enums\CategoryEnum;
use Myth\Betta\Enums\SentimentEnum;
use Myth\Betta\Enums\StatusEnum;

class FeedbackModel extends Model
{
    protected $table            = 'betta_feedback';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'session_id',
        'email',
        'category',
        'message',
        'url_context',
        'sentiment',
        'status',
        'cluster_id',
        'github_issue_url',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * @var array<string, string>
     */
    protected array $casts = [
        'category'  => 'enum[' . CategoryEnum::class . ']',
        'status'    => 'enum[' . StatusEnum::class . ']',
        'sentiment' => '?enum[' . SentimentEnum::class . ']',
    ];

    protected $validationRules = [
        'message'   => 'required',
        'category'  => 'permit_empty|in_list[bug,ux,feature,other]',
        'status'    => 'permit_empty|in_list[new,reviewed,grouped,dismissed]',
        'sentiment' => 'permit_empty|in_list[-1,0,1]',
    ];

    /**
     * Returns feedback rows joined with cluster labels, applying the given filters.
     *
     * @return list<object>
     */
    public function forList(FeedbackListFilters $filters): array
    {
        $builder = $this->db->table('betta_feedback AS f')
            ->select('f.id, f.category, f.status, f.message, fc.label AS cluster_label')
            ->join('feedback_clusters AS fc', 'fc.id = f.cluster_id', 'left')
            ->where('f.status', $filters->status)
            ->orderBy('f.created_at', 'DESC')
            ->limit($filters->limit);

        if ($filters->category !== null) {
            $builder->where('f.category', $filters->category);
        }

        if ($filters->ungrouped) {
            $builder->where('f.cluster_id IS NULL', null, false);
        }

        if ($filters->cluster !== null) {
            $builder->where('f.cluster_id', $filters->cluster);
        }

        return $builder->get()->getResultObject();
    }
}
