<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\graphql_compose_codegen\PluginManager\FieldTypeMapperManager;
use Drupal\graphql_compose_codegen\Service\SchemaInspector;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\graphql_compose_codegen\Service\SchemaInspector
 *
 * @group graphql_compose_codegen
 */
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
   * @covers ::getGraphQlTypeName */
  public function testGraphQlTypeName(): void {
    self::assertSame('NodeBasicPage', $this->inspector()->getGraphQlTypeName('basic_page'));
    self::assertSame('NodePlatform', $this->inspector()->getGraphQlTypeName('platform'));
    self::assertSame('NodeNewsletter', $this->inspector()->getGraphQlTypeName('newsletter'));
  }

  /**
   * @covers ::getTsTypeName */
  public function testTsTypeName(): void {
    self::assertSame('DrupalBasicPage', $this->inspector()->getTsTypeName('basic_page'));
    self::assertSame('DrupalPlatform', $this->inspector()->getTsTypeName('platform'));
  }

  /**
   * @covers ::getGraphQlTypeNameForParagraph */
  public function testParagraphGraphQlName(): void {
    self::assertSame('ParagraphTwoColumn', $this->inspector()->getGraphQlTypeNameForParagraph('two_column'));
  }

  /**
   * @covers ::getTsTypeNameForParagraph */
  public function testParagraphTsName(): void {
    self::assertSame('DrupalParagraphTwoColumn', $this->inspector()->getTsTypeNameForParagraph('two_column'));
  }

  /**
   * @covers ::toGqlFieldName */
  public function testFieldNameConversion(): void {
    $i = $this->inspector();
    self::assertSame('missionImpact', $i->toGqlFieldName('field_mission_impact'));
    self::assertSame('keyCapabilities', $i->toGqlFieldName('field_key_capabilities'));
    self::assertSame('body', $i->toGqlFieldName('body'));
    self::assertSame('title', $i->toGqlFieldName('title'));
  }

  /**
   * @covers ::deriveParagraphAlias */
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
   * @covers ::deriveParagraphAlias */
  public function testParagraphAliasFullBundleFallback(): void {
    $i = $this->inspector();
    self::assertSame('tabGroupItems', $i->deriveParagraphAlias('p_tab_group', 'items', TRUE));
    self::assertSame('useCaseTitle', $i->deriveParagraphAlias('use_case', 'title', TRUE));
  }

  /**
   * @covers ::getComponentNameForParagraph */
  public function testParagraphComponentName(): void {
    $i = $this->inspector();
    self::assertSame('FaqGroupParagraph', $i->getComponentNameForParagraph('p_faq_group'));
    self::assertSame('CapabilityParagraph', $i->getComponentNameForParagraph('capability'));
    self::assertSame('HeroParagraph', $i->getComponentNameForParagraph('p_hero'));
  }

}
