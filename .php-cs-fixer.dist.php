<?php

/**
 * Custom configuration for PHP-CS-Fixer.
 *
 * @link https://cs.symfony.com/
 */

/**
 * First, retrieve the default configuration object.
 *
 * @var \PhpCsFixer\Config $config
 */
$config = require_once __DIR__ . '/vendor/stellarwp/coding-standards/src/php-cs-fixer.php';

$config->getFinder()
    ->notPath('tests/benchmark.php')
    ->files();

/*
 * IMPORTANT: the $config object must be returned from this file!
 */
return $config;
