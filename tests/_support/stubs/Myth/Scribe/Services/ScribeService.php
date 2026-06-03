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

namespace Myth\Scribe\Services;

use Myth\Scribe\Response\ScribeResponse;

/**
 * Stub for myth/scribe ScribeService — used by PHPStan and tests only.
 */
class ScribeService
{
    public function run(object $prompt): ScribeResponse
    {
        return new ScribeResponse();
    }
}
