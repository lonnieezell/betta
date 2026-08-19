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

namespace Myth\Betta\Enums;

enum ClusterStatusEnum: string
{
    case Active    = 'active';
    case Resolved  = 'resolved';
    case Dismissed = 'dismissed';
}
