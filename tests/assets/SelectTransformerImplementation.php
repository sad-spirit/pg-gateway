<?php

/*
 * This file is part of sad_spirit/pg_gateway:
 * Table Data Gateway for Postgres - auto-converts types, allows raw SQL, supports joins between gateways
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\assets;

use sad_spirit\pg_builder\SetOpSelect;
use sad_spirit\pg_builder\SelectCommon;
use sad_spirit\pg_builder\enums\SetOperator;
use sad_spirit\pg_gateway\SelectTransformer;

class SelectTransformerImplementation extends SelectTransformer
{
    protected function transform(SelectCommon $original): SelectCommon
    {
        return new SetOpSelect(clone $original, clone $original, SetOperator::UNION_ALL);
    }
}
