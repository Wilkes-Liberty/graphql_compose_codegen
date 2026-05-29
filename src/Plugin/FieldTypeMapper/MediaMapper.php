<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\graphql_compose_codegen\Attribute\FieldTypeMapper;

/**
 * Maps Drupal image/file fields to TypeScript.
 */
#[FieldTypeMapper(id: 'media', drupalTypes: ['image', 'file'])]
final class MediaMapper extends FieldTypeMapperBase {

  protected function getBaseTsType(FieldDefinitionInterface $definition): string {
    return $definition->getType() === 'image' ? 'Image' : 'DrupalMedia';
  }

}
