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
    /** @var array<string,string> */
    private array $schemaMapping = [];
    /** @var array<string,?class-string<TableGateway>> */
    private array $gatewayClasses = [];
    /** @var array<string,?class-string<FragmentListBuilder>> */
    private array $builderClasses = [];
    private string $gatewayClassNameTemplate = '%sGateway';
    private string $builderClassNameTemplate = '%sBuilder';

    /**
     * Constructor, sets mappings from database schemas to PHP namespaces
     *
     * @param array<string,string> $schemaMapping
     */
    public function __construct(array $schemaMapping)
    {
        foreach ($schemaMapping as $schema => $namespace) {
            $this->setSchemaMapping($schema, $namespace);
        }
    }

    /**
     * Adds a mapping from database schema to gateway and builder namespaces
     *
     * Removes mapping if $namespace is null
     */
    public function setSchemaMapping(string $schema, ?string $namespace): void
    {
        if (null === $namespace) {
            unset($this->schemaMapping[$schema]);
        } else {
            $this->schemaMapping[$schema] = $this->normalizeNamespace($namespace);
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
     * Returns namespace for the given schema, null if not found
     */
    private function mapSchemaToNamespace(string $schema): ?string
    {
        return $this->schemaMapping[$schema] ?? null;
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
     * @param array<string, ?T> $classMap Existing mapping of table names to class names
     * @param string $template            Template for generated class name, sprintf-style
     * @return ?T
     */
    private function getClassNameForTable(TableName $name, array &$classMap, string $template): ?string
    {
        $nameAsString = (string)$name;
        if (\array_key_exists($nameAsString, $classMap)) {
            return $classMap[$nameAsString];
        }
        $fqn = null;
        if (null !== $namespace = $this->mapSchemaToNamespace($name->getSchema())) {
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
     * @return ?class-string<TableGateway>
     */
    private function getGatewayClassNameForTable(TableName $name): ?string
    {
        return $this->getClassNameForTable($name, $this->gatewayClasses, $this->gatewayClassNameTemplate);
    }

    /**
     * Returns a name of an existing builder class for the given table name
     *
     * @return ?class-string<FragmentListBuilder>
     */
    private function getBuilderClassNameForTable(TableName $name): ?string
    {
        return $this->getClassNameForTable($name, $this->builderClasses, $this->builderClassNameTemplate);
    }

    public function createGateway(TableDefinition $definition, TableLocator $tableLocator): ?TableGateway
    {
        if (null !== $className = $this->getGatewayClassNameForTable($definition->getName())) {
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
