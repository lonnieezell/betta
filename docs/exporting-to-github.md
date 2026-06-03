# Exporting to GitHub

`feedback:github` turns a feedback item — or every item in a cluster — into a GitHub issue. Run it when you're ready to move actionable feedback into your normal dev workflow.

## Setup

### 1. Set your environment variables

The command reads credentials from the environment. Add these to your `.env` file (or however you manage secrets in your deployment):

```bash
GITHUB_TOKEN=ghp_yourPersonalAccessToken
GITHUB_OWNER=your-org-or-username
GITHUB_REPO=your-repo-name
```

Your token needs the `repo` scope (or `public_repo` for public repositories).

!!! danger "Never hard-code credentials"
    Don't put your token in `app/Config/Betta.php`. The config properties (`$githubToken`, `$githubOwner`, `$githubRepo`) exist as a fallback for unusual setups, but `.env` is the right place for credentials.

### 2. Create labels in your GitHub repo

The command applies labels to each issue — it won't create them. Before you start exporting, make sure these labels exist in your repo:

| Label | When it's applied |
|-------|------------------|
| `bug` | Feedback category is *bug* |
| `ux` | Feedback category is *ux* |
| `feature` | Feedback category is *feature* |
| `other` | Feedback category is *other* |
| `low`, `medium`, `high`, `critical` | Cluster priority (when exporting a whole cluster) |

You only need the labels that match your actual data — missing labels are silently skipped, not an error.

## Basic usage

Export a single feedback item by its ID:

```bash
php spark feedback:github 42
```

That creates one GitHub issue and writes the issue URL back to the `betta_feedback` row. You'll see the link printed in the terminal:

```
Feedback #42 → https://github.com/your-org/your-repo/issues/123
```

## Cluster mode

Export every item in a cluster at once with `--cluster`:

```bash
php spark feedback:github 7 --cluster
```

This creates one GitHub issue **per feedback item** in cluster 7 — not one issue for the cluster. Each item becomes its own trackable ticket so you can close them independently as you fix things.

```
Feedback #14 → https://github.com/your-org/your-repo/issues/124
Feedback #27 → https://github.com/your-org/your-repo/issues/125
Feedback #31 → https://github.com/your-org/your-repo/issues/126
```

## `--dry-run`

Not sure what you're about to create? Use `--dry-run` to print a preview without touching the GitHub API:

```bash
php spark feedback:github 7 --cluster --dry-run
```

Output looks like:

```
Would create issue for feedback #14:
Title:  [Feedback] bug: Login button doesn't respond on mobile Safari
Body:
Login button doesn't respond on mobile Safari — tapping it does nothing.

---
**Category:** bug
**Email:** user@example.com
**URL:** https://app.example.com/login
**Submitted:** 2026-05-12 09:14:33
Labels: bug, high
```

Dry-run works without credentials set — useful for previewing on a CI machine before a real run.

## How issues are formatted

Every issue has the same structure:

**Title:** `[Feedback] {category}: {first 80 characters of the message}`

**Body:**

```
{full message}

---
**Category:** bug
**Email:** user@example.com        (omitted if blank)
**URL:** https://app.example.com   (omitted if blank)
**Sentiment:** 1                   (omitted if not recorded)
**Submitted:** 2026-05-12 09:14:33
```

**Labels:** the category value (`bug`, `ux`, `feature`, or `other`), plus the cluster's priority label if you exported via `--cluster`.

## Re-running safely

If a feedback item already has a `github_issue_url`, the command skips it and shows you the existing link:

```
Feedback #14 already exported: https://github.com/your-org/your-repo/issues/124
```

This means you can safely re-run the command on a cluster after adding new items — previously exported items are untouched, and only the new ones get issues created.

## Next steps

- [Managing Clusters](managing-clusters.md) — set priorities on clusters before exporting so the right labels land on your GitHub issues
- [AI Clustering](ai-clustering.md) — use AI to group ungrouped feedback before exporting
