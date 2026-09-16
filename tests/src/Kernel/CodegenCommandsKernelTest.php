<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

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
   * Tests that a skipped write must snapshot disk, not the desired set.
   *
   * When emitFiles() leaves existing files untouched, recording the desired
   * hashes makes gqcc:diff go green against stale scaffold.
   */
  public function testSkippedWriteSnapshotMustReflectDisk(): void {
    /** @var \Drupal\graphql_compose_codegen\Service\TypeScriptGenerator $gen */
    $gen = $this->container->get('graphql_compose_codegen.typescript_generator');
    /** @var \Drupal\graphql_compose_codegen\Service\ArtefactSnapshot $snapshot */
    $snapshot = $this->container->get('graphql_compose_codegen.artefact_snapshot');
    $artefacts = $gen->buildArtefacts(['demo']);
    self::assertNotEmpty($artefacts);

    $siteDir = $this->siteDirectory;
    if (!str_starts_with($siteDir, '/')) {
      $siteDir = DRUPAL_ROOT . '/' . $siteDir;
    }
    $genDir = $siteDir . '/gqcc-ui/generated';

    foreach (array_keys($artefacts) as $rel) {
      $abs = $genDir . '/' . $rel;
      $dir = dirname($abs);
      if (!is_dir($dir) && !mkdir($dir, 0777, TRUE) && !is_dir($dir)) {
        self::fail("Could not create {$dir}");
      }
      file_put_contents($abs, "stale scaffold\n");
    }

    // The old generate() path: snapshot the desired set after a no-op write.
    $snapshot->record($artefacts);
    $lie = $snapshot->diff($artefacts);
    self::assertSame([], $lie['added']);
    self::assertSame([], $lie['removed']);
    self::assertSame(
      [],
      $lie['changed'],
      'Recording the desired set hides skipped writes from diff.',
    );

    // generate() now records on-disk content instead.
    $snapshot->recordFromDisk($genDir, $artefacts);
    $stored = $snapshot->load();
    self::assertIsArray($stored);

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

}
