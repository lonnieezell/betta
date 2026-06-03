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
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockInputOutput;
use Myth\Betta\Enums\StatusEnum;
use Myth\Betta\Models\FeedbackClusterModel;
use Myth\Betta\Models\FeedbackModel;
use Tests\Support\DatabaseTestTrait;

/**
 * @internal
 */
final class FeedbackGroupCommandTest extends CIUnitTestCase
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

    public function testGroupAssignsClusterIdAndSetsStatusGrouped(): void
    {
        $clusterId  = $this->clusters->insert(['label' => 'Login Issues']);
        $feedbackId = $this->feedback->insert(['message' => 'Cannot log in']);

        $this->runCommand("feedback:group {$feedbackId} {$clusterId}");

        $item = $this->feedback->find($feedbackId);
        $this->assertSame($clusterId, $item->cluster_id);
        $this->assertSame(StatusEnum::Grouped, $item->status);
    }

    public function testGroupErrorsWhenFeedbackIdDoesNotExist(): void
    {
        $clusterId = $this->clusters->insert(['label' => 'Login Issues']);

        $output = $this->runCommand("feedback:group 9999 {$clusterId}");

        $this->assertStringContainsString('9999', $output);
        $this->assertCount(0, $this->feedback->findAll());
    }

    public function testGroupErrorsWhenClusterIdDoesNotExist(): void
    {
        $feedbackId = $this->feedback->insert(['message' => 'Some issue']);

        $output = $this->runCommand("feedback:group {$feedbackId} 9999");

        $this->assertStringContainsString('9999', $output);
        $item = $this->feedback->find($feedbackId);
        $this->assertNull($item->cluster_id);
    }

    private function runCommand(string $cmd): string
    {
        $io = new MockInputOutput();
        CLI::setInputOutput($io); // @phpstan-ignore staticMethod.internal
        command($cmd);
        CLI::resetInputOutput(); // @phpstan-ignore staticMethod.internal

        return $io->getOutput();
    }
}
