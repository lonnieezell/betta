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

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Betta\Enums\CategoryEnum;
use Myth\Betta\Enums\PriorityEnum;
use Myth\Betta\Enums\StatusEnum;
use Myth\Betta\Models\FeedbackClusterModel;
use Myth\Betta\Models\FeedbackModel;
use Tests\Support\DatabaseTestTrait;

/**
 * @internal
 */
final class FeedbackClusterModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'Myth\Betta';
    protected $DBGroup     = 'tests';
    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = false;

    private FeedbackClusterModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new FeedbackClusterModel();
    }

    public function testInsertAndFindReturnsEnumInstance(): void
    {
        $id = $this->model->insert([
            'label'    => 'Login Issues',
            'priority' => PriorityEnum::High,
        ]);

        $this->assertIsInt($id);
        $row = $this->model->find($id);

        $this->assertInstanceOf(PriorityEnum::class, $row->priority);
        $this->assertSame(PriorityEnum::High, $row->priority);
    }

    public function testDefaultPriorityIsMedium(): void
    {
        $id  = $this->model->insert(['label' => 'Misc cluster']);
        $row = $this->model->find($id);

        $this->assertSame(PriorityEnum::Medium, $row->priority);
    }

    public function testUpdateChangesPriority(): void
    {
        $id = $this->model->insert(['label' => 'Cluster to update']);
        $this->model->update($id, ['priority' => PriorityEnum::Critical]);

        $row = $this->model->find($id);
        $this->assertSame(PriorityEnum::Critical, $row->priority);
    }

    public function testDeleteRemovesCluster(): void
    {
        $id = $this->model->insert(['label' => 'Delete me']);
        $this->assertNotNull($this->model->find($id));

        $this->model->delete($id);
        $this->assertNull($this->model->find($id));
    }

    public function testAllPriorityEnumRoundTrips(): void
    {
        foreach (PriorityEnum::cases() as $priority) {
            $id  = $this->model->insert(['label' => 'test', 'priority' => $priority]);
            $row = $this->model->find($id);
            $this->assertSame($priority, $row->priority);
        }
    }

    public function testFindAllWithCountReturnsZeroItemsForEmptyCluster(): void
    {
        $id      = $this->model->insert(['label' => 'Empty cluster']);
        $results = $this->model->findAllWithCount();

        $cluster = array_values(array_filter($results, static fn ($r) => $r->id === $id))[0] ?? null;
        $this->assertNotNull($cluster);
        $this->assertSame(0, $cluster->item_count);
    }

    public function testFindAllWithCountReflectsLinkedFeedback(): void
    {
        $clusterId = $this->model->insert(['label' => 'Cluster with items']);

        $feedbackModel = new FeedbackModel();
        $feedbackModel->insert(['message' => 'Item 1', 'cluster_id' => $clusterId]);
        $feedbackModel->insert(['message' => 'Item 2', 'cluster_id' => $clusterId]);
        $feedbackModel->insert(['message' => 'Item 3', 'cluster_id' => $clusterId]);

        $results  = $this->model->findAllWithCount();
        $cluster  = array_values(array_filter($results, static fn ($r) => $r->id === $clusterId))[0];
        $this->assertSame(3, $cluster->item_count);
    }

    public function testItemCountDoesNotCountOtherClustersItems(): void
    {
        $clusterA = $this->model->insert(['label' => 'Cluster A']);
        $clusterB = $this->model->insert(['label' => 'Cluster B']);

        $feedbackModel = new FeedbackModel();
        $feedbackModel->insert(['message' => 'For A', 'cluster_id' => $clusterA]);
        $feedbackModel->insert(['message' => 'For B', 'cluster_id' => $clusterB]);
        $feedbackModel->insert(['message' => 'For B 2', 'cluster_id' => $clusterB]);

        $results  = $this->model->findAllWithCount();
        $a        = array_values(array_filter($results, static fn ($r) => $r->id === $clusterA))[0];
        $b        = array_values(array_filter($results, static fn ($r) => $r->id === $clusterB))[0];

        $this->assertSame(1, $a->item_count);
        $this->assertSame(2, $b->item_count);
    }
}
