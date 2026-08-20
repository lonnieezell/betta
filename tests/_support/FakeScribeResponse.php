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

namespace Tests\Support;

use Myth\Scribe\AIResponse;

/**
 * A real AIResponse carrying canned suggestions, so tests exercise scribe's
 * own JSON decoding in toArray() rather than a stubbed-out version of it.
 */
final readonly class FakeScribeResponse extends AIResponse
{
    /**
     * @param array<int, mixed> $data Suggestion objects, or deliberately
     *                                malformed entries when a test needs them.
     */
    public function __construct(array $data)
    {
        parent::__construct(
            content: json_encode($data, JSON_THROW_ON_ERROR),
            model: 'fake-model',
            inputTokens: 0,
            outputTokens: 0,
            raw: [],
        );
    }
}
