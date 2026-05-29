# Installation

## Requirements

- PHP 8.2+
- CodeIgniter 4.5+ (required for PHP enum model casting)

## Install the package

```bash
composer require myth/betta
php spark migrate --all
```

CI4 auto-discovers the package — routes, Spark commands, and migrations are all wired up with no manual bootstrapping.

## Optional: AI clustering

To use `spark feedback:analyze`, install `myth/scribe` and configure an API key:

```bash
composer require myth/scribe
php spark config:publish Myth\Scribe\Config\AI
```

Then add your key to `.env`:

```
CLAUDE_API_KEY=sk-ant-...
# or OPENAI_API_KEY=sk-...
```

`myth/betta` works fine without Scribe — the `feedback:analyze` command just exits cleanly if it isn't installed.

## Optional: override config

You don't need to publish the config to override it. Create `app/Config/Betta.php` and extend the package class:

```php
<?php

use Myth\Betta\Config\Betta as BaseBetta;

class Betta extends BaseBetta
{
    public string $routePrefix = 'betta';   // default: 'feedback'
    public bool $acceptSubmissions = false;  // close the form
    public int $maxMessageLength = 1000;     // default: 2000
}
```

CI4's config cascade picks this up automatically — no publish step needed.

## Next steps

- [Database](database.md) — what the two tables store
- [Models](models.md) — working with `FeedbackModel` and `FeedbackClusterModel`
