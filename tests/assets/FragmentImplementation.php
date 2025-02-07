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

use sad_spirit\pg_builder\{
    nodes\ScalarExpression,
    Statement
};
use sad_spirit\pg_gateway\{
    Fragment,
    exceptions\InvalidArgumentException,
    fragments\VariablePriority
};

class FragmentImplementation implements Fragment
{
    use VariablePriority;

    private array $parameterNames;

    public function __construct(
        private ScalarExpression $expression,
        private ?string $key,
        int $priority = Fragment::PRIORITY_DEFAULT
    ) {
        $this->setPriority($priority);
    }

    public function applyTo(Statement $statement): void
    {
        if (!isset($statement->where)) {
            throw new InvalidArgumentException(sprintf(
                "This fragment can only be applied to Statements containing a WHERE clause, instance of %s given",
                $statement::class
            ));
        }
        $statement->where->condition = $this->expression;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }
}
