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

use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;
use Myth\Betta\Config\Betta;
use Myth\Betta\DTOs\FeedbackListFilters;
use Myth\Betta\Enums\CategoryEnum;
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
        'platform',
        'category',
        'message',
        'url_context',
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
        'category' => 'enum[' . CategoryEnum::class . ']',
        'status'   => 'enum[' . StatusEnum::class . ']',
    ];

    protected $validationRules = [
        'message'   => 'required',
        'email'    => 'permit_empty|valid_email',
        'category' => 'permit_empty|in_list[bug,ux,feature,other]',
        'status'   => 'permit_empty|in_list[new,reviewed,grouped,dismissed]',
    ];

    public function __construct(?ConnectionInterface $db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);

        /** @phpstan-ignore codeigniter.factoriesClassConstFetch */
        $platforms = config(Betta::class)->platforms;
        $this->validationRules['platform'] = 'permit_empty|in_list[' . implode(',', $platforms) . ']';
    }

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

        if ($filters->platform !== null) {
            $builder->where('f.platform', $filters->platform);
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
