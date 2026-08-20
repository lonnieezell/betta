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
use Myth\Scribe\AIService;
use Myth\Scribe\Config\AI;
use Myth\Scribe\Exceptions\AIException;
use Myth\Scribe\Prompts\BasePrompt;
use Override;

/**
 * Test double for the scribe AI service, returning canned suggestions instead
 * of calling a provider.
 *
 * It extends the real AIService so that anything type-hinting scribe's service
 * still accepts it — which also means the prompt it is handed has to be a real
 * BasePrompt, keeping the double honest about the contract callers must meet.
 *
 * Inject via Services::injectMock('scribe', new FakeScribeService([...]))
 */
final class FakeScribeService extends AIService
{
    /**
     * @param array<int, mixed> $suggestions
     */
    public function __construct(
        private readonly array $suggestions,
        private readonly bool $shouldThrow = false,
    ) {
        parent::__construct(new AI(), []);
    }

    #[Override]
    public function run(BasePrompt $prompt): AIResponse
    {
        if ($this->shouldThrow) {
            throw new AIException('AI service unavailable');
        }

        return new FakeScribeResponse($this->suggestions);
    }
}
