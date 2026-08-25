<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Unit\Service;

use Drupal\graphql_compose_codegen\Service\PathGuard;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\graphql_compose_codegen\Service\PathGuard
 * @covers \Drupal\graphql_compose_codegen\Service\PathGuard::validate
 *
 * @group graphql_compose_codegen
 */
#[CoversMethod(PathGuard::class, 'validate')]
#[Group('graphql_compose_codegen')]
final class PathGuardTest extends TestCase {

  /**
   * Temporary root directory for the test file system.
   *
   * @var string
   */
  private string $tmpRoot;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->tmpRoot = sys_get_temp_dir() . '/gqcc-pathguard-' . bin2hex(random_bytes(4));
    mkdir($this->tmpRoot . '/web', 0700, TRUE);
    mkdir($this->tmpRoot . '/ui', 0700, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    exec('rm -rf ' . escapeshellarg($this->tmpRoot));
    parent::tearDown();
  }

  /**
   * Returns a PathGuard with the test web root.
   *
   * @return \Drupal\graphql_compose_codegen\Service\PathGuard
   *   The guard instance.
   */
  private function guard(): PathGuard {
    return new PathGuard($this->tmpRoot . '/web');
  }

  /**
   * Tests that a path inside the project root is allowed.
   */
  public function testAllowsPathInsideProjectRoot(): void {
    $this->guard()->validate($this->tmpRoot . '/ui');
    $this->expectNotToPerformAssertions();
  }

  /**
   * Tests that absolute paths outside the project root are rejected.
   */
  public function testRejectsAbsolutePathOutsideProjectRoot(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->guard()->validate('/etc');
  }

  /**
   * Tests that paths containing parent-directory traversal are rejected.
   */
  public function testRejectsDotDot(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->guard()->validate($this->tmpRoot . '/web/../../escape');
  }

  /**
   * Tests that the filesystem root is rejected.
   */
  public function testRejectsFilesystemRoot(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->guard()->validate('/');
  }

  /**
   * Tests that arbitrary paths outside the project root are rejected.
   */
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

  /**
   * Tests that allowExternal bypasses the project-root check.
   */
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

  /**
   * Tests that allowExternal still rejects parent-directory traversal.
   */
  public function testAllowExternalStillRejectsDotDot(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->guard()->validate($this->tmpRoot . '/web/../../escape', allowExternal: TRUE);
  }

  /**
   * Tests that allowExternal still rejects dangerous roots.
   */
  public function testAllowExternalStillRejectsDangerousRoots(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->guard()->validate('/etc', allowExternal: TRUE);
  }

  /**
   * Rejects the macOS /private variants of the dangerous roots.
   *
   * On macOS /etc, /tmp and /var are symlinks into /private, so realpath()
   * resolves them to /private/etc etc. — which must be rejected too. The
   * literal /private paths exercise the same comparison on Linux, where
   * they fall through realpath() to lexical normalisation.
   */
  public function testAllowExternalRejectsMacosPrivateVariants(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->guard()->validate('/private/etc', allowExternal: TRUE);
  }

  /**
   * Tests that allowExternal rejects the macOS private temporary directory.
   */
  public function testAllowExternalRejectsMacosPrivateTmp(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->guard()->validate('/private/tmp', allowExternal: TRUE);
  }

  /**
   * Tests that allowExternal rejects the macOS private root.
   */
  public function testAllowExternalRejectsPrivateItself(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->guard()->validate('/private', allowExternal: TRUE);
  }

}
