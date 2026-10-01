<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\graphql_compose_codegen\Drush\Commands\CodegenCommands;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves the base module stays usable when the Tool API integration is off.
 *
 * @group graphql_compose_codegen
 *
 * @runTestsInSeparateProcesses
 */
#[Group('graphql_compose_codegen')]
#[RunTestsInSeparateProcesses]
final class DisabledToolIntegrationKernelTest extends KernelTestBase {

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
    'tool',
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
    NodeType::create(['type' => 'demo', 'name' => 'Demo'])->save();
  }

  /**
   * Preview and Drush remain available; the three tools are not registered.
   */
  public function testBaseModuleWorksWithoutIntegration(): void {
    self::assertFalse(
      $this->container->get('module_handler')
        ->moduleExists('graphql_compose_codegen_mcp'),
    );
    $manager = $this->container->get('plugin.manager.tool');
    foreach ([
      'graphql_compose_codegen_inspect',
      'graphql_compose_codegen_diff',
      'graphql_compose_codegen_preview',
    ] as $plugin_id) {
      self::assertFalse($manager->hasDefinition($plugin_id));
    }

    $preview = $this->container->get('graphql_compose_codegen.schema_preview');
    $result = $preview->run('preview', ['demo']);
    self::assertArrayHasKey('artefacts', $result);
    self::assertNotEmpty($result['artefacts']);
    self::assertTrue(class_exists(CodegenCommands::class));
  }

}
