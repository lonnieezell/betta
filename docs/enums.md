# Enums

The four enums in `Myth\Betta\Enums\` back the enum-typed columns in `betta_feedback` and `feedback_clusters`. CI4's model layer casts these automatically on read, so you always get enum instances back from a `find()` call.

## CategoryEnum

Classifies what kind of feedback was submitted.

```php
use Myth\Betta\Enums\CategoryEnum;

CategoryEnum::Bug      // 'bug'
CategoryEnum::UX       // 'ux'
CategoryEnum::Feature  // 'feature'
CategoryEnum::Other    // 'other'  ← column default
```

**Column:** `betta_feedback.category` (VARCHAR 20, default `other`)

## StatusEnum

Tracks where a feedback item is in the triage workflow.

```php
use Myth\Betta\Enums\StatusEnum;

StatusEnum::New        // 'new'       ← column default, needs review
StatusEnum::Reviewed   // 'reviewed'  set automatically on view
StatusEnum::Grouped    // 'grouped'   assigned to a cluster
StatusEnum::Dismissed  // 'dismissed' hidden from default list
```

**Column:** `betta_feedback.status` (VARCHAR 20, default `new`)

## PriorityEnum

Ranks how urgently a cluster needs attention.

```php
use Myth\Betta\Enums\PriorityEnum;

PriorityEnum::Low       // 'low'
PriorityEnum::Medium    // 'medium'  ← column default
PriorityEnum::High      // 'high'
PriorityEnum::Critical  // 'critical'
```

**Column:** `feedback_clusters.priority` (VARCHAR 20, default `medium`)

## SentimentEnum

Records the emotional tone of a feedback submission. It's int-backed so it sorts naturally.

```php
use Myth\Betta\Enums\SentimentEnum;

SentimentEnum::Negative  // -1
SentimentEnum::Neutral   //  0
SentimentEnum::Positive  //  1
```

**Column:** `betta_feedback.sentiment` (TINYINT, nullable — omit if unknown)

---

## Using enums in queries

All four enums are native PHP backed enums, so you can use `cases()`, `from()`, and `tryFrom()` as usual:

```php
// Get all feedback that still needs review
$items = (new FeedbackModel())
    ->where('status', StatusEnum::New->value)
    ->findAll();

// Convert a user-supplied string safely
$category = CategoryEnum::tryFrom($input) ?? CategoryEnum::Other;
```

!!! warning "Pass instances, not values, to model methods"
    When inserting or updating via the model, pass the enum instance (`StatusEnum::Reviewed`), not the backing value (`'reviewed'`). The model's write cast expects an enum object. Passing a string will throw a `CastException`.

## Next steps

- [Models](models.md) — how the models use these enums for casting
- [Database](database.md) — the column definitions behind each enum
