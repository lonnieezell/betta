# Browsing Feedback

Once submissions start coming in, you'll want to triage them. The `feedback:list` Spark command gives you a quick filtered view straight from the terminal — no admin UI required.

## Basic usage

```bash
php spark feedback:list
```

This returns up to 20 `new` items, sorted newest first:

```
+----+----------+--------+-----------+---------------------------------------------------+
| ID | Category | Status | Cluster   | Message                                           |
+----+----------+--------+-----------+---------------------------------------------------+
| 42 | bug      | new    | —         | The login button doesn't respond on mobile Safari |
| 41 | ux       | new    | Login UX  | The password field loses focus when I tap it…     |
| 40 | feature  | new    | —         | Could you add dark mode to the dashboard?         |
+----+----------+--------+-----------+---------------------------------------------------+
```

Dismissed items are always hidden unless you ask for them explicitly.

## Flags

### `--status`

Filter by status. Accepted values match `StatusEnum`: `new`, `reviewed`, `grouped`, `dismissed`.

```bash
php spark feedback:list --status reviewed
php spark feedback:list --status dismissed
```

### `--category`

Filter by category. Accepted values: `bug`, `ux`, `feature`, `other`.

```bash
php spark feedback:list --category bug
```

Combine with `--status` to narrow further:

```bash
php spark feedback:list --category bug --status reviewed
```

### `--cluster`

Show only items belonging to a specific cluster. Pass the cluster ID.

```bash
php spark feedback:list --cluster 7
```

### `--ungrouped`

Show only items that haven't been assigned to a cluster yet.

```bash
php spark feedback:list --ungrouped
```

This combines additively with `--status`, so you can find unreviewed orphans:

```bash
php spark feedback:list --ungrouped --status new
```

### `--limit`

Override the default 20-row cap.

```bash
php spark feedback:list --limit 50
```

## Output columns

| Column | Description |
|--------|-------------|
| ID | The feedback row's primary key |
| Category | `bug`, `ux`, `feature`, or `other` |
| Status | Current workflow status |
| Cluster | Cluster label, or `—` if ungrouped |
| Message | First 50 characters of the submission (truncated with `…` if longer) |

## Next steps

- [Collecting Feedback](collecting-feedback.md) — how submissions are saved in the first place
- [Models](models.md) — query the feedback table directly when you need more power
