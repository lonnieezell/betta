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

namespace Myth\Betta\Prompts;

class ClusterFeedbackPrompt
{
    /**
     * @param array<int, array<string, mixed>> $items            Ungrouped feedback items: [['id' => int, 'message' => string], ...]
     * @param array<int, array<string, mixed>> $existingClusters Existing clusters: [['id' => int, 'label' => string], ...]
     */
    public function __construct(
        private readonly array $items,
        private readonly array $existingClusters,
    ) {
    }

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
            You are a feedback analyst. Group the provided feedback items into between 3 and 8 meaningful clusters.
            Where possible, reference existing cluster labels to avoid fragmentation.
            Each cluster should have a concise label and a one-sentence summary of the items it contains.
            Return only valid JSON matching the provided schema.
            PROMPT;
    }

    public function userPrompt(): string
    {
        $parts = [];

        if ($this->existingClusters !== []) {
            $clusterLines = ['## Existing Clusters'];

            foreach ($this->existingClusters as $cluster) {
                $clusterLines[] = "- ID {$cluster['id']}: {$cluster['label']}";
            }

            $parts[] = implode("\n", $clusterLines);
        }

        $itemLines = ['## Ungrouped Feedback Items'];

        foreach ($this->items as $item) {
            $itemLines[] = "- ID {$item['id']}: \"{$item['message']}\"";
        }

        $parts[] = implode("\n", $itemLines);

        return implode("\n\n", $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type'  => 'array',
            'items' => [
                'type'       => 'object',
                'required'   => ['label', 'summary', 'ids'],
                'properties' => [
                    'label' => [
                        'type'        => 'string',
                        'description' => 'A short, descriptive cluster label',
                    ],
                    'summary' => [
                        'type'        => 'string',
                        'description' => 'A one-sentence summary of the feedback items in this cluster',
                    ],
                    'ids' => [
                        'type'        => 'array',
                        'items'       => ['type' => 'integer'],
                        'description' => 'IDs of feedback items to include in this cluster',
                    ],
                    'existing_cluster_id' => [
                        'type'        => ['integer', 'null'],
                        'description' => 'Non-null: assign items to this existing cluster. Null: create a new cluster.',
                    ],
                ],
            ],
        ];
    }
}
