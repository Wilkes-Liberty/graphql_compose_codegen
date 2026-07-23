<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Kernel test for the runtime requirements report.
 *
 * The report used hook_runtime_requirements()/#[Hook], which are Drupal 11.1+
 * only, so it silently never ran on the 10.6 support floor. It now lives in
 * the procedural hook_requirements($phase); this test pins it on every core
 * version the module claims (^10.6 || ^11.3).
 *
 * @group graphql_compose_codegen
 */
final class RequirementsKernelTest extends KernelTestBase {

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
    \Drupal::moduleHandler()->loadInclude('graphql_compose_codegen', 'install');
  }

  /**
   * The report warns when no node bundle exists yet.
   */
  public function testWarnsWhenNoNodeBundles(): void {
    $requirements = graphql_compose_codegen_requirements('runtime');
    $this->assertArrayHasKey('graphql_compose_codegen_bundles', $requirements);
    $this->assertSame(REQUIREMENT_WARNING, $requirements['graphql_compose_codegen_bundles']['severity']);
  }

  /**
   * With a bundle present, a configured-but-absent base field is flagged stale.
   */
  public function testFlagsStaleBaseTypeFields(): void {
    NodeType::create(['type' => 'demo', 'name' => 'Demo'])->save();
    \Drupal::configFactory()
      ->getEditable('graphql_compose_codegen.settings')
      ->set('base_type_fields', ['field_does_not_exist'])
      ->save();

    $requirements = graphql_compose_codegen_requirements('runtime');
    $this->assertArrayNotHasKey('graphql_compose_codegen_bundles', $requirements);
    $this->assertArrayHasKey('graphql_compose_codegen_stale_base_fields', $requirements);
    $this->assertSame(REQUIREMENT_WARNING, $requirements['graphql_compose_codegen_stale_base_fields']['severity']);
  }

}
