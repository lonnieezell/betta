# Triaging Feedback

`feedback:review` is the main way to work through your queue. It walks you through each unreviewed item one at a time, shows you the full submission, and lets you assign it, create a new cluster for it, dismiss it, or quit — all without leaving the terminal.

## Basic usage

```bash
php spark feedback:review
```

This loads the oldest `new` item and starts the session. Once you act on an item (assign, dismiss, or move on), it automatically advances to the next one. When the queue is empty, it tells you and exits cleanly.

To jump straight to a specific item:

```bash
php spark feedback:review 42
```

After you act on item 42 the session continues with the next `new` item automatically.

## The triage loop

Each item is displayed in full:

```
--- Feedback #42 (bug / new) ---
Email:   user@example.com
URL:     https://example.com/login
Date:    2026-06-01 14:22:35

The login button doesn't respond on mobile Safari. I've tried three times
and it just sits there. No error message either.

Action (a=assign, n=new cluster, d=dismiss, q=quit):
```

As soon as the item is displayed, its status flips to `reviewed` — so even if you quit immediately, you won't see it again in the default `new` queue.

## Actions

### `a` — Assign to an existing cluster

Shows the cluster list inline, then prompts for a cluster ID:

```
[1] Login UX Issues
[3] Mobile Crashes
[7] Onboarding Requests

Cluster ID: 3
Feedback 42 assigned to cluster 3.
```

The item's status becomes `grouped` and its `cluster_id` is set.

### `n` — Create a new cluster and assign

Prompts for a label, creates the cluster, and assigns the item in one step:

```
Cluster label: Safari Login Bug
Created cluster 'Safari Login Bug' and assigned feedback 42.
```

You can always go back and edit the cluster label later with `feedback:cluster:edit`.

### `d` — Dismiss

Marks the item as `dismissed`. It'll stop appearing in normal list output and won't come up in future review sessions.

```
Feedback 42 dismissed.
```

Dismissal isn't permanent at the data level — a dismissed item can still be found with `feedback:list --status dismissed` and reassigned if needed.

### `q` — Quit

Exits the session immediately. The current item keeps whatever status was set when it was displayed (`reviewed`), and no further items are loaded.

## Auto-advance

After every `a`, `n`, or `d` action, the session automatically loads the next oldest `new` item. You don't have to do anything — just read and decide.

When there are no more items left:

```
No more items to review.
```

## Workflow example

You've got a batch of new feedback after a release. Here's a typical session:

```
$ php spark feedback:review

--- Feedback #55 (bug / new) ---
Email:   alice@example.com
URL:     https://app.example.com/checkout
Date:    2026-06-01 18:05:00

Payment fails on the final step with no error shown.

Action (a=assign, n=new cluster, d=dismiss, q=quit): n
Cluster label: Checkout Bugs
Created cluster 'Checkout Bugs' and assigned feedback 55.

--- Feedback #56 (ux / new) ---
Email:   —
URL:     https://app.example.com/settings
Date:    2026-06-01 18:12:00

The settings sidebar overlaps the main content on iPad.

Action (a=assign, n=new cluster, d=dismiss, q=quit): a
[1] Login UX Issues
[2] Checkout Bugs

Cluster ID: 1
Feedback 56 assigned to cluster 1.

--- Feedback #57 (other / new) ---
...

Action (a=assign, n=new cluster, d=dismiss, q=quit): d
Feedback 57 dismissed.

No more items to review.
```

## Next steps

- [Browsing Feedback](browsing-feedback.md) — filter and view items with `feedback:list`
- [Managing Clusters](managing-clusters.md) — create, edit, and delete clusters; bulk-assign via `feedback:group`
