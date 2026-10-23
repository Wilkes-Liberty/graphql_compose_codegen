<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Psr\Log\AbstractLogger;

/**
 * Captures Drush logger lines, including success() which is not PSR-3.
 */
final class RecordingDrushLogger extends AbstractLogger {

  /**
   * Captured "level: message" lines.
   *
   * @var string[]
   */
  public array $lines = [];

  /**
   * {@inheritdoc}
   */
  public function log(
    $level,
    string|\Stringable $message,
    array $context = [],
  ): void {
    $this->lines[] = $level . ': ' . (string) $message;
  }

  /**
   * Records a Drush success() line.
   *
   * @param string $message
   *   The success message.
   * @param array<string, mixed> $context
   *   PSR-3 context (unused; accepted for Drush logger compatibility).
   */
  public function success(string $message, array $context = []): void {
    $this->log('success', $message, $context);
  }

}
