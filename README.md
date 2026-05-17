# YourVendor/YourPackage

A starter template for building CodeIgniter 4 packages. Replace `YourVendor`, `YourPackage`, and related placeholders throughout the codebase before publishing.

## Requirements

- PHP 8.4+
- CodeIgniter 4.7+

## Project Structure

```
src/
  Config/
    Registrar.php   # Hooks into CI4's auto-discovery (filters, etc.)
    Services.php    # Register package services
  Exceptions/
    PackageException.php
tests/
  ExampleTest.php
  _support/         # Test helpers and fixtures
```

## Getting Started with Docker

The repo includes a Docker setup using PHP 8.4 with all CI4-required extensions and Xdebug for coverage. Dependencies are installed automatically on first run.

Start the dev server (visits `http://localhost:8080` to see the CI4 welcome page):

```bash
docker compose up
```

Rebuild the image after changing the `Dockerfile`:

```bash
composer docker:build
```

## Running Tests

```bash
composer docker:test            # run phpunit in Docker
composer docker:test:coverage   # run with HTML coverage report (build/phpunit/html/)

# or locally
composer test
composer test:coverage
```

## Code Quality

```bash
composer docker:cs          # check coding style
composer docker:cs-fix      # fix coding style
composer docker:analyze     # PHPStan + Rector dry-run
composer docker:rector      # apply Rector changes
composer docker:ci          # run all checks (style, analysis, tests)

# or locally (same commands without the docker: prefix)
composer cs
composer cs-fix
composer analyze
composer ci
```

## Docker Shell

Open a bash shell inside the container:

```bash
composer docker:shell
```

## How the Package Integrates with CI4

CI4 auto-discovers your package via `src/Config/Registrar.php`. Add filter aliases, routes, or other config there. Register services in `src/Config/Services.php`. No manual wiring needed in the host app — Composer autoload and CI4's discovery handle it automatically.

## License

MIT — see [LICENSE](LICENSE).
