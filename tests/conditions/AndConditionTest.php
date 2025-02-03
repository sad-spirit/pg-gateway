<?php

/*
 * This file is part of sad_spirit/pg_gateway package
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\conditions;

use sad_spirit\pg_gateway\conditions\AndCondition;

class AndConditionTest extends LogicalConditionTestCase
{
    protected function getTestedClassName(): string
    {
        return AndCondition::class;
    }
}
