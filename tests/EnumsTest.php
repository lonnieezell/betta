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

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Betta\Enums\CategoryEnum;
use Myth\Betta\Enums\PriorityEnum;
use Myth\Betta\Enums\SentimentEnum;
use Myth\Betta\Enums\StatusEnum;

/**
 * @internal
 */
final class EnumsTest extends CIUnitTestCase
{
    public function testCategoryEnumCases(): void
    {
        $this->assertSame('bug', CategoryEnum::Bug->value);
        $this->assertSame('ux', CategoryEnum::UX->value);
        $this->assertSame('feature', CategoryEnum::Feature->value);
        $this->assertSame('other', CategoryEnum::Other->value);
    }

    public function testStatusEnumCases(): void
    {
        $this->assertSame('new', StatusEnum::New->value);
        $this->assertSame('reviewed', StatusEnum::Reviewed->value);
        $this->assertSame('grouped', StatusEnum::Grouped->value);
        $this->assertSame('dismissed', StatusEnum::Dismissed->value);
    }

    public function testPriorityEnumCases(): void
    {
        $this->assertSame('low', PriorityEnum::Low->value);
        $this->assertSame('medium', PriorityEnum::Medium->value);
        $this->assertSame('high', PriorityEnum::High->value);
        $this->assertSame('critical', PriorityEnum::Critical->value);
    }

    public function testSentimentEnumCases(): void
    {
        $this->assertSame(-1, SentimentEnum::Negative->value);
        $this->assertSame(0, SentimentEnum::Neutral->value);
        $this->assertSame(1, SentimentEnum::Positive->value);
    }

    public function testCategoryEnumFromValue(): void
    {
        $this->assertSame(CategoryEnum::Bug, CategoryEnum::from('bug'));
        $this->assertSame(CategoryEnum::Feature, CategoryEnum::from('feature'));
    }

    public function testSentimentEnumFromValue(): void
    {
        $this->assertSame(SentimentEnum::Negative, SentimentEnum::from(-1));
        $this->assertSame(SentimentEnum::Positive, SentimentEnum::from(1));
    }
}
