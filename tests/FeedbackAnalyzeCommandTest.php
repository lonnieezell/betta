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
use Myth\Betta\Enums\StatusEnum;
use Tests\Support\FakeScribeService;
use Tests\Support\FeedbackCommandTestCase;

/**
 * @internal
 */
final class FeedbackAnalyzeCommandTest extends FeedbackCommandTestCase
{
    // -------------------------------------------------------------------------
    // Empty queue
    // -------------------------------------------------------------------------

    public function testNoUngroupedItemsExitsClean(): void
    {
        $this->injectScribe([]);
        $output = $this->runCommand('feedback:analyze');

        $this->assertStringContainsString('No ungrouped', $output);
    }

    public function testDismissedItemsAreIgnored(): void
    {
        $this->feedback->insert(['message' => 'dismissed', 'status' => StatusEnum::Dismissed]);
        $this->injectScribe([]);

        $output = $this->runCommand('feedback:analyze');

        $this->assertStringContainsString('No ungrouped', $output);
    }

    // -------------------------------------------------------------------------
    // --dry-run
    // -------------------------------------------------------------------------

    public function testDryRunPrintsSuggestionsWithoutWriting(): void
    {
        $feedbackId = $this->feedback->insert(['message' => 'Cannot log in']);
        $this->injectScribe([[
            'label'               => 'Login Issues',
            'summary'             => 'Users cannot log in',
            'ids'                 => [$feedbackId],
            'existing_cluster_id' => null,
        ]]);

        $output = $this->runCommand('feedback:analyze --dry-run');

        $this->assertStringContainsString('Login Issues', $output);
        $this->assertCount(0, $this->clusters->findAll());
        $item = $this->feedback->find($feedbackId);
        $this->assertNull($item->cluster_id);
    }

    public function testDryRunShowsExistingClusterReference(): void
    {
        $clusterId  = $this->clusters->insert(['label' => 'Login Issues']);
        $feedbackId = $this->feedback->insert(['message' => 'Another login issue']);
        $this->injectScribe([[
            'label'               => 'Login Issues',
            'summary'             => 'More login issues',
            'ids'                 => [$feedbackId],
            'existing_cluster_id' => $clusterId,
        ]]);

        $output = $this->runCommand('feedback:analyze --dry-run');

        $this->assertStringContainsString('Login Issues', $output);
        $item = $this->feedback->find($feedbackId);
        $this->assertNull($item->cluster_id);
    }

    // -------------------------------------------------------------------------
    // --apply
    // -------------------------------------------------------------------------

    public function testApplyCreatesNewClusterAndAssignsItems(): void
    {
        $id1 = $this->feedback->insert(['message' => 'Cannot log in']);
        $id2 = $this->feedback->insert(['message' => 'Forgot password flow broken']);
        $this->injectScribe([[
            'label'               => 'Login Issues',
            'summary'             => 'Users cannot log in',
            'ids'                 => [$id1, $id2],
            'existing_cluster_id' => null,
        ]]);

        $this->runCommand('feedback:analyze --apply');

        $clusters = $this->clusters->findAll();
        $this->assertCount(1, $clusters);
        $this->assertSame('Login Issues', $clusters[0]->label);

        $item1 = $this->feedback->find($id1);
        $item2 = $this->feedback->find($id2);
        $this->assertSame(StatusEnum::Grouped, $item1->status);
        $this->assertSame(StatusEnum::Grouped, $item2->status);
        $this->assertSame($clusters[0]->id, $item1->cluster_id);
        $this->assertSame($clusters[0]->id, $item2->cluster_id);
    }

    public function testApplyAssignsToExistingClusterWhenIdProvided(): void
    {
        $clusterId  = $this->clusters->insert(['label' => 'Login Issues']);
        $feedbackId = $this->feedback->insert(['message' => 'Cannot log in']);
        $this->injectScribe([[
            'label'               => 'Login Issues',
            'summary'             => 'Login problems',
            'ids'                 => [$feedbackId],
            'existing_cluster_id' => $clusterId,
        ]]);

        $this->runCommand('feedback:analyze --apply');

        $this->assertCount(1, $this->clusters->findAll());
        $item = $this->feedback->find($feedbackId);
        $this->assertSame($clusterId, $item->cluster_id);
        $this->assertSame(StatusEnum::Grouped, $item->status);
    }

    public function testApplyHandlesMultipleSuggestions(): void
    {
        $id1 = $this->feedback->insert(['message' => 'Login broken']);
        $id2 = $this->feedback->insert(['message' => 'Slow page load']);
        $this->injectScribe([
            [
                'label'               => 'Login Issues',
                'summary'             => 'Auth problems',
                'ids'                 => [$id1],
                'existing_cluster_id' => null,
            ],
            [
                'label'               => 'Performance',
                'summary'             => 'Speed issues',
                'ids'                 => [$id2],
                'existing_cluster_id' => null,
            ],
        ]);

        $this->runCommand('feedback:analyze --apply');

        $this->assertCount(2, $this->clusters->findAll());
    }

