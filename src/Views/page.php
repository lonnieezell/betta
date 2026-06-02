<?php
/**
 * @var list<\Myth\Betta\Enums\CategoryEnum> $categories
 * @var string                               $submitUrl
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback</title>
</head>
<body>
    <main>
        <h1>Share Your Feedback</h1>
        <?= view('Myth\Betta\Views\form', ['categories' => $categories, 'submitUrl' => $submitUrl]) ?>
    </main>
</body>
</html>
