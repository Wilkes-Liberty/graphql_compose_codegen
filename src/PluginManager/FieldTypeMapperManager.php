<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\PluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\graphql_compose_codegen\Attribute\FieldTypeMapper;
use Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper\FieldTypeMapperInterface;

/**
 * Plugin manager for field-type mapper plugins.
 */
final class FieldTypeMapperManager extends DefaultPluginManager {

  /**
   * Drupal field type plugin ID → plugin ID, populated lazily.
   *
   * @var array<string, string>|null
   */
  private ?array $typeIndex = NULL;

  /**
   * Constructs a FieldTypeMapperManager.
   *
   * @param \Traversable $namespaces
   *   Module namespaces, keyed by module machine name.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/FieldTypeMapper',
      $namespaces,
      $module_handler,
      FieldTypeMapperInterface::class,
      FieldTypeMapper::class,
    );
    $this->setCacheBackend($cache_backend, 'graphql_compose_codegen_field_type_mappers');
    $this->alterInfo('graphql_compose_codegen_field_type_mappers');
  }

  /**
   * Maps a Drupal field definition to a TypeScript type string.
   *
   * Returns 'unknown' if no plugin claims the Drupal field type.
   */
  public function mapDefinition(FieldDefinitionInterface $definition): string {
    $drupalType = $definition->getType();
    $pluginId = $this->indexByType()[$drupalType] ?? NULL;
    if ($pluginId === NULL) {
      return 'unknown';
    }
    /** @var \Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper\FieldTypeMapperInterface $plugin */
    $plugin = $this->createInstance($pluginId);
    return $plugin->map($definition);
  }

  /**
   * Returns the index of Drupal field type → mapper plugin ID.
   *
   * Last plugin wins for any given Drupal type (later modules can override
   * earlier ones via the standard plugin alter hook).
   *
   * @return array<string, string>
   */
  private function indexByType(): array {
    if ($this->typeIndex !== NULL) {
      return $this->typeIndex;
    }
    $index = [];
    foreach ($this->getDefinitions() as $pluginId => $definition) {
      foreach ($definition['drupalTypes'] ?? [] as $drupalType) {
        $index[$drupalType] = $pluginId;
      }
    }
    return $this->typeIndex = $index;
  }

}
