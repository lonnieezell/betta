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
use Myth\Betta\Services\GitHubService;
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

    /**
     * Returns the GitHub API service.
     * Credentials are read from GITHUB_TOKEN / GITHUB_OWNER / GITHUB_REPO env vars
     * (with fallback to Betta config properties).
     */
    public static function github(bool $getShared = true): GitHubService
    {
        if ($getShared) {
            return static::getSharedInstance('github');
        }

        $config = config('Betta');
        assert($config instanceof Betta);

        $token = (string) (env('GITHUB_TOKEN') ?? $config->githubToken);
        $owner = (string) (env('GITHUB_OWNER') ?? $config->githubOwner);
        $repo  = (string) (env('GITHUB_REPO') ?? $config->githubRepo);

        return new GitHubService($token, $owner, $repo, $config->githubTimeout);
    }
}
