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

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Throttle\ThrottlerInterface;
use Config\Services;
use Myth\Betta\Config\Betta;
use Myth\Betta\Models\FeedbackModel;
use Tests\Support\DatabaseTestTrait;

/**
 * @internal
 */
final class FeedbackControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace   = 'Myth\Betta';
    protected $DBGroup     = 'tests';
    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('betta_feedback')->truncate();
        config(Betta::class)->acceptSubmissions = true;

        // Default to a permissive throttler so the rate-limit filter never
        // blocks tests that are not specifically testing rate limiting.
        $permissive = $this->createMock(ThrottlerInterface::class);
        $permissive->method('check')->willReturn(true);
        Services::injectMock('throttler', $permissive);
    }

    public function testGetFeedbackRendersForm(): void
    {
        $result = $this->get('feedback');

        $result->assertStatus(200);
        $result->assertSee('feedback');
    }

    public function testGetFeedbackRendersClosedWhenSubmissionsDisabled(): void
    {
        $config                    = config(Betta::class);
        $config->acceptSubmissions = false;

        $result = $this->get('feedback');

        $result->assertStatus(200);
        $result->assertSee('not currently accepting');
    }

    public function testPostSubmitSavesRowWithAllFields(): void
    {
        $result = $this->post('feedback/submit', [
            'category'    => 'bug',
            'message'     => 'Something is broken',
            'email'       => 'user@example.com',
            'url_context' => 'https://example.com/page',
        ]);

        $result->assertStatus(302);

        $model = new FeedbackModel();
        $rows  = $model->findAll();
        $this->assertCount(1, $rows);

        $row = $rows[0];
        $this->assertSame('bug', $row->category->value);
        $this->assertSame('Something is broken', $row->message);
        $this->assertSame('user@example.com', $row->email);
        $this->assertSame('https://example.com/page', $row->url_context);
        $this->assertNotEmpty($row->session_id);
        $this->assertSame(64, strlen((string) $row->session_id)); // sha256 hex length
    }

    public function testPostSubmitStoresSessionIdAsHash(): void
    {
        $this->post('feedback/submit', [
            'category' => 'bug',
            'message'  => 'Test',
        ]);

        $model = new FeedbackModel();
        $row   = $model->findAll()[0];

        $expectedHash = hash('sha256', session_id());
        $this->assertSame($expectedHash, $row->session_id);
    }

    public function testPostSubmitUsesRefererWhenUrlContextAbsent(): void
    {
        $result = $this->withHeaders(['Referer' => 'https://example.com/referer-page'])
            ->post('feedback/submit', [
                'category' => 'ux',
                'message'  => 'Navigation is confusing',
            ]);

        $result->assertStatus(302);

        $model = new FeedbackModel();
        $row   = $model->findAll()[0];
        $this->assertSame('https://example.com/referer-page', $row->url_context);
    }

    public function testPostSubmitWithEmptyMessageReturns422Json(): void
    {
        $result = $this->withHeaders([
            'Accept'           => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('feedback/submit', [
            'category' => 'bug',
            'message'  => '',
        ]);

        $result->assertStatus(422);
        $json = json_decode((string) $result->response()->getBody(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('message', $json['errors']);
    }

    public function testPostSubmitWithEmptyMessageRedirectsOnPlainPost(): void
    {
        $result = $this->post('feedback/submit', [
            'category' => 'bug',
            'message'  => '',
        ]);

        $result->assertStatus(302);
        $result->assertSessionHas('errors');
    }

    public function testPostSubmitWithInvalidEmailReturns422Json(): void
    {
        $result = $this->withHeaders([
            'Accept' => 'application/json',
        ])->post('feedback/submit', [
            'category' => 'bug',
            'message'  => 'Valid message',
            'email'    => 'not-an-email',
        ]);

        $result->assertStatus(422);
        $json = json_decode((string) $result->response()->getBody(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('email', $json['errors']);
    }

    public function testPostSubmitReturnsJsonOkOnFetchSuccess(): void
    {
        $result = $this->withHeaders([
            'Accept' => 'application/json',
        ])->post('feedback/submit', [
            'category' => 'feature',
            'message'  => 'Great idea',
        ]);

        $result->assertStatus(200);
        $json = json_decode((string) $result->response()->getBody(), true);
        $this->assertTrue($json['ok']);
    }

    public function testPostSubmitSetsSuccessFlashOnPlainPost(): void
    {
        $result = $this->post('feedback/submit', [
            'category' => 'other',
            'message'  => 'Works great!',
        ]);

        $result->assertStatus(302);
        $result->assertSessionHas('feedback_success');
    }

    public function testPostSubmitReturns429WhenRateLimited(): void
    {
        $throttler = $this->createMock(ThrottlerInterface::class);
        $throttler->method('check')->willReturn(false);
        $throttler->method('getTokenTime')->willReturn(30);
        Services::injectMock('throttler', $throttler);

        $result = $this->post('feedback/submit', [
            'category' => 'bug',
            'message'  => 'Rate limited request',
        ]);

        $result->assertStatus(429);

        Services::resetSingle('throttler');
    }

    public function testPostSubmitReturns429JsonWhenRateLimitedAndJsonRequest(): void
    {
        $throttler = $this->createMock(ThrottlerInterface::class);
        $throttler->method('check')->willReturn(false);
        $throttler->method('getTokenTime')->willReturn(30);
        Services::injectMock('throttler', $throttler);

        $result = $this->withHeaders(['Accept' => 'application/json'])
            ->post('feedback/submit', [
                'category' => 'bug',
                'message'  => 'Rate limited JSON request',
            ]);

        $result->assertStatus(429);
        $json = json_decode((string) $result->response()->getBody(), true);
        $this->assertArrayHasKey('error', $json);

        Services::resetSingle('throttler');
    }
}
