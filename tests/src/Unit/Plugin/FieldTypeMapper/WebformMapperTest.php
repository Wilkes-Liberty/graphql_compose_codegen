<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Unit\Plugin\FieldTypeMapper;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper\WebformMapper;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper\WebformMapper
 * @covers \Drupal\graphql_compose_codegen\Plugin\FieldTypeMapper\WebformMapper::getBaseTsType
 *
 * @group graphql_compose_codegen
 */
#[CoversMethod(WebformMapper::class, 'getBaseTsType')]
#[Group('graphql_compose_codegen')]
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
   * Tests mapping a single-value webform field.
   */
  public function testMapsWebformFieldToDrupalWebform(): void {
    $mapper = new WebformMapper([], 'webform', []);
    self::assertSame('DrupalWebform', $mapper->map($this->definition(1)));
  }

  /**
   * Tests mapping a multi-value webform field.
   */
  public function testMultiValueWebformFieldMapsToArray(): void {
    $mapper = new WebformMapper([], 'webform', []);
    self::assertSame('DrupalWebform[]', $mapper->map($this->definition(-1)));
  }

}
