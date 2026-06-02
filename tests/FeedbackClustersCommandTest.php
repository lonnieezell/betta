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
use Myth\Betta\Enums\PriorityEnum;
use Myth\Betta\Enums\StatusEnum;
use Myth\Betta\Models\FeedbackClusterModel;
use Myth\Betta\Models\FeedbackModel;
use Tests\Support\DatabaseTestTrait;

/**
 * @internal
 */
final class FeedbackClustersCommandTest extends CIUnitTestCase
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
    // feedback:clusters
    // -------------------------------------------------------------------------

    public function testClustersListShowsAllClusters(): void
    {
        $this->clusters->insert(['label' => 'Login Issues', 'priority' => PriorityEnum::High]);
        $this->clusters->insert(['label' => 'UI Bugs', 'priority' => PriorityEnum::Low]);

        $output = $this->runCommand('feedback:clusters');

        $this->assertStringContainsString('Login Issues', $output);
        $this->assertStringContainsString('UI Bugs', $output);
    }

    public function testClustersListShowsItemCount(): void
    {
        $id = $this->clusters->insert(['label' => 'Cluster A']);
        $this->feedback->insert(['message' => 'Item 1', 'cluster_id' => $id]);
        $this->feedback->insert(['message' => 'Item 2', 'cluster_id' => $id]);

        $output = $this->runCommand('feedback:clusters');

        $this->assertStringContainsString('2', $output);
    }

    public function testClustersListEmptyShowsMessage(): void
    {
        $output = $this->runCommand('feedback:clusters');

        $this->assertStringContainsString('No clusters found', $output);
    }

    public function testClustersListFiltersByPriority(): void
    {
        $this->clusters->insert(['label' => 'High cluster', 'priority' => PriorityEnum::High]);
        $this->clusters->insert(['label' => 'Low cluster', 'priority' => PriorityEnum::Low]);

        $output = $this->runCommand('feedback:clusters --priority high');

        $this->assertStringContainsString('High cluster', $output);
        $this->assertStringNotContainsString('Low cluster', $output);
    }

    public function testClustersListSortsByCount(): void
    {
        $clusterA = $this->clusters->insert(['label' => 'Few']);
        $clusterB = $this->clusters->insert(['label' => 'Many']);
        $this->feedback->insert(['message' => 'Item', 'cluster_id' => $clusterB]);
        $this->feedback->insert(['message' => 'Item', 'cluster_id' => $clusterB]);
        $this->feedback->insert(['message' => 'Item', 'cluster_id' => $clusterA]);

        $output = $this->runCommand('feedback:clusters --sort count');

        $this->assertLessThan(strpos($output, 'Few'), strpos($output, 'Many'));
    }

    // -------------------------------------------------------------------------
    // feedback:cluster:create
    // -------------------------------------------------------------------------

    public function testClusterCreateInsertsClusters(): void
    {
        $this->runCommand('feedback:cluster:create "Login Bugs"');

        $this->assertSame(1, $this->clusters->countAll());
        $cluster = $this->clusters->first();
        $this->assertSame('Login Bugs', $cluster->label);
    }

    public function testClusterCreateDefaultsPriorityToMedium(): void
    {
        $this->runCommand('feedback:cluster:create "Test cluster"');

        $cluster = $this->clusters->first();
        $this->assertSame(PriorityEnum::Medium, $cluster->priority);
    }

    public function testClusterCreateWithPriorityFlag(): void
    {
        $this->runCommand('feedback:cluster:create "Critical cluster" --priority critical');

        $cluster = $this->clusters->first();
        $this->assertSame(PriorityEnum::Critical, $cluster->priority);
    }

    public function testClusterCreateOutputsNewId(): void
    {
        $output = $this->runCommand('feedback:cluster:create "New cluster"');

        $this->assertMatchesRegularExpression('/\d+/', $output);
    }

    // -------------------------------------------------------------------------
    // feedback:cluster:edit
    // -------------------------------------------------------------------------

    public function testClusterEditUpdatesLabel(): void
    {
        $id = $this->clusters->insert(['label' => 'Old label']);

        $this->runCommand("feedback:cluster:edit {$id} --label \"New label\"");

        $this->assertSame('New label', $this->clusters->find($id)->label);
    }

    public function testClusterEditUpdatesPriority(): void
    {
        $id = $this->clusters->insert(['label' => 'Cluster', 'priority' => PriorityEnum::Low]);

        $this->runCommand("feedback:cluster:edit {$id} --priority high");

        $this->assertSame(PriorityEnum::High, $this->clusters->find($id)->priority);
    }

    public function testClusterEditUpdatesBothFields(): void
    {
        $id = $this->clusters->insert(['label' => 'Old', 'priority' => PriorityEnum::Low]);

        $this->runCommand("feedback:cluster:edit {$id} --label \"New\" --priority critical");

        $cluster = $this->clusters->find($id);
        $this->assertSame('New', $cluster->label);
        $this->assertSame(PriorityEnum::Critical, $cluster->priority);
    }

    public function testClusterEditWithNoOptionsShowsError(): void
    {
        $id = $this->clusters->insert(['label' => 'Cluster']);

        $output = $this->runCommand("feedback:cluster:edit {$id}");

        $this->assertStringContainsString('--label', $output);
        $this->assertStringContainsString('--priority', $output);
    }

    public function testClusterEditWithUnknownIdShowsError(): void
    {
        $output = $this->runCommand('feedback:cluster:edit 9999 --label "x"');

        $this->assertStringContainsString('9999', $output);
    }

    // -------------------------------------------------------------------------
    // feedback:cluster:delete
    // -------------------------------------------------------------------------

    public function testClusterDeleteRemovesClusterAfterConfirmation(): void
    {
        $id = $this->clusters->insert(['label' => 'Delete me']);

        $this->runCommand("feedback:cluster:delete {$id}", ['yes']);

        $this->assertNull($this->clusters->find($id));
    }

    public function testClusterDeleteAbortedWhenNotConfirmed(): void
    {
        $id = $this->clusters->insert(['label' => 'Keep me']);

        $this->runCommand("feedback:cluster:delete {$id}", ['no']);

        $this->assertNotNull($this->clusters->find($id));
    }

    public function testClusterDeleteUngroupsGroupedItems(): void
    {
        $id = $this->clusters->insert(['label' => 'Cluster']);
        $this->feedback->insert(['message' => 'Item', 'cluster_id' => $id, 'status' => StatusEnum::Grouped]);

        $this->runCommand("feedback:cluster:delete {$id}", ['yes']);

        $item = $this->feedback->first();
        $this->assertSame(StatusEnum::New, $item->status);
        $this->assertNull($item->cluster_id);
    }

    public function testClusterDeleteLeavesDismissedItems(): void
    {
        $id         = $this->clusters->insert(['label' => 'Cluster']);
        $feedbackId = $this->feedback->insert(['message' => 'Dismissed', 'cluster_id' => $id, 'status' => StatusEnum::Dismissed]);

        $this->runCommand("feedback:cluster:delete {$id}", ['yes']);

        $item = $this->feedback->find($feedbackId);
        $this->assertSame(StatusEnum::Dismissed, $item->status);
    }

    public function testClusterDeleteWithUnknownIdShowsError(): void
    {
        $output = $this->runCommand('feedback:cluster:delete 9999', ['yes']);

        $this->assertStringContainsString('9999', $output);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param list<string> $inputs
     */
    private function runCommand(string $cmd, array $inputs = []): string
    {
        $io = new MockInputOutput();

        foreach ($inputs as $input) {
            $io->setInputs([$input]);
        }

        CLI::setInputOutput($io); // @phpstan-ignore staticMethod.internal
        command($cmd);
        CLI::resetInputOutput(); // @phpstan-ignore staticMethod.internal

        return $io->getOutput();
    }
}
