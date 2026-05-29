<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

/**
 * Kernel test verifying generator output for a real bundle.
 *
 * @group graphql_compose_codegen
 */
final class CodegenCommandsKernelTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'graphql_compose_codegen',
  ];

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

  public function testTypeDefinitionsIncludeBundleFields(): void {
    /** @var \Drupal\graphql_compose_codegen\Service\TypeScriptGenerator $gen */
    $gen = $this->container->get('graphql_compose_codegen.typescript_generator');
    $out = $gen->generateTypeDefinitions();

    self::assertStringContainsString('export type DrupalDemo', $out);
    self::assertStringContainsString('__typename: "NodeDemo"', $out);
    self::assertStringContainsString('tagline: string', $out);
  }

  public function testFragmentsIncludeBundleSpread(): void {
    /** @var \Drupal\graphql_compose_codegen\Service\TypeScriptGenerator $gen */
    $gen = $this->container->get('graphql_compose_codegen.typescript_generator');
    $out = $gen->generateFragments();

    self::assertStringContainsString('... on NodeDemo {', $out);
    self::assertStringContainsString('${COMMON_NODE_FIELDS}', $out);
    self::assertStringContainsString('tagline', $out);
  }

}
