<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Unit\Attribute;

use Drupal\graphql_compose_codegen\Attribute\FieldTypeMapper;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\graphql_compose_codegen\Attribute\FieldTypeMapper
 * @covers \Drupal\graphql_compose_codegen\Attribute\FieldTypeMapper::__construct
 *
 * @group graphql_compose_codegen
 */
#[CoversMethod(FieldTypeMapper::class, '__construct')]
#[Group('graphql_compose_codegen')]
final class FieldTypeMapperTest extends TestCase {

  /**
   * Tests that constructor arguments are retained.
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
   * Tests that an empty Drupal type list is rejected.
   */
  public function testRejectsEmptyDrupalTypes(): void {
    $this->expectException(\InvalidArgumentException::class);
    new FieldTypeMapper(id: 'broken', drupalTypes: []);
  }

}
