<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the SchemaInspector service against a real node bundle.
 *
 * @group graphql_compose_codegen
 *
 * @runTestsInSeparateProcesses
 */
#[RunTestsInSeparateProcesses]
final class SchemaInspectorKernelTest extends KernelTestBase {

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
    /** @var \Drupal\graphql_compose_codegen\Service\SchemaInspector $inspector */
    $inspector = $this->container->get('graphql_compose_codegen.schema_inspector');

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

}
