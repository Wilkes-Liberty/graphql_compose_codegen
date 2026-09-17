<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\graphql_compose_codegen\Service\SchemaPreview;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies bounded previews against the real generator and stored baseline.
 *
 * @group graphql_compose_codegen
 *
 * @runTestsInSeparateProcesses
 */
#[Group('graphql_compose_codegen')]
#[RunTestsInSeparateProcesses]
final class SchemaPreviewKernelTest extends KernelTestBase {

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
   * Preview shares the generator output without writing files or a snapshot.
   */
  public function testPreviewAndDiffAreReadOnly(): void {
    $preview = $this->container->get('graphql_compose_codegen.schema_preview');
    $snapshot = $this->container->get('graphql_compose_codegen.artefact_snapshot');
    $generator = $this->container->get('graphql_compose_codegen.typescript_generator');
    $target = $this->siteDirectory . '/preview-must-not-create';
    $this->config('graphql_compose_codegen.settings')->set('output_dir', $target)->save();

    self::assertNull($snapshot->load());
    self::assertFalse($preview->run('diff', ['demo'])['has_snapshot']);
    self::assertNull($snapshot->load());
    $snapshot->record(['old.generated.ts' => 'old content']);
    $before = $snapshot->load();
    $result = $preview->run('preview', ['demo']);
    self::assertSame($generator->buildArtefacts(['demo']), $result['artefacts']);
    self::assertSame($before, $snapshot->load());
    self::assertDirectoryDoesNotExist($target);
    $diff = $preview->run('diff', ['demo']);
    self::assertTrue($diff['has_snapshot']);
    self::assertSame(['old.generated.ts'], $diff['changes']['removed']);
    self::assertSame($before, $snapshot->load());
    self::assertArrayHasKey('field_tagline', $preview->run('inspect', ['demo'])['nodes']['demo']['fields']);
    self::assertArrayNotHasKey('field_tagline', $preview->run('inspect', ['demo'], ['field_tagline'])['nodes']['demo']['fields']);
  }

  /**
   * Invalid input cannot fall through to the generator's all-bundles default.
   */
  public function testMalformedSelectorsAreRejected(): void {
    $preview = $this->container->get('graphql_compose_codegen.schema_preview');
    foreach ([['../demo'], ['demo', 'demo'], ['unknown'], [NULL], ['named' => 'demo'], array_fill(0, 65, 'demo')] as $selectors) {
      try {
        $preview->run('preview', $selectors);
        self::fail('Invalid selectors must be refused.');
      }
      catch (\InvalidArgumentException $exception) {
        self::assertNotSame('', $exception->getMessage());
      }
    }
    $this->expectException(\InvalidArgumentException::class);
    $preview->run('write', ['demo']);
  }

  /**
   * A subset cannot bypass the global dependency budget for alias generation.
   */
  public function testSchemaBudgetIncludesUnselectedBundles(): void {
    for ($index = 0; $index < SchemaPreview::MAX_BUNDLES; $index++) {
      NodeType::create(['type' => 'extra_' . $index, 'name' => 'Extra'])->save();
    }
    $this->expectException(\LengthException::class);
    $this->container->get('graphql_compose_codegen.schema_preview')->run('preview', ['demo']);
  }

  /**
   * Large generated artefacts fail without changing the comparison baseline.
   */
  public function testResponseBudgetRejectsOversizedArtefacts(): void {
    $this->config('graphql_compose_codegen.settings')
      ->set('base_ts_type', str_repeat('X', SchemaPreview::MAX_RESPONSE_BYTES))->save();
    $snapshot = $this->container->get('graphql_compose_codegen.artefact_snapshot');
    self::assertNull($snapshot->load());
    try {
      $this->container->get('graphql_compose_codegen.schema_preview')->run('preview', ['demo']);
      self::fail('Oversized artefacts must fail.');
    }
    catch (\LengthException $exception) {
      self::assertSame('Schema result exceeds the preview response limit.', $exception->getMessage());
      self::assertNull($snapshot->load());
    }
  }

  /**
   * Excessive field definitions are rejected before mapping or generation.
   */
  public function testFieldBudgetRejectsOversizedBundles(): void {
    $fields = $this->createMock(EntityFieldManagerInterface::class);
    $fields->expects(self::once())->method('getFieldDefinitions')
      ->with('node', 'demo')
      ->willReturn(array_fill(0, SchemaPreview::MAX_FIELDS_PER_BUNDLE + 1, BaseFieldDefinition::create('string')));
    $preview = new SchemaPreview(
      $this->container->get('graphql_compose_codegen.schema_inspector'),
      $this->container->get('graphql_compose_codegen.typescript_generator'),
      $this->container->get('graphql_compose_codegen.artefact_snapshot'),
      $fields,
    );
    $this->expectException(\LengthException::class);
    $this->expectExceptionMessage('Schema exceeds the preview field limit.');
    $preview->run('inspect', ['demo']);
  }

}
