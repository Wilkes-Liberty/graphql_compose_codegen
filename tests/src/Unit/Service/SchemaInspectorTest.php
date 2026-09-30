<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\graphql_compose_codegen\PluginManager\FieldTypeMapperManager;
use Drupal\graphql_compose_codegen\Service\SchemaInspector;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\graphql_compose_codegen\Service\SchemaInspector
 * @covers \Drupal\graphql_compose_codegen\Service\SchemaInspector::getTypeName
 * @covers \Drupal\graphql_compose_codegen\Service\SchemaInspector::toGqlFieldName
 * @covers \Drupal\graphql_compose_codegen\Service\SchemaInspector::deriveParagraphAlias
 * @covers \Drupal\graphql_compose_codegen\Service\SchemaInspector::getComponentName
 * @covers \Drupal\graphql_compose_codegen\Service\SchemaInspector::getComponentNameForParagraph
 *
 * @group graphql_compose_codegen
 */
#[CoversMethod(SchemaInspector::class, 'getTypeName')]
#[CoversMethod(SchemaInspector::class, 'toGqlFieldName')]
#[CoversMethod(SchemaInspector::class, 'deriveParagraphAlias')]
#[CoversMethod(SchemaInspector::class, 'getComponentName')]
#[CoversMethod(SchemaInspector::class, 'getComponentNameForParagraph')]
#[Group('graphql_compose_codegen')]
final class SchemaInspectorTest extends TestCase {

  /**
   * Returns a SchemaInspector wired with stub dependencies.
   *
   * @return \Drupal\graphql_compose_codegen\Service\SchemaInspector
   *   The inspector instance.
   */
  private function inspector(): SchemaInspector {
    return new SchemaInspector(
      $this->createStub(EntityTypeBundleInfoInterface::class),
      $this->createStub(EntityFieldManagerInterface::class),
      $this->createStub(ConfigFactoryInterface::class),
      $this->createStub(FieldTypeMapperManager::class),
    );
  }

  /**
   * Tests GraphQL type name generation.
   */
  public function testGraphQlTypeName(): void {
    self::assertSame('NodeBasicPage', $this->inspector()->getTypeName('node', 'gql', 'basic_page'));
    self::assertSame('NodePlatform', $this->inspector()->getTypeName('node', 'gql', 'platform'));
    self::assertSame('NodeNewsletter', $this->inspector()->getTypeName('node', 'gql', 'newsletter'));
  }

  /**
   * Tests TypeScript type name generation.
   */
  public function testTsTypeName(): void {
    self::assertSame('DrupalBasicPage', $this->inspector()->getTypeName('node', 'ts', 'basic_page'));
    self::assertSame('DrupalPlatform', $this->inspector()->getTypeName('node', 'ts', 'platform'));
  }

  /**
   * Tests paragraph GraphQL type name generation.
   */
  public function testParagraphGraphQlName(): void {
    self::assertSame('ParagraphTwoColumn', $this->inspector()->getTypeName('paragraph', 'gql', 'two_column'));
  }

  /**
   * Tests paragraph TypeScript type name generation.
   */
  public function testParagraphTsName(): void {
    self::assertSame('DrupalParagraphTwoColumn', $this->inspector()->getTypeName('paragraph', 'ts', 'two_column'));
  }

  /**
   * Tests Drupal-to-GraphQL field name conversion.
   */
  public function testFieldNameConversion(): void {
    $i = $this->inspector();
    self::assertSame('missionImpact', $i->toGqlFieldName('field_mission_impact'));
    self::assertSame('keyCapabilities', $i->toGqlFieldName('field_key_capabilities'));
    self::assertSame('body', $i->toGqlFieldName('body'));
    self::assertSame('title', $i->toGqlFieldName('title'));
  }

  /**
   * Tests paragraph alias derivation.
   */
  public function testParagraphAliasDerivation(): void {
    $i = $this->inspector();
    // First segment of the stripped bundle name prefixes the field name.
    self::assertSame('tabItems', $i->deriveParagraphAlias('p_tab_group', 'items'));
    self::assertSame('heroTitle', $i->deriveParagraphAlias('p_hero', 'title'));
    self::assertSame('noticeTitle', $i->deriveParagraphAlias('p_notice', 'title'));
    // Bundles without the p_ prefix use their own first segment.
    self::assertSame('useTitle', $i->deriveParagraphAlias('use_case', 'title'));
  }

  /**
   * Tests full-bundle paragraph alias derivation.
   */
  public function testParagraphAliasFullBundleFallback(): void {
    $i = $this->inspector();
    self::assertSame('tabGroupItems', $i->deriveParagraphAlias('p_tab_group', 'items', TRUE));
    self::assertSame('useCaseTitle', $i->deriveParagraphAlias('use_case', 'title', TRUE));
  }

  /**
   * Tests paragraph component name generation.
   */
  public function testParagraphComponentName(): void {
    $i = $this->inspector();
    self::assertSame('FaqGroupParagraph', $i->getComponentName('paragraph', 'p_faq_group'));
    self::assertSame('CapabilityParagraph', $i->getComponentNameForParagraph('capability'));
    self::assertSame('HeroParagraph', $i->getComponentNameForParagraph('p_hero'));
    self::assertSame('Article', $i->getComponentName('node', 'article'));
  }

}
