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
use CodeIgniter\Test\Mock\MockInputOutput;
use Config\Services;
use Myth\Betta\Prompts\ClusterFeedbackPrompt;
use Myth\Scribe\AIResponse;
use Myth\Scribe\AIService;
use Myth\Scribe\Config\AI;
use Myth\Scribe\Drivers\FakeDriver;
use Tests\Support\FakeScribeService;
use Tests\Support\FeedbackCommandTestCase;

/**
 * Pins Betta's clustering pipeline to the API lonnieezell/scribe actually ships.
 *
 * These tests deliberately use the real AIService and the real BasePrompt
 * contract rather than a hand-rolled double: the class names Betta coded
 * against originally (Myth\Scribe\Services\ScribeService, Myth\Scribe\Response
 * \ScribeResponse) never existed in the published package, and because they
 * were stubbed in tests/_support/stubs the whole suite passed while
 * `spark feedback:analyze` failed on every real install.
 *
 * @internal
 */
final class ScribeIntegrationTest extends FeedbackCommandTestCase
{
    public function testScribeServiceResolvesToTheRealAIService(): void
    {
        $this->assertInstanceOf(AIService::class, service('scribe'));
    }

    /**
     * The end-to-end proof: the real AIService accepts Betta's prompt object,
     * hands it to a driver, and the decoded response is the suggestion array
     * FeedbackAnalyzeCommand expects.
     */
    public function testRealAIServiceRunsTheClusterPromptAndDecodesSuggestions(): void
    {
        $suggestions = [[
            'label'               => 'Login Issues',
            'summary'             => 'Users cannot log in',
            'priority'            => 'high',
            'ids'                 => [1],
            'existing_cluster_id' => null,
        ]];

        $service = new AIService(
            new AI(),
            ['claude' => static fn (): FakeDriver => new FakeDriver(new AIResponse(
                content: json_encode($suggestions, JSON_THROW_ON_ERROR),
                model: 'fake-model',
                inputTokens: 0,
                outputTokens: 0,
                raw: [],
            ))],
        );

        $prompt = new ClusterFeedbackPrompt(
            [['id' => 1, 'message' => 'Cannot log in']],
            [],
        );

        $this->assertSame($suggestions, $service->run($prompt)->toArray());
    }

    /**
     * The schema has to survive buildSystemPrompt(), which json-encodes it and
     * appends it to the system prompt — that is how scribe asks for structured
     * output, and it is the step a non-BasePrompt prompt never reached.
     */
    public function testBuiltSystemPromptCarriesTheClusterSchema(): void
    {
        $system = (new ClusterFeedbackPrompt([], []))->buildSystemPrompt();

        $this->assertStringContainsString('feedback analyst', $system);
        $this->assertStringContainsString('existing_cluster_id', $system);
        $this->assertStringContainsString('critical', $system);
    }

    public function testAnalyzeDoesNotClaimScribeIsMissingWhenItIsInstalled(): void
    {
        $this->feedback->insert(['message' => 'Cannot log in']);

        $io = new MockInputOutput();
        CLI::setInputOutput($io); // @phpstan-ignore staticMethod.internal
        command('feedback:analyze --dry-run');
        CLI::resetInputOutput(); // @phpstan-ignore staticMethod.internal

        $this->assertStringNotContainsString('is not installed', $io->getOutput());
    }

    /**
     * Nothing forces the provider to honour the schema, so a run containing
     * junk entries has to keep going and apply the usable ones.
     */
    public function testMalformedSuggestionsAreSkippedRatherThanCrashingTheRun(): void
    {
        $id = $this->feedback->insert(['message' => 'Cannot log in']);

        Services::injectMock('scribe', new FakeScribeService([
            'a bare string where an object belongs',
            ['summary' => 'no label and no ids'],
            [
                'label'               => 'Login Issues',
                'summary'             => 'Users cannot log in',
                'priority'            => 'high',
                'ids'                 => [$id],
                'existing_cluster_id' => null,
            ],
        ]));

        $io = new MockInputOutput();
        CLI::setInputOutput($io); // @phpstan-ignore staticMethod.internal
        command('feedback:analyze --apply');
        CLI::resetInputOutput(); // @phpstan-ignore staticMethod.internal

        $clusters = $this->clusters->findAll();
        $this->assertCount(1, $clusters);
        $this->assertSame('Login Issues', $clusters[0]->label);
        $this->assertSame($clusters[0]->id, $this->feedback->find($id)->cluster_id);
    }
}
