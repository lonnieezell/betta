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

namespace Myth\Betta\Config;

use CodeIgniter\Config\BaseService;
use Myth\Betta\Services\GitHubService;
use Myth\Scribe\AIService;

class Services extends BaseService
{
    /**
     * Returns the scribe AI service.
     * Requires lonnieezell/scribe to be installed (guarded by class_exists in callers).
     */
    public static function scribe(bool $getShared = true): AIService
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

        $token = (string) (env('GITHUB_TOKEN') ?? $config->githubToken);
        $owner = (string) (env('GITHUB_OWNER') ?? $config->githubOwner);
        $repo  = (string) (env('GITHUB_REPO') ?? $config->githubRepo);

        return new GitHubService($token, $owner, $repo, $config->githubTimeout);
    }
}
