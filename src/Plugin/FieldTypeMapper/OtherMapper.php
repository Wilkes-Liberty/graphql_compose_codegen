<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\graphql_compose_codegen\Attribute\FieldTypeMapper;

/**
 * Maps complex/structured Drupal field types to a generic 'object' TS type.
 *
 * These fields have non-trivial shapes; consumers are expected to refine
 * with their own type definitions or register a custom mapper plugin.
 */
#[FieldTypeMapper(
  id: 'other',
  drupalTypes: ['address', 'geolocation', 'range_integer', 'range_float'],
)]
final class OtherMapper extends FieldTypeMapperBase {

  protected function getBaseTsType(FieldDefinitionInterface $definition): string {
    return 'object';
  }

}