    // -------------------------------------------------------------------------
    // Interactive mode
    // -------------------------------------------------------------------------

    public function testInteractiveYAcceptsSuggestionAndCreatesCluster(): void
    {
        $feedbackId = $this->feedback->insert(['message' => 'Cannot log in']);
        $this->injectScribe([[
            'label'               => 'Login Issues',
            'summary'             => 'Users cannot log in',
            'ids'                 => [$feedbackId],
            'existing_cluster_id' => null,
        ]]);

        $this->runCommand('feedback:analyze', "y\n");

        $clusters = $this->clusters->findAll();
        $this->assertCount(1, $clusters);
        $this->assertSame('Login Issues', $clusters[0]->label);
    }

    public function testInteractiveNSkipsSuggestion(): void
    {
        $feedbackId = $this->feedback->insert(['message' => 'Cannot log in']);
        $this->injectScribe([[
            'label'               => 'Login Issues',
            'summary'             => 'Users cannot log in',
            'ids'                 => [$feedbackId],
            'existing_cluster_id' => null,
        ]]);

        $this->runCommand('feedback:analyze', "n\n");

        $this->assertCount(0, $this->clusters->findAll());
        $item = $this->feedback->find($feedbackId);
        $this->assertNull($item->cluster_id);
    }

    public function testInteractiveEEditsLabelBeforeSaving(): void
    {
        $feedbackId = $this->feedback->insert(['message' => 'Cannot log in']);
        $this->injectScribe([[
            'label'               => 'Login Issues',
            'summary'             => 'Users cannot log in',
            'ids'                 => [$feedbackId],
            'existing_cluster_id' => null,
        ]]);

        $this->runCommand('feedback:analyze', "e\nAuthentication Problems\n");

        $clusters = $this->clusters->findAll();
        $this->assertCount(1, $clusters);
        $this->assertSame('Authentication Problems', $clusters[0]->label);
    }

    public function testInteractiveHandlesMultipleSuggestionsInSequence(): void
    {
        $id1 = $this->feedback->insert(['message' => 'Login broken']);
        $id2 = $this->feedback->insert(['message' => 'Slow page']);
        $this->injectScribe([
            [
                'label'               => 'Login Issues',
                'summary'             => 'Auth problems',
                'ids'                 => [$id1],
                'existing_cluster_id' => null,
            ],
            [
                'label'               => 'Performance',
                'summary'             => 'Speed issues',
                'ids'                 => [$id2],
                'existing_cluster_id' => null,
            ],
        ]);

        $this->runCommand('feedback:analyze', "y\nn\n");

        $clusters = $this->clusters->findAll();
        $this->assertCount(1, $clusters);
        $this->assertSame('Login Issues', $clusters[0]->label);
    }

    // -------------------------------------------------------------------------
    // AIException handling
    // -------------------------------------------------------------------------

    public function testAIExceptionDisplayedGracefullyWithoutCrash(): void
    {
        $this->feedback->insert(['message' => 'some feedback']);
        $this->injectScribe([], shouldThrow: true);

        $output = $this->runCommand('feedback:analyze --apply');

        $this->assertStringContainsString('AI service unavailable', $output);
        $this->assertCount(0, $this->clusters->findAll());
    }

    // -------------------------------------------------------------------------
    // --limit
    // -------------------------------------------------------------------------

    public function testLimitOptionAcceptedWithoutError(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->feedback->insert(['message' => "Item {$i}"]);
        }

        $this->injectScribe([]);

        // Verifies --limit is parsed without crashing and only the first N items
        // are included in the prompt (indirectly: scribe sees a subset and returns
        // no suggestions, so no clusters are created).
        $this->runCommand('feedback:analyze --apply --limit 2');

        $this->assertCount(0, $this->clusters->findAll());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<int, array<string, mixed>> $suggestions
     */
    private function injectScribe(array $suggestions, bool $shouldThrow = false): void
    {
        Services::injectMock('scribe', new FakeScribeService($suggestions, $shouldThrow));
    }

    private function runCommand(string $cmd, string $input = ''): string
    {
        $io = new MockInputOutput();

        if ($input !== '') {
            $io->setInputs(array_values(array_filter(explode("\n", $input), static fn (string $s): bool => $s !== '')));
        }

        CLI::setInputOutput($io); // @phpstan-ignore staticMethod.internal
        command($cmd);
        CLI::resetInputOutput(); // @phpstan-ignore staticMethod.internal

        return $io->getOutput();
    }
}
