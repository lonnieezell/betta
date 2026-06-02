# Managing Clusters

Clusters let you group related feedback items together — "Login UX issues", "Mobile crashes", "Onboarding requests" — so you can triage in bulk instead of item by item. These four Spark commands cover the full lifecycle.

## Listing clusters

```bash
php spark feedback:clusters
```

This shows all clusters sorted by most recently updated:

```
+----+------------------+----------+-------+---------------------+
| ID | Label            | Priority | Items | Updated             |
+----+------------------+----------+-------+---------------------+
|  3 | Login UX Issues  | high     |    12 | 2026-06-01 14:22:00 |
|  1 | Onboarding Asks  | medium   |     5 | 2026-05-30 09:10:00 |
|  2 | Mobile Crashes   | critical |     3 | 2026-05-28 17:45:00 |
+----+------------------+----------+-------+---------------------+
```

Item count is always computed live — it reflects how many feedback rows are currently assigned to the cluster.

### `--priority`

Filter to clusters of a specific priority. Accepted values: `low`, `medium`, `high`, `critical`.

```bash
php spark feedback:clusters --priority high
```

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
php spark feedback:cluster:edit <id> [--label="..."] [--priority=<val>]
```

Update the label, the priority, or both. You need to pass at least one flag — the command errors if you don't.

```bash
# Rename only
php spark feedback:cluster:edit 7 --label "Login & Auth Issues"

# Change priority only
php spark feedback:cluster:edit 7 --priority critical

# Update both at once
php spark feedback:cluster:edit 7 --label "Login & Auth Issues" --priority critical
```

If the ID doesn't exist you'll get an error rather than a silent no-op.

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

## Next steps

- [Browsing Feedback](browsing-feedback.md) — filter and view individual items, including by cluster
- [Models](models.md) — query clusters and feedback directly when you need more than the CLI offers
