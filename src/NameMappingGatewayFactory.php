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

namespace sad_spirit\pg_gateway;

use sad_spirit\pg_gateway\builders\FragmentListBuilder;
use sad_spirit\pg_gateway\metadata\TableName;

/**
 * Factory that maps table names in a given schema to class names in given gateway / builder namespaces
 *
 * @since 0.2.0
 */
class NameMappingGatewayFactory implements TableGatewayFactory
{
    /** @var array{array<string,string>,array<string,string>} */
    private array $schemaMapping = [[], []];
    /** @var array<string,?class-string<TableGateway>> */
    private array $gatewayClasses = [];
    /** @var array<string,?class-string<FragmentListBuilder>> */
    private array $builderClasses = [];
    private string $gatewayClassNameTemplate = '%sGateway';
    private string $builderClassNameTemplate = '%sBuilder';

    /**
     * Constructor, sets mappings from database schemas to PHP namespaces
     *
     * @param array<string,array{?string,?string}> $schemaMapping
     */
    public function __construct(array $schemaMapping)
    {
        foreach ($schemaMapping as $schema => $namespaces) {
            $this->addSchemaMapping($schema, $namespaces[0] ?? null, $namespaces[1] ?? null);
        }
    }

    /**
     * Adds a mapping from database schema to gateway and builder namespaces
     */
    public function addSchemaMapping(string $schema, ?string $gatewayNamespace, ?string $builderNamespace): void
    {
        if (null === $gatewayNamespace) {
            unset($this->schemaMapping[0][$schema]);
        } else {
            $this->schemaMapping[0][$schema] = $this->normalizeNamespace($gatewayNamespace);
        }
        if (null === $builderNamespace) {
            unset($this->schemaMapping[1][$schema]);
        } else {
            $this->schemaMapping[1][$schema] = $this->normalizeNamespace($builderNamespace);
        }
    }

    /**
     * Sets a template for gateway class names
     */
    public function setGatewayClassNameTemplate(string $template): void
    {
        $this->gatewayClassNameTemplate = $template;
    }

    /**
     * Sets a template for builder class names
     */
    public function setBuilderClassNameTemplate(string $template): void
    {
        $this->builderClassNameTemplate = $template;
    }

    /**
     * Normalizes namespace, it should start with '\' and not end with it
     */
    private function normalizeNamespace(string $namespace): string
    {
        $parts = \array_filter(\explode('\\', $namespace));
        return '\\' . \implode('\\', $parts);
    }

    /**
     * Returns namespace for the given schema and index (0 - gateway, 1 - builder), null if not found
     */
    private function mapSchemaToNamespace(string $schema, int $idx = 0): ?string
    {
        return $this->schemaMapping[$idx][$schema] ?? null;
    }

    /**
     * Converts "table_name" to "TableName"
     */
    public function classify(string $name): string
    {
        return \str_replace([' ', '_', '-'], '', \ucwords($name, ' _-'));
    }

    /**
     * Returns a name of an existing gateway/builder class for the given table name
     *
     * @template T of class-string
     * @param TableName $name
     * @param array<string, ?T> $classMap Existing mapping of table names to class names
     * @param int $idx                    Index in the $schemaMapping array
     * @param string $template            Template for generated class name, sprintf-style
     * @return ?T
     */
    private function getClassNameForTable(TableName $name, array &$classMap, int $idx, string $template): ?string
    {
        $nameAsString = (string)$name;
        if (\array_key_exists($nameAsString, $classMap)) {
            return $classMap[$nameAsString];
        }
        $fqn = null;
        if (null !== ($namespace = $this->mapSchemaToNamespace($name->getSchema(), $idx))) {
            $className = \sprintf($template, $this->classify($name->getRelation()));
            if (\class_exists($namespace . '\\' . $className, true)) {
                /** @var T $fqn */
                $fqn = $namespace . '\\' . $className;
            }
        }
        return $classMap[$nameAsString] = $fqn;
    }

    /**
     * Returns a name of an existing gateway class for the given table name
     *
     * @param TableName $name
     * @return ?class-string<TableGateway>
     */
    private function getGatewayClassNameForTable(TableName $name): ?string
    {
        return $this->getClassNameForTable($name, $this->gatewayClasses, 0, $this->gatewayClassNameTemplate);
    }

    /**
     * @param TableName $name
     * @return ?class-string<FragmentListBuilder>
     */
    private function getBuilderClassNameForTable(TableName $name): ?string
    {
        return $this->getClassNameForTable($name, $this->builderClasses, 1, $this->builderClassNameTemplate);
    }

    public function createGateway(TableDefinition $definition, TableLocator $tableLocator): ?TableGateway
    {
        if (null !== ($className = $this->getGatewayClassNameForTable($definition->getName()))) {
            return new $className($definition, $tableLocator);
        }
        return null;
    }

    public function createBuilder(
        TableDefinition $definition,
        TableLocator $tableLocator
    ): ?builders\FragmentListBuilder {
        if (null !== ($className = $this->getBuilderClassNameForTable($definition->getName()))) {
            return new $className($definition, $tableLocator);
        }
        return null;
    }
}
