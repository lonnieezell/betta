# Managing Clusters

Clusters let you group related feedback items together — "Login UX issues", "Mobile crashes", "Onboarding requests" — so you can triage in bulk instead of item by item. These four Spark commands cover the full lifecycle.

## Listing clusters

```bash
php spark feedback:clusters
```

This shows all clusters sorted by most recently updated:

```
+----+------------------+----------+----------+-------+---------------------+
| ID | Label            | Priority | Status   | Items | Updated             |
+----+------------------+----------+----------+-------+---------------------+
|  3 | Login UX Issues  | high     | active   |    12 | 2026-06-01 14:22:00 |
|  1 | Onboarding Asks  | medium   | resolved |     5 | 2026-05-30 09:10:00 |
|  2 | Mobile Crashes   | critical | active   |     3 | 2026-05-28 17:45:00 |
+----+------------------+----------+----------+-------+---------------------+
```

Item count is always computed live — it reflects how many feedback rows are currently assigned to the cluster.

Dismissed clusters are hidden unless you ask for them — see [`--status`](#-status) below.

### `--priority`

Filter to clusters of a specific priority. Accepted values: `low`, `medium`, `high`, `critical`.

```bash
php spark feedback:clusters --priority high
```

### `--status`

Filter by lifecycle status. Accepted values: `active`, `resolved`, `dismissed`, and `all`.

```bash
php spark feedback:clusters --status dismissed
```

With no `--status`, dismissed clusters are left out so your day-to-day view only shows what still needs attention. Pass `--status all` to see every cluster regardless of status.

### `--sort`

Change the sort order. Default is `updated_at` (most recently active first). Use `count` to float the most-populated clusters to the top.

```bash
php spark feedback:clusters --sort count
```

## Creating a cluster

```bash
php spark feedback:cluster:create "Login UX Issues"
```

Prints the new cluster's ID on success:

```
7
```

Use `--priority` to set the priority at creation time. If you skip it, priority defaults to `medium`.

```bash
php spark feedback:cluster:create "Critical Auth Bugs" --priority critical
```

## Editing a cluster

```bash
php spark feedback:cluster:edit <id> [--label="..."] [--priority=<val>] [--status=<val>]
```

Update the label, the priority, the status, or any combination. You need to pass at least one flag — the command errors if you don't.

```bash
# Rename only
php spark feedback:cluster:edit 7 --label "Login & Auth Issues"

# Change priority only
php spark feedback:cluster:edit 7 --priority critical

# Mark it fixed
php spark feedback:cluster:edit 7 --status resolved

# Update several at once
php spark feedback:cluster:edit 7 --label "Login & Auth Issues" --priority critical --status active
```

If the ID doesn't exist you'll get an error rather than a silent no-op.

### Cluster status

| Status | Meaning |
|--------|---------|
| `active` | Still open. This is the default for every new cluster. |
| `resolved` | You've fixed it. If [`feedback:analyze`](ai-clustering.md) later attaches new matching feedback, the cluster flips back to `active` so a recurrence doesn't get buried. |
| `dismissed` | Not actually a problem. New matching feedback is still filed against the cluster — nothing is lost or duplicated — but the cluster stays dismissed and stays out of the default listing. |

### Priority locking

Setting `--priority` on `feedback:cluster:edit` also locks the priority (`feedback:cluster:create --priority` does not — a cluster starts unlocked). `feedback:analyze` re-scores an unlocked cluster's priority every time it adds new items to it, but it leaves a locked cluster's priority exactly as you set it.

There's no separate unlock flag: set `--priority` again to change your call and keep it locked. If you want the AI to take priority back over, clear the lock directly:

```php
model('Myth\Betta\Models\FeedbackClusterModel')->update(7, ['priority_locked' => false]);
```

`--status` on its own never touches the lock.

## Deleting a cluster

```bash
php spark feedback:cluster:delete <id>
```

Before anything is changed, the command asks you to confirm:

```
Delete cluster #7 "Login & Auth Issues" and ungroup all its items? [yes, no]:
```

Type `yes` to proceed or anything else to abort.

**What happens to items when you delete a cluster:**

- Items with status `new`, `reviewed`, or `grouped` have their `cluster_id` cleared and status reset to `new`. They go back into the unreviewed pile.
- Items with status `dismissed` are left exactly as they are — their `cluster_id` is preserved and their status doesn't change.

!!! warning "Deletion is not reversible"
    There's no undo. If you want to preserve the grouping, consider renaming or reprioritising the cluster instead of deleting it.

## Assigning an item to a cluster

```bash
php spark feedback:group <id> <cluster_id>
```

Sets `cluster_id` and flips the item's status to `grouped` in one step. Both arguments are required.

```bash
php spark feedback:group 142 3
```

On success:

```
Feedback 142 assigned to cluster 3.
```

If either ID doesn't exist you'll get a clear error and a non-zero exit code:

```
Feedback item 142 not found.
```

```
Cluster 3 not found.
```

The non-zero exit makes it safe to use in scripts — failures won't silently pass through `&&` chains.

### Bulk reassignment

Because the command is non-interactive, it's easy to loop over a list of IDs in a shell script:

```bash
for id in 142 143 144 145; do
    php spark feedback:group "$id" 3
done
```

Or pipe IDs from another command:

```bash
php spark feedback:list --status new --limit 50 | awk '{print $1}' | xargs -I{} php spark feedback:group {} 3
```

!!! tip "Finding item IDs"
    Use `php spark feedback:list` to browse unassigned items and note their IDs before running a bulk assignment.

## Next steps

- [Browsing Feedback](browsing-feedback.md) — filter and view individual items, including by cluster
- [Models](models.md) — query clusters and feedback directly when you need more than the CLI offers
