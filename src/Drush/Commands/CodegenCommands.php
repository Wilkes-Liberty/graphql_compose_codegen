<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Drush\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\graphql_compose_codegen\Service\ArtefactSnapshot;
use Drupal\graphql_compose_codegen\Service\PathGuard;
use Drupal\graphql_compose_codegen\Service\SchemaInspector;
use Drupal\graphql_compose_codegen\Service\TypeScriptGenerator;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for graphql_compose_codegen.
 */
final class CodegenCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs a CodegenCommands instance.
   *
   * @param \Drupal\graphql_compose_codegen\Service\SchemaInspector $inspector
   *   The schema inspector.
   * @param \Drupal\graphql_compose_codegen\Service\TypeScriptGenerator $generator
   *   The TypeScript generator.
   * @param \Drupal\graphql_compose_codegen\Service\ArtefactSnapshot $snapshot
   *   The snapshot service.
   * @param \Drupal\graphql_compose_codegen\Service\PathGuard $pathGuard
   *   The output-dir safety guard.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private readonly SchemaInspector $inspector,
    private readonly TypeScriptGenerator $generator,
    private readonly ArtefactSnapshot $snapshot,
    private readonly PathGuard $pathGuard,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct();
  }

  /**
   * Lists all node + paragraph bundles and their extra fields.
   */
  #[CLI\Command(
    name: 'graphql-compose-codegen:inspect',
    aliases: ['gqcc:inspect', 'gqcc:i'],
  )]
  #[CLI\Option(name: 'bundles', description: 'Comma-separated bundle IDs to inspect (omit for all bundles).')]
  #[CLI\Option(name: 'skip-fields', description: 'Comma-separated additional field names to exclude.')]
  #[CLI\Usage(name: 'drush gqcc:inspect', description: 'List all node and paragraph bundles and their extra fields.')]
  #[CLI\Usage(name: 'drush gqcc:inspect --bundles=platform,service', description: 'Inspect only the listed bundles.')]
  public function inspect(array $options = ['bundles' => '', 'skip-fields' => '']): void {
    $only = $this->parseList((string) ($options['bundles'] ?? ''));
    $skip = $this->parseList((string) ($options['skip-fields'] ?? ''));

    $bundleInfo = $this->inspector->getBundles($only);
    $paragraphMap = $this->inspector->getParagraphFieldMap($only, $skip);
    if (!$bundleInfo && !$paragraphMap) {
      $this->logger()->warning('No matching bundles found.');
      return;
    }

    foreach ($bundleInfo as $bundle => $info) {
      $this->writeBundleHeader(
        $bundle,
        (string) ($info['label'] ?? $bundle),
        $this->inspector->getGraphQlTypeName($bundle),
        $this->inspector->getTsTypeName($bundle),
      );
      $fields = $this->inspector->getFieldsForBundle($bundle, $skip);
      $this->writeFields($fields);
      $this->output()->writeln('└──');
    }

    if ($paragraphMap) {
      $paragraphInfo = $this->inspector->getParagraphBundles($only);
      $this->output()->writeln('');
      $this->output()->writeln('  Paragraph bundles (enabled in graphql_compose):');
      foreach ($paragraphMap as $bundle => $entry) {
        $this->writeBundleHeader(
          $bundle,
          (string) ($paragraphInfo[$bundle]['label'] ?? $bundle),
          $this->inspector->getGraphQlTypeNameForParagraph($bundle),
          $this->inspector->getTsTypeNameForParagraph($bundle),
        );
        if ($entry['child_only']) {
          $this->output()->writeln('│  (nested-only: selected inside its parent bundles)');
        }
        $this->writeFields($entry['fields']);
        $this->output()->writeln('└──');
      }
    }

    $this->output()->writeln('');
    $this->output()->writeln(sprintf(
      "  %d bundle(s) inspected. Run <comment>drush gqcc:generate</comment> to scaffold.",
      count($bundleInfo) + count($paragraphMap)
    ));
  }

  /**
   * Generates TypeScript scaffold artefacts for node bundles.
   */
  #[CLI\Command(
    name: 'graphql-compose-codegen:generate',
    aliases: ['gqcc:generate', 'gqcc:gen'],
  )]
  #[CLI\Option(name: 'bundles', description: 'Comma-separated bundle IDs.')]
  #[CLI\Option(name: 'output-dir', description: 'Path to Next.js project root. Artefacts go to {output-dir}/generated/.')]
  #[CLI\Option(name: 'overwrite', description: 'Overwrite existing generated files.')]
  #[CLI\Option(name: 'skip-fields', description: 'Comma-separated extra field names to exclude.')]
  #[CLI\Option(name: 'dry-run', description: 'Print what would be written without touching disk.')]
  #[CLI\Option(name: 'allow-external', description: 'Permit output-dir paths outside the project root.')]
  #[CLI\Usage(name: 'drush gqcc:generate', description: 'Print scaffold for all bundles to stdout.')]
  #[CLI\Usage(name: 'drush gqcc:generate --output-dir=../ui', description: 'Write scaffold to ../ui/generated/.')]
  #[CLI\Usage(name: 'drush gqcc:generate --output-dir=../ui --dry-run', description: 'Preview without writing.')]
  public function generate(
    array $options = [
      'bundles' => '',
      'output-dir' => '',
      'overwrite' => FALSE,
      'skip-fields' => '',
      'dry-run' => FALSE,
      'allow-external' => FALSE,
    ],
  ): void {
    $only = $this->parseList((string) ($options['bundles'] ?? ''));
    $skip = $this->parseList((string) ($options['skip-fields'] ?? ''));
    $outputDir = rtrim((string) ($options['output-dir'] ?? ''), '/');
    $overwrite = (bool) $options['overwrite'];
    $dryRun = (bool) $options['dry-run'];
    $allowExternal = (bool) $options['allow-external'];

    if (!$outputDir) {
      $cfg = $this->configFactory->get('graphql_compose_codegen.settings');
      $outputDir = rtrim((string) ($cfg->get('output_dir') ?? ''), '/');
    }
    if ($outputDir && !str_starts_with($outputDir, '/')) {
      $outputDir = DRUPAL_ROOT . '/' . $outputDir;
    }

    // Validate the output directory BEFORE any other work — defense in depth.
    if ($outputDir) {
      $this->pathGuard->validate($outputDir, $allowExternal);
    }

    $bundleInfo = $this->inspector->getBundles($only);
    $paragraphBundles = $this->inspector->getParagraphBundles($only);
    if (!$bundleInfo && !$paragraphBundles) {
      $this->logger()->warning('No matching bundles found.');
      return;
    }

    $artefacts = $this->generator->buildArtefacts($only, $skip);

    $context = [
      'bundles' => array_keys($bundleInfo),
      'paragraph_bundles' => array_keys($paragraphBundles),
      'output_dir' => $outputDir,
      'overwrite' => $overwrite,
      'dry_run' => $dryRun,
    ];
    $this->moduleHandler->invokeAll('graphql_compose_codegen_pre_generate', [$context]);

    if (!$outputDir) {
      $this->emitStdout($artefacts);
    }
    else {
      $this->emitFiles($artefacts, $outputDir . '/generated', $overwrite, $dryRun);
      if (!$dryRun) {
        $this->snapshot->record($artefacts);
      }
    }

    $context['artefacts'] = $artefacts;
    $this->moduleHandler->invokeAll('graphql_compose_codegen_post_generate', [$context]);
  }

  /**
   * Shows what would change versus the last gqcc:generate run.
   */
  #[CLI\Command(
    name: 'graphql-compose-codegen:diff',
    aliases: ['gqcc:diff'],
  )]
  #[CLI\Option(name: 'bundles', description: 'Comma-separated bundle IDs.')]
  #[CLI\Option(name: 'skip-fields', description: 'Comma-separated extra field names to exclude.')]
  #[CLI\Usage(name: 'drush gqcc:diff', description: 'Show what would change vs the last gqcc:generate run.')]
  public function diff(array $options = ['bundles' => '', 'skip-fields' => '']): int {
    if ($this->snapshot->load() === NULL) {
      $this->logger()->warning('No previous snapshot — run gqcc:generate --output-dir=... once first.');
      return self::EXIT_SUCCESS;
    }

    $only = $this->parseList((string) ($options['bundles'] ?? ''));
    $skip = $this->parseList((string) ($options['skip-fields'] ?? ''));
    $artefacts = $this->generator->buildArtefacts($only, $skip);
    $diff = $this->snapshot->diff($artefacts);

    $this->output()->writeln(sprintf('  Added:   %d', count($diff['added'])));
    foreach ($diff['added'] as $p) {
      $this->output()->writeln("    + {$p}");
    }
    $this->output()->writeln(sprintf('  Changed: %d', count($diff['changed'])));
    foreach ($diff['changed'] as $p) {
      $this->output()->writeln("    ~ {$p}");
    }
    $this->output()->writeln(sprintf('  Removed: %d', count($diff['removed'])));
    foreach ($diff['removed'] as $p) {
      $this->output()->writeln("    - {$p}");
    }

    return self::EXIT_SUCCESS;
  }

  /**
   * Validates that scaffold files on disk match the live schema.
   */
  #[CLI\Command(
    name: 'graphql-compose-codegen:validate',
    aliases: ['gqcc:validate'],
  )]
  #[CLI\Option(name: 'bundles', description: 'Comma-separated bundle IDs.')]
  #[CLI\Option(name: 'output-dir', description: 'Path to Next.js project root (required).')]
  #[CLI\Option(name: 'skip-fields', description: 'Comma-separated extra field names to exclude.')]
  #[CLI\Option(name: 'allow-external', description: 'Permit output-dir paths outside the project root.')]
  #[CLI\Usage(name: 'drush gqcc:validate --output-dir=../ui', description: 'Exit non-zero if scaffold files on disk are out of sync with the live schema.')]
  public function validate(
    array $options = [
      'bundles' => '',
      'output-dir' => '',
      'skip-fields' => '',
      'allow-external' => FALSE,
    ],
  ): int {
    $outputDir = rtrim((string) ($options['output-dir'] ?? ''), '/');
    if (!$outputDir) {
      $this->logger()->error('--output-dir is required for gqcc:validate.');
      return self::EXIT_FAILURE;
    }
    if (!str_starts_with($outputDir, '/')) {
      $outputDir = DRUPAL_ROOT . '/' . $outputDir;
    }
    $this->pathGuard->validate($outputDir, (bool) $options['allow-external']);

    $only = $this->parseList((string) ($options['bundles'] ?? ''));
    $skip = $this->parseList((string) ($options['skip-fields'] ?? ''));
    $current = $this->generator->buildArtefacts($only, $skip);

    $report = $this->snapshot->compareDisk($outputDir . '/generated', $current);
    $bad = count($report['missing']) + count($report['stale']);

    foreach ($report['missing'] as $p) {
      $this->output()->writeln("  MISSING: {$p}");
    }
    foreach ($report['stale'] as $p) {
      $this->output()->writeln("  STALE:   {$p}");
    }
    foreach ($report['extra'] as $p) {
      $this->output()->writeln("  EXTRA:   {$p}");
    }

    if ($bad === 0) {
      $this->logger()->success('All generated files in sync with the live schema.');
      return self::EXIT_SUCCESS;
    }
    $this->logger()->error(sprintf('%d file(s) out of sync. Run gqcc:generate to refresh.', $bad));
    return self::EXIT_FAILURE;
  }

  /**
   * Emits all artefact content to stdout.
   *
   * @param array<string, string> $artefacts
   *   Relative path to artefact content.
   */
  private function emitStdout(array $artefacts): void {
    $this->output()->writeln('');
    foreach ($artefacts as $relPath => $content) {
      $sep = str_repeat('═', 72);
      $this->output()->writeln("╔{$sep}╗");
      $padded = str_pad("  FILE: {$relPath}  ", 74);
      $this->output()->writeln("║{$padded}║");
      $this->output()->writeln("╚{$sep}╝");
      $this->output()->writeln($content);
      $this->output()->writeln('');
    }
    $this->output()->writeln('  Tip: use --output-dir=/path/to/nextjs to write files directly.');
  }

  /**
   * Writes artefacts to disk under the given generated directory.
   *
   * @param array<string, string> $artefacts
   *   Relative path to artefact content.
   * @param string $genDir
   *   The absolute path to the generated directory.
   * @param bool $overwrite
   *   Whether to overwrite existing files.
   * @param bool $dryRun
   *   If TRUE, only log what would be written.
   */
  private function emitFiles(array $artefacts, string $genDir, bool $overwrite, bool $dryRun): void {
    foreach ($artefacts as $relPath => $content) {
      $absPath = $genDir . '/' . $relPath;
      $dir = dirname($absPath);

      if ($dryRun) {
        $this->logger()->notice(sprintf('Would write: %s (%d bytes)', $relPath, strlen($content)));
        continue;
      }

      if (!is_dir($dir) && !mkdir($dir, 0755, TRUE) && !is_dir($dir)) {
        $this->logger()->error("Could not create directory: {$dir}");
        continue;
      }

      if (file_exists($absPath) && !$overwrite) {
        $this->logger()->warning("Skipped (exists): {$relPath}  — use --overwrite to regenerate.");
        continue;
      }

      // Idempotency: skip write if content unchanged.
      if (file_exists($absPath)) {
        $onDisk = file_get_contents($absPath);
        if (rtrim($onDisk ?: '', "\n") === rtrim($content, "\n")) {
          $this->logger()->notice("Unchanged: {$relPath}");
          continue;
        }
      }

      if (file_put_contents($absPath, $content . "\n") === FALSE) {
        $this->logger()->error("Failed to write: {$absPath}");
        continue;
      }
      $this->logger()->success("Written: {$relPath}");
    }

    if (!$dryRun) {
      $this->output()->writeln('');
      $this->output()->writeln("✓ Scaffold written to: <info>{$genDir}</info>");
    }
  }

  /**
   * Writes a formatted bundle header to output.
   *
   * @param string $bundle
   *   The bundle machine name.
   * @param string $label
   *   The human-readable bundle label.
   * @param string $gqlType
   *   The GraphQL type name.
   * @param string $tsType
   *   The TypeScript type name.
   */
  private function writeBundleHeader(string $bundle, string $label, string $gqlType, string $tsType): void {
    $this->output()->writeln(sprintf("\n┌─ <info>%s</info> (%s)", $label, $bundle));
    $this->output()->writeln(sprintf("│  GQL: <comment>%s</comment>    TS: <comment>%s</comment>", $gqlType, $tsType));
    $this->output()->writeln("│");
  }

  /**
   * Writes field descriptors for a bundle to output.
   *
   * @param array<string, mixed> $fields
   *   Field descriptor arrays keyed by field machine name.
   */
  private function writeFields(array $fields): void {
    if (!$fields) {
      $this->output()->writeln("│  (no extra fields — only base type fields)");
      return;
    }
    foreach ($fields as $fieldName => $field) {
      $req = $field['required'] ? '  ' : '? ';
      // Aliased response keys display as "tabItems: items".
      $displayName = isset($field['response_key']) && $field['response_key'] !== $field['gql_name']
        ? "{$field['response_key']}: {$field['gql_name']}"
        : $field['gql_name'];
      $this->output()->writeln(sprintf(
        "│  %-45s <info>%s</info>%s  # %s (%s)",
        $displayName . $req,
        $field['ts_type'],
        str_repeat(' ', max(0, 30 - strlen($field['ts_type']))),
        $fieldName,
        $field['drupal_type']
      ));
    }
  }

  /**
   * Parses a comma-separated string into a filtered array of strings.
   *
   * @param string $csv
   *   Comma-separated values.
   *
   * @return string[]
   *   Trimmed, non-empty string values.
   */
  private function parseList(string $csv): array {
    return array_values(array_filter(array_map('trim', explode(',', $csv))));
  }

}
