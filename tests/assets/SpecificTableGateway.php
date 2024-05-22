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
    TableLocator,
    gateways\GenericTableGateway,
    metadata\TableName
};

class SpecificTableGateway extends GenericTableGateway
{
    public function __construct(TableLocator $tableLocator)
    {
        parent::__construct(new TableName('public', 'unconditional'), $tableLocator);
    }
}
