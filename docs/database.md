# Database

Running `php spark migrate --all` creates two tables. Here's what each one stores.

## `betta_feedback`

Each row is a single piece of feedback submitted by a user.

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | Auto-increment |
| `session_id` | VARCHAR(64) | SHA-256 of `session_id()` — anonymous identity across submissions |
| `email` | VARCHAR(255) | Optional contact address |
| `category` | VARCHAR(20) | One of `bug`, `ux`, `feature`, `other` — default `other` |
| `message` | TEXT | The feedback body — required |
| `url_context` | VARCHAR(500) | The page the user was on (`window.location.href`, fallback to `HTTP_REFERER`) |
| `sentiment` | TINYINT | `-1` negative, `0` neutral, `1` positive — nullable |
| `status` | VARCHAR(20) | One of `new`, `reviewed`, `grouped`, `dismissed` — default `new` |
| `cluster_id` | INT UNSIGNED | FK to `feedback_clusters.id` — nullable |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

The enum-like columns (`category`, `status`, `sentiment`) are `VARCHAR`/`TINYINT` — no DB-level ENUM type — so the schema works across MySQL, SQLite, PostgreSQL, and SQLSRV without modification.

## `feedback_clusters`

Clusters are groupings you create to organize related feedback. They have no `item_count` column — the count is always computed live via a `COUNT()` join, so it can never drift out of sync.

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | Auto-increment |
| `label` | VARCHAR(255) | Short name for the cluster — required |
| `summary` | TEXT | Manual or AI-generated description — nullable |
| `priority` | VARCHAR(20) | One of `low`, `medium`, `high`, `critical` — default `medium` |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

## Next steps

- [Models](models.md) — reading and writing feedback via CI4 models
- [Enums](enums.md) — the PHP enum types that back these columns
