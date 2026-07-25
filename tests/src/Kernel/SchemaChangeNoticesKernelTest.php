<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the schema-change dblog notices and their suggested commands.
 *
 * The suggested command in each notice must actually work when copied and
 * run: a paragraph bundle tip without --bundles support or --overwrite
 * silently generates nothing, which is worse than no tip at all.
 *
 * @runTestsInSeparateProcesses
 *
 * @group graphql_compose_codegen
 */
#[RunTestsInSeparateProcesses]
final class SchemaChangeNoticesKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'file',
    'node',
    'entity_reference_revisions',
    'paragraphs',
    'graphql_compose_codegen',
  ];

  /**
   * The in-memory log collector.
   */
  private TestLogCollector $logs;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('user');

    $this->logs = new TestLogCollector();
    $this->container->get('logger.factory')->addLogger($this->logs);
  }

  /**
   * Returns collected records for the module's channel.
   *
   * @param mixed $level
   *   Optional RFC 5424 level to filter on.
   *
   * @return array<int, array{level: mixed, message: string, context: array}>
   *   Matching records.
   */
  private function moduleRecords($level = NULL): array {
    return array_values(array_filter(
      $this->logs->records,
      fn(array $record) => ($record['context']['channel'] ?? '') === 'graphql_compose_codegen'
        && ($level === NULL || $record['level'] === $level)
    ));
  }

  /**
   * A new paragraph bundle gets a tip that actually generates its scaffold.
   */
  public function testParagraphBundleCreateTip(): void {
    ParagraphsType::create(['id' => 'p_banner', 'label' => 'Banner'])->save();

    $records = $this->moduleRecords();
    self::assertCount(1, $records);
    self::assertSame('paragraph', $records[0]['context']['@type']);
    self::assertSame('p_banner', $records[0]['context']['@bundle']);
    // --bundles must name the bundle (paragraph filtering is supported) and
    // --overwrite is required because the shared paragraph files already
    // exist after the first generate run.
    self::assertSame(
      'drush gqcc:generate --bundles=p_banner --output-dir=/path/to/nextjs --overwrite',
      $records[0]['context']['@tip']
    );
  }

  /**
   * A new node bundle gets the same runnable tip form.
   */
  public function testNodeBundleCreateTip(): void {
    NodeType::create(['type' => 'landing', 'name' => 'Landing'])->save();

    $records = $this->moduleRecords();
    self::assertCount(1, $records);
    self::assertSame('node', $records[0]['context']['@type']);
    self::assertSame(
      'drush gqcc:generate --bundles=landing --output-dir=/path/to/nextjs --overwrite',
      $records[0]['context']['@tip']
    );
  }

  /**
   * Bundle deletion names the renderer that matches the entity type.
   */
  public function testBundleDeleteNamesMatchingRenderer(): void {
    $paragraphType = ParagraphsType::create(['id' => 'p_banner', 'label' => 'Banner']);
    $paragraphType->save();
    $nodeType = NodeType::create(['type' => 'landing', 'name' => 'Landing']);
    $nodeType->save();
    $this->logs->records = [];

    $paragraphType->delete();
    $nodeType->delete();

    $records = $this->moduleRecords();
    self::assertCount(2, $records);
    self::assertSame('paragraph', $records[0]['context']['@type']);
    self::assertSame('ParagraphRenderer', $records[0]['context']['@renderer']);
    self::assertSame('node', $records[1]['context']['@type']);
    self::assertSame('NodeRenderer', $records[1]['context']['@renderer']);
  }

  /**
   * Adding a field to a paragraph bundle suggests a working command.
   *
   * Regression coverage: this tip used to emit --bundles=<paragraph bundle>
   * at a time when --bundles only matched node bundles, so the suggested
   * command printed "No matching node bundles found" and wrote nothing.
   */
  public function testParagraphFieldInsertTip(): void {
    ParagraphsType::create(['id' => 'p_banner', 'label' => 'Banner'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_headline',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    $this->logs->records = [];

    FieldConfig::create([
      'field_name' => 'field_headline',
      'entity_type' => 'paragraph',
      'bundle' => 'p_banner',
    ])->save();

    $records = $this->moduleRecords();
    self::assertCount(1, $records);
    self::assertSame('field_headline', $records[0]['context']['@field']);
    self::assertSame('paragraph', $records[0]['context']['@entity_type']);
    self::assertSame('p_banner', $records[0]['context']['@bundle']);
    self::assertStringContainsString('--bundles=@bundle', $records[0]['message']);
    self::assertStringContainsString('--overwrite', $records[0]['message']);
  }

}
