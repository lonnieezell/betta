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
}
