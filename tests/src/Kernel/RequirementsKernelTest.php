<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the runtime requirements report.
 *
 * The report uses the OOP runtime requirements hook on Drupal 11.3+ and a
 * procedural compatibility bridge on Drupal 10.6. These tests exercise the
 * Status Report's System Manager path on every supported core version.
 *
 * @runTestsInSeparateProcesses
 *
 * @group graphql_compose_codegen
 */
#[Group('graphql_compose_codegen')]
#[RunTestsInSeparateProcesses]
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
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'graphql_compose_codegen']);
    \Drupal::moduleHandler()->loadInclude('graphql_compose_codegen', 'install');
  }

  /**
   * The report warns when no node bundle exists yet.
   */
  public function testWarnsWhenNoNodeBundles(): void {
    $requirements = $this->runtimeRequirements();
    $this->assertArrayHasKey('graphql_compose_codegen_bundles', $requirements);
    $this->assertWarningSeverity(
      $requirements['graphql_compose_codegen_bundles']['severity'],
    );
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

    $requirements = $this->runtimeRequirements();
    $this->assertArrayNotHasKey('graphql_compose_codegen_bundles', $requirements);
    $this->assertArrayHasKey('graphql_compose_codegen_stale_base_fields', $requirements);
    $this->assertWarningSeverity(
      $requirements['graphql_compose_codegen_stale_base_fields']['severity'],
    );
  }

  /**
   * Collects requirements through the Status Report's runtime path.
   *
   * @return array<string, array<string, mixed>>
   *   The collected requirements.
   */
  private function runtimeRequirements(): array {
    return $this->container->get('system.manager')->listRequirements();
  }

  /**
   * Asserts the warning representation used by the running Drupal version.
   *
   * @param int|\Drupal\Core\Extension\Requirement\RequirementSeverity $severity
   *   The requirements severity value.
   */
  private function assertWarningSeverity(int|RequirementSeverity $severity): void {
    if (version_compare(\Drupal::VERSION, '11.2.0', '>=')) {
      self::assertSame(RequirementSeverity::Warning, $severity);
      return;
    }

    self::assertSame(REQUIREMENT_WARNING, $severity);
  }

}
