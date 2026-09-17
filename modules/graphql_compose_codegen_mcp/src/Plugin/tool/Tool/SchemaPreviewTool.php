<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen_mcp\Plugin\tool\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Return scaffold artefacts without writing files or updating the baseline.
 */
#[Tool(
  id: 'graphql_compose_codegen_preview',
  label: new TranslatableMarkup('Preview scaffold'),
  description: new TranslatableMarkup('Return scaffold artefacts without writing files or updating the baseline.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'bundles' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundles'),
      description: new TranslatableMarkup('Up to 64 bundle IDs. Empty selects all enabled bundles.'),
      required: FALSE,
      multiple: TRUE,
      default_value: [],
    ),
    'skip_fields' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Skipped fields'),
      description: new TranslatableMarkup('Up to 256 additional field IDs to omit.'),
      required: FALSE,
      multiple: TRUE,
      default_value: [],
    ),
  ],
)]
final class SchemaPreviewTool extends SchemaToolBase {

  protected const OPERATION = 'preview';

}
