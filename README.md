# Betta

A drop-in CodeIgniter 4 package that gives beta-stage applications a complete feedback collection and triage system. It ships with a public-facing form endpoint, a database-backed storage layer, and a full CLI toolkit for filtering, reviewing, grouping, and prioritizing user feedback — with no separate admin UI required.

An optional AI-powered grouping command uses an LLM to cluster similar feedback items on demand.

**[Full Documentation →](https://lonnieezell.github.io/betta)**

---

## Requirements

- PHP 8.2+
- CodeIgniter 4.3+

---

## Installation

```bash
composer require newmythmedia/ci4-beta-feedback
php spark migrate --all
```

That's it. Routes, Spark commands, and migrations are all auto-discovered by CI4 — no manual bootstrapping required.

---

## Configuration

Override the default config by creating `app/Config/BetaFeedback.php` in your host app:

```php
// app/Config/BetaFeedback.php
use Myth\Betta\Config\BetaFeedback as BaseBetaFeedback;

class BetaFeedback extends BaseBetaFeedback
{
    public string $routePrefix = 'feedback';
    public bool   $acceptSubmissions = true;
    public string $anthropicApiKey   = '';   // or use env('ANTHROPIC_API_KEY')
}
```

To enable the AI-powered `feedback:analyze` command, add your Anthropic API key to `.env`:

```
ANTHROPIC_API_KEY=sk-ant-...
```

To publish views for customization:

```bash
php spark feedback:publish --views
```

---

## Collecting Feedback

The package registers these routes automatically:

```
GET  /feedback         → renders the embeddable feedback form
POST /feedback/submit  → accepts and stores submissions
```

Embed the form in any view:

```php
echo view('Myth\Betta\Views\form');
```

The form submits via `fetch()` with a non-JS POST fallback. No frontend framework required.

---

## CLI Commands

All triage workflows run via `php spark feedback:*`.

### List feedback

```bash
php spark feedback:list
php spark feedback:list --category=bug --status=new
php spark feedback:list --ungrouped
php spark feedback:list --cluster=3 --limit=50
```

### Review a single item

```bash
php spark feedback:review 142
```

Opens an interactive prompt to assign the item to a cluster, create a new cluster, or dismiss it.

### Assign to a cluster

```bash
php spark feedback:group 142 3
```

### Manage clusters

```bash
php spark feedback:clusters
php spark feedback:clusters --priority=high
php spark feedback:clusters --create "Mobile Layout Issues" --priority=high
php spark feedback:clusters --edit=3 --label="Mobile Navigation" --priority=critical
php spark feedback:clusters --delete=7
```

### AI-powered grouping *(optional — requires Anthropic API key)*

```bash
php spark feedback:analyze            # review suggestions interactively
php spark feedback:analyze --dry-run  # print suggestions only, no writes
php spark feedback:analyze --apply    # auto-accept all suggestions
```

Reads ungrouped feedback, asks AI to suggest clusters, and presents them for confirmation before writing anything. Costs roughly $0.002–$0.01 per run at 50 items.

---

## License

MIT — see [LICENSE](LICENSE).
