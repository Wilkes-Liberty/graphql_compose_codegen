<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests paragraph-bundle scaffolding: aliasing, nesting, config filtering.
 *
 * The fixture reproduces the response-key merge trap: p_faq_group and
 * p_tab_group both select `items`, but the FAQ item's title/body are
 * optional while the tab item's are required. GraphQL forbids merging one
 * response key with conflicting sub-field nullability across the union, so
 * the generator must alias the later bundle's field (tabItems: items).
 *
 * @runTestsInSeparateProcesses
 *
 * @group graphql_compose_codegen
 */
#[Group('graphql_compose_codegen')]
#[RunTestsInSeparateProcesses]
final class ParagraphScaffoldKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Strict schema checking is disabled because these tests write
   * graphql_compose.settings config without installing graphql_compose:
   * the inspector only reads that config, never the module's code, and the
   * schema belongs to graphql_compose (2.x and 3.x name it differently), so
   * this module cannot declare it.
   */
  // phpcs:ignore DrupalPractice.Objects.StrictSchemaDisabled.StrictConfigSchema
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'file',
    'entity_reference_revisions',
    'paragraphs',
    'graphql_compose_codegen',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('user');
    $this->installConfig(['graphql_compose_codegen']);

    foreach ([
      'p_faq_group' => 'FAQ Group',
      'p_faq_item' => 'FAQ Item',
      'p_tab_group' => 'Tab Group',
      'p_tab_item' => 'Tab Item',
    ] as $id => $label) {
      ParagraphsType::create(['id' => $id, 'label' => $label])->save();
    }

    FieldStorageConfig::create([
      'field_name' => 'field_title',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_body',
      'entity_type' => 'paragraph',
      'type' => 'text_long',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_items',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference_revisions',
      'cardinality' => -1,
      'settings' => ['target_type' => 'paragraph'],
    ])->save();

    // FAQ item: optional title + body. Tab item: required title + body.
    foreach ([
      ['p_faq_item', 'field_title', FALSE],
      ['p_faq_item', 'field_body', FALSE],
      ['p_tab_item', 'field_title', TRUE],
      ['p_tab_item', 'field_body', TRUE],
      ['p_faq_group', 'field_title', FALSE],
      ['p_tab_group', 'field_title', FALSE],
    ] as [$bundle, $fieldName, $required]) {
      FieldConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'paragraph',
        'bundle' => $bundle,
        'required' => $required,
      ])->save();
    }

    foreach ([
      'p_faq_group' => 'p_faq_item',
      'p_tab_group' => 'p_tab_item',
    ] as $group => $item) {
      FieldConfig::create([
        'field_name' => 'field_items',
        'entity_type' => 'paragraph',
        'bundle' => $group,
        'required' => FALSE,
        'settings' => [
          'handler' => 'default:paragraph',
          'handler_settings' => ['target_bundles' => [$item => $item]],
        ],
      ])->save();
    }
  }

  /**
   * Returns the schema inspector service.
   *
   * @return \Drupal\graphql_compose_codegen\Service\SchemaInspector
   *   The inspector.
   */
  private function inspector() {
    return $this->container->get('graphql_compose_codegen.schema_inspector');
  }

  /**
   * Returns the TypeScript generator service.
   *
   * @return \Drupal\graphql_compose_codegen\Service\TypeScriptGenerator
   *   The generator.
   */
  private function generator() {
    return $this->container->get('graphql_compose_codegen.typescript_generator');
  }

  /**
   * Field descriptors expose reference targets and bundle-specific TS types.
   */
  public function testParagraphFieldDescriptorsIncludeTargets(): void {
    $fields = $this->inspector()->getFieldsForParagraphBundle('p_faq_group');

    self::assertArrayHasKey('field_items', $fields);
    self::assertSame('paragraph', $fields['field_items']['target_type']);
    self::assertSame(['p_faq_item'], $fields['field_items']['target_bundles']);
    self::assertSame('DrupalParagraphPFaqItem[]', $fields['field_items']['ts_type']);
  }

  /**
   * Colliding response keys get bundle-derived aliases; the rest stay plain.
   */
  public function testParagraphFieldMapAliasesCollidingResponseKeys(): void {
    $map = $this->inspector()->getParagraphFieldMap();

    self::assertSame(
      ['p_faq_group', 'p_faq_item', 'p_tab_group', 'p_tab_item'],
      array_keys($map)
    );

    // Nested-only bundles are flagged: they render through their parents.
    self::assertFalse($map['p_faq_group']['child_only']);
    self::assertFalse($map['p_tab_group']['child_only']);
    self::assertTrue($map['p_faq_item']['child_only']);
    self::assertTrue($map['p_tab_item']['child_only']);

    // The worked example: faq keeps `items`, tab is aliased to `tabItems`.
    self::assertSame('items', $map['p_faq_group']['fields']['field_items']['response_key']);
    self::assertSame('tabItems', $map['p_tab_group']['fields']['field_items']['response_key']);

    // Same shape on both groups -> no alias needed for `title`.
    self::assertSame('title', $map['p_faq_group']['fields']['field_title']['response_key']);
    self::assertSame('title', $map['p_tab_group']['fields']['field_title']['response_key']);

    // Child-only bundle fields keep their plain names (nested selections).
    self::assertSame('title', $map['p_tab_item']['fields']['field_title']['response_key']);
    self::assertSame('body', $map['p_tab_item']['fields']['field_body']['response_key']);
  }

  /**
   * Paragraph bundles are filtered by graphql_compose entity_config.
   */
  public function testEntityConfigFilteringLimitsBundles(): void {
    // Without any graphql_compose config every bundle is listed.
    self::assertSame(
      ['p_faq_group', 'p_faq_item', 'p_tab_group', 'p_tab_item'],
      array_keys($this->inspector()->getParagraphBundles())
    );

    // The 3.x per-server config name must be recognized too.
    $this->container->get('config.factory')
      ->getEditable('graphql_compose.settings.graphql_compose_server')
      ->set('entity_config.paragraph', [
        'p_faq_group' => ['enabled' => TRUE, 'query_load_enabled' => TRUE],
        'p_faq_item' => ['enabled' => TRUE, 'query_load_enabled' => TRUE],
        'p_tab_group' => ['enabled' => TRUE, 'query_load_enabled' => FALSE],
      ])
      ->save();

    self::assertSame(
      ['p_faq_group', 'p_faq_item'],
      array_keys($this->inspector()->getParagraphBundles())
    );
  }

  /**
   * Paragraph fields are filtered by graphql_compose field_config.
   */
  public function testFieldConfigFilteringLimitsFields(): void {
    $this->container->get('config.factory')
      ->getEditable('graphql_compose.settings')
      ->set('entity_config.paragraph', [
        'p_faq_group' => ['enabled' => TRUE, 'query_load_enabled' => TRUE],
        'p_faq_item' => ['enabled' => TRUE, 'query_load_enabled' => TRUE],
        'p_tab_group' => ['enabled' => TRUE, 'query_load_enabled' => TRUE],
        'p_tab_item' => ['enabled' => TRUE, 'query_load_enabled' => TRUE],
      ])
      ->set('field_config.paragraph', [
        'p_faq_group' => [
          'field_title' => ['enabled' => TRUE],
          'field_items' => ['enabled' => FALSE],
        ],
      ])
      ->save();

    $fields = $this->inspector()->getFieldsForParagraphBundle('p_faq_group');
    self::assertSame(['field_title'], array_keys($fields));
  }

  /**
   * Fragments alias colliding keys and nest child bundles one level deep.
   */
  public function testParagraphFragmentsAliasAndNest(): void {
    $fragments = $this->generator()->generateParagraphFragments();

    self::assertStringContainsString('... on ParagraphPFaqGroup {', $fragments);
    self::assertStringContainsString('... on ParagraphPTabGroup {', $fragments);

    // The worked example: faq keeps `items`, tab is aliased to `tabItems`.
    // Fields are emitted in machine-name order (body before title).
    self::assertStringContainsString(
      "  items {\n    __typename\n    ... on ParagraphPFaqItem { body { processed } title }\n  }",
      $fragments
    );
    self::assertStringContainsString(
      "  tabItems: items {\n    __typename\n    ... on ParagraphPTabItem { body { processed } title }\n  }",
      $fragments
    );

    // Nested references never re-embed the whole fragment set (that would
    // make the PARAGRAPH_FRAGMENTS template literal self-referential).
    self::assertStringNotContainsString('${PARAGRAPH_FRAGMENTS}', $fragments);

    // Nested-only bundles get no top-level fragment entry of their own.
    self::assertStringNotContainsString("... on ParagraphPFaqItem {\n", $fragments);
    self::assertStringNotContainsString("... on ParagraphPTabItem {\n", $fragments);
  }

  /**
   * Type definitions use the aliased property names and nested item types.
   */
  public function testParagraphTypesFollowAliases(): void {
    $types = $this->generator()->generateParagraphTypeDefinitions();

    self::assertStringContainsString('export type DrupalParagraphPFaqGroup = {', $types);
    self::assertStringContainsString('items?: DrupalParagraphPFaqItem[] | null', $types);

    // The TS property name follows the GraphQL alias.
    self::assertStringContainsString('tabItems?: DrupalParagraphPTabItem[] | null', $types);

    // Child bundle types keep plain names; required fields have no `?`.
    self::assertStringContainsString('export type DrupalParagraphPTabItem = {', $types);
    self::assertStringContainsString("\n  title: string\n", $types);

    foreach ([
      'DrupalParagraphPFaqGroup',
      'DrupalParagraphPFaqItem',
      'DrupalParagraphPTabGroup',
      'DrupalParagraphPTabItem',
    ] as $member) {
      self::assertStringContainsString("| {$member}", $types);
    }
  }

  /**
   * Renderer cases target ParagraphRenderer.tsx and skip nested-only bundles.
   */
  public function testParagraphRendererCases(): void {
    $cases = $this->generator()->generateParagraphRendererCases();

    self::assertStringContainsString('ParagraphRenderer.tsx', $cases);
    self::assertStringContainsString('import { FaqGroupParagraph } from "./FaqGroupParagraph"', $cases);
    self::assertStringContainsString('case "ParagraphPFaqGroup":', $cases);
    self::assertStringContainsString(
      'return <FaqGroupParagraph data={paragraph as DrupalParagraphPFaqGroup} />',
      $cases
    );

    self::assertStringNotContainsString('case "ParagraphPFaqItem":', $cases);
    self::assertStringNotContainsString('case "ParagraphPTabItem":', $cases);
    self::assertStringContainsString('ParagraphPFaqItem, ParagraphPTabItem', $cases);
  }

  /**
   * The artefact set contains all four paragraph artefacts and filters.
   */
  public function testBuildArtefactsEmitsFourParagraphArtefacts(): void {
    $artefacts = $this->generator()->buildArtefacts();

    self::assertArrayHasKey('paragraphs/types.generated.d.ts', $artefacts);
    self::assertArrayHasKey('paragraphs/fragments.generated.ts', $artefacts);
    self::assertArrayHasKey('paragraphs/paragraph-renderer-cases.generated.tsx', $artefacts);
    self::assertArrayHasKey('paragraphs/components/FaqGroupParagraph.generated.tsx', $artefacts);
    // Every bundle gets a stub, nested-only ones included.
    self::assertArrayHasKey('paragraphs/components/TabItemParagraph.generated.tsx', $artefacts);

    // No node bundles exist in this fixture, so no node artefacts.
    self::assertArrayNotHasKey('types.generated.d.ts', $artefacts);
    self::assertArrayNotHasKey('fragments.generated.ts', $artefacts);
    self::assertArrayNotHasKey('node-renderer-cases.generated.tsx', $artefacts);
  }

  /**
   * Bundle filtering applies to paragraphs and keeps aliases stable.
   */
  public function testBuildArtefactsFiltersParagraphBundles(): void {
    $subset = $this->generator()->buildArtefacts(['p_tab_group']);

    self::assertArrayHasKey('paragraphs/components/TabGroupParagraph.generated.tsx', $subset);
    self::assertArrayNotHasKey('paragraphs/components/FaqGroupParagraph.generated.tsx', $subset);

    // The alias is computed across all enabled bundles, so a subset run
    // emits the same names as a full run.
    self::assertStringContainsString('tabItems: items', $subset['paragraphs/fragments.generated.ts']);
    self::assertStringContainsString('tabItems?:', $subset['paragraphs/types.generated.d.ts']);
  }

  /**
   * Paragraph component stubs use the data prop and component naming.
   */
  public function testParagraphComponentStubUsesDataProp(): void {
    $stub = $this->generator()->generateParagraphComponentStub('p_faq_group');

    self::assertStringContainsString(
      'export function FaqGroupParagraph({ data }: { data: DrupalParagraphPFaqGroup })',
      $stub
    );
    self::assertStringContainsString('<section>', $stub);
    self::assertStringContainsString('components/drupal/paragraphs/', $stub);
    self::assertStringNotContainsString('node.title', $stub);
  }

}
