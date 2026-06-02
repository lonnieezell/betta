# PRD: myth/betta — CI4 Beta Feedback Package v1.0

**Status:** Draft
**Date:** 2026-05-28
**Package:** `myth/betta` | Namespace: `Myth\Betta`

---

## Overview

`myth/betta` is a drop-in CodeIgniter 4 Composer package that gives beta-stage applications a complete feedback collection and triage system. It ships with a public-facing form endpoint, a database-backed storage layer, and a full CLI toolkit for filtering, reviewing, grouping, and prioritizing user feedback — with no admin UI required. An optional AI-powered clustering command delegates to `myth/scribe` (opt-in, gracefully absent if not installed).

---

## Goals

- Zero-friction install: `composer require`, `php spark migrate --all`, done
- CLI-first triage via `php spark feedback:*` commands
- Smart grouping: manual clustering or AI-assisted via Scribe on demand
- Minimal footprint: no frontend framework, no queue, two DB tables
- Fully functional without Scribe; AI clustering is opt-in

---

## Non-Goals (v1)

- Email digest or notification system
- Web-based admin UI
- Bulk import/export (CSV/JSON)
- Cursor/offset pagination beyond `--limit`
- Sentiment auto-detection on ingest
- Multi-user triage (solo developer workflow)
- Rate limiting (delegate to host app middleware)

---

## Package Structure

```
src/
├── Config/
│   ├── Betta.php                    ← BaseConfig subclass
│   └── Routes.php                   ← auto-discovered routes
├── Controllers/
│   └── FeedbackController.php
├── Models/
│   ├── FeedbackModel.php
│   └── FeedbackClusterModel.php
├── Enums/
│   ├── CategoryEnum.php             ← string-backed: bug|ux|feature|other
│   ├── StatusEnum.php               ← string-backed: new|reviewed|grouped|dismissed
│   ├── PriorityEnum.php             ← string-backed: low|medium|high|critical
│   └── SentimentEnum.php            ← int-backed: -1|0|1
├── Commands/
│   ├── FeedbackList.php             ← spark feedback:list
│   ├── FeedbackReview.php           ← spark feedback:review [id]
│   ├── FeedbackGroup.php            ← spark feedback:group <id> <cluster_id>
│   ├── FeedbackClusters.php         ← spark feedback:clusters
│   ├── FeedbackClusterCreate.php    ← spark feedback:cluster:create
│   ├── FeedbackClusterEdit.php      ← spark feedback:cluster:edit
│   ├── FeedbackClusterDelete.php    ← spark feedback:cluster:delete
│   ├── FeedbackAnalyze.php          ← spark feedback:analyze (opt-in, requires Scribe)
│   └── FeedbackPublish.php          ← spark feedback:publish
├── Prompts/
│   └── ClusterFeedbackPrompt.php    ← Scribe BasePrompt subclass
├── Database/
│   └── Migrations/
│       ├── 2026-05-28-100000_CreateBettaFeedbackTable.php
│       └── 2026-05-28-100001_CreateFeedbackClustersTable.php
└── Views/
    ├── form.php                     ← embeddable fragment
    ├── page.php                     ← full-page wrapper (renders form.php)
    └── closed.php                   ← shown when acceptSubmissions = false
tests/
├── FeedbackModelTest.php
├── FeedbackClusterModelTest.php
├── FeedbackCommandTest.php
└── Prompts/
    └── ClusterFeedbackPromptTest.php
```

---

## Database Schema

### `betta_feedback`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK AUTO_INCREMENT | |
| `session_id` | VARCHAR(64) NULL | `hash('sha256', session_id())` |
| `email` | VARCHAR(255) NULL | |
| `category` | VARCHAR(20) NOT NULL | Cast to `CategoryEnum`; default `'other'` |
| `message` | TEXT NOT NULL | |
| `url_context` | VARCHAR(500) NULL | JS `window.location.href`, fallback to `HTTP_REFERER` |
| `sentiment` | TINYINT NULL | Cast to `SentimentEnum` (-1/0/1) |
| `status` | VARCHAR(20) NOT NULL | Cast to `StatusEnum`; default `'new'` |
| `cluster_id` | INT UNSIGNED NULL FK | References `feedback_clusters.id` |
| `created_at` | DATETIME NOT NULL | |
| `updated_at` | DATETIME NOT NULL | |

