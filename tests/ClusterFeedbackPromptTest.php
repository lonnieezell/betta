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
use Myth\Betta\Prompts\ClusterFeedbackPrompt;

/**
 * @internal
 */
final class ClusterFeedbackPromptTest extends CIUnitTestCase
{
    public function testSystemPromptMentionsClusterCountRange(): void
    {
        $prompt = new ClusterFeedbackPrompt([], []);
        $system = $prompt->systemPrompt();

        $this->assertStringContainsString('3', $system);
        $this->assertStringContainsString('8', $system);
    }

    public function testSystemPromptReferencesExistingClusterLabels(): void
    {
        $prompt = new ClusterFeedbackPrompt([], []);
        $system = $prompt->systemPrompt();

        $this->assertStringContainsStringIgnoringCase('existing', $system);
    }

    public function testUserPromptIncludesExistingClusterIdAndLabel(): void
    {
        $clusters = [
            ['id' => 1, 'label' => 'Login Issues'],
            ['id' => 2, 'label' => 'Performance'],
        ];
        $prompt = new ClusterFeedbackPrompt([], $clusters);
        $user   = $prompt->userPrompt();

        $this->assertStringContainsString('Login Issues', $user);
        $this->assertStringContainsString('Performance', $user);
        $this->assertStringContainsString('1', $user);
        $this->assertStringContainsString('2', $user);
    }

    public function testUserPromptIncludesUngroupedItemIdAndMessage(): void
    {
        $items = [
            ['id' => 5, 'message' => 'Cannot log in to my account'],
            ['id' => 9, 'message' => 'The page loads very slowly'],
        ];
        $prompt = new ClusterFeedbackPrompt($items, []);
        $user   = $prompt->userPrompt();

        $this->assertStringContainsString('5', $user);
        $this->assertStringContainsString('Cannot log in to my account', $user);
        $this->assertStringContainsString('9', $user);
        $this->assertStringContainsString('The page loads very slowly', $user);
    }

    public function testUserPromptWithNoExistingClustersOmitsClustersSection(): void
    {
        $items  = [['id' => 1, 'message' => 'Some feedback']];
        $prompt = new ClusterFeedbackPrompt($items, []);
        $user   = $prompt->userPrompt();

        $this->assertStringNotContainsString('Existing Clusters', $user);
    }

    public function testSchemaIsAnArray(): void
    {
        $prompt = new ClusterFeedbackPrompt([], []);
        $schema = $prompt->schema();

        $this->assertIsArray($schema);
    }

    public function testSchemaRequiresLabelSummaryAndIds(): void
    {
        $prompt   = new ClusterFeedbackPrompt([], []);
        $schema   = $prompt->schema();
        $required = $schema['items']['required'] ?? [];

        $this->assertContains('label', $required);
        $this->assertContains('summary', $required);
        $this->assertContains('ids', $required);
    }

    public function testSchemaIncludesNullableExistingClusterId(): void
    {
        $prompt     = new ClusterFeedbackPrompt([], []);
        $schema     = $prompt->schema();
        $properties = $schema['items']['properties'] ?? [];

        $this->assertArrayHasKey('existing_cluster_id', $properties);
    }
}
