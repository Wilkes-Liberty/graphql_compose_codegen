<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\graphql_compose_codegen\Attribute\FieldTypeMapper;

/**
 * Maps entity_reference and entity_reference_revisions fields to TypeScript.
 *
 * Paragraph references are always multi-value (cardinality unlimited by
 * convention) regardless of storage cardinality, and emit DrupalParagraph[].
 */
#[FieldTypeMapper(
  id: 'entity_reference',
  drupalTypes: ['entity_reference', 'entity_reference_revisions'],
)]
final class EntityReferenceMapper extends FieldTypeMapperBase {

  /**
   * {@inheritdoc}
   */
  protected function getBaseTsType(FieldDefinitionInterface $definition): string {
    $targetType = $definition->getSetting('target_type') ?? 'node';
    return match ($targetType) {
      'taxonomy_term' => 'TaxonomyTermRef',
      'user' => 'Author',
      'media' => 'DrupalMedia',
      'node' => 'RelatedNode',
      'paragraph' => 'DrupalParagraph[]',
      default => 'unknown',
    };
  }

}
