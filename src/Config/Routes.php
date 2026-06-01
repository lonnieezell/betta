<?php

declare(strict_types=1);

/**
 * This file is part of Myth/Betta.
 *
 * (c) Your Name <you@example.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

$betta  = config(\Myth\Betta\Config\Betta::class);
$prefix = $betta->routePrefix;

$routes->get($prefix, '\Myth\Betta\Controllers\FeedbackController::index');
$routes->post($prefix . '/submit', '\Myth\Betta\Controllers\FeedbackController::submit');
