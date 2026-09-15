<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the SchemaInspector service against a real node bundle.
 *
 * Strict schema checking is disabled because compose-filter tests write
 * graphql_compose.settings config without installing graphql_compose:
 * the inspector only reads that config, never the module's code.
 *
 * @group graphql_compose_codegen
 *
 * @runTestsInSeparateProcesses
 */
#[Group('graphql_compose_codegen')]
#[RunTestsInSeparateProcesses]
final class SchemaInspectorKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore DrupalPractice.Objects.StrictSchemaDisabled.StrictConfigSchema
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'graphql_compose_codegen',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['node', 'graphql_compose_codegen']);

    NodeType::create([
      'type' => 'demo',
      'name' => 'Demo',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_tagline',
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_tagline',
      'entity_type' => 'node',
      'bundle' => 'demo',
      'required' => TRUE,
    ])->save();
  }

  /**
   * Tests that SchemaInspector extracts the field shape for a real bundle.
   */
  public function testExtractsExtraFieldShape(): void {
    $inspector = $this->inspector();

    $bundles = $inspector->getBundles();
    self::assertArrayHasKey('demo', $bundles);

    $fields = $inspector->getFieldsForBundle('demo');
    self::assertArrayHasKey('field_tagline', $fields);

    $field = $fields['field_tagline'];
    self::assertSame('tagline', $field['gql_name']);
    self::assertSame('string', $field['ts_type']);
    self::assertSame('string', $field['drupal_type']);
    self::assertSame(1, $field['cardinality']);
    self::assertTrue($field['required']);
  }

  /**
   * Node bundles are filtered by graphql_compose entity_config.
   */
  public function testEntityConfigFilteringLimitsNodeBundles(): void {
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    $listed = array_keys($this->inspector()->getBundles());
    sort($listed);
    self::assertSame(['demo', 'page'], $listed);

    // The 3.x per-server config name must be recognized too.
    $this->container->get('config.factory')
      ->getEditable('graphql_compose.settings.graphql_compose_server')
      ->set('entity_config.node', [
        'demo' => ['enabled' => TRUE, 'query_load_enabled' => TRUE],
        'page' => ['enabled' => TRUE, 'query_load_enabled' => FALSE],
      ])
      ->save();

    self::assertSame(['demo'], array_keys($this->inspector()->getBundles()));
  }

  /**
   * Node fields are filtered by graphql_compose field_config.
   */
  public function testFieldConfigFilteringLimitsNodeFields(): void {
    $this->container->get('config.factory')
      ->getEditable('graphql_compose.settings')
      ->set('entity_config.node', [
        'demo' => ['enabled' => TRUE, 'query_load_enabled' => TRUE],
      ])
      ->set('field_config.node', [
        'demo' => [
          'field_tagline' => ['enabled' => FALSE],
        ],
      ])
      ->save();

    $fields = $this->inspector()->getFieldsForBundle('demo');
    self::assertSame([], array_keys($fields));
  }

  /**
   * Returns the schema inspector service.
   *
   * @return \Drupal\graphql_compose_codegen\Service\SchemaInspector
   *   The inspector.
   */
  private function inspector() {
    return $this->container->get('graphql_compose_codegen.schema_inspector');
  }

}
