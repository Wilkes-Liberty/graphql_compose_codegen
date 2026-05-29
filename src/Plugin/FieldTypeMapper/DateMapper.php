<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\graphql_compose_codegen\Attribute\FieldTypeMapper;

/**
 * Maps Drupal date/time fields to TypeScript.
 */
#[FieldTypeMapper(
  id: 'date',
  drupalTypes: ['datetime', 'timestamp', 'created', 'changed', 'smartdate'],
)]
final class DateMapper extends FieldTypeMapperBase {

  protected function getBaseTsType(FieldDefinitionInterface $definition): string {
    return $definition->getType() === 'smartdate' ? 'SmartDate' : 'string';
  }

}
