# Collecting Feedback

This page covers everything about the public-facing feedback endpoint: the routes that get auto-discovered, the config options that control behavior, the three views, and how to swap in your own templates.

## How it works

Installing the package gives you two routes automatically — no manual wiring needed:

| Method | URL | Behavior |
|--------|-----|----------|
| `GET` | `/feedback` | Renders the feedback form (or the closed page) |
| `POST` | `/feedback/submit` | Validates and saves a submission |

Both routes respect your `$routePrefix` config, so if you change the prefix they both update together.

The `POST` endpoint does content negotiation out of the box. Send `Accept: application/json` (or the `X-Requested-With: XMLHttpRequest` header) and you'll get a JSON response. Leave it as a plain form POST and you'll get a redirect with flash messages. The bundled form handles this automatically via `fetch()` with a graceful non-JS fallback.

## Configuration

Create `app/Config/Betta.php` in your host app to override any option:

```php
<?php

namespace Config;

use Myth\Betta\Config\Betta as BettaConfig;

class Betta extends BettaConfig
{
    public string $routePrefix = 'feedback';
    public bool $acceptSubmissions = true;
}
```

### Options

#### `$routePrefix`

The URL segment for both routes. Defaults to `feedback`.

```php
// Registers GET /beta-feedback and POST /beta-feedback/submit
public string $routePrefix = 'beta-feedback';
```

#### `$acceptSubmissions`

Set to `false` to stop accepting new feedback without removing the routes. Visitors will see `closed.php` instead of the form.

```php
public bool $acceptSubmissions = false;
```

!!! tip "Flip the switch without a deploy"
    Since `Betta` extends `BaseConfig`, you can also drive `$acceptSubmissions` from a `.env` value: `betta.acceptSubmissions = false`. Useful for quickly closing the form without touching code.

## What gets saved

Every successful submission stores:

| Field | Source | Notes |
|-------|--------|-------|
| `session_id` | `sha256(session_id())` | Pseudonymous — not reversible to a real session |
| `category` | POST `category` field | One of `bug`, `ux`, `feature`, `other` |
| `message` | POST `message` field | Required |
| `email` | POST `email` field | Optional; validated if provided |
| `url_context` | POST `url_context` field, then `Referer` header | The page the user was on when they submitted |

The `url_context` hidden field is populated by JavaScript (`window.location.href`) before submission, so you get the exact page — not just the previous URL from the `Referer` header.

## The views

Three views ship with the package:

| View | Rendered when |
|------|---------------|
| `page.php` | `$acceptSubmissions` is `true` — the full standalone page |
| `form.php` | Embedded inside `page.php`; also embeddable in your own layouts |
| `closed.php` | `$acceptSubmissions` is `false` |

### Embedding the form in your own layout

You don't have to use `page.php`. Render just the form fragment anywhere:

```php
<?= view('Myth\Betta\Views\form', [
    'categories' => \Myth\Betta\Enums\CategoryEnum::cases(),
    'submitUrl'  => config(\Myth\Betta\Config\Betta::class)->routePrefix . '/submit',
]) ?>
```

### Overriding views

Drop a replacement file into your host app at `app/Views/vendor/betta/{view}.php` and it takes precedence over the package view. The controller checks that path first.

```
app/
  Views/
    vendor/
      betta/
        form.php      ← overrides the package form
        page.php      ← overrides the full-page wrapper
        closed.php    ← overrides the closed state page
```

You only need to create the files you want to override — the rest fall back to the package defaults.

## Response formats

### JSON (fetch path)

Send `Accept: application/json` to get structured responses:

**Success (200)**
```json
{ "ok": true }
```

**Validation failure (422)**
```json
{
  "errors": {
    "message": "The message field is required."
  }
}
```

### Plain POST

- **Success** → redirect to `/{routePrefix}` with a `feedback_success` flash message
- **Validation failure** → redirect back with an `errors` flash and repopulated input via `withInput()`

The bundled `form.php` handles both paths — it reads flash messages on load and intercepts submissions with `fetch()` when JavaScript is available.

## Next steps

- [Database](database.md) — the schema behind feedback storage
- [Models](models.md) — querying and working with saved feedback
- [Enums](enums.md) — the category, status, sentiment, and priority types
