# AI Clustering

`feedback:analyze` uses AI to group your ungrouped feedback into clusters automatically. Instead of manually reading each item and deciding where it belongs, you run the command and get a set of suggested clusters — then accept, skip, or tweak each one.

It requires [`myth/scribe`](https://github.com/myth/scribe) to be installed. The rest of the package works fine without it.

## Prerequisites

```bash
composer require myth/scribe
```

If you run `feedback:analyze` without Scribe installed, you'll get a clear message and a non-zero exit code — nothing breaks.

## Basic usage

```bash
php spark feedback:analyze
```

This fetches your ungrouped, non-dismissed feedback (up to `$analyzeBatchSize` items — see [Configuration](#configuration)), sends them to the AI alongside your existing cluster labels, and walks you through the suggestions one by one.

Each suggestion looks like this:

```
New cluster: Login & Auth Issues
Summary: Users are having trouble signing in and resetting passwords.
Items: 14, 27, 31, 58
Action ([y] Accept / [n] Skip / [e] Edit label):
```

When the AI spots an existing cluster that fits, it'll suggest assigning to it instead:

```
Cluster: Mobile Crashes → existing cluster #3
Summary: App crashes reported on iOS 17 and Android 14.
Items: 22, 39
Action ([y] Accept / [n] Skip / [e] Edit label):
```

## Actions

| Key | What it does |
|-----|--------------|
| `y` | Accept the suggestion as-is. Creates or updates the cluster and assigns the items. |
| `n` | Skip this suggestion. Items stay ungrouped. |
| `e` | Edit the AI-suggested label before saving. The prompt pre-fills the AI label — overwrite it and press Enter. |

## Modes

### `--dry-run`

Prints suggestions without writing anything to the database. Useful for seeing what the AI thinks before committing.

```bash
php spark feedback:analyze --dry-run
```

### `--apply`

Accepts all suggestions automatically without any prompts. Good for scripts or when you trust the AI's groupings.

```bash
php spark feedback:analyze --apply
```

### `--limit`

Override how many ungrouped items are sent to the AI in this batch. Useful when you want to process a smaller slice of a large backlog.

```bash
php spark feedback:analyze --limit 20
```

## Configuration

Add this to your `app/Config/Betta.php` to change the default batch size:

```php
public int $analyzeBatchSize = 50;
```

The default is `50`. `--limit` at the CLI always takes precedence.

!!! tip "Start small"
    On a fresh install with hundreds of backlogged items, run with `--limit 30 --dry-run` first to get a feel for how the AI groups your specific data before applying anything.

## How the AI is prompted

`ClusterFeedbackPrompt` builds the prompt that gets sent to Scribe. It has three methods that Scribe's driver reads:

| Method | What it returns |
|--------|----------------|
| `systemPrompt()` | Instructs the AI to produce 3–8 clusters and reference existing labels |
| `userPrompt()` | Existing cluster labels + ungrouped item IDs and messages |
| `schema()` | JSON schema defining the expected response shape |

You can extend or replace this class if you need different prompt logic — for example, to add domain-specific instructions or change the cluster count range. Swap in your subclass wherever `ClusterFeedbackPrompt` is instantiated in `FeedbackAnalyzeCommand`.

### Response schema

Each AI suggestion has this shape:

```json
{
  "label": "Login & Auth Issues",
  "summary": "Users are having trouble signing in and resetting passwords.",
  "ids": [14, 27, 31, 58],
  "existing_cluster_id": null
}
```

- `existing_cluster_id` — `null` means create a new cluster; an integer means assign items to that existing cluster ID.
- `label` and `summary` are required. `ids` is required and must be non-empty for the suggestion to do anything.

## Error handling

If the AI service throws an error, the command prints the message and exits with a non-zero code — it won't silently skip half your data or corrupt anything already written.

```
AI error: upstream API returned 503
```

Any items already applied before the error are preserved. The command is safe to re-run after fixing the issue.

## Next steps

- [Managing Clusters](managing-clusters.md) — edit labels, set priorities, delete clusters the AI got wrong
- [Triaging Feedback](triaging-feedback.md) — manual item-by-item triage for the items that didn't cluster cleanly
