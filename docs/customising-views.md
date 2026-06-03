# Customising Views & Config

The `feedback:publish` command copies the package's view files and/or config into your app so you can customise them. Published files take precedence over the package defaults automatically — no extra wiring needed.

## Publishing views

Run this to copy all three view files into your app:

```bash
php spark feedback:publish --views
```

That places the following files in your project:

```
app/
  Views/
    vendor/
      betta/
        form.php      ← the feedback form fragment
        page.php      ← the full-page wrapper
        closed.php    ← shown when submissions are disabled
```

Edit any file and your changes are live immediately. You only need to keep the files you're actually changing — delete the rest and the package falls back to its own defaults.

!!! tip "Just want to tweak the form?"
    You can publish only the views you need. Delete `page.php` and `closed.php` after publishing and the package will serve its built-in versions for those, while still using your custom `form.php`.

### How overrides work

The controller checks `app/Views/vendor/betta/{view}.php` before falling back to the package view. `page.php` applies the same check for its embedded `form.php` — so if you publish `form.php`, `page.php` will use your version automatically, whether `page.php` itself is published or not.

## Publishing the config

Run this to scaffold a config file in your app:

```bash
php spark feedback:publish --config
```

That writes `app/Config/Betta.php`:

```php
<?php

declare(strict_types=1);

namespace Config;

use Myth\Betta\Config\Betta as BettaConfig;

class Betta extends BettaConfig
{
    // The route prefix for the feedback endpoints.
    // Produces GET /{routePrefix} and POST /{routePrefix}/submit.
    // public string $routePrefix = 'feedback';

    // Whether to accept new feedback submissions.
    // When false, the closed view is shown instead of the form.
    // public bool $acceptSubmissions = true;

    // Maximum number of ungrouped items to send to the AI in a single
    // feedback:analyze batch. Override with --limit at the CLI.
    // public int $analyzeBatchSize = 50;
}
```

All properties are commented out — they inherit their defaults from the package config. Uncomment and change only the ones you need:

```php
class Betta extends BettaConfig
{
    public string $routePrefix = 'beta-feedback';
    public bool $acceptSubmissions = false;
}
```

CI4's config cascade picks up `app/Config/Betta.php` automatically. No registration required.

!!! note "You can also skip the publish step"
    If all you need to change is one or two values, you can create `app/Config/Betta.php` by hand or drive values from `.env` (e.g. `betta.acceptSubmissions = false`) without publishing at all.

## Publishing both at once

The two flags are independent and can be combined in a single command:

```bash
php spark feedback:publish --views --config
```

## Overwrite prompts

If a file already exists at the destination, the command asks before overwriting it:

```
Overwrite existing form.php? [y, n]:
```

Answer `y` to replace it with the current package version, or `n` to leave your file untouched. Each file is prompted separately, so you can selectively refresh just the ones you want.

## Next steps

- [Collecting Feedback](collecting-feedback.md) — config options and how the form endpoint works
- [Triaging Feedback](triaging-feedback.md) — reviewing and acting on submitted feedback
