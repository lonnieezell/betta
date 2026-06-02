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
use Myth\Betta\Enums\StatusEnum;
use Myth\Betta\Models\FeedbackClusterModel;
use Myth\Betta\Models\FeedbackModel;
use Tests\Support\DatabaseTestTrait;

/**
 * @internal
 */
final class FeedbackReviewCommandTest extends CIUnitTestCase
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
    // Empty queue
    // -------------------------------------------------------------------------

    public function testEmptyQueuePrintsNoMoreItemsAndExitsClean(): void
    {
        $output = $this->runCommand('feedback:review');

        $this->assertStringContainsString('No more items to review.', $output);
    }

    // -------------------------------------------------------------------------
    // Item display and status=reviewed on load
    // -------------------------------------------------------------------------

    public function testLoadByIdDisplaysFullItemDetail(): void
    {
        $id     = $this->feedback->insert(['message' => 'Cannot log in', 'email' => 'user@example.com', 'url_context' => 'https://example.com/login']);
        $output = $this->runCommand("feedback:review {$id}", "q\n");

        $this->assertStringContainsString((string) $id, $output);
        $this->assertStringContainsString('Cannot log in', $output);
        $this->assertStringContainsString('user@example.com', $output);
        $this->assertStringContainsString('https://example.com/login', $output);
    }

    public function testViewingItemImmediatelySetsStatusReviewed(): void
    {
        $id = $this->feedback->insert(['message' => 'some feedback', 'status' => StatusEnum::New]);

        $this->runCommand("feedback:review {$id}", "q\n");

        $item = $this->feedback->find($id);
        $this->assertSame(StatusEnum::Reviewed, $item->status);
    }

    public function testNoIdLoadsOldestNewItem(): void
    {
        $this->db->table('betta_feedback')->insert([
            'message'    => 'Older item',
            'status'     => 'new',
            'category'   => 'other',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);
        $this->db->table('betta_feedback')->insert([
            'message'    => 'Newer item',
            'status'     => 'new',
            'category'   => 'other',
            'created_at' => '2024-01-02 00:00:00',
            'updated_at' => '2024-01-02 00:00:00',
        ]);

        $output = $this->runCommand('feedback:review', "q\n");

        $this->assertStringContainsString('Older item', $output);
    }

    public function testLoadByIdErrorsWhenNotFound(): void
    {
        $output = $this->runCommand('feedback:review 9999');

        $this->assertStringContainsString('9999', $output);
    }

    // -------------------------------------------------------------------------
    // q — quit
    // -------------------------------------------------------------------------

    public function testQuitExitsImmediatelyWithoutAdvancing(): void
    {
        $id1 = $this->feedback->insert(['message' => 'first', 'status' => StatusEnum::New]);
        $id2 = $this->feedback->insert(['message' => 'second', 'status' => StatusEnum::New]);

        $this->runCommand('feedback:review', "q\n");

        $reviewed = array_filter(
            $this->feedback->findAll(),
            static fn (object $i): bool => $i->status === StatusEnum::Reviewed,
        );
        $this->assertCount(1, $reviewed);
    }

    // -------------------------------------------------------------------------
    // d — dismiss
    // -------------------------------------------------------------------------

    public function testDismissSetsDismissedStatus(): void
    {
        $id = $this->feedback->insert(['message' => 'to dismiss', 'status' => StatusEnum::New]);

        $this->runCommand('feedback:review', "d\n");

        $item = $this->feedback->find($id);
        $this->assertSame(StatusEnum::Dismissed, $item->status);
    }

    public function testDismissAutoAdvancesToNextNewItem(): void
    {
        $id1 = $this->feedback->insert(['message' => 'first', 'status' => StatusEnum::New]);
        $id2 = $this->feedback->insert(['message' => 'second', 'status' => StatusEnum::New]);

        $this->runCommand('feedback:review', "d\nq\n");

        $item1 = $this->feedback->find($id1);
        $item2 = $this->feedback->find($id2);
        $this->assertSame(StatusEnum::Dismissed, $item1->status);
        $this->assertSame(StatusEnum::Reviewed, $item2->status);
    }

    // -------------------------------------------------------------------------
    // a — assign to existing cluster
    // -------------------------------------------------------------------------

    public function testAssignSetsClusterIdAndStatusGrouped(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'Login Issues']);
        $id        = $this->feedback->insert(['message' => 'Cannot login', 'status' => StatusEnum::New]);

        $this->runCommand('feedback:review', "a\n{$clusterId}\n");

        $item = $this->feedback->find($id);
        $this->assertSame(StatusEnum::Grouped, $item->status);
        $this->assertSame($clusterId, $item->cluster_id);
    }

    public function testAssignDisplaysClusterListBeforePrompting(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'Login Issues']);
        $this->feedback->insert(['message' => 'test', 'status' => StatusEnum::New]);

        $output = $this->runCommand('feedback:review', "a\n{$clusterId}\n");

        $this->assertStringContainsString('Login Issues', $output);
    }

    public function testAssignAutoAdvancesAfterAssigning(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'Bugs']);
        $id1       = $this->feedback->insert(['message' => 'first', 'status' => StatusEnum::New]);
        $id2       = $this->feedback->insert(['message' => 'second', 'status' => StatusEnum::New]);

        $this->runCommand('feedback:review', "a\n{$clusterId}\nq\n");

        $item1 = $this->feedback->find($id1);
        $item2 = $this->feedback->find($id2);
        $this->assertSame(StatusEnum::Grouped, $item1->status);
        $this->assertSame(StatusEnum::Reviewed, $item2->status);
    }

    // -------------------------------------------------------------------------
    // n — new cluster
    // -------------------------------------------------------------------------

    public function testNewClusterCreatesClusterAndAssignsItem(): void
    {
        $id = $this->feedback->insert(['message' => 'nav is broken', 'status' => StatusEnum::New]);

        $this->runCommand('feedback:review', "n\nNavigation Bugs\n");

        $item = $this->feedback->find($id);
        $this->assertSame(StatusEnum::Grouped, $item->status);
        $this->assertNotNull($item->cluster_id);

        $cluster = $this->clusters->find($item->cluster_id);
        $this->assertSame('Navigation Bugs', $cluster->label);
    }

    public function testNewClusterAutoAdvancesAfterCreating(): void
    {
        $id1 = $this->feedback->insert(['message' => 'first', 'status' => StatusEnum::New]);
        $id2 = $this->feedback->insert(['message' => 'second', 'status' => StatusEnum::New]);

        $this->runCommand('feedback:review', "n\nNew Cluster\nq\n");

        $item1 = $this->feedback->find($id1);
        $item2 = $this->feedback->find($id2);
        $this->assertSame(StatusEnum::Grouped, $item1->status);
        $this->assertSame(StatusEnum::Reviewed, $item2->status);
    }

    // -------------------------------------------------------------------------
    // Auto-advance exhausts queue
    // -------------------------------------------------------------------------

    public function testExhaustingQueuePrintsNoMoreItems(): void
    {
        $this->feedback->insert(['message' => 'only item', 'status' => StatusEnum::New]);

        $output = $this->runCommand('feedback:review', "d\n");

        $this->assertStringContainsString('No more items to review.', $output);
    }

    // -------------------------------------------------------------------------
    // Jump-to-id then auto-advance
    // -------------------------------------------------------------------------

    public function testJumpToIdThenAutoAdvancesToNextNew(): void
    {
        $id1 = $this->feedback->insert(['message' => 'first new', 'status' => StatusEnum::New]);
        $id2 = $this->feedback->insert(['message' => 'second new', 'status' => StatusEnum::New]);

        $this->runCommand("feedback:review {$id2}", "d\nq\n");

        $item1 = $this->feedback->find($id1);
        $item2 = $this->feedback->find($id2);
        $this->assertSame(StatusEnum::Dismissed, $item2->status);
        $this->assertSame(StatusEnum::Reviewed, $item1->status);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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
