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

namespace Tests;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockInputOutput;
use Myth\Betta\Enums\CategoryEnum;
use Myth\Betta\Enums\StatusEnum;
use Myth\Betta\Models\FeedbackClusterModel;
use Myth\Betta\Models\FeedbackModel;
use Tests\Support\DatabaseTestTrait;

/**
 * @internal
 */
final class FeedbackListCommandTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'Myth\Betta';
    protected $DBGroup     = 'tests';
    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = false;
    private FeedbackModel $feedback;
    private FeedbackClusterModel $clusters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('betta_feedback')->truncate();
        $this->db->table('feedback_clusters')->truncate();
        $this->feedback = new FeedbackModel();
        $this->clusters = new FeedbackClusterModel();
    }

    // -------------------------------------------------------------------------
    // Default behaviour
    // -------------------------------------------------------------------------

    public function testNoFlagsReturnsNewItemsOnly(): void
    {
        $this->feedback->insert(['message' => 'New item', 'status' => StatusEnum::New]);
        $this->feedback->insert(['message' => 'Reviewed item', 'status' => StatusEnum::Reviewed]);
        $this->feedback->insert(['message' => 'Dismissed item', 'status' => StatusEnum::Dismissed]);

        $output = $this->runCommand('feedback:list');

        $this->assertStringContainsString('New item', $output);
        $this->assertStringNotContainsString('Reviewed item', $output);
        $this->assertStringNotContainsString('Dismissed item', $output);
    }

    public function testNoFlagsLimitsTo20Rows(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->feedback->insert(['message' => "Item {$i}", 'status' => StatusEnum::New]);
        }

        $output = $this->runCommand('feedback:list');

        $this->assertStringContainsString('Item 1', $output);
        $this->assertStringNotContainsString('Item 21', $output);
    }

    public function testEmptyResultShowsMessageNotTable(): void
    {
        $output = $this->runCommand('feedback:list');

        $this->assertStringContainsString('No feedback found', $output);
    }

    // -------------------------------------------------------------------------
    // --status flag
    // -------------------------------------------------------------------------

    public function testStatusFlagFiltersCorrectly(): void
    {
        $this->feedback->insert(['message' => 'New item', 'status' => StatusEnum::New]);
        $this->feedback->insert(['message' => 'Reviewed item', 'status' => StatusEnum::Reviewed]);

        $output = $this->runCommand('feedback:list --status reviewed');

        $this->assertStringNotContainsString('New item', $output);
        $this->assertStringContainsString('Reviewed item', $output);
    }

    public function testStatusDismissedShowsDismissedItems(): void
    {
        $this->feedback->insert(['message' => 'Dismissed item', 'status' => StatusEnum::Dismissed]);
        $this->feedback->insert(['message' => 'New item', 'status' => StatusEnum::New]);

        $output = $this->runCommand('feedback:list --status dismissed');

        $this->assertStringContainsString('Dismissed item', $output);
        $this->assertStringNotContainsString('New item', $output);
    }

    // -------------------------------------------------------------------------
    // --category flag
    // -------------------------------------------------------------------------

    public function testCategoryFlagFiltersCorrectly(): void
    {
        $this->feedback->insert(['message' => 'Bug report', 'category' => CategoryEnum::Bug, 'status' => StatusEnum::New]);
        $this->feedback->insert(['message' => 'Feature request', 'category' => CategoryEnum::Feature, 'status' => StatusEnum::New]);

        $output = $this->runCommand('feedback:list --category bug');

        $this->assertStringContainsString('Bug report', $output);
        $this->assertStringNotContainsString('Feature request', $output);
    }

    // -------------------------------------------------------------------------
    // --ungrouped flag
    // -------------------------------------------------------------------------

    public function testUngroupedFlagReturnsItemsWithNoCluster(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'Cluster A']);
        $this->feedback->insert(['message' => 'In cluster', 'status' => StatusEnum::New, 'cluster_id' => $clusterId]);
        $this->feedback->insert(['message' => 'No cluster', 'status' => StatusEnum::New]);

        $output = $this->runCommand('feedback:list --ungrouped');

        $this->assertStringContainsString('No cluster', $output);
        $this->assertStringNotContainsString('In cluster', $output);
    }

    public function testUngroupedCombinesWithStatusFilter(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'Cluster A']);
        $this->feedback->insert(['message' => 'Reviewed no cluster', 'status' => StatusEnum::Reviewed]);
        $this->feedback->insert(['message' => 'New no cluster', 'status' => StatusEnum::New]);
        $this->feedback->insert(['message' => 'Reviewed in cluster', 'status' => StatusEnum::Reviewed, 'cluster_id' => $clusterId]);

        $output = $this->runCommand('feedback:list --ungrouped --status reviewed');

        $this->assertStringContainsString('Reviewed no cluster', $output);
        $this->assertStringNotContainsString('New no cluster', $output);
        $this->assertStringNotContainsString('Reviewed in cluster', $output);
    }

    // -------------------------------------------------------------------------
    // --cluster flag
    // -------------------------------------------------------------------------

    public function testClusterFlagFiltersToClusterItems(): void
    {
        $clusterA = $this->clusters->insert(['label' => 'Cluster A']);
        $clusterB = $this->clusters->insert(['label' => 'Cluster B']);
        $this->feedback->insert(['message' => 'In A', 'status' => StatusEnum::New, 'cluster_id' => $clusterA]);
        $this->feedback->insert(['message' => 'In B', 'status' => StatusEnum::New, 'cluster_id' => $clusterB]);

        $output = $this->runCommand("feedback:list --cluster {$clusterA}");

        $this->assertStringContainsString('In A', $output);
        $this->assertStringNotContainsString('In B', $output);
    }

    public function testInvalidClusterIdReturnsEmpty(): void
    {
        $this->feedback->insert(['message' => 'Some item', 'status' => StatusEnum::New]);

        $output = $this->runCommand('feedback:list --cluster 9999');

        $this->assertStringContainsString('No feedback found', $output);
    }

    // -------------------------------------------------------------------------
    // --limit flag
    // -------------------------------------------------------------------------

    public function testLimitFlagRestrictsRowCount(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->feedback->insert(['message' => "Item {$i}", 'status' => StatusEnum::New]);
        }

        $output = $this->runCommand('feedback:list --limit 3');

        // Table should contain exactly 3 data rows — check presence/absence
        $this->assertStringContainsString('Item', $output);
        // Rows 4+ should not appear because limit=3 applies in insertion order
        $this->assertStringNotContainsString('Item 4', $output);
    }

    // -------------------------------------------------------------------------
    // Table columns
    // -------------------------------------------------------------------------

    public function testClusterLabelShownForGroupedItems(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'Login Bugs']);
        $this->feedback->insert(['message' => 'Grouped item', 'status' => StatusEnum::New, 'cluster_id' => $clusterId]);

        $output = $this->runCommand('feedback:list');

        $this->assertStringContainsString('Login Bugs', $output);
    }

    public function testClusterLabelShowsDashForUngroupedItems(): void
    {
        $this->feedback->insert(['message' => 'Standalone item', 'status' => StatusEnum::New]);

        $output = $this->runCommand('feedback:list');

        $this->assertStringContainsString('—', $output);
    }

    public function testMessageTruncatedAt50CharsWithEllipsis(): void
    {
        $long = str_repeat('x', 60);
        $this->feedback->insert(['message' => $long, 'status' => StatusEnum::New]);

        $output = $this->runCommand('feedback:list');

        $expected = mb_substr($long, 0, 50) . '…';
        $this->assertStringContainsString($expected, $output);
    }

    public function testShortMessageNotTruncated(): void
    {
        $this->feedback->insert(['message' => 'Short message', 'status' => StatusEnum::New]);

        $output = $this->runCommand('feedback:list');

        $this->assertStringContainsString('Short message', $output);
        $this->assertStringNotContainsString('Short message…', $output);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function runCommand(string $cmd): string
    {
        $io = new MockInputOutput();
        CLI::setInputOutput($io); // @phpstan-ignore staticMethod.internal
        command($cmd);
        CLI::resetInputOutput(); // @phpstan-ignore staticMethod.internal

        return $io->getOutput();
    }
}
