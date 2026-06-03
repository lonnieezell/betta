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

namespace Myth\Betta\Config;

use CodeIgniter\Filters\CSRF;
use Myth\Betta\Filters\RateLimitFilter;

class Registrar
{
    public static function Filters(): array
    {
        return [
            'aliases' => [
                'betta-rate-limit' => RateLimitFilter::class,
                'betta-csrf'       => CSRF::class,
            ],
        ];
    }
}
