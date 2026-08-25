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
 * Tests scaffolding of webform reference fields on paragraph bundles.
 *
 * The selection and type shape follow what graphql_compose_webform exposes
 * over the wire.
 *
 * @runTestsInSeparateProcesses
 *
 * @group graphql_compose_codegen
 */
#[Group('graphql_compose_codegen')]
#[RunTestsInSeparateProcesses]
final class WebformScaffoldKernelTest extends KernelTestBase {

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
    'webform',
    'graphql_compose_codegen',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('user');

    ParagraphsType::create(['id' => 'p_form', 'label' => 'Form'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_webform',
      'entity_type' => 'paragraph',
      'type' => 'webform',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_webform',
      'entity_type' => 'paragraph',
      'bundle' => 'p_form',
      'required' => TRUE,
    ])->save();
  }

  /**
   * Webform fields map to the DrupalWebform TypeScript type.
   */
  public function testWebformFieldMapsToDrupalWebform(): void {
    $fields = $this->container->get('graphql_compose_codegen.schema_inspector')
      ->getFieldsForParagraphBundle('p_form');

    self::assertArrayHasKey('field_webform', $fields);
    self::assertSame('webform', $fields['field_webform']['drupal_type']);
    self::assertSame('DrupalWebform', $fields['field_webform']['ts_type']);
  }

  /**
   * Fragments select the shape graphql_compose_webform exposes.
   */
  public function testWebformFragmentSelection(): void {
    $fragments = $this->container->get('graphql_compose_codegen.typescript_generator')
      ->generateParagraphFragments();

    self::assertStringContainsString(
      'webform { id label description elements { webform_key type title required placeholder description options { id value } } }',
      $fragments
    );
    self::assertStringNotContainsString('TODO: add sub-selection', $fragments);
  }

  /**
   * Type definitions use DrupalWebform and ship its helper types once.
   */
  public function testWebformTypeDefinitions(): void {
    $types = $this->container->get('graphql_compose_codegen.typescript_generator')
      ->generateParagraphTypeDefinitions();

    // Required field: no `?`, no `| null`.
    self::assertStringContainsString("\n  webform: DrupalWebform\n", $types);

    // The helper types are appended so the scaffold is self-contained.
    self::assertStringContainsString('export type DrupalWebform = {', $types);
    self::assertStringContainsString('export type DrupalWebformElement = {', $types);
    self::assertStringContainsString('export type DrupalWebformElementOption = {', $types);
  }

}
