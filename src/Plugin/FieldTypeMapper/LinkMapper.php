<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\graphql_compose_codegen\Attribute\FieldTypeMapper;

/**
 * Maps Drupal link fields to TypeScript.
 */
#[FieldTypeMapper(id: 'link', drupalTypes: ['link'])]
final class LinkMapper extends FieldTypeMapperBase {

  protected function getBaseTsType(FieldDefinitionInterface $definition): string {
    return 'Link';
  }

}
