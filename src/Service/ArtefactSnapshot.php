<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Service;

use Drupal\Core\State\StateInterface;

/**
 * Persists hashes of the most recent generated artefact set.
 *
 * Used by gqcc:diff and gqcc:validate to detect drift between the live
 * schema and the last generation run.
 *
 * Storage: state API (always available); no filesystem dependency.
 */
final class ArtefactSnapshot {

  private const STATE_KEY = 'graphql_compose_codegen.last_snapshot';

  /**
   * Constructs an ArtefactSnapshot.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    private readonly StateInterface $state,
  ) {}

  /**
   * Records hashes for the given artefact set.
   *
   * @param array<string, string> $artefacts
   *   Relative path → file content.
   */
  public function record(array $artefacts): void {
    $hashes = [];
    foreach ($artefacts as $path => $content) {
      $hashes[$path] = sha1($content);
    }
    $this->state->set(self::STATE_KEY, [
      'recorded_at' => time(),
      'hashes' => $hashes,
    ]);
  }

  /**
   * Returns the last snapshot, or NULL if none recorded.
   *
   * @return array{recorded_at: int, hashes: array<string, string>}|null
   *   The snapshot data, or NULL if no snapshot has been recorded.
   */
  public function load(): ?array {
    $raw = $this->state->get(self::STATE_KEY);
    return is_array($raw) && isset($raw['hashes']) ? $raw : NULL;
  }

  /**
   * Returns a per-path diff between current artefacts and the last snapshot.
   *
   * @param array<string, string> $current
   *   Relative path → file content.
   *
   * @return array{added: string[], removed: string[], changed: string[]}
   *   Arrays of relative paths added, removed, or changed.
   */
  public function diff(array $current): array {
    $snapshot = $this->load();
    $previous = $snapshot['hashes'] ?? [];

    $currentHashes = array_map('sha1', $current);
    $added = array_values(array_diff(array_keys($currentHashes), array_keys($previous)));
    $removed = array_values(array_diff(array_keys($previous), array_keys($currentHashes)));
    $changed = [];
    foreach ($currentHashes as $path => $hash) {
      if (isset($previous[$path]) && $previous[$path] !== $hash) {
        $changed[] = $path;
      }
    }
    return [
      'added' => $added,
      'removed' => $removed,
      'changed' => $changed,
    ];
  }

  /**
   * Compares artefacts on disk to the current expected artefact set.
   *
   * @param string $genDir
   *   Absolute directory containing previously-written generated files.
   * @param array<string, string> $current
   *   Relative path → expected file content.
   *
   * @return array{missing: string[], extra: string[], stale: string[]}
   *   Arrays of relative paths that are missing, extra, or stale on disk.
   */
  public function compareDisk(string $genDir, array $current): array {
    $missing = [];
    $stale = [];
    foreach ($current as $rel => $content) {
      $abs = $genDir . '/' . $rel;
      if (!is_file($abs)) {
        $missing[] = $rel;
        continue;
      }
      $onDisk = file_get_contents($abs);
      // The generator appends "\n" when writing, account for it.
      if (rtrim($onDisk ?: '', "\n") !== rtrim($content, "\n")) {
        $stale[] = $rel;
      }
    }

    // Extras: any file in $genDir that's not in the current artefact set.
    $extra = [];
    if (is_dir($genDir)) {
      $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($genDir, \FilesystemIterator::SKIP_DOTS));
      foreach ($iterator as $info) {
        if (!$info->isFile()) {
          continue;
        }
        $rel = ltrim(str_replace($genDir, '', (string) $info->getRealPath()), '/');
        if (!isset($current[$rel])) {
          $extra[] = $rel;
        }
      }
    }

    return [
      'missing' => $missing,
      'extra' => $extra,
      'stale' => $stale,
    ];
  }

}
