<?php

/**
 * @file
 * Bootstrap file for unit tests that don't need the full Drupal stack.
 *
 * Loads the project autoloader and registers the module's namespaces.
 */

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

require __DIR__ . '/../../../../../vendor/autoload.php';

$loader = new ClassLoader();
$loader->addPsr4(
  'Drupal\\graphql_compose_codegen\\',
  __DIR__ . '/../src'
);
$loader->addPsr4(
  'Drupal\\Tests\\graphql_compose_codegen\\',
  __DIR__ . '/src'
);
$loader->register(TRUE);
