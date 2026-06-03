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

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Throttle\ThrottlerInterface;
use Config\Services;
use Myth\Betta\Filters\RateLimitFilter;

/**
 * @internal
 */
final class RateLimitFilterTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Services::resetSingle('throttler');
    }

    public function testBeforeReturnsNullWhenUnderLimit(): void
    {
        $throttler = $this->createMock(ThrottlerInterface::class);
        $throttler->method('check')->willReturn(true);
        Services::injectMock('throttler', $throttler);

        $filter  = new RateLimitFilter();
        $request = service('request');
        $result  = $filter->before($request);

        $this->assertNull($result);
    }

    public function testBeforeReturns429WhenLimitExceeded(): void
    {
        $throttler = $this->createMock(ThrottlerInterface::class);
        $throttler->method('check')->willReturn(false);
        $throttler->method('getTokenTime')->willReturn(30);
        Services::injectMock('throttler', $throttler);

        $filter  = new RateLimitFilter();
        $request = service('request');
        $result  = $filter->before($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(429, $result->getStatusCode());
    }

    public function testBeforeReturns429JsonWhenJsonRequestExceedsLimit(): void
    {
        $throttler = $this->createMock(ThrottlerInterface::class);
        $throttler->method('check')->willReturn(false);
        $throttler->method('getTokenTime')->willReturn(30);
        Services::injectMock('throttler', $throttler);

        $filter  = new RateLimitFilter();
        $request = service('request');
        $request->setHeader('Accept', 'application/json');
        $result = $filter->before($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(429, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }

    public function testAfterReturnsNull(): void
    {
        $filter   = new RateLimitFilter();
        $request  = service('request');
        $response = service('response');
        $result   = $filter->after($request, $response);

        $this->assertNull($result);
    }
}
