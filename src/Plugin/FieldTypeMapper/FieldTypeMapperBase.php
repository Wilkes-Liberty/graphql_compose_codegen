<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Base class for field-type mapper plugins.
 */
abstract class FieldTypeMapperBase extends PluginBase implements FieldTypeMapperInterface {

  /**
   * {@inheritdoc}
   */
  public function map(FieldDefinitionInterface $definition): string {
    $base = $this->getBaseTsType($definition);
    if ($this->isMulti($definition) && !str_ends_with($base, '[]')) {
      $base .= '[]';
    }
    return $base;
  }

  /**
   * Returns the base TypeScript type for this Drupal field definition.
   *
   * Subclasses override this to return the bare type string (e.g. 'string',
   * 'Image'). The base class adds the '[]' suffix for multi-value fields.
   */
  abstract protected function getBaseTsType(FieldDefinitionInterface $definition): string;

  /**
   * Whether the field is multi-value.
   */
  protected function isMulti(FieldDefinitionInterface $definition): bool {
    $cardinality = $definition->getFieldStorageDefinition()->getCardinality();
    return $cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
      || $cardinality > 1;
  }

}