### `feedback_clusters`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK AUTO_INCREMENT | |
| `label` | VARCHAR(255) NOT NULL | |
| `summary` | TEXT NULL | Manual or AI-generated |
| `priority` | VARCHAR(20) NOT NULL | Cast to `PriorityEnum`; default `'medium'` |
| `created_at` | DATETIME NOT NULL | |
| `updated_at` | DATETIME NOT NULL | |

> **Note:** No `item_count` column. Count is computed dynamically via `COUNT()` join in `FeedbackClusterModel`. This eliminates counter-drift across all write paths.

---

## Configuration

```php
// src/Config/Betta.php
class Betta extends BaseConfig
{
    public string $routePrefix = 'feedback';
    public bool $acceptSubmissions = true;
    public array $categories = ['bug', 'ux', 'feature', 'other'];
    public int $analyzeBatchSize = 50;
    public int $maxMessageLength = 2000;
}
```

Host app overrides via CI4 config cascade — create `app/Config/Betta.php` extending the package class. No publish step required.

AI credentials live in Scribe's own `Config/AI.php`, not here.

---

## PHP Enums

Four enums live in `src/Enums/`:

```php
enum CategoryEnum: string {
    case Bug     = 'bug';
    case UX      = 'ux';
    case Feature = 'feature';
    case Other   = 'other';
}

enum StatusEnum: string {
    case New      = 'new';
    case Reviewed = 'reviewed';
    case Grouped  = 'grouped';
    case Dismissed = 'dismissed';
}

enum PriorityEnum: string {
    case Low      = 'low';
    case Medium   = 'medium';
    case High     = 'high';
    case Critical = 'critical';
}

enum SentimentEnum: int {
    case Negative = -1;
    case Neutral  = 0;
    case Positive = 1;
}
```

All four are declared in model `$casts`. CI4 4.5+ is required for enum casting support.

---

## Public-Facing Collection

### Routes (`src/Config/Routes.php`)

Auto-discovered by CI4. No manual include needed.

```php
$routes->group(config('Betta')->routePrefix, ['namespace' => 'Myth\Betta\Controllers'], function ($routes) {
    $routes->get('/',       'FeedbackController::index');
    $routes->post('submit', 'FeedbackController::submit');
});
```

### `FeedbackController::index`

- If `$acceptSubmissions === false`: render `closed.php`
- Otherwise: render `page.php` (which includes `form.php`)

### `FeedbackController::submit`

- Validates `category`, `message` (required), `email` (optional)
- Captures `url_context`: uses POSTed hidden field value if present, falls back to `HTTP_REFERER`
- Stores `hash('sha256', session_id())` as `session_id`
- **Content negotiation:**
  - `Accept: application/json` or `X-Requested-With: XMLHttpRequest` → JSON `{"ok":true}` or HTTP 422 + JSON errors
  - Plain POST → redirect back with flash message (success or validation errors)
- Honors host app CSRF configuration

### Form Views

**`form.php`** — embeddable fragment. Contains `<form>` with:
- Category dropdown (values from `$categories` config)
- Message textarea
- Email field (optional)
- Hidden `url_context` field populated by inline JS: `document.currentScript` or `DOMContentLoaded`
- CSRF field via `csrf_field()`
- Submits to `<?= base_url(config('Betta')->routePrefix . '/submit') ?>`

**`page.php`** — minimal full-page wrapper that renders `form.php`.

**`closed.php`** — "feedback is currently closed" message page.

View override: controller checks for `app/Views/vendor/betta/{view}.php` before falling back to package namespace.

---

## CLI Commands

### `feedback:list`

```bash
php spark feedback:list
php spark feedback:list --category=bug --status=new --ungrouped --cluster=3 --limit=25
```

