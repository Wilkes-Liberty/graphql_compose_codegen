<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Requirements hook implementations for graphql_compose_codegen.
 */
class RequirementsHooks {

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $requirements = [];

    /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo */
    $bundleInfo = \Drupal::service('entity_type.bundle.info');
    $nodeBundles = $bundleInfo->getBundleInfo('node');

    if (empty($nodeBundles)) {
      $requirements['graphql_compose_codegen_bundles'] = [
        'title' => t('GraphQL Compose Codegen'),
        'value' => t('No node bundles found'),
        'description' => t('Create at least one node bundle to use graphql_compose_codegen.'),
        'severity' => REQUIREMENT_WARNING,
      ];
    }

    // Verify configured base_type_fields actually exist on at least one node
    // bundle. Stale entries are non-fatal but worth flagging.
    $config = \Drupal::config('graphql_compose_codegen.settings');
    $configured = (array) ($config->get('base_type_fields') ?? []);
    if (!empty($nodeBundles) && !empty($configured)) {
      /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager */
      $fieldManager = \Drupal::service('entity_field.manager');
      $seen = [];
      foreach (array_keys($nodeBundles) as $bundle) {
        foreach (array_keys($fieldManager->getFieldDefinitions('node', $bundle)) as $name) {
          $seen[$name] = TRUE;
        }
      }
      $stale = array_values(array_diff($configured, array_keys($seen)));
      if ($stale) {
        $requirements['graphql_compose_codegen_stale_base_fields'] = [
          'title' => t('GraphQL Compose Codegen — stale base type fields'),
          'value' => t('@count stale entries', ['@count' => count($stale)]),
          'description' => t('These fields are listed in <code>base_type_fields</code> but do not exist on any node bundle: @list', [
            '@list' => implode(', ', $stale),
          ]),
          'severity' => REQUIREMENT_WARNING,
        ];
      }
    }

    return $requirements;
  }

}
