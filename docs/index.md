# Myth/Betta

`myth/betta` is a drop-in CodeIgniter 4 package that gives beta-stage apps a complete feedback collection and triage system. Install it, run the migration, and you've got a database-backed feedback store with a full CLI toolkit for reviewing, grouping, and prioritizing submissions — no admin UI required.

## What you get

- A public-facing endpoint that accepts feedback via a standard form or `fetch()`
- Two database tables with enum-typed columns and automatic CI4 model casting
- `php spark feedback:*` commands for reviewing and clustering feedback from the terminal
- Optional AI-assisted clustering via [`myth/scribe`](https://github.com/myth/scribe) — but it works great without it

## Quick start

```bash
composer require myth/betta
php spark migrate --all
```

That's it. Routes, commands, and migrations are all auto-discovered by CI4.

## Next steps

- [Installation](installation.md) — requirements and optional config
- [Database](database.md) — what the two tables store
- [Models](models.md) — working with `FeedbackModel` and `FeedbackClusterModel`
- [Enums](enums.md) — the four enum types and how casting works
