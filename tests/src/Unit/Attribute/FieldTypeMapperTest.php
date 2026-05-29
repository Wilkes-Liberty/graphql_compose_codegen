<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Unit\Attribute;

use Drupal\graphql_compose_codegen\Attribute\FieldTypeMapper;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\graphql_compose_codegen\Attribute\FieldTypeMapper
 *
 * @group graphql_compose_codegen
 */
final class FieldTypeMapperTest extends TestCase {

  /**
   * @covers ::__construct
   */
  public function testStoresIdAndDrupalTypes(): void {
    $attribute = new FieldTypeMapper(
      id: 'text',
      drupalTypes: ['string', 'string_long'],
    );

    self::assertSame('text', $attribute->id);
    self::assertSame(['string', 'string_long'], $attribute->drupalTypes);
  }

  /**
   * @covers ::__construct
   */
  public function testRejectsEmptyDrupalTypes(): void {
    $this->expectException(\InvalidArgumentException::class);
    new FieldTypeMapper(id: 'broken', drupalTypes: []);
  }

}
