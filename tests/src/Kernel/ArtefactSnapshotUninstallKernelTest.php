<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test that uninstall clears leftover ArtefactSnapshot state.
 *
 * @group graphql_compose_codegen
 *
 * @runTestsInSeparateProcesses
 */
#[Group('graphql_compose_codegen')]
#[RunTestsInSeparateProcesses]
final class ArtefactSnapshotUninstallKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'graphql_compose_codegen',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['graphql_compose_codegen']);
  }

  /**
   * Uninstall deletes snapshot state so a reinstall cannot inherit hashes.
   */
  public function testUninstallClearsSnapshotBeforeReinstall(): void {
    /** @var \Drupal\graphql_compose_codegen\Service\ArtefactSnapshot $snapshot */
    $snapshot = $this->container->get('graphql_compose_codegen.artefact_snapshot');
    $snapshot->record([
      'types.generated.d.ts' => 'export type DrupalDemo = { id: string };',
    ]);
    self::assertNotNull($snapshot->load());

    $this->container->get('module_installer')->uninstall([
      'graphql_compose_codegen',
    ]);
    $this->container = $this->container->get('kernel')->getContainer();

    // Re-enable the same way KernelTestBase loads this module, without
    // pulling graphql_compose as a hard installer dependency.
    $this->enableModules(['graphql_compose_codegen']);
    $this->installConfig(['graphql_compose_codegen']);

    /** @var \Drupal\graphql_compose_codegen\Service\ArtefactSnapshot $snapshot */
    $snapshot = $this->container->get('graphql_compose_codegen.artefact_snapshot');
    self::assertNull($snapshot->load());
  }

}
