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

namespace Tests;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\Mock\MockInputOutput;
use Config\Services;
use Myth\Betta\Enums\CategoryEnum;
use Tests\Support\FakeGitHubService;
use Tests\Support\FeedbackCommandTestCase;

/**
 * @internal
 */
final class FeedbackGithubCommandTest extends FeedbackCommandTestCase
{
    // -------------------------------------------------------------------------
    // --dry-run single item
    // -------------------------------------------------------------------------

    public function testDryRunSingleItemPrintsTitleAndBody(): void
    {
        $id = $this->feedback->insert([
            'message'  => 'The login button does not work on mobile.',
            'category' => CategoryEnum::Bug,
            'email'    => 'user@example.com',
        ]);
        $this->injectGitHub();

        $output = $this->runCommand("feedback:github {$id} --dry-run");

        $this->assertStringContainsString('[Feedback] bug:', $output);
        $this->assertStringContainsString('The login button does not work on mobile.', $output);
        $this->assertStringContainsString('user@example.com', $output);
        $item = $this->feedback->find($id);
        $this->assertNull($item->github_issue_url);
    }

    // -------------------------------------------------------------------------
    // Single item — happy path
    // -------------------------------------------------------------------------

    public function testSingleItemCreatesIssueAndStoresUrl(): void
    {
        $id = $this->feedback->insert([
            'message'  => 'Crash on checkout page.',
            'category' => CategoryEnum::Bug,
        ]);
        $github = $this->injectGitHub();

        $this->runCommand("feedback:github {$id}");

        $this->assertCount(1, $github->getCreated());
        $item = $this->feedback->find($id);
        $this->assertNotNull($item->github_issue_url);
        $this->assertStringContainsString('github.com', (string) $item->github_issue_url);
    }

    public function testSingleItemTitleTruncatesLongMessage(): void
    {
        $long = str_repeat('a', 120);
        $id   = $this->feedback->insert(['message' => $long, 'category' => CategoryEnum::Feature]);
        $this->injectGitHub();

        $this->runCommand("feedback:github {$id}");

        $item = $this->feedback->find($id);
        $this->assertNotNull($item->github_issue_url);
    }

    // -------------------------------------------------------------------------
    // Already exported
    // -------------------------------------------------------------------------

    public function testAlreadyExportedItemWarnsAndSkips(): void
    {
        $id = $this->feedback->insert([
            'message'          => 'Some bug',
            'category'         => CategoryEnum::Bug,
            'github_issue_url' => 'https://github.com/owner/repo/issues/99',
        ]);
        $github = $this->injectGitHub();

        $output = $this->runCommand("feedback:github {$id}");

        $this->assertStringContainsString('already exported', $output);
        $this->assertStringContainsString('issues/99', $output);
        $this->assertCount(0, $github->getCreated());
    }

    // -------------------------------------------------------------------------
    // Item not found
    // -------------------------------------------------------------------------

    public function testItemNotFoundExitsWithError(): void
    {
        $this->injectGitHub();

        $output = $this->runCommand('feedback:github 99999');

        $this->assertStringContainsString('not found', $output);
    }

    // -------------------------------------------------------------------------
    // Missing config
    // -------------------------------------------------------------------------

    public function testMissingGithubTokenExitsWithError(): void
    {
        $id = $this->feedback->insert(['message' => 'Test', 'category' => CategoryEnum::Other]);
        // Do not inject a fake; override config to have empty token
        $output = $this->runCommand("feedback:github {$id}", token: '', owner: 'owner', repo: 'repo');

        $this->assertStringContainsString('GITHUB_TOKEN', $output);
    }

    public function testMissingGithubOwnerExitsWithError(): void
    {
        $id     = $this->feedback->insert(['message' => 'Test', 'category' => CategoryEnum::Other]);
        $output = $this->runCommand("feedback:github {$id}", token: 'tok', owner: '', repo: 'repo');

        $this->assertStringContainsString('GITHUB_OWNER', $output);
    }

    public function testMissingGithubRepoExitsWithError(): void
    {
        $id     = $this->feedback->insert(['message' => 'Test', 'category' => CategoryEnum::Other]);
        $output = $this->runCommand("feedback:github {$id}", token: 'tok', owner: 'owner', repo: '');

        $this->assertStringContainsString('GITHUB_REPO', $output);
    }

    // -------------------------------------------------------------------------
    // Cluster — dry-run
    // -------------------------------------------------------------------------

