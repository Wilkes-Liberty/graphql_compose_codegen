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

  private function inspector(): SchemaInspector {
    return new SchemaInspector(
      $this->createStub(EntityTypeBundleInfoInterface::class),
      $this->createStub(EntityFieldManagerInterface::class),
      $this->createStub(ConfigFactoryInterface::class),
      $this->createStub(FieldTypeMapperManager::class),
    );
  }

  /** @covers ::getGraphQlTypeName */
  public function testGraphQlTypeName(): void {
    self::assertSame('NodeBasicPage', $this->inspector()->getGraphQlTypeName('basic_page'));
    self::assertSame('NodePlatform', $this->inspector()->getGraphQlTypeName('platform'));
    self::assertSame('NodeNewsletter', $this->inspector()->getGraphQlTypeName('newsletter'));
  }

  /** @covers ::getTsTypeName */
  public function testTsTypeName(): void {
    self::assertSame('DrupalBasicPage', $this->inspector()->getTsTypeName('basic_page'));
    self::assertSame('DrupalPlatform', $this->inspector()->getTsTypeName('platform'));
  }

  /** @covers ::getGraphQlTypeNameForParagraph */
  public function testParagraphGraphQlName(): void {
    self::assertSame('ParagraphTwoColumn', $this->inspector()->getGraphQlTypeNameForParagraph('two_column'));
  }

  /** @covers ::getTsTypeNameForParagraph */
  public function testParagraphTsName(): void {
    self::assertSame('DrupalParagraphTwoColumn', $this->inspector()->getTsTypeNameForParagraph('two_column'));
  }

  /** @covers ::toGqlFieldName */
  public function testFieldNameConversion(): void {
    $i = $this->inspector();
    self::assertSame('missionImpact', $i->toGqlFieldName('field_mission_impact'));
    self::assertSame('keyCapabilities', $i->toGqlFieldName('field_key_capabilities'));
    self::assertSame('body', $i->toGqlFieldName('body'));
    self::assertSame('title', $i->toGqlFieldName('title'));
  }

}
