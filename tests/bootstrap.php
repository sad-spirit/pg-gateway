<?php

/*
 * This file is part of sad_spirit/pg_gateway package
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$autoloader = dirname(__DIR__) . '/vendor/autoload.php';

if (!file_exists($autoloader)) {
    echo "Composer autoloader not found: $autoloader" . PHP_EOL;
    echo "Please install dependencies with 'composer install' and try again." . PHP_EOL;
    exit(1);
}

require_once $autoloader;


$configuration = __DIR__ . '/config.php';

if (!file_exists($configuration)) {
    echo "Database configuration file not found: $configuration" . PHP_EOL;
    echo "Please edit the provided config.php.dist file setting connection properties" . PHP_EOL;
    echo "and save it as $configuration" . PHP_EOL;
    exit(1);
}

require_once $configuration;
