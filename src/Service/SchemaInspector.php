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
    ksort($all);
    $compose = $this->getComposeConfig('paragraph');
    if ($compose !== NULL) {
      $all = array_filter(
        $all,
        fn(string $bundle) => !empty($compose['entities'][$bundle]['enabled'])
          && !empty($compose['entities'][$bundle]['query_load_enabled']),
        ARRAY_FILTER_USE_KEY,
      );
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
    $fields = $this->collectFields('paragraph', $bundle, self::SKIP_PARAGRAPH_BASE_FIELDS, $additionalSkip);

    // When graphql_compose field config exists, only fields it explicitly
    // enables are exposed over the wire, so only those are scaffolded.
    $compose = $this->getComposeConfig('paragraph');
    if ($compose !== NULL) {
      $enabledFields = $compose['fields'][$bundle] ?? [];
      $fields = array_filter(
        $fields,
        fn(string $name) => !empty($enabledFields[$name]['enabled']),
        ARRAY_FILTER_USE_KEY,
      );
    }

    // Nested paragraph references get bundle-specific TS types derived from
    // the reference field's allowed target bundles (enabled ones only).
    $enabledBundles = NULL;
    foreach ($fields as $name => $field) {
      if ($field['target_type'] !== 'paragraph' || !$field['target_bundles']) {
        continue;
      }
      $enabledBundles ??= array_keys($this->getParagraphBundles());
      $targets = array_values(array_intersect($field['target_bundles'], $enabledBundles));
      if (!$targets) {
        continue;
      }
      $names = array_map(
        fn(string $target) => $this->getTsTypeNameForParagraph($target),
        $targets,
      );
      $fields[$name]['target_bundles'] = $targets;
      $fields[$name]['ts_type'] = count($names) === 1
        ? $names[0] . '[]'
        : '(' . implode(' | ', $names) . ')[]';
    }

    return $fields;
  }

  /**
   * Builds the full paragraph scaffolding map with collision-safe aliases.
   *
   * Bundles referenced by another paragraph bundle's reference field are
   * flagged child_only: they render nested inside their parents and get no
   * top-level fragment entry. Across the remaining top-level bundles, fields
   * sharing a GraphQL response key must have identical shapes (type,
   * nullability, and — for nested references — the combined shape of their
   * children); when shapes differ, later bundles get a bundle-derived alias
   * (e.g. tabItems: items) recorded in each field's response_key.
   *
   * Aliases are computed across every enabled bundle regardless of $only so
   * that generating a subset produces the same names as a full run.
   *
   * @param string[] $only
   *   If non-empty, only these bundle IDs are returned (aliases are still
   *   computed across all enabled bundles).
   * @param string[] $additionalSkip
   *   Extra field names to exclude.
   *
   * @return array<string, array{child_only: bool, fields: array<string, mixed>}>
   *   Per-bundle child_only flag and field descriptors including
   *   response_key, keyed by bundle machine name.
   */
  public function getParagraphFieldMap(array $only = [], array $additionalSkip = []): array {
    $bundles = array_keys($this->getParagraphBundles());
    $fieldsByBundle = [];
    foreach ($bundles as $bundle) {
      $fieldsByBundle[$bundle] = $this->getFieldsForParagraphBundle($bundle, $additionalSkip);
    }

    $childOnly = [];
    foreach ($fieldsByBundle as $fields) {
      foreach ($fields as $field) {
        if ($field['target_type'] === 'paragraph') {
          foreach ($field['target_bundles'] as $target) {
            $childOnly[$target] = TRUE;
          }
        }
      }
    }

    $signatures = [];
    $map = [];
    foreach ($fieldsByBundle as $bundle => $fields) {
      $isChild = isset($childOnly[$bundle]);
      foreach ($fields as $name => $field) {
        $fields[$name]['response_key'] = $isChild
          ? $field['gql_name']
          : $this->assignResponseKey($bundle, $field, $fieldsByBundle, $signatures);
      }
      $map[$bundle] = ['child_only' => $isChild, 'fields' => $fields];
    }

    return $only ? array_intersect_key($map, array_flip($only)) : $map;
  }

  /**
   * Derives a GraphQL alias for a paragraph field from its bundle name.
   *
   * Used when two paragraph bundles select the same response key with
   * incompatible shapes (e.g. required vs optional sub-fields), which GraphQL
   * refuses to merge across a union. The alias prefixes the field name with
   * the bundle's leading name segment: p_tab_group + items -> tabItems.
   *
   * @param string $bundle
   *   The paragraph bundle machine name.
   * @param string $gqlFieldName
   *   The camelCase GraphQL field name being aliased.
   * @param bool $useFullBundle
   *   When TRUE, prefix with the full stripped bundle name instead of its
   *   first segment (fallback when the short alias is already taken):
   *   p_tab_group + items -> tabGroupItems.
   *
   * @return string
   *   The camelCase alias.
   */
  public function deriveParagraphAlias(string $bundle, string $gqlFieldName, bool $useFullBundle = FALSE): string {
    $stripped = str_starts_with($bundle, 'p_') ? substr($bundle, 2) : $bundle;
    $prefix = $useFullBundle ? $stripped : explode('_', $stripped)[0];
    $camelPrefix = lcfirst(str_replace('_', '', ucwords($prefix, '_')));
    return $camelPrefix . ucfirst($gqlFieldName);
  }

  /**
   * Returns the React component name for a paragraph bundle.
   *
   * Follows the <Bundle>Paragraph convention used by ParagraphRenderer
   * implementations: p_faq_group -> FaqGroupParagraph.
   *
   * @param string $bundle
   *   The paragraph bundle machine name.
   *
   * @return string
   *   The component name.
   */
  public function getComponentNameForParagraph(string $bundle): string {
    $stripped = str_starts_with($bundle, 'p_') ? substr($bundle, 2) : $bundle;
    return str_replace('_', '', ucwords($stripped, '_')) . 'Paragraph';
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
      $settings = $definition->getSettings();
      $targetBundles = (array) (($settings['handler_settings'] ?? [])['target_bundles'] ?? []);
      $fields[$fieldName] = [
        'name' => $fieldName,
        'gql_name' => $this->toGqlFieldName($fieldName),
        'ts_type' => $this->mapFieldType($definition),
        'drupal_type' => $definition->getType(),
        'cardinality' => $cardinality,
        'required' => $definition->isRequired(),
        'target_type' => isset($settings['target_type']) ? (string) $settings['target_type'] : NULL,
        'target_bundles' => array_map('strval', array_keys($targetBundles)),
      ];
    }

    // Field-definition order varies between bundles and environments; sort by
    // machine name so generated output (and gqcc:validate) is deterministic.
    ksort($fields);
    return $fields;
  }

  /**
   * Reads graphql_compose's entity/field enablement config for an entity type.
   *
   * Handles both the 2.x config name (graphql_compose.settings) and the 3.x
   * per-server names (graphql_compose.settings.<server_id>) by prefix
   * listing. The per-field enabled flags live in a top-level field_config
   * map that mirrors entity_config's entity_type -> bundle nesting.
   *
   * @param string $entityType
   *   The entity type ID (e.g. 'paragraph').
   *
   * @return array{entities: array<string, mixed>, fields: array<string, mixed>}|null
   *   Bundle and field enablement maps, or NULL when no graphql_compose
   *   config exists at all (callers fall back to enumerating everything).
   */
  private function getComposeConfig(string $entityType): ?array {
    $entities = [];
    $fields = [];
    $found = FALSE;
    foreach ($this->configFactory->listAll('graphql_compose.settings') as $name) {
      $config = $this->configFactory->get($name);
      if (is_array($config->get('entity_config'))) {
        $found = TRUE;
        $entities = array_replace($entities, (array) ($config->get('entity_config.' . $entityType) ?? []));
        $fields = array_replace($fields, (array) ($config->get('field_config.' . $entityType) ?? []));
      }
    }
    return $found ? ['entities' => $entities, 'fields' => $fields] : NULL;
  }

  /**
   * Assigns a collision-free GraphQL response key for a top-level field.
   *
   * The first bundle to use a response key keeps the plain field name; a
   * later bundle whose field has an incompatible shape gets a bundle-derived
   * alias, escalating from the short form (tabItems) to the full-bundle form
   * (tabGroupItems) to a numbered suffix if both are taken.
   *
   * @param string $bundle
   *   The paragraph bundle machine name.
   * @param array<string, mixed> $field
   *   The field descriptor.
   * @param array<string, array<string, mixed>> $fieldsByBundle
   *   All enabled bundles' field descriptors, for nested-shape comparison.
   * @param array<string, mixed> $signatures
   *   Response key to merge-signature registry, updated in place.
   *
   * @return string
   *   The response key (plain name or alias).
   */
  private function assignResponseKey(string $bundle, array $field, array $fieldsByBundle, array &$signatures): string {
    $signature = $this->buildMergeSignature($field, $fieldsByBundle);
    $plain = $field['gql_name'];

    $candidates = [
      $plain,
      $this->deriveParagraphAlias($bundle, $plain),
      $this->deriveParagraphAlias($bundle, $plain, TRUE),
    ];
    foreach ($candidates as $candidate) {
      if (!array_key_exists($candidate, $signatures) || $signatures[$candidate] === $signature) {
        $signatures[$candidate] = $signature;
        return $candidate;
      }
    }

    $suffix = 2;
    do {
      $candidate = $this->deriveParagraphAlias($bundle, $plain, TRUE) . $suffix++;
    } while (array_key_exists($candidate, $signatures) && $signatures[$candidate] !== $signature);
    $signatures[$candidate] = $signature;
    return $candidate;
  }

  /**
   * Builds a comparable shape signature for a top-level paragraph field.
   *
   * Two fields sharing a response key merge cleanly only when their
   * signatures match: same TS type and nullability for scalars, and — for
   * nested paragraph references — the same combined child field shapes
   * (GraphQL's SameResponseShape rule applied one level deep).
   *
   * @param array<string, mixed> $field
   *   The field descriptor.
   * @param array<string, array<string, mixed>> $fieldsByBundle
   *   All enabled bundles' field descriptors.
   *
   * @return array<string, mixed>
   *   A structure comparable with ===.
   */
  private function buildMergeSignature(array $field, array $fieldsByBundle): array {
    if ($field['target_type'] === 'paragraph' && $field['target_bundles']) {
      $children = [];
      foreach ($field['target_bundles'] as $target) {
        foreach ($fieldsByBundle[$target] ?? [] as $childField) {
          $children[$childField['gql_name']] = [
            'ts' => $childField['ts_type'],
            'required' => $childField['required'],
          ];
        }
      }
      ksort($children);
      return ['kind' => 'paragraph_ref', 'children' => $children];
    }
    return [
      'kind' => 'scalar',
      'ts' => $field['ts_type'],
      'required' => $field['required'],
    ];
  }

}
