<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\graphql_compose_codegen\Attribute\FieldTypeMapper;

/**
 * Maps webform reference fields to the DrupalWebform TypeScript type.
 *
 * The type and its GraphQL sub-selection follow the shape that
 * graphql_compose_webform exposes. Like every other mapper, this one maps
 * the field type unconditionally; whether the schema actually exposes the
 * field is a graphql_compose configuration concern.
 */
#[FieldTypeMapper(
  id: 'webform',
  drupalTypes: ['webform'],
)]
final class WebformMapper extends FieldTypeMapperBase {

  /**
   * {@inheritdoc}
   */
  protected function getBaseTsType(FieldDefinitionInterface $definition): string {
    return 'DrupalWebform';
  }

}
