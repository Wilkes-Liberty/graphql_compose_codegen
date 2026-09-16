<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * Provides bounded, read-only schema inspection and scaffold previews.
 *
 * Authorization belongs to the calling adapter. This service has no filesystem
 * writer and never records a snapshot or invokes generation lifecycle hooks.
 */
final class SchemaPreview {

  public const MAX_BUNDLES = 64;
  public const MAX_FIELDS_PER_BUNDLE = 256;
  public const MAX_RESPONSE_BYTES = 262144;

  /**
   * Constructs the preview service.
   */
  public function __construct(
    private readonly SchemaInspector $inspector,
    private readonly TypeScriptGenerator $generator,
    private readonly ArtefactSnapshot $snapshot,
    private readonly EntityFieldManagerInterface $fieldManager,
  ) {}

  /**
   * Runs one read-only operation against a bounded schema.
   *
   * @param string $operation
   *   One of inspect, diff, or preview.
   * @param string[] $bundles
   *   Optional bundle IDs; empty selects all enabled bundles.
   * @param string[] $skipFields
   *   Additional field IDs to omit.
   *
   * @return array<string, mixed>
   *   Structured inspection, comparison, or generated artefacts.
   */
  public function run(string $operation, array $bundles = [], array $skipFields = []): array {
    if (!in_array($operation, ['inspect', 'diff', 'preview'], TRUE)) {
      throw new \InvalidArgumentException('Unsupported schema operation.');
    }
    $this->validateIdentifiers($bundles, self::MAX_BUNDLES);
    $this->validateIdentifiers($skipFields, self::MAX_FIELDS_PER_BUNDLE);
    $this->assertSchemaBudget();

    $nodes = $this->inspector->getBundles($bundles);
    $paragraphs = $this->inspector->getParagraphBundles($bundles);
    if (array_diff($bundles, array_keys($nodes), array_keys($paragraphs))) {
      throw new \InvalidArgumentException('Unknown or disabled bundle selector.');
    }

    if ($operation === 'inspect') {
      $nodeFields = [];
      foreach (array_keys($nodes) as $bundle) {
        $nodeFields[$bundle] = [
          'graphql_type' => $this->inspector->getGraphQlTypeName($bundle),
          'typescript_type' => $this->inspector->getTsTypeName($bundle),
          'fields' => $this->inspector->getFieldsForBundle($bundle, $skipFields),
        ];
      }
      $result = [
        'nodes' => $nodeFields,
        'paragraphs' => $this->inspector->getParagraphFieldMap($bundles, $skipFields),
      ];
    }
    else {
      $artefacts = $this->generator->buildArtefacts($bundles, $skipFields);
      $this->assertResponseBudget($artefacts);
      $result = $operation === 'preview'
        ? ['artefacts' => $artefacts]
        : [
          'has_snapshot' => $this->snapshot->load() !== NULL,
          'changes' => $this->snapshot->diff($artefacts),
        ];
    }
    $this->assertResponseBudget($result);
    return $result;
  }

  /**
   * Rejects malformed or oversized selectors before reading any schema.
   *
   * @param string[] $values
   *   Identifiers supplied by the caller.
   * @param int $limit
   *   Maximum number of identifiers.
   */
  private function validateIdentifiers(array $values, int $limit): void {
    if (!array_is_list($values) || count($values) > $limit) {
      throw new \InvalidArgumentException('Schema selectors exceed the allowed limit.');
    }
    foreach ($values as $value) {
      if (!is_string($value) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $value)) {
        throw new \InvalidArgumentException('Invalid schema selector.');
      }
    }
    if (count(array_unique($values)) !== count($values)) {
      throw new \InvalidArgumentException('Duplicate schema selector.');
    }
  }

  /**
   * Caps the schema before generation, including paragraph alias dependencies.
   */
  private function assertSchemaBudget(): void {
    // Paragraph aliases depend on all enabled paragraph bundles, including
    // bundles outside a requested subset. Bound that dependency set as well.
    $groups = [
      'node' => $this->inspector->getBundles(),
      'paragraph' => $this->inspector->getParagraphBundles(),
    ];
    if (count($groups['node']) + count($groups['paragraph']) > self::MAX_BUNDLES) {
      throw new \LengthException('Schema exceeds the preview bundle limit.');
    }
    foreach ($groups as $entityType => $bundles) {
      foreach (array_keys($bundles) as $bundle) {
        if (count($this->fieldManager->getFieldDefinitions($entityType, $bundle)) > self::MAX_FIELDS_PER_BUNDLE) {
          throw new \LengthException('Schema exceeds the preview field limit.');
        }
      }
    }
  }

  /**
   * Fails explicitly instead of returning a truncated or incomplete contract.
   *
   * @param array<string, mixed> $result
   *   Result to serialize.
   */
  private function assertResponseBudget(array $result): void {
    if (strlen(json_encode($result, JSON_THROW_ON_ERROR)) > self::MAX_RESPONSE_BYTES) {
      throw new \LengthException('Schema result exceeds the preview response limit.');
    }
  }

}
