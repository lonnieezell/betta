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
use Myth\Betta\Enums\ClusterStatusEnum;
use Myth\Betta\Enums\PriorityEnum;
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

    // -------------------------------------------------------------------------
    // Priority
    // -------------------------------------------------------------------------

    public function testSchemaRequiresPriority(): void
    {
        $prompt   = new ClusterFeedbackPrompt([], []);
        $schema   = $prompt->schema();
        $required = $schema['items']['required'] ?? [];

        $this->assertContains('priority', $required);
    }

    public function testSchemaLimitsPriorityToTheKnownValues(): void
    {
        $prompt = new ClusterFeedbackPrompt([], []);
        $schema = $prompt->schema();

        $this->assertSame(
            array_column(PriorityEnum::cases(), 'value'),
            $schema['items']['properties']['priority']['enum'] ?? [],
        );
    }

    public function testSystemPromptGivesPriorityGuidance(): void
    {
        $prompt = new ClusterFeedbackPrompt([], []);
        $system = $prompt->systemPrompt();

        $this->assertStringContainsStringIgnoringCase('priority', $system);
        $this->assertStringContainsStringIgnoringCase('severity', $system);
    }

    // -------------------------------------------------------------------------
    // Existing clusters of every status are offered as merge targets
    // -------------------------------------------------------------------------

    public function testUserPromptIncludesDismissedAndResolvedClusters(): void
    {
        $clusters = [
            ['id' => 1, 'label' => 'Active Cluster', 'status' => ClusterStatusEnum::Active->value],
            ['id' => 2, 'label' => 'Resolved Cluster', 'status' => ClusterStatusEnum::Resolved->value],
            ['id' => 3, 'label' => 'Dismissed Cluster', 'status' => ClusterStatusEnum::Dismissed->value],
        ];
        $prompt = new ClusterFeedbackPrompt([], $clusters);
        $user   = $prompt->userPrompt();

        $this->assertStringContainsString('Active Cluster', $user);
        $this->assertStringContainsString('Resolved Cluster', $user);
        $this->assertStringContainsString('Dismissed Cluster', $user);
    }
}
