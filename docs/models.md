# Models

The package ships two CI4 models. Both extend `CodeIgniter\Model` and use CI4 4.5's native enum casting, so reading a row always gives you proper PHP enum instances — no string comparisons needed.

## FeedbackModel

Manages the `betta_feedback` table.

```php
use Myth\Betta\Models\FeedbackModel;

$model = new FeedbackModel();
```

### Inserting feedback

Pass enum instances directly — the model's cast layer handles serialisation to the DB value.

```php
use Myth\Betta\Enums\CategoryEnum;
use Myth\Betta\Enums\SentimentEnum;
use Myth\Betta\Enums\StatusEnum;

$id = $model->insert([
    'message'   => 'The onboarding flow is confusing on mobile.',
    'category'  => CategoryEnum::UX,
    'sentiment' => SentimentEnum::Negative,
    'email'     => 'user@example.com',
]);
```

Omitting `category`, `status`, or `sentiment` uses the column defaults (`other`, `new`, `null`).

### Reading feedback

`find()` and `findAll()` return `stdClass` objects with enum instances in the cast fields:

```php
$row = $model->find($id);

$row->category;  // CategoryEnum::UX
$row->status;    // StatusEnum::New
$row->sentiment; // SentimentEnum::Negative  (or null if not set)
```

You can compare directly with enum cases:

```php
if ($row->status === StatusEnum::New) {
    // needs review
}
```

### Updating a row

```php
$model->update($id, ['status' => StatusEnum::Reviewed]);
```

!!! tip "Always pass enum instances, not strings"
    The model's `set()` cast expects enum instances on write. Passing the raw string `'reviewed'` instead of `StatusEnum::Reviewed` will throw a `CastException`.

### Validation

The model validates automatically on insert and update. The rules guard enum values at the application layer:

| Field | Rule |
|---|---|
| `message` | required |
| `category` | must be one of `bug`, `ux`, `feature`, `other` |
| `status` | must be one of `new`, `reviewed`, `grouped`, `dismissed` |
| `sentiment` | must be `-1`, `0`, or `1` if provided |

---

## FeedbackClusterModel

Manages the `feedback_clusters` table.

```php
use Myth\Betta\Models\FeedbackClusterModel;

$model = new FeedbackClusterModel();
```

### Creating a cluster

```php
use Myth\Betta\Enums\PriorityEnum;

$id = $model->insert([
    'label'    => 'Onboarding UX issues',
    'priority' => PriorityEnum::High,
    'summary'  => 'Multiple users struggling with the first-run flow on mobile.',
]);
```

### Reading a cluster

```php
$cluster = $model->find($id);

$cluster->label;    // 'Onboarding UX issues'
$cluster->priority; // PriorityEnum::High
```

### findAllWithCount()

The standard `findAll()` returns clusters without item counts. Use `findAllWithCount()` when you need the number of feedback items in each cluster — it computes the count via a `COUNT()` join so it's always accurate.

```php
$clusters = $model->findAllWithCount();

foreach ($clusters as $cluster) {
    echo $cluster->label;      // string
    echo $cluster->item_count; // int — always current
    echo $cluster->priority->value; // 'high' — still a PriorityEnum instance
}
```

!!! note "item_count is not stored"
    There's no `item_count` column. The count comes from a live `LEFT JOIN` against `betta_feedback`. This means it can never drift — reassigning or deleting feedback updates the count automatically on the next query.

## Next steps

- [Enums](enums.md) — all four enum types and their cases
- [Database](database.md) — the underlying table structure