Default status filter: `new`. Dismissed items excluded from default output.

Output: ID, category, status, cluster label (or `—`), 50-char message preview.

### `feedback:review [id]`

```bash
php spark feedback:review        # start triage from oldest new item
php spark feedback:review 142    # jump to item 142, then auto-advance
```

**Behavior:**
1. Load item (by ID or oldest `status=new`)
2. Display full detail
3. **Immediately set status to `reviewed`** (marks "seen")
4. Present line-based menu via `$this->prompt()`:
   - `a` — assign to existing cluster (shows cluster list, prompts for ID)
   - `n` — new cluster (prompts for label, creates cluster, assigns item)
   - `d` — dismiss (sets status to `dismissed`)
   - `q` — quit
5. After `a`, `n`, or `d`: auto-advance to next `status=new` item
6. When no more `new` items: print "No more items to review." and exit

### `feedback:group <id> <cluster_id>`

Non-interactive assignment. Sets `cluster_id`, sets status to `grouped`.

```bash
php spark feedback:group 142 3
```

### `feedback:clusters`

```bash
php spark feedback:clusters
php spark feedback:clusters --priority=high --sort=count
```

Lists all clusters. Item count computed via `COUNT()` join. Default sort: `updated_at DESC`.

### `feedback:cluster:create`

```bash
php spark feedback:cluster:create "Mobile Layout Issues" --priority=high
```

### `feedback:cluster:edit <id>`

```bash
php spark feedback:cluster:edit 3 --label="Mobile Navigation Issues" --priority=critical
```

### `feedback:cluster:delete <id>`

```bash
php spark feedback:cluster:delete 7
```

Ungroups all items in the cluster: sets `cluster_id = NULL`, resets status from `grouped` → `new`. Items with status `dismissed` are not touched.

### `feedback:analyze` *(requires `myth/scribe`)*

```bash
php spark feedback:analyze
php spark feedback:analyze --apply     # auto-accept all, no prompts
php spark feedback:analyze --dry-run   # print only, no writes
php spark feedback:analyze --limit=30  # override batch size
```

**Scribe guard:**
```php
if (! class_exists(\Myth\Scribe\Services\ScribeService::class)) {
    CLI::error('myth/scribe is not installed. Run: composer require myth/scribe');
    return EXIT_ERROR;
}
```

**`ClusterFeedbackPrompt`:**

Constructor: `__construct(private array $items, private array $existingClusters)`.

`systemPrompt()` instructs the AI to group items into 3–8 clusters, referencing existing cluster labels where appropriate.

`userPrompt()` provides existing clusters (id + label) and the ungrouped item list (id + message).

`schema()` returns:
```php
[
    'type' => 'array',
    'items' => [
        'type' => 'object',
        'properties' => [
            'label'               => ['type' => 'string'],
            'summary'             => ['type' => 'string'],
            'ids'                 => ['type' => 'array', 'items' => ['type' => 'integer']],
            'existing_cluster_id' => ['type' => ['integer', 'null']],
        ],
        'required' => ['label', 'summary', 'ids'],
    ],
]
```

If `existing_cluster_id` is non-null: assign `ids` to that cluster.
If null: create a new cluster with `label`/`summary`, then assign `ids`.

**Interactive review (default mode):** for each suggestion:
```
[1] "Save Button Disappears on Resize" (3 items) — NEW cluster
    IDs: 142, 139, 133
    Summary: Save button invisible when viewport < 900px.

[y] Accept  [n] Skip  [e] Edit label
```

`[e]` pre-fills the suggested label; developer overwrites before saving.

**Error handling:** `AIException` from `toArray()` caught and displayed — no crash.

### `feedback:publish`

```bash
php spark feedback:publish --views     # copies form.php, page.php, closed.php to app/Views/vendor/betta/
php spark feedback:publish --config    # writes app/Config/Betta.php extending package config
```

Flags are independent. Either or both may be used.

---

## CI4 Package Auto-Discovery

CI4 discovers the following automatically when the PSR-4 autoload entry is registered:

