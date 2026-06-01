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

use CodeIgniter\Config\BaseConfig;

class Betta extends BaseConfig
{
    /**
     * The route prefix for the feedback endpoints.
     * Produces GET /{routePrefix} and POST /{routePrefix}/submit.
     */
    public string $routePrefix = 'feedback';

    /**
     * Whether to accept new feedback submissions.
     * When false, the closed view is shown instead of the form.
     */
    public bool $acceptSubmissions = true;
}
