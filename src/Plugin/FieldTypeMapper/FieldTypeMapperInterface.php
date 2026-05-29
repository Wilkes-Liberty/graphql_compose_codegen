<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Interface for field-type mapper plugins.
 */
interface FieldTypeMapperInterface {

  /**
   * Maps a Drupal field definition to a TypeScript type string.
   */
  public function map(FieldDefinitionInterface $definition): string;

}