- `Config/Routes.php` — loaded alongside app routes
- `Config/Betta.php` — available via `config('Betta')`
- `Commands/*.php` — all Spark commands registered
- `Database/Migrations/` — picked up by `php spark migrate --all`
- `Views/` — accessible via namespaced `view()` call

No manual bootstrapping in host app beyond `composer require`.

---

## Installation

```bash
composer require myth/betta
php spark migrate --all
```

Optional AI clustering:
```bash
composer require myth/scribe
php spark config:publish Myth\Scribe\Config\AI
# add CLAUDE_API_KEY (or OPENAI_API_KEY etc.) to .env
```

Optional config override (no publish step needed — just create the file):
```php
// app/Config/Betta.php
use Myth\Betta\Config\Betta as BaseBetta;
class Betta extends BaseBetta {
    public string $routePrefix = 'betta';
}
```

---

## Requirements

- PHP 8.2+
- CodeIgniter 4.5+ (required for PHP enum model casting)
- `myth/scribe` — optional, required only for `feedback:analyze`

---

## Testing Strategy

| Layer | Approach |
|---|---|
| Models (`FeedbackModel`, `FeedbackClusterModel`) | `DatabaseTestTrait` + SQLite in-memory |
| Commands (`FeedbackList`, `FeedbackReview`, etc.) | Mocked model dependencies via Mockery |
| Controller (`FeedbackController`) | CI4 `FeatureTestCase` (HTTP-level) |
| `ClusterFeedbackPrompt` | Scribe `FakeDriver` — no HTTP calls |

---

## Acceptance Criteria

### Collection
- [ ] `POST /feedback/submit` saves all expected fields
- [ ] `url_context` uses JS hidden field value; falls back to `HTTP_REFERER`
- [ ] `session_id` stored as `hash('sha256', session_id())`
- [ ] Empty `message` returns HTTP 422 + JSON error body (fetch path) or redirect + flash (POST path)
- [ ] `GET /feedback` renders `closed.php` when `$acceptSubmissions = false`
- [ ] Form renders and submits via `fetch()`; degrades to standard POST without JS

### CLI — List & Review
- [ ] `feedback:list` filters correctly for all flag combinations
- [ ] `feedback:review` with no ID starts from oldest `status=new` item
- [ ] `feedback:review <id>` jumps to that item then auto-advances
- [ ] Viewing an item sets its status to `reviewed` immediately
- [ ] Auto-advances to next `status=new` item after each action
- [ ] Prints "No more items to review." and exits cleanly when queue is empty
- [ ] `[d]` sets status to `dismissed`; item excluded from default `feedback:list`

### CLI — Clustering
- [ ] `feedback:group` assigns item, sets status to `grouped`
- [ ] Item count in `feedback:clusters` output is always correct (computed via `COUNT()`)
- [ ] `feedback:cluster:delete` resets `grouped` items to `new`; leaves `dismissed` items untouched
- [ ] `feedback:cluster:create` and `feedback:cluster:edit` work end-to-end

### AI Analysis
- [ ] `feedback:analyze` exits with a clear message when `myth/scribe` is not installed
- [ ] `--dry-run` prints suggestions without writing
- [ ] `--apply` writes all suggestions without interactive prompts
- [ ] `[e]` in review mode pre-fills suggested label and accepts overwrite
- [ ] `AIException` caught and displayed gracefully
- [ ] `ClusterFeedbackPrompt` testable with Scribe `FakeDriver`
- [ ] Existing cluster labels passed in prompt; `existing_cluster_id` correctly routes assign vs create

### Package
- [ ] Installs cleanly into a fresh CI4 4.5+ app via Composer
- [ ] Migrations, commands, and routes auto-discovered with no manual wiring
- [ ] Config overridable via `app/Config/Betta.php` config cascade
- [ ] `feedback:publish --views` and `--config` work independently
- [ ] No conflicts with default CI4 app routes

---

## Out of Scope (v1)

See Non-Goals above. Future v2 candidates: `feedback:export`, `feedback:digest`, sentiment scoring on ingest, web UI option, duplicate detection at submission time.
