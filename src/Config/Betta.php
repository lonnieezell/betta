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

    /**
     * Maximum number of ungrouped items to send to the AI in a single
     * feedback:analyze batch. Override with --limit at the CLI.
     */
    public int $analyzeBatchSize = 50;

    /**
     * Maximum feedback submissions allowed per IP within $rateLimitWindow seconds.
     * Set to 0 to disable rate limiting entirely.
     */
    public int $rateLimitRequests = 5;

    /**
     * Rolling window in seconds for the rate limit bucket.
     */
    public int $rateLimitWindow = 60;

    /**
     * GitHub personal access token (needs `repo` scope).
     * Set via GITHUB_TOKEN environment variable — never hard-code credentials.
     */
    public string $githubToken = '';

    /**
     * GitHub repository owner (user or organization name).
     * Set via GITHUB_OWNER environment variable.
     */
    public string $githubOwner = '';

    /**
     * GitHub repository name.
     * Set via GITHUB_REPO environment variable.
     */
    public string $githubRepo = '';
}
