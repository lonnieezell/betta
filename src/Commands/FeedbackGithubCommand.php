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

namespace Myth\Betta\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Myth\Betta\Enums\CategoryEnum;
use Myth\Betta\Enums\PriorityEnum;
use Myth\Betta\Models\FeedbackClusterModel;
use Myth\Betta\Models\FeedbackModel;
use RuntimeException;

class FeedbackGithubCommand extends BaseCommand
{
    protected $group       = 'Betta';
    protected $name        = 'feedback:github';
    protected $description = 'Create GitHub issues from a feedback item or an entire cluster.';
    protected $arguments   = [
        'id' => 'Feedback item ID, or cluster ID when --cluster is used.',
    ];
    protected $options = [
        '--cluster' => 'Treat the ID as a cluster; create one issue per feedback item in it.',
        '--dry-run' => 'Print what would be created without calling the GitHub API.',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        $id      = isset($params[0]) && ctype_digit($params[0]) ? (int) $params[0] : null;
        $cluster = array_key_exists('cluster', $params) || CLI::getOption('cluster') !== null;
        $dryRun  = array_key_exists('dry-run', $params) || CLI::getOption('dry-run') !== null;

        if ($id === null || $id <= 0) {
            CLI::error('Usage: feedback:github <id> [--cluster] [--dry-run]');

            return EXIT_ERROR;
        }

        $token = (string) (env('GITHUB_TOKEN') ?? '');
        $owner = (string) (env('GITHUB_OWNER') ?? '');
        $repo  = (string) (env('GITHUB_REPO') ?? '');

        if (! $dryRun) {
            if ($token === '') {
                CLI::error('GitHub token is not set. Configure the GITHUB_TOKEN environment variable.');

                return EXIT_ERROR;
            }

            if ($owner === '') {
                CLI::error('GitHub owner is not set. Configure the GITHUB_OWNER environment variable.');

                return EXIT_ERROR;
            }

            if ($repo === '') {
                CLI::error('GitHub repo is not set. Configure the GITHUB_REPO environment variable.');

                return EXIT_ERROR;
            }
        }

        if ($cluster) {
            return $this->handleCluster($id, $dryRun);
        }

        return $this->handleSingle($id, $dryRun);
    }

    private function handleSingle(int $id, bool $dryRun): int
    {
        $feedbackModel = new FeedbackModel();
        $item          = $feedbackModel->find($id);

        if ($item === null) {
            CLI::error("Feedback item {$id} not found.");

            return EXIT_ERROR;
        }

        $this->processItem($item, $feedbackModel, $dryRun, null);

        return EXIT_SUCCESS;
    }

    private function handleCluster(int $clusterId, bool $dryRun): int
    {
        $clusterModel = new FeedbackClusterModel();
        $cluster      = $clusterModel->find($clusterId);

        if ($cluster === null) {
            CLI::error("Cluster {$clusterId} not found.");

            return EXIT_ERROR;
        }

        $feedbackModel = new FeedbackModel();
        $items         = $feedbackModel->where('cluster_id', $clusterId)->findAll();

        if ($items === []) {
            CLI::write("Cluster {$clusterId} has no feedback items.");

            return EXIT_SUCCESS;
        }

        $priority = $cluster->priority instanceof PriorityEnum ? $cluster->priority->value : null;

        foreach ($items as $item) {
            $this->processItem($item, $feedbackModel, $dryRun, $priority);
        }

        return EXIT_SUCCESS;
    }

    private function processItem(object $item, FeedbackModel $feedbackModel, bool $dryRun, ?string $clusterPriority): void
    {
        $issueUrl = isset($item->github_issue_url) ? (string) $item->github_issue_url : '';

        if ($issueUrl !== '') {
            CLI::write(CLI::color("Feedback #{$item->id} already exported: {$issueUrl}", 'yellow'));

            return;
        }

        $category = $item->category instanceof CategoryEnum ? $item->category->value : (string) $item->category;
        $message  = (string) $item->message;
        $title    = '[Feedback] ' . $category . ': ' . mb_substr($message, 0, 80);

        $lines   = [];
        $lines[] = $message;
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '**Category:** ' . $category;

        $email = isset($item->email) ? (string) $item->email : '';

        if ($email !== '') {
            $lines[] = '**Email:** ' . $email;
        }

        $urlContext = isset($item->url_context) ? (string) $item->url_context : '';

        if ($urlContext !== '') {
            $lines[] = '**URL:** ' . $urlContext;
        }

        if (isset($item->sentiment)) {
            $lines[] = '**Sentiment:** ' . $item->sentiment;
        }

        $createdAt = isset($item->created_at) ? (string) $item->created_at : '';

        if ($createdAt !== '') {
            $lines[] = '**Submitted:** ' . $createdAt;
        }

        $body = implode("\n", $lines);

        $labels = [];

        if (CategoryEnum::tryFrom($category) !== null) {
            $labels[] = $category;
        }

        if ($clusterPriority !== null) {
            $labels[] = $clusterPriority;
        }

        if ($dryRun) {
            CLI::write('');
            CLI::write(CLI::color("Would create issue for feedback #{$item->id}:", 'yellow'));
            CLI::write("Title:  {$title}");
            CLI::write("Body:\n{$body}");

            if ($labels !== []) {
                CLI::write('Labels: ' . implode(', ', $labels));
            }

            return;
        }

        try {
            $github = service('github');
            $url    = $github->createIssue($title, $body, $labels);
            $feedbackModel->update($item->id, ['github_issue_url' => $url]);
            CLI::write(CLI::color("Feedback #{$item->id} → {$url}", 'green'));
        } catch (RuntimeException $e) {
            CLI::error("Failed to create issue for feedback #{$item->id}: " . $e->getMessage());
        }
    }
}
