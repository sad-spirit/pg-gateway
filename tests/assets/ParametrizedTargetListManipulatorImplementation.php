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

namespace sad_spirit\pg_gateway\tests\assets;

use sad_spirit\pg_gateway\ParameterHolder;
use sad_spirit\pg_gateway\Parametrized;
use sad_spirit\pg_gateway\holders\SimpleParameterHolder;
use sad_spirit\pg_builder\nodes\TargetElement;

class ParametrizedTargetListManipulatorImplementation extends TargetListManipulatorImplementation implements
    Parametrized
{
    private array $parameters;

    public function __construct(TargetElement $item, ?string $key = null, array $parameters = [])
    {
        parent::__construct($item, $key);
        $this->parameters = $parameters;
    }

    public function getParameterHolder(): ParameterHolder
    {
        return new SimpleParameterHolder($this, $this->parameters);
    }
}
