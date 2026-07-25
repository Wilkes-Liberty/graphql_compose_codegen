<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;

/**
 * Collects log records in memory so tests can assert on them.
 *
 * Registered via logger.factory::addLogger(); receives every channel, so
 * assertions should filter on $record['context']['channel'].
 */
final class TestLogCollector implements LoggerInterface {

  use RfcLoggerTrait;

  /**
   * Collected records: level, message (raw, with placeholders), context.
   *
   * @var array<int, array{level: mixed, message: string, context: array}>
   */
  public array $records = [];

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    $this->records[] = [
      'level' => $level,
      'message' => (string) $message,
      'context' => $context,
    ];
  }

}
