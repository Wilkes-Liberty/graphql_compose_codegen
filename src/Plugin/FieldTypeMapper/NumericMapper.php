<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\graphql_compose_codegen\Attribute\FieldTypeMapper;

/**
 * Maps Drupal numeric and boolean fields to TypeScript.
 */
#[FieldTypeMapper(
  id: 'numeric',
  drupalTypes: ['boolean', 'integer', 'float', 'decimal', 'list_integer', 'list_float'],
)]
final class NumericMapper extends FieldTypeMapperBase {

  /**
   * {@inheritdoc}
   */
  protected function getBaseTsType(FieldDefinitionInterface $definition): string {
    return $definition->getType() === 'boolean' ? 'boolean' : 'number';
  }

}
