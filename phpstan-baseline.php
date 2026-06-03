<?php declare(strict_types = 1);

$ignoreErrors = [
    [
        'message' => '#^PHPDoc tag @var with type Myth\\\\Betta\\\\Config\\\\Betta is not subtype of type Config\\\\Betta\\.$#',
        'identifier' => 'varTag.type',
        'count' => 1,
        'path' => __DIR__ . '/src/Commands/FeedbackAnalyzeCommand.php',
    ],
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
