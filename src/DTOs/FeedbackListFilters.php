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

namespace Myth\Betta\DTOs;

readonly class FeedbackListFilters
{
    public function __construct(
        public ?string $category = null,
        public string $status = 'new',
        public bool $ungrouped = false,
        public ?int $cluster = null,
        public int $limit = 20,
    ) {
    }
}