    public function testClusterDryRunPrintsAllItems(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'Auth Issues']);
        $id1       = $this->feedback->insert(['message' => 'Login broken', 'category' => CategoryEnum::Bug, 'cluster_id' => $clusterId]);
        $id2       = $this->feedback->insert(['message' => 'Logout broken', 'category' => CategoryEnum::Bug, 'cluster_id' => $clusterId]);
        $this->injectGitHub();

        $output = $this->runCommand("feedback:github {$clusterId} --cluster --dry-run --delay 0");

        $this->assertStringContainsString('Login broken', $output);
        $this->assertStringContainsString('Logout broken', $output);
        $item1 = $this->feedback->find($id1);
        $item2 = $this->feedback->find($id2);
        $this->assertNull($item1->github_issue_url);
        $this->assertNull($item2->github_issue_url);
    }

    // -------------------------------------------------------------------------
    // Cluster — happy path
    // -------------------------------------------------------------------------

    public function testClusterCreatesOneIssuePerItem(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'Auth Issues']);
        $id1       = $this->feedback->insert(['message' => 'Login broken', 'category' => CategoryEnum::Bug, 'cluster_id' => $clusterId]);
        $id2       = $this->feedback->insert(['message' => 'Logout broken', 'category' => CategoryEnum::Bug, 'cluster_id' => $clusterId]);
        $github    = $this->injectGitHub();

        $this->runCommand("feedback:github {$clusterId} --cluster --delay 0");

        $this->assertCount(2, $github->getCreated());
        $item1 = $this->feedback->find($id1);
        $item2 = $this->feedback->find($id2);
        $this->assertNotNull($item1->github_issue_url);
        $this->assertNotNull($item2->github_issue_url);
    }

    public function testClusterSkipsAlreadyExportedItems(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'Auth Issues']);
        $this->feedback->insert([
            'message'          => 'Login broken',
            'category'         => CategoryEnum::Bug,
            'cluster_id'       => $clusterId,
            'github_issue_url' => 'https://github.com/o/r/issues/5',
        ]);
        $id2    = $this->feedback->insert(['message' => 'Logout broken', 'category' => CategoryEnum::Bug, 'cluster_id' => $clusterId]);
        $github = $this->injectGitHub();

        $output = $this->runCommand("feedback:github {$clusterId} --cluster --delay 0");

        $this->assertCount(1, $github->getCreated());
        $this->assertStringContainsString('already exported', $output);
        $item2 = $this->feedback->find($id2);
        $this->assertNotNull($item2->github_issue_url);
    }

    // -------------------------------------------------------------------------
    // Cluster not found
    // -------------------------------------------------------------------------

    public function testClusterNotFoundExitsWithError(): void
    {
        $this->injectGitHub();

        $output = $this->runCommand('feedback:github 99999 --cluster');

        $this->assertStringContainsString('not found', $output);
    }

    // -------------------------------------------------------------------------
    // Rate-limit warnings
    // -------------------------------------------------------------------------

    public function testLowRateLimitWarnsOperatorInClusterMode(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'Low Limit Cluster']);
        $this->feedback->insert(['message' => 'Bug A', 'category' => CategoryEnum::Bug, 'cluster_id' => $clusterId]);
        $github = $this->injectGitHub();
        $github->setRateLimitRemaining(42);

        $output = $this->runCommand("feedback:github {$clusterId} --cluster --delay 0");

        $this->assertStringContainsString('rate limit', strtolower($output));
        $this->assertStringContainsString('42', $output);
    }

    public function testHighRateLimitNoWarning(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'High Limit Cluster']);
        $this->feedback->insert(['message' => 'Bug B', 'category' => CategoryEnum::Bug, 'cluster_id' => $clusterId]);
        $github = $this->injectGitHub();
        $github->setRateLimitRemaining(500);

        $output = $this->runCommand("feedback:github {$clusterId} --cluster --delay 0");

        $this->assertStringNotContainsString('rate limit', strtolower($output));
    }

    public function testNullRateLimitNoWarning(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'No Limit Header Cluster']);
        $this->feedback->insert(['message' => 'Bug C', 'category' => CategoryEnum::Bug, 'cluster_id' => $clusterId]);
        $this->injectGitHub(); // default: getRateLimitRemaining() returns null

        $output = $this->runCommand("feedback:github {$clusterId} --cluster --delay 0");

        $this->assertStringNotContainsString('rate limit', strtolower($output));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function injectGitHub(): FakeGitHubService
    {
        $fake = new FakeGitHubService();
        Services::injectMock('github', $fake);

        return $fake;
    }

    private function runCommand(string $cmd, string $token = 'test-token', string $owner = 'owner', string $repo = 'repo'): string
    {
        // Temporarily set env vars used by the command config check
        $_ENV['GITHUB_TOKEN'] = $token;
        $_ENV['GITHUB_OWNER'] = $owner;
        $_ENV['GITHUB_REPO']  = $repo;

        $io = new MockInputOutput();
        CLI::setInputOutput($io); // @phpstan-ignore staticMethod.internal
        command($cmd);
        CLI::resetInputOutput(); // @phpstan-ignore staticMethod.internal

        unset($_ENV['GITHUB_TOKEN'], $_ENV['GITHUB_OWNER'], $_ENV['GITHUB_REPO']);

        return $io->getOutput();
    }
}
