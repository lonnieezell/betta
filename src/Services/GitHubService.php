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

namespace Myth\Betta\Services;

use CodeIgniter\HTTP\Exceptions\HTTPException;
use Config\Services as CIServices;
use RuntimeException;

class GitHubService
{
    private ?int $rateLimitRemaining = null;

    public function __construct(
        private readonly string $token,
        private readonly string $owner,
        private readonly string $repo,
        private readonly int $timeout = 30,
    ) {
    }

    public function getRateLimitRemaining(): ?int
    {
        return $this->rateLimitRemaining;
    }

    /**
     * Creates a GitHub issue and returns its HTML URL.
     *
     * @param list<string> $labels
     *
     * @throws RuntimeException on HTTP or API error
     */
    public function createIssue(string $title, string $body, array $labels = []): string
    {
        $client = CIServices::curlrequest();

        $payload = ['title' => $title, 'body' => $body];

        if ($labels !== []) {
            $payload['labels'] = $labels;
        }

        try {
            $response = $client->request('POST', "https://api.github.com/repos/{$this->owner}/{$this->repo}/issues", [
                'headers' => [
                    'Authorization'        => "Bearer {$this->token}",
                    'Accept'               => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'User-Agent'           => 'myth/betta',
                ],
                'json'    => $payload,
                'timeout' => $this->timeout,
            ]);
        } catch (HTTPException $e) {
            throw new RuntimeException('GitHub API request failed: ' . $e->getMessage(), 0, $e);
        }

        $remaining                = $response->getHeaderLine('X-RateLimit-Remaining');
        $this->rateLimitRemaining = $remaining !== '' ? (int) $remaining : null;

        /** @var array<string, mixed>|null $data */
        $data = json_decode((string) $response->getBody(), true);

        if (! is_array($data) || ! isset($data['html_url'])) {
            throw new RuntimeException('GitHub API did not return an issue URL. Response: ' . $response->getBody());
        }

        return (string) $data['html_url'];
    }
}
