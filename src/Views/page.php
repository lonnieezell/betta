<?php

use Myth\Betta\Enums\CategoryEnum;

/**
 * @var list<CategoryEnum> $categories
 * @var string             $submitUrl
 * @var list<string>       $platforms
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
        <?php $formData = ['categories' => $categories, 'submitUrl' => $submitUrl, 'platforms' => $platforms]; ?>
        <?= is_file(APPPATH . 'Views/vendor/betta/form.php')
            ? view('vendor/betta/form', $formData)
            : view('Myth\Betta\Views\form', $formData) ?>
    </main>
</body>
</html>
