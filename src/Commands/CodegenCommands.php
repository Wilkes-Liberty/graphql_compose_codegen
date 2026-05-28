<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Commands;

use Drupal\graphql_compose_codegen\Service\SchemaInspector;
use Drupal\graphql_compose_codegen\Service\TypeScriptGenerator;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for graphql_compose_codegen.
 *
 * Usage overview:
 *   drush gqcc:inspect                         # list bundles + extra fields
 *   drush gqcc:generate                        # print scaffold to stdout
 *   drush gqcc:generate --output-dir=/path     # write scaffold to /path/generated/
 *   drush gqcc:generate --bundles=platform,service --output-dir=/path
 *   drush gqcc:generate --overwrite            # regenerate even if files exist
 *
 * Services are resolved lazily via \Drupal::service() so that the command
 * class itself has no constructor arguments — required for Drush 12's
 * drush.services.yml discovery mechanism which does not bridge Drupal's DI
 * container at registration time.
 */
class CodegenCommands extends DrushCommands {

  /**
   * Lazy accessor for the SchemaInspector service.
   */
  private function inspector(): SchemaInspector {
    return \Drupal::service('graphql_compose_codegen.schema_inspector');
  }

  /**
   * Lazy accessor for the TypeScriptGenerator service.
   */
  private function generator(): TypeScriptGenerator {
    return \Drupal::service('graphql_compose_codegen.typescript_generator');
  }

  // ---------------------------------------------------------------------------
  // gqcc:inspect
  // ---------------------------------------------------------------------------

  /**
   * Inspect the Drupal content model — list node bundles and their extra fields.
   *
   * "Extra" means fields that are NOT in the shared base type (NodeCommonFields
   * or equivalent). Configure the base type field list via
   * `graphql_compose_codegen.settings.base_type_fields`.
   *
   * @command graphql-compose-codegen:inspect
   * @aliases gqcc:inspect,gqcc:i
   *
   * @option bundles     Comma-separated bundle IDs to inspect (omit for all bundles).
   * @option skip-fields Comma-separated additional field names to exclude.
   *
   * @usage drush gqcc:inspect
   *   List all node bundles and their extra fields.
   * @usage drush gqcc:inspect --bundles=platform,service
   *   Inspect only the 'platform' and 'service' bundles.
   */
  public function inspect(array $options = ['bundles' => '', 'skip-fields' => '']): void {
    $only = $this->parseList($options['bundles'] ?? '');
    $skip = $this->parseList($options['skip-fields'] ?? '');

    $bundleInfo = $this->inspector()->getBundles($only);

    if (empty($bundleInfo)) {
      $this->logger()->warning('No matching bundles found.');
      return;
    }

    foreach ($bundleInfo as $bundle => $info) {
      $gqlType = $this->inspector()->getGraphQlTypeName($bundle);
      $tsType = $this->inspector()->getTsTypeName($bundle);
      $label = (string) ($info['label'] ?? $bundle);

      $this->output()->writeln(sprintf(
        "\n┌─ <info>%s</info> (%s)",
        $label,
        $bundle
      ));
      $this->output()->writeln(sprintf(
        "│  GQL: <comment>%s</comment>    TS: <comment>%s</comment>",
        $gqlType,
        $tsType
      ));
      $this->output()->writeln("│");

      $fields = $this->inspector()->getFieldsForBundle($bundle, $skip);

      if (empty($fields)) {
        $this->output()->writeln("│  (no extra fields — only base type fields)");
      }
      else {
        foreach ($fields as $fieldName => $field) {
          $req = $field['required'] ? '  ' : '? ';
          $this->output()->writeln(sprintf(
            "│  %-45s <info>%s</info>%s  # %s (%s)",
            $field['gql_name'] . $req,
            $field['ts_type'],
            str_repeat(' ', max(0, 30 - strlen($field['ts_type']))),
            $fieldName,
            $field['drupal_type']
          ));
        }
      }

      $this->output()->writeln("└──");
    }

    $this->output()->writeln('');
    $this->output()->writeln(sprintf(
      "  %d bundle(s) inspected. Run <comment>drush gqcc:generate</comment> to scaffold Next.js artefacts.",
      count($bundleInfo)
    ));
  }

  // ---------------------------------------------------------------------------
  // gqcc:generate
  // ---------------------------------------------------------------------------

