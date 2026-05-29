<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Unit\Service;

use Drupal\graphql_compose_codegen\Service\PathGuard;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\graphql_compose_codegen\Service\PathGuard
 *
 * @group graphql_compose_codegen
 */
final class PathGuardTest extends TestCase {

  private string $tmpRoot;

  protected function setUp(): void {
    parent::setUp();
    $this->tmpRoot = sys_get_temp_dir() . '/gqcc-pathguard-' . bin2hex(random_bytes(4));
    mkdir($this->tmpRoot . '/web', 0700, TRUE);
    mkdir($this->tmpRoot . '/ui', 0700, TRUE);
  }

  protected function tearDown(): void {
    exec('rm -rf ' . escapeshellarg($this->tmpRoot));
    parent::tearDown();
  }

  private function guard(): PathGuard {
    return new PathGuard($this->tmpRoot . '/web');
  }

  /** @covers ::validate */
  public function testAllowsPathInsideProjectRoot(): void {
    $this->guard()->validate($this->tmpRoot . '/ui');
    $this->expectNotToPerformAssertions();
  }

  /** @covers ::validate */
  public function testRejectsAbsolutePathOutsideProjectRoot(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->guard()->validate('/etc');
  }

  /** @covers ::validate */
  public function testRejectsDotDot(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->guard()->validate($this->tmpRoot . '/web/../../escape');
  }

  /** @covers ::validate */
  public function testRejectsFilesystemRoot(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->guard()->validate('/');
  }

  /** @covers ::validate */
  public function testRejectsArbitraryPathOutsideProjectRoot(): void {
    // Create a directory that is NOT in ALWAYS_REJECT and NOT under the
    // project root, to exercise the "outside project root" rejection branch.
    $external = sys_get_temp_dir() . '/gqcc-outside-' . bin2hex(random_bytes(4));
    mkdir($external . '/leaf', 0700, TRUE);
    try {
      $this->expectException(\InvalidArgumentException::class);
      $this->guard()->validate($external . '/leaf');
    }
    finally {
      exec('rm -rf ' . escapeshellarg($external));
    }
  }

  /** @covers ::validate */
  public function testAllowExternalBypassesProjectRootCheck(): void {
    $external = sys_get_temp_dir() . '/gqcc-external-' . bin2hex(random_bytes(4));
    mkdir($external, 0700);
    try {
      $this->guard()->validate($external, allowExternal: TRUE);
      $this->expectNotToPerformAssertions();
    }
    finally {
      rmdir($external);
    }
  }

  /** @covers ::validate */
  public function testAllowExternalStillRejectsDotDot(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->guard()->validate($this->tmpRoot . '/web/../../escape', allowExternal: TRUE);
  }

  /** @covers ::validate */
  public function testAllowExternalStillRejectsDangerousRoots(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->guard()->validate('/etc', allowExternal: TRUE);
  }

}
