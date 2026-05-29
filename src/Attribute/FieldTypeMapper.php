<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

/**
 * Defines a field-type mapper plugin attribute.
 *
 * Plugins implementing this attribute claim one or more Drupal field type
 * plugin IDs and map them to a TypeScript type string.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class FieldTypeMapper extends Plugin {

  /**
   * Constructs a FieldTypeMapper attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param string[] $drupalTypes
   *   Drupal field type plugin IDs this mapper handles.
   */
  public function __construct(
    string $id,
    public readonly array $drupalTypes,
  ) {
    if ($drupalTypes === []) {
      throw new \InvalidArgumentException('FieldTypeMapper requires at least one Drupal type.');
    }
    parent::__construct($id);
  }

}
