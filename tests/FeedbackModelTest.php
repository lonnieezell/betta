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
use Myth\Betta\Enums\SentimentEnum;
use Myth\Betta\Enums\StatusEnum;
use Myth\Betta\Models\FeedbackModel;
use Tests\Support\DatabaseTestTrait;

/**
 * @internal
 */
final class FeedbackModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'Myth\Betta';
    protected $DBGroup     = 'tests';
    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = false;
    private FeedbackModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('betta_feedback')->truncate();
        $this->db->table('feedback_clusters')->truncate();
        $this->model = new FeedbackModel();
    }

    public function testInsertAndFindReturnsEnumInstances(): void
    {
        $id = $this->model->insert([
            'message'   => 'Great feature idea',
            'category'  => CategoryEnum::Feature,
            'status'    => StatusEnum::New,
            'sentiment' => SentimentEnum::Positive,
        ]);

        $this->assertIsInt($id);
        $row = $this->model->find($id);

        $this->assertInstanceOf(CategoryEnum::class, $row->category);
        $this->assertSame(CategoryEnum::Feature, $row->category);

        $this->assertInstanceOf(StatusEnum::class, $row->status);
        $this->assertSame(StatusEnum::New, $row->status);

        $this->assertInstanceOf(SentimentEnum::class, $row->sentiment);
        $this->assertSame(SentimentEnum::Positive, $row->sentiment);
    }

    public function testDefaultCategoryIsOther(): void
    {
        $id  = $this->model->insert(['message' => 'Some feedback']);
        $row = $this->model->find($id);

        $this->assertSame(CategoryEnum::Other, $row->category);
    }

    public function testDefaultStatusIsNew(): void
    {
        $id  = $this->model->insert(['message' => 'Some feedback']);
        $row = $this->model->find($id);

        $this->assertSame(StatusEnum::New, $row->status);
    }

    public function testUpdateChangesStatus(): void
    {
        $id = $this->model->insert(['message' => 'Needs review']);
        $this->model->update($id, ['status' => StatusEnum::Reviewed]);

        $row = $this->model->find($id);
        $this->assertSame(StatusEnum::Reviewed, $row->status);
    }

    public function testDeleteRemovesRow(): void
    {
        $id = $this->model->insert(['message' => 'Delete me']);
        $this->assertNotNull($this->model->find($id));

        $this->model->delete($id);
        $this->assertNull($this->model->find($id));
    }

    public function testNullSentimentIsAllowed(): void
    {
        $id  = $this->model->insert(['message' => 'No sentiment']);
        $row = $this->model->find($id);

        $this->assertNull($row->sentiment);
    }

    public function testAllEnumRoundTrips(): void
    {
        foreach (CategoryEnum::cases() as $category) {
            $id  = $this->model->insert(['message' => 'test', 'category' => $category]);
            $row = $this->model->find($id);
            $this->assertSame($category, $row->category);
        }

        foreach (StatusEnum::cases() as $status) {
            $id  = $this->model->insert(['message' => 'test', 'status' => $status]);
            $row = $this->model->find($id);
            $this->assertSame($status, $row->status);
        }

        foreach (SentimentEnum::cases() as $sentiment) {
            $id  = $this->model->insert(['message' => 'test', 'sentiment' => $sentiment]);
            $row = $this->model->find($id);
            $this->assertSame($sentiment, $row->sentiment);
        }
    }
}
