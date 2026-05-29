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

namespace Tests\Support;

use CodeIgniter\Test\DatabaseTestTrait as CIDatabaseTestTrait;

/**
 * Wraps CI4's DatabaseTestTrait with defaults for the Myth/Betta package tests.
 * Individual test classes may override $namespace, $DBGroup, $migrate, etc.
 */
trait DatabaseTestTrait
{
    use CIDatabaseTestTrait;
}
