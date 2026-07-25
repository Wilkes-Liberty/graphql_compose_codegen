<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Unit\Plugin\FieldTypeMapper;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper\WebformMapper;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper\WebformMapper
 *
 * @group graphql_compose_codegen
 */
final class WebformMapperTest extends TestCase {

  /**
   * Returns a stub field definition with the given storage cardinality.
   *
   * @param int $cardinality
   *   The storage cardinality.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   The stub definition.
   */
  private function definition(int $cardinality): FieldDefinitionInterface {
    $storage = $this->createStub(FieldStorageDefinitionInterface::class);
    $storage->method('getCardinality')->willReturn($cardinality);
    $definition = $this->createStub(FieldDefinitionInterface::class);
    $definition->method('getFieldStorageDefinition')->willReturn($storage);
    return $definition;
  }

  /**
   * @covers ::getBaseTsType */
  public function testMapsWebformFieldToDrupalWebform(): void {
    $mapper = new WebformMapper([], 'webform', []);
    self::assertSame('DrupalWebform', $mapper->map($this->definition(1)));
  }

  /**
   * @covers ::getBaseTsType */
  public function testMultiValueWebformFieldMapsToArray(): void {
    $mapper = new WebformMapper([], 'webform', []);
    self::assertSame('DrupalWebform[]', $mapper->map($this->definition(-1)));
  }

}
