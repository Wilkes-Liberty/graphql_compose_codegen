<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\graphql_compose_codegen\Drush\Commands\CodegenCommands;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Kernel tests for generator output and gqcc:generate snapshot honesty.
 *
 * @group graphql_compose_codegen
 *
 * @runTestsInSeparateProcesses
 */
#[Group('graphql_compose_codegen')]
#[RunTestsInSeparateProcesses]
final class CodegenCommandsKernelTest extends KernelTestBase {

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
   * Tests that type definitions include the fields from the demo bundle.
   */
  public function testTypeDefinitionsIncludeBundleFields(): void {
    /** @var \Drupal\graphql_compose_codegen\Service\TypeScriptGenerator $gen */
    $gen = $this->container->get('graphql_compose_codegen.typescript_generator');
    $out = $gen->generateTypeDefinitions();

    self::assertStringContainsString('export type DrupalDemo', $out);
    self::assertStringContainsString('__typename: "NodeDemo"', $out);
    self::assertStringContainsString('tagline: string', $out);
  }

  /**
   * Tests that fragments include the spread for the demo bundle.
   */
  public function testFragmentsIncludeBundleSpread(): void {
    /** @var \Drupal\graphql_compose_codegen\Service\TypeScriptGenerator $gen */
    $gen = $this->container->get('graphql_compose_codegen.typescript_generator');
    $out = $gen->generateFragments();

    self::assertStringContainsString('... on NodeDemo {', $out);
    self::assertStringContainsString('${COMMON_NODE_FIELDS}', $out);
    self::assertStringContainsString('tagline', $out);
  }

  /**
   * Tests that generate without overwrite snapshots disk, not the desired set.
   *
   * Recording desired hashes while emitFiles() skips existing files makes
   * gqcc:diff go green against stale scaffold.
   */
  public function testGenerateWithoutOverwriteSnapshotsDisk(): void {
    /** @var \Drupal\graphql_compose_codegen\Service\TypeScriptGenerator $gen */
    $gen = $this->container->get('graphql_compose_codegen.typescript_generator');
    $artefacts = $gen->buildArtefacts(['demo']);
    self::assertNotEmpty($artefacts);

    $siteDir = $this->siteDirectory;
    if (!str_starts_with($siteDir, '/')) {
      $siteDir = DRUPAL_ROOT . '/' . $siteDir;
    }
    $outputDir = $siteDir . '/gqcc-ui';
    $genDir = $outputDir . '/generated';

    foreach (array_keys($artefacts) as $rel) {
      $abs = $genDir . '/' . $rel;
      $dir = dirname($abs);
      if (!is_dir($dir) && !mkdir($dir, 0777, TRUE) && !is_dir($dir)) {
        self::fail("Could not create {$dir}");
      }
      file_put_contents($abs, "stale scaffold\n");
    }

    $this->command()->generate([
      'bundles' => 'demo',
      'output-dir' => $outputDir,
      'overwrite' => FALSE,
      'skip-fields' => '',
      'dry-run' => FALSE,
      'allow-external' => TRUE,
    ]);

    foreach (array_keys($artefacts) as $rel) {
      $onDisk = file_get_contents($genDir . '/' . $rel);
      self::assertSame(
        "stale scaffold\n",
        $onDisk,
        "{$rel} must stay stale when --overwrite is off.",
      );
    }

    /** @var \Drupal\graphql_compose_codegen\Service\ArtefactSnapshot $snapshot */
    $snapshot = $this->container->get('graphql_compose_codegen.artefact_snapshot');
    $stored = $snapshot->load();
    self::assertIsArray($stored);
    self::assertArrayHasKey('hashes', $stored);

    foreach (array_keys($artefacts) as $rel) {
      self::assertSame(
        sha1('stale scaffold'),
        $stored['hashes'][$rel] ?? NULL,
        "Snapshot for {$rel} must hash the stale on-disk file.",
      );
      self::assertNotSame(
        sha1($artefacts[$rel]),
        $stored['hashes'][$rel] ?? NULL,
        "Snapshot for {$rel} must not hash the desired (unwritten) set.",
      );
    }

    $diff = $snapshot->diff($artefacts);
    self::assertNotSame(
      [],
      $diff['changed'],
      'Diff must not go green when generate skipped writes.',
    );
  }

  /**
   * Returns a CodegenCommands instance wired for kernel tests.
   */
  private function command(): CodegenCommands {
    $cmd = new CodegenCommands(
      $this->container->get('graphql_compose_codegen.schema_inspector'),
      $this->container->get('graphql_compose_codegen.typescript_generator'),
      $this->container->get('graphql_compose_codegen.artefact_snapshot'),
      $this->container->get('graphql_compose_codegen.path_guard'),
      $this->container->get('module_handler'),
      $this->container->get('config.factory'),
    );
    $cmd->setLogger(new class () implements LoggerInterface {

      use LoggerTrait;

      /**
       * {@inheritdoc}
       */
      public function log($level, string|\Stringable $message, array $context = []): void {}

      /**
       * Drush logger extension used by emitFiles().
       */
      public function success(string|\Stringable $message, array $context = []): void {}

    });
    if (method_exists($cmd, 'setOutput')) {
      $cmd->setOutput(new BufferedOutput());
    }
    return $cmd;
  }

}
