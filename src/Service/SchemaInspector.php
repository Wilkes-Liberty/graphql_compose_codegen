<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Inspects the Drupal node schema and maps it to TypeScript/GraphQL shapes.
 *
 * Responsible for:
 *   - Enumerating node bundles.
 *   - Resolving which fields are "extra" (i.e. not already in the shared base
 *     type) for each bundle.
 *   - Mapping Drupal field type plugin IDs to TypeScript type strings.
 *   - Producing the graphql_compose-style field name (field_ prefix stripped,
 *     camelCased).
 */
class SchemaInspector {

  /**
   * Drupal-internal base fields that are never included in generated output.
   *
   * These are standard Drupal node fields that have no meaningful representation
   * in a headless GraphQL schema, or are handled by the Drupal internals layer.
   */
  public const SKIP_BASE_FIELDS = [
    'nid', 'vid', 'uuid', 'langcode', 'type',
    'revision_timestamp', 'revision_uid', 'revision_log', 'revision_log_message',
    'uid', 'created', 'changed', 'promote', 'sticky',
    'default_langcode', 'revision_default', 'revision_translation_affected',
    'content_translation_source', 'content_translation_outdated',
    'content_translation_uid', 'content_translation_created',
    'metatag',
    // graphql_compose exposes status and path as top-level — skip raw fields.
    'status', 'path',
  ];

  /**
   * Drupal field type plugin ID → TypeScript type string.
   *
   * Single-value fields; multi-value (cardinality > 1) get '[]' appended.
   */
  public const FIELD_TYPE_MAP = [
    // Text.
    'string' => 'string',
    'string_long' => 'string',
    'text' => 'ProcessedText',
    'text_long' => 'ProcessedText',
    'text_with_summary' => 'ProcessedText',
    // Numbers / booleans.
    'boolean' => 'boolean',
    'integer' => 'number',
    'float' => 'number',
    'decimal' => 'number',
    // Dates.
    'datetime' => 'string',
    'timestamp' => 'string',
    'created' => 'string',
    'changed' => 'string',
    // Smart Date (smart_date module).
    'smartdate' => 'SmartDate',
    // Links / media.
    'link' => 'Link',
    'image' => 'Image',
    'file' => 'DrupalMedia',
    // Lists.
    'list_string' => 'string',
    'list_integer' => 'number',
    'list_float' => 'number',
    // Contact / misc.
    'telephone' => 'string',
    'email' => 'string',
    'uri' => 'string',
    // Address (address module).
    'address' => 'object',
    // Geolocation.
    'geolocation' => 'object',
    // Range (range module).
    'range_integer' => 'object',
    'range_float' => 'object',
    // Color (color_field module).
    'color_field_type' => 'string',
  ];

  /**
   * Constructs a SchemaInspector object.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
    private readonly EntityFieldManagerInterface $fieldManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  // ---------------------------------------------------------------------------
  // Bundle helpers
  // ---------------------------------------------------------------------------

  /**
   * Returns all node bundles, optionally filtered to a subset.
   *
   * @param array $only
   *   When non-empty, only return these bundle IDs.
   *
   * @return array<string, mixed>
   *   Keyed by bundle machine name; value is the bundle info array
   *   (keys: label, translatable, …).
   */
  public function getBundles(array $only = []): array {
    $all = $this->bundleInfo->getBundleInfo('node');
    return $only ? array_intersect_key($all, array_flip($only)) : $all;
  }

  /**
   * Returns the graphql_compose GraphQL type name for a bundle.
   *
   * Convention: 'basic_page' → 'NodeBasicPage'.
   */
  public function getGraphQlTypeName(string $bundle): string {
    return 'Node' . str_replace('_', '', ucwords($bundle, '_'));
  }

  /**
   * Returns the TypeScript type name for a bundle.
   *
   * Convention: 'basic_page' → 'DrupalBasicPage'.
   */
  public function getTsTypeName(string $bundle): string {
    return 'Drupal' . str_replace('_', '', ucwords($bundle, '_'));
  }

  // ---------------------------------------------------------------------------
  // Field helpers
  // ---------------------------------------------------------------------------

