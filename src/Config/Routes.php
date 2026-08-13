<?php

declare(strict_types=1);

/**
 * This file is part of Myth/Betta.
 *
 * (c) Lonnie Ezell <lonnieje@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$betta  = config('Betta');
$prefix = $betta->routePrefix;

$routes->get($prefix, '\Myth\Betta\Controllers\FeedbackController::index');
$routes->post($prefix . '/submit', '\Myth\Betta\Controllers\FeedbackController::submit', ['filter' => ['betta-rate-limit', 'betta-csrf']]);
