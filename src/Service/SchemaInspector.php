<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\graphql_compose_codegen\PluginManager\FieldTypeMapperManager;

/**
 * Inspects the Drupal node + paragraph schema and maps it to TS / GQL shapes.
 */
final class SchemaInspector {

  /**
   * Drupal-internal base node fields that are never included in output.
   */
  public const SKIP_BASE_FIELDS = [
    'nid', 'vid', 'uuid', 'langcode', 'type',
    'revision_timestamp', 'revision_uid', 'revision_log', 'revision_log_message',
    'uid', 'created', 'changed', 'promote', 'sticky',
    'default_langcode', 'revision_default', 'revision_translation_affected',
    'content_translation_source', 'content_translation_outdated',
    'content_translation_uid', 'content_translation_created',
    'metatag',
    'status', 'path',
  ];

  /**
   * Drupal-internal base paragraph fields that are never included in output.
   */
  public const SKIP_PARAGRAPH_BASE_FIELDS = [
    'id', 'revision_id', 'uuid', 'langcode', 'type', 'status', 'created',
    'parent_id', 'parent_type', 'parent_field_name', 'behavior_settings',
    'default_langcode', 'revision_default', 'revision_translation_affected',
    'content_translation_source', 'content_translation_outdated',
  ];

  /**
   * Constructs a SchemaInspector.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\graphql_compose_codegen\PluginManager\FieldTypeMapperManager $mapperManager
   *   The field-type mapper plugin manager.
   */
  public function __construct(
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
    private readonly EntityFieldManagerInterface $fieldManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FieldTypeMapperManager $mapperManager,
  ) {}

  /**
   * Returns all node bundles, optionally filtered by bundle ID.
   *
   * @param string[] $only
   *   If non-empty, only these bundle IDs are returned.
   *
   * @return array<string, mixed>
   *   Bundle info keyed by bundle machine name.
   */
  public function getBundles(array $only = []): array {
    $all = $this->bundleInfo->getBundleInfo('node');
    return $only ? array_intersect_key($all, array_flip($only)) : $all;
  }

  /**
   * Returns the GraphQL type name for a node bundle.
   *
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return string
   *   The GraphQL type name (e.g. NodeArticle).
   */
  public function getGraphQlTypeName(string $bundle): string {
    return 'Node' . str_replace('_', '', ucwords($bundle, '_'));
  }

  /**
   * Returns the TypeScript type name for a node bundle.
   *
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return string
   *   The TypeScript type name (e.g. DrupalArticle).
   */
  public function getTsTypeName(string $bundle): string {
    return 'Drupal' . str_replace('_', '', ucwords($bundle, '_'));
  }

  /**
   * Returns extra fields for a node bundle, filtered by skip lists.
   *
   * @param string $bundle
   *   The bundle machine name.
   * @param string[] $additionalSkip
   *   Extra field names to exclude.
   *
   * @return array<string, mixed>
   *   Field descriptor arrays keyed by field machine name.
   */
  public function getFieldsForBundle(string $bundle, array $additionalSkip = []): array {
    return $this->collectFields('node', $bundle, self::SKIP_BASE_FIELDS, $additionalSkip);
  }

  /**
   * Returns all paragraph bundles, optionally filtered by bundle ID.
   *
   * @param string[] $only
   *   If non-empty, only these bundle IDs are returned.
   *
   * @return array<string, mixed>
   *   Bundle info keyed by bundle machine name, or empty if paragraphs
   *   module is not installed.
   */
  public function getParagraphBundles(array $only = []): array {
    $all = $this->bundleInfo->getBundleInfo('paragraph');
    if (!$all) {
      return [];
    }
    return $only ? array_intersect_key($all, array_flip($only)) : $all;
  }

  /**
   * Returns the GraphQL type name for a paragraph bundle.
   *
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return string
   *   The GraphQL type name (e.g. ParagraphHero).
   */
  public function getGraphQlTypeNameForParagraph(string $bundle): string {
    return 'Paragraph' . str_replace('_', '', ucwords($bundle, '_'));
  }

  /**
   * Returns the TypeScript type name for a paragraph bundle.
   *
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return string
   *   The TypeScript type name (e.g. DrupalParagraphHero).
   */
  public function getTsTypeNameForParagraph(string $bundle): string {
    return 'DrupalParagraph' . str_replace('_', '', ucwords($bundle, '_'));
  }

  /**
   * Returns extra fields for a paragraph bundle, filtered by skip lists.
   *
   * @param string $bundle
   *   The bundle machine name.
   * @param string[] $additionalSkip
   *   Extra field names to exclude.
   *
   * @return array<string, mixed>
   *   Field descriptor arrays keyed by field machine name.
   */
  public function getFieldsForParagraphBundle(string $bundle, array $additionalSkip = []): array {
    return $this->collectFields('paragraph', $bundle, self::SKIP_PARAGRAPH_BASE_FIELDS, $additionalSkip);
  }

  /**
   * Converts a Drupal field machine name to its camelCase GQL field name.
   *
   * @param string $fieldName
   *   The Drupal field machine name.
   *
   * @return string
   *   The camelCase GQL field name (field_ prefix stripped).
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
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   The field definition.
   *
   * @return string
   *   The TypeScript type string.
   */
  public function mapFieldType(FieldDefinitionInterface $definition): string {
    return $this->mapperManager->mapDefinition($definition);
  }

  /**
   * Collects field descriptors for a given entity type and bundle.
   *
   * @param string $entityType
   *   The entity type ID (e.g. 'node', 'paragraph').
   * @param string $bundle
   *   The bundle machine name.
   * @param string[] $baseSkip
   *   Base fields always excluded for this entity type.
   * @param string[] $additionalSkip
   *   Extra field names to exclude for this call.
   *
   * @return array<string, mixed>
   *   Field descriptor arrays keyed by field machine name.
   */
  private function collectFields(string $entityType, string $bundle, array $baseSkip, array $additionalSkip): array {
    $config = $this->configFactory->get('graphql_compose_codegen.settings');
    $skipList = array_unique(array_merge(
      $baseSkip,
      $entityType === 'node' ? (array) ($config->get('base_type_fields') ?? []) : [],
      $additionalSkip,
    ));

    $definitions = $this->fieldManager->getFieldDefinitions($entityType, $bundle);
    $fields = [];

    foreach ($definitions as $fieldName => $definition) {
      if (in_array($fieldName, $skipList, TRUE)) {
        continue;
      }
      if ($definition->isComputed()) {
        continue;
      }
      if (str_starts_with($fieldName, 'revision_') || str_starts_with($fieldName, 'content_translation_')) {
        continue;
      }

      $cardinality = $definition->getFieldStorageDefinition()->getCardinality();
      $fields[$fieldName] = [
        'name' => $fieldName,
        'gql_name' => $this->toGqlFieldName($fieldName),
        'ts_type' => $this->mapFieldType($definition),
        'drupal_type' => $definition->getType(),
        'cardinality' => $cardinality,
        'required' => $definition->isRequired(),
      ];
    }

    return $fields;
  }

}
