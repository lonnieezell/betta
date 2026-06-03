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

use Override;
use Myth\Scribe\Response\ScribeResponse;

/**
 * Named test-double response used by FakeScribeService.
 */
final class FakeScribeResponse extends ScribeResponse
{
    /**
     * @param array<int, array<string, mixed>> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Override]
    public function toArray(): array
    {
        return $this->data;
    }
}
