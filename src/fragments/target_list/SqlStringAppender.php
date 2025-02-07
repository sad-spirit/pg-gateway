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

namespace sad_spirit\pg_gateway\fragments\target_list;

use sad_spirit\pg_gateway\{
    TableLocator,
    exceptions\LogicException,
    fragments\TargetListManipulator
};
use sad_spirit\pg_builder\Parser;
use sad_spirit\pg_builder\nodes\{
    Identifier,
    TargetElement,
    lists\TargetList
};

/**
 * Parses the given SQL string and merge()s it to the TargetList
 */
class SqlStringAppender extends TargetListManipulator
{
    public function __construct(
        private readonly Parser $parser,
        private readonly string $sql,
        private readonly ?string $alias = null
    ) {
    }

    public function modifyTargetList(TargetList $targetList): void
    {
        $parsed = $this->parser->parseTargetList($this->sql);

        if (null === $this->alias) {
            $targetList->merge($parsed);
        } elseif (1 === \count($parsed)) {
            $targetList[] = new TargetElement(clone $parsed[0]->expression, new Identifier($this->alias));
        } else {
            throw new LogicException("Parsing resulted in multiple expressions, cannot apply alias");
        }
    }

    public function getKey(): ?string
    {
        return TableLocator::hash([self::class, $this->sql, $this->alias]);
    }
}
