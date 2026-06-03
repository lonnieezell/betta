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

namespace Tests\Support;

use Myth\Scribe\Exceptions\AIException;
use Myth\Scribe\Response\ScribeResponse;

/**
 * Test double for the myth/scribe service.
 * Inject via Services::injectMock('scribe', new FakeScribeService([...]))
 */
final readonly class FakeScribeService
{
    /**
     * @param array<int, array<string, mixed>> $suggestions
     */
    public function __construct(
        private array $suggestions,
        private bool $shouldThrow = false,
    ) {
    }

    public function run(): ScribeResponse
    {
        if ($this->shouldThrow) {
            throw new AIException('AI service unavailable');
        }
        return new FakeScribeResponse($this->suggestions);
    }
}
