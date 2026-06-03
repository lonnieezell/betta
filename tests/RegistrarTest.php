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

namespace Tests;

use CodeIgniter\Filters\CSRF;
use CodeIgniter\Test\CIUnitTestCase;
use Myth\Betta\Config\Registrar;
use Myth\Betta\Filters\RateLimitFilter;

/**
 * @internal
 */
final class RegistrarTest extends CIUnitTestCase
{
    public function testFiltersRegistersBettaRateLimitAlias(): void
    {
        $aliases = Registrar::Filters()['aliases'];

        $this->assertArrayHasKey('betta-rate-limit', $aliases);
        $this->assertSame(RateLimitFilter::class, $aliases['betta-rate-limit']);
    }

    public function testFiltersRegistersBettaCsrfAlias(): void
    {
        $aliases = Registrar::Filters()['aliases'];

        $this->assertArrayHasKey('betta-csrf', $aliases);
        $this->assertSame(CSRF::class, $aliases['betta-csrf']);
    }
}
