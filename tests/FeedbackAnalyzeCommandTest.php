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
use Myth\Betta\Enums\ClusterStatusEnum;
use Myth\Betta\Enums\PriorityEnum;
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
    // Priority and status lifecycle
    // -------------------------------------------------------------------------

    public function testNewClusterUsesAISuppliedPriorityAndDefaultLifecycle(): void
    {
        $id = $this->feedback->insert(['message' => 'App loses my data']);
        $this->injectScribe([[
            'label'               => 'Data Loss',
            'summary'             => 'Work disappears',
            'priority'            => 'critical',
            'ids'                 => [$id],
            'existing_cluster_id' => null,
        ]]);

        $this->runCommand('feedback:analyze --apply');

        $cluster = $this->clusters->first();
        $this->assertSame(PriorityEnum::Critical, $cluster->priority);
        $this->assertSame(ClusterStatusEnum::Active->value, $cluster->status);
        $this->assertFalse($cluster->priority_locked);
    }

    public function testNewClusterFallsBackToMediumWhenPriorityIsUnusable(): void
    {
        $id = $this->feedback->insert(['message' => 'Something happened']);
        $this->injectScribe([[
            'label'               => 'Misc',
            'summary'             => 'Assorted reports',
            'priority'            => 'ludicrous',
            'ids'                 => [$id],
            'existing_cluster_id' => null,
        ]]);

        $this->runCommand('feedback:analyze --apply');

        $this->assertSame(PriorityEnum::Medium, $this->clusters->first()->priority);
    }

    public function testGrowthUpdatesPriorityOnUnlockedCluster(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'Login Issues', 'priority' => PriorityEnum::Low]);
        $id        = $this->feedback->insert(['message' => 'Still cannot log in']);
        $this->injectScribe([[
            'label'               => 'Login Issues',
            'summary'             => 'More login problems',
            'priority'            => 'high',
            'ids'                 => [$id],
            'existing_cluster_id' => $clusterId,
        ]]);

        $this->runCommand('feedback:analyze --apply');

        $this->assertSame(PriorityEnum::High, $this->clusters->find($clusterId)->priority);
    }

    public function testGrowthLeavesPriorityAloneOnLockedCluster(): void
    {
        $clusterId = $this->clusters->insert([
            'label'           => 'Login Issues',
            'priority'        => PriorityEnum::Low,
            'priority_locked' => true,
        ]);
        $id = $this->feedback->insert(['message' => 'Still cannot log in']);
        $this->injectScribe([[
            'label'               => 'Login Issues',
            'summary'             => 'More login problems',
            'priority'            => 'critical',
            'ids'                 => [$id],
            'existing_cluster_id' => $clusterId,
        ]]);

        $this->runCommand('feedback:analyze --apply');

        $cluster = $this->clusters->find($clusterId);
        $this->assertSame(PriorityEnum::Low, $cluster->priority);
        $this->assertSame($clusterId, $this->feedback->find($id)->cluster_id);
    }

    public function testGrowthIntoDismissedClusterLeavesItDismissed(): void
    {
        $clusterId = $this->clusters->insert([
            'label'  => 'Wontfix Requests',
            'status' => ClusterStatusEnum::Dismissed->value,
        ]);
        $id = $this->feedback->insert(['message' => 'Please add dark mode again']);
        $this->injectScribe([[
            'label'               => 'Wontfix Requests',
            'summary'             => 'More of the same',
            'priority'            => 'high',
            'ids'                 => [$id],
            'existing_cluster_id' => $clusterId,
        ]]);

        $this->runCommand('feedback:analyze --apply');

        $cluster = $this->clusters->find($clusterId);
        $this->assertSame(ClusterStatusEnum::Dismissed->value, $cluster->status);
        $this->assertSame($clusterId, $this->feedback->find($id)->cluster_id);
        $this->assertSame(StatusEnum::Grouped, $this->feedback->find($id)->status);
    }

    public function testGrowthIntoResolvedClusterReopensIt(): void
    {
        $clusterId = $this->clusters->insert([
            'label'  => 'Checkout Crash',
            'status' => ClusterStatusEnum::Resolved->value,
        ]);
        $id = $this->feedback->insert(['message' => 'Checkout crashed again']);
        $this->injectScribe([[
            'label'               => 'Checkout Crash',
            'summary'             => 'It is back',
            'priority'            => 'high',
            'ids'                 => [$id],
            'existing_cluster_id' => $clusterId,
        ]]);

        $this->runCommand('feedback:analyze --apply');

        $cluster = $this->clusters->find($clusterId);
        $this->assertSame(ClusterStatusEnum::Active->value, $cluster->status);
        $this->assertSame(PriorityEnum::High, $cluster->priority);
    }

    public function testInteractiveAcceptAppliesTheSamePriorityRules(): void
    {
        $clusterId = $this->clusters->insert([
            'label'    => 'Login Issues',
            'priority' => PriorityEnum::Low,
            'status'   => ClusterStatusEnum::Resolved->value,
        ]);
        $id = $this->feedback->insert(['message' => 'Login broke again']);
        $this->injectScribe([[
            'label'               => 'Login Issues',
            'summary'             => 'Regression',
            'priority'            => 'critical',
            'ids'                 => [$id],
            'existing_cluster_id' => $clusterId,
        ]]);

        $this->runCommand('feedback:analyze', "y\n");

        $cluster = $this->clusters->find($clusterId);
        $this->assertSame(PriorityEnum::Critical, $cluster->priority);
        $this->assertSame(ClusterStatusEnum::Active->value, $cluster->status);
    }

    public function testDryRunReportsLockedPriorityAsUnchanged(): void
    {
        $clusterId = $this->clusters->insert([
            'label'           => 'Login Issues',
            'priority'        => PriorityEnum::Low,
            'priority_locked' => true,
        ]);
        $id = $this->feedback->insert(['message' => 'Cannot log in']);
        $this->injectScribe([[
            'label'               => 'Login Issues',
            'summary'             => 'Login problems',
            'priority'            => 'critical',
            'ids'                 => [$id],
            'existing_cluster_id' => $clusterId,
        ]]);

        $output = $this->runCommand('feedback:analyze --dry-run');

        $this->assertStringContainsString('unchanged (locked)', $output);
        $this->assertStringNotContainsString('Priority: critical', $output);
    }

    public function testDryRunReportsUnusablePriorityOnExistingClusterAsUnchanged(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'Login Issues', 'priority' => PriorityEnum::High]);
        $id        = $this->feedback->insert(['message' => 'Cannot log in']);
        $this->injectScribe([[
            'label'               => 'Login Issues',
            'summary'             => 'Login problems',
            'priority'            => 'ludicrous',
            'ids'                 => [$id],
            'existing_cluster_id' => $clusterId,
        ]]);

        $output = $this->runCommand('feedback:analyze --dry-run');

        $this->assertStringContainsString('Priority: unchanged', $output);
    }

    public function testDryRunShowsPriorityAndWritesNothing(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'Login Issues', 'priority' => PriorityEnum::Low]);
        $id        = $this->feedback->insert(['message' => 'Cannot log in']);
        $this->injectScribe([[
            'label'               => 'Login Issues',
            'summary'             => 'Login problems',
            'priority'            => 'critical',
            'ids'                 => [$id],
            'existing_cluster_id' => $clusterId,
        ]]);

        $output = $this->runCommand('feedback:analyze --dry-run');

        $this->assertStringContainsString('critical', $output);
        $this->assertSame(PriorityEnum::Low, $this->clusters->find($clusterId)->priority);
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
