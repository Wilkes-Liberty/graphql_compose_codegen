<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Service;

/**
 * Validates that an output directory path is safe to write into.
 *
 * Used as a defense-in-depth check against `--output-dir` values that
 * resolve to dangerous filesystem locations.
 */
final class PathGuard {

  /**
   * Paths the guard always rejects, regardless of allowExternal.
   */
  private const ALWAYS_REJECT = ['/', '/etc', '/usr', '/var', '/root', '/home'];

  /**
   * Constructs a PathGuard.
   *
   * @param string $drupalRoot
   *   Absolute path to the Drupal root directory.
   */
  public function __construct(
    private readonly string $drupalRoot,
  ) {}

  /**
   * Throws InvalidArgumentException if the path is not safe to write into.
   *
   * @param string $outputDir
   *   Absolute or relative path. Relative paths are resolved against
   *   the Drupal root.
   * @param bool $allowExternal
   *   When TRUE, paths outside the project root (Drupal root's parent) are
   *   permitted. Dangerous roots and `..` traversal remain rejected.
   */
  public function validate(string $outputDir, bool $allowExternal = FALSE): void {
    if ($outputDir === '') {
      throw new \InvalidArgumentException('Output directory is empty.');
    }

    // Reject literal '..' segments before resolution to catch attempts
    // before the parent directory exists.
    if (str_contains($outputDir, '..')) {
      throw new \InvalidArgumentException("Output directory must not contain '..' segments.");
    }

    $absolute = str_starts_with($outputDir, '/')
      ? $outputDir
      : $this->drupalRoot . '/' . $outputDir;

    // Real-resolve as far as we can; if the directory doesn't exist yet,
    // fall back to lexical normalisation of the absolute path.
    $resolved = realpath($absolute) ?: $this->lexicalNormalise($absolute);

    foreach (self::ALWAYS_REJECT as $bad) {
      if ($resolved === $bad || str_starts_with($resolved . '/', $bad . '/')) {
        throw new \InvalidArgumentException("Refusing to write under '{$bad}'.");
      }
    }

    if ($allowExternal) {
      return;
    }

    $projectRoot = realpath($this->drupalRoot . '/..') ?: dirname($this->drupalRoot);
    if (!str_starts_with($resolved . '/', $projectRoot . '/')) {
      throw new \InvalidArgumentException(
        "Output directory '{$resolved}' is outside the project root '{$projectRoot}'. "
        . "Pass --allow-external if this is intentional."
      );
    }
  }

  /**
   * Lexically normalises a path without touching the filesystem.
   */
  private function lexicalNormalise(string $path): string {
    $segments = [];
    foreach (explode('/', $path) as $segment) {
      if ($segment === '' || $segment === '.') {
        continue;
      }
      if ($segment === '..') {
        array_pop($segments);
        continue;
      }
      $segments[] = $segment;
    }
    return '/' . implode('/', $segments);
  }

}