  /**
   * Generate TypeScript/GraphQL scaffolding from the Drupal content model.
   *
   * Produces four artefacts per run:
   *   • types.generated.d.ts              — TypeScript type definitions
   *   • fragments.generated.ts            — GraphQL inline fragments
   *   • node-renderer-cases.generated.tsx — NodeRenderer switch-case stubs
   *   • components/{Name}.generated.tsx   — One React stub per bundle
   *
   * Without --output-dir the artefacts are printed to stdout (useful for
   * quick review or piping to a file). With --output-dir they are written
   * under {output-dir}/generated/ as separate files.
   *
   * @command graphql-compose-codegen:generate
   * @aliases gqcc:generate,gqcc:gen
   *
   * @option bundles     Comma-separated bundle IDs to generate for (omit for all bundles).
   * @option output-dir  Path to Next.js project root. Artefacts written to
   *                     {output-dir}/generated/. Omit to print to stdout.
   * @option overwrite   Overwrite existing generated files.
   * @option skip-fields Comma-separated extra field names to exclude.
   *
   * @usage drush gqcc:generate
   *   Print scaffold for all bundles to stdout.
   * @usage drush gqcc:generate --bundles=newsletter --output-dir=../ui
   *   Write scaffold for the 'newsletter' bundle to ../ui/generated/.
   * @usage drush gqcc:generate --output-dir=../ui --overwrite
   *   Regenerate all scaffold files even if they already exist.
   */
  public function generate(array $options = [
    'bundles' => '',
    'output-dir' => '',
    'overwrite' => FALSE,
    'skip-fields' => '',
  ]): void {
    $only = $this->parseList($options['bundles'] ?? '');
    $skip = $this->parseList($options['skip-fields'] ?? '');
    $outputDir = rtrim((string) ($options['output-dir'] ?? ''), '/');
    $overwrite = (bool) $options['overwrite'];

    // If no --output-dir on CLI, check module config for a default.
    if (!$outputDir) {
      $cfg = \Drupal::config('graphql_compose_codegen.settings');
      $outputDir = rtrim((string) ($cfg->get('output_dir') ?? ''), '/');
    }

    // Resolve a relative path against Drupal root.
    if ($outputDir && !str_starts_with($outputDir, '/')) {
      $outputDir = DRUPAL_ROOT . '/' . $outputDir;
    }

    $bundleInfo = $this->inspector()->getBundles($only);
    if (empty($bundleInfo)) {
      $this->logger()->warning('No matching bundles found.');
      return;
    }

    // Build the artefact set.
    $artefacts = [
      'types.generated.d.ts' => $this->generator()->generateTypeDefinitions($only, $skip),
      'fragments.generated.ts' => $this->generator()->generateFragments($only, $skip),
      'node-renderer-cases.generated.tsx' => $this->generator()->generateRendererCases($only),
    ];
    foreach (array_keys($bundleInfo) as $bundle) {
      $component = str_replace('Drupal', '', $this->inspector()->getTsTypeName($bundle));
      $artefacts["components/{$component}.generated.tsx"] = $this->generator()->generateComponentStub($bundle);
    }

    // --- stdout mode ---
    if (!$outputDir) {
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
      return;
    }

    // --- file write mode ---
    $genDir = $outputDir . '/generated';
    foreach ($artefacts as $relPath => $content) {
      $absPath = $genDir . '/' . $relPath;
      $dir = dirname($absPath);

      if (!is_dir($dir) && !mkdir($dir, 0755, TRUE) && !is_dir($dir)) {
        $this->logger()->error("Could not create directory: {$dir}");
        continue;
      }

      if (file_exists($absPath) && !$overwrite) {
        $this->logger()->warning("Skipped (exists): {$relPath}  — use --overwrite to regenerate.");
        continue;
      }

      if (file_put_contents($absPath, $content . "\n") === FALSE) {
        $this->logger()->error("Failed to write: {$absPath}");
        continue;
      }

      $this->logger()->success("Written: {$relPath}");
    }

    $this->output()->writeln('');
    $this->output()->writeln("✓ Scaffold written to: <info>{$genDir}</info>");
    $this->output()->writeln("  Review each file, then integrate into your Next.js project:");
    $this->output()->writeln("    types.generated.d.ts          → merge into types/index.d.ts");
    $this->output()->writeln("    fragments.generated.ts        → merge into lib/queries/node-by-path.ts");
    $this->output()->writeln("    node-renderer-cases.generated → merge into components/drupal/NodeRenderer.tsx");
    $this->output()->writeln("    components/*.generated.tsx    → rename + move to components/drupal/");
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Splits a comma-separated option string into a filtered array.
   */
  private function parseList(string $csv): array {
    return array_filter(array_map('trim', explode(',', $csv)));
  }

}
