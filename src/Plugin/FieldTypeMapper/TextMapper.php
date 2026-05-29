<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\graphql_compose_codegen\Attribute\FieldTypeMapper;

/**
 * Maps Drupal text-like fields to TypeScript.
 */
#[FieldTypeMapper(
  id: 'text',
  drupalTypes: [
    'string',
    'string_long',
    'text',
    'text_long',
    'text_with_summary',
    'telephone',
    'email',
    'uri',
    'list_string',
    'color_field_type',
  ],
)]
final class TextMapper extends FieldTypeMapperBase {

  protected function getBaseTsType(FieldDefinitionInterface $definition): string {
    return match ($definition->getType()) {
      'text', 'text_long', 'text_with_summary' => 'ProcessedText',
      default => 'string',
    };
  }

}
