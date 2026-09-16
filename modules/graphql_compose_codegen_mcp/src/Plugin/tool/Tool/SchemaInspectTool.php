<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen_mcp\Plugin\tool\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Inspect node and paragraph schema fields without reading content.
 */
#[Tool(
  id: 'graphql_compose_codegen_inspect',
  label: new TranslatableMarkup('Inspect schema'),
  description: new TranslatableMarkup('Inspect node and paragraph schema fields without reading content.'),
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
final class SchemaInspectTool extends SchemaToolBase {

  protected const OPERATION = 'inspect';

}