  /**
   * Returns the "extra" fields for a bundle — those NOT in the shared base type.
   *
   * @param string $bundle
   *   Node bundle machine name.
   * @param array $additionalSkip
   *   Extra field names to exclude beyond the defaults and configured base
   *   type fields.
   *
   * @return array<string, array{
   *   name: string,
   *   gql_name: string,
   *   ts_type: string,
   *   drupal_type: string,
   *   cardinality: int,
   *   required: bool,
   * }>
   *   Keyed by Drupal field machine name.
   */
  public function getFieldsForBundle(string $bundle, array $additionalSkip = []): array {
    $config = $this->configFactory->get('graphql_compose_codegen.settings');
    $skipList = array_unique(array_merge(
      self::SKIP_BASE_FIELDS,
      (array) ($config->get('base_type_fields') ?? []),
      $additionalSkip,
    ));

    $definitions = $this->fieldManager->getFieldDefinitions('node', $bundle);
    $fields = [];

    foreach ($definitions as $fieldName => $definition) {
      if (in_array($fieldName, $skipList, TRUE)) {
        continue;
      }
      // Computed fields have no storage and cannot be queried directly.
      if ($definition->isComputed()) {
        continue;
      }
      // Skip internal revision/translation fields not caught by the name list.
      if (str_starts_with($fieldName, 'revision_')) {
        continue;
      }
      if (str_starts_with($fieldName, 'content_translation_')) {
        continue;
      }

      $drupalType = $definition->getType();
      $tsType = $this->mapFieldType($definition);
      $gqlName = $this->toGqlFieldName($fieldName);
      $cardinality = $definition->getFieldStorageDefinition()->getCardinality();

      $fields[$fieldName] = [
        'name' => $fieldName,
        'gql_name' => $gqlName,
        'ts_type' => $tsType,
        'drupal_type' => $drupalType,
        'cardinality' => $cardinality,
        'required' => $definition->isRequired(),
      ];
    }

    return $fields;
  }

  // ---------------------------------------------------------------------------
  // Internal helpers
  // ---------------------------------------------------------------------------

  /**
   * Converts a Drupal field machine name to graphql_compose's camelCase name.
   *
   * graphql_compose strips the 'field_' prefix and camelCases the remainder.
   * Non-prefixed fields (base fields like 'body') are left as-is.
   *
   * Examples:
   *   field_mission_impact → missionImpact
   *   field_key_capabilities → keyCapabilities
   *   body → body
   */
  public function toGqlFieldName(string $fieldName): string {
    if (!str_starts_with($fieldName, 'field_')) {
      return $fieldName;
    }
    $stripped = substr($fieldName, strlen('field_'));
    return lcfirst(str_replace('_', '', ucwords($stripped, '_')));
  }

  /**
   * Maps a field definition to its TypeScript type string.
   *
   * Multi-value fields get '[]' appended unless the base type already ends
   * with '[]' (e.g. entity_reference_revisions → DrupalParagraph[]).
   */
  protected function mapFieldType(FieldDefinitionInterface $definition): string {
    $type = $definition->getType();
    $storage = $definition->getFieldStorageDefinition();
    $cardinality = $storage->getCardinality();
    $multi = ($cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      || ($cardinality > 1);

    // Entity reference — resolve based on target entity type.
    if ($type === 'entity_reference' || $type === 'entity_reference_revisions') {
      $targetType = $definition->getSetting('target_type') ?? 'node';
      $ts = match ($targetType) {
        'taxonomy_term' => 'TaxonomyTermRef',
        'user' => 'Author',
        'media' => 'DrupalMedia',
        'node' => 'RelatedNode',
        'paragraph' => 'DrupalParagraph',
        default => 'unknown',
      };
      // Paragraphs (entity_reference_revisions) are always multi.
      if ($type === 'entity_reference_revisions' || $ts === 'DrupalParagraph') {
        return 'DrupalParagraph[]';
      }
      return $multi ? "{$ts}[]" : $ts;
    }

    $ts = self::FIELD_TYPE_MAP[$type] ?? 'unknown';
    if ($multi && !str_ends_with($ts, '[]')) {
      $ts .= '[]';
    }
    return $ts;
  }

}
