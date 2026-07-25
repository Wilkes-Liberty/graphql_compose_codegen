<?php

/**
 * @file
 * Hooks and extension points for graphql_compose_codegen.
 */

declare(strict_types=1);

/**
 * @addtogroup hooks
 * @{
 */

/**
 * React before scaffold artefacts are generated.
 *
 * @param array $context
 *   Keyed:
 *   - bundles: list of node bundle IDs being processed.
 *   - paragraph_bundles: list of paragraph bundle IDs being processed
 *     (empty when the paragraphs module is not installed or no paragraph
 *     bundle matches the --bundles filter).
 *   - output_dir: absolute output directory, or empty string for stdout mode.
 *   - overwrite: whether existing files will be overwritten.
 *   - dry_run: whether this is a preview-only run.
 */
function hook_graphql_compose_codegen_pre_generate(array $context): void {
  \Drupal::logger('my_module')->info('Codegen starting for @count bundles.', [
    '@count' => count($context['bundles']),
  ]);
}

/**
 * React after scaffold artefacts are generated.
 *
 * @param array $context
 *   Same as pre_generate, plus:
 *   - artefacts: relative path → file content for every artefact produced.
 */
function hook_graphql_compose_codegen_post_generate(array $context): void {
  // Example: regenerate downstream OpenAPI spec.
}

/**
 * Alter discovered field-type mapper plugin definitions.
 *
 * @param array $definitions
 *   Plugin ID → definition. Each definition has 'drupalTypes' (array of
 *   Drupal field type plugin IDs claimed by the mapper).
 */
function hook_graphql_compose_codegen_field_type_mappers_alter(array &$definitions): void {
  // Example: take 'string_long' away from TextMapper and route it elsewhere.
  if (isset($definitions['text'])) {
    $definitions['text']['drupalTypes'] = array_diff(
      $definitions['text']['drupalTypes'],
      ['string_long']
    );
  }
}

/**
 * @} End of "addtogroup hooks".
 */
