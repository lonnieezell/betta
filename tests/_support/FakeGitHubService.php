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

namespace Tests\Support;

/**
 * Test double for GitHubService.
 * Inject via Services::injectMock('github', new FakeGitHubService())
 */
final class FakeGitHubService
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $created = [];

    private ?int $rateLimitRemaining = null;

    /**
     * @param list<string> $labels
     */
    public function createIssue(string $title, string $body, array $labels = []): string
    {
        $num             = count($this->created) + 1;
        $url             = "https://github.com/owner/repo/issues/{$num}";
        $this->created[] = ['title' => $title, 'body' => $body, 'labels' => $labels, 'url' => $url];

        return $url;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCreated(): array
    {
        return $this->created;
    }

    public function setRateLimitRemaining(?int $remaining): void
    {
        $this->rateLimitRemaining = $remaining;
    }

    public function getRateLimitRemaining(): ?int
    {
        return $this->rateLimitRemaining;
    }
}
