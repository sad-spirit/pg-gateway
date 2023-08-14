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

use sad_spirit\pg_gateway\{
    Fragment,
    ParameterHolder,
    Parametrized,
    holders\SimpleParameterHolder
};
use sad_spirit\pg_builder\nodes\ScalarExpression;

class ParametrizedFragmentImplementation extends FragmentImplementation implements Parametrized
{
    /** @var array<string, mixed> */
    private array $parameters;

    public function __construct(
        ScalarExpression $expression,
        array $parameters,
        ?string $key,
        int $priority = Fragment::PRIORITY_DEFAULT
    ) {
        parent::__construct($expression, $key, $priority);
        $this->parameters = $parameters;
    }

    public function getParameterHolder(): ?ParameterHolder
    {
        return new SimpleParameterHolder($this, $this->parameters);
    }
}
