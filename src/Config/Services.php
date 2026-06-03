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

use CodeIgniter\Config\BaseService;
use Myth\Scribe\Services\ScribeService;

class Services extends BaseService
{
    /**
     * Returns the myth/scribe AI service.
     * Requires myth/scribe to be installed (guarded by class_exists in callers).
     */
    public static function scribe(bool $getShared = true): ScribeService
    {
        return static::getSharedInstance('scribe', $getShared);
    }
}
