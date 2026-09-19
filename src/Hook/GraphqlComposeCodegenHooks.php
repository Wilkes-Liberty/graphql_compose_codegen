<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\field\FieldConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Hook implementations for GraphQL Compose Codegen.
 */
final class GraphqlComposeCodegenHooks {

  use StringTranslationTrait;

  /**
   * Constructs the hook service.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The module logger.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle information service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(
    private readonly LoggerInterface $logger,
    TranslationInterface $string_translation,
    private readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    $this->setStringTranslation($string_translation);
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name !== 'help.page.graphql_compose_codegen') {
      return NULL;
    }

    $output = '';
    $output .= '<h2>' . $this->t('About') . '</h2>';
    $output .= '<p>' . $this->t('GraphQL Compose Codegen is a Drush-powered TypeScript and GraphQL scaffold generator for Next.js frontends driven by the GraphQL Compose module.') . '</p>';
    $output .= '<h2>' . $this->t('Commands') . '</h2>';
    $output .= '<dl>';
    $output .= '<dt><code>drush gqcc:inspect</code></dt>';
    $output .= '<dd>' . $this->t('List every node and paragraph bundle and its extra (non-base-type) fields.') . '</dd>';
    $output .= '<dt><code>drush gqcc:generate</code></dt>';
    $output .= '<dd>' . $this->t('Write scaffold artefacts (TypeScript types, GraphQL fragments, NodeRenderer and ParagraphRenderer cases, per-bundle component stubs) to a Next.js project.') . '</dd>';
    $output .= '<dt><code>drush gqcc:diff</code></dt>';
    $output .= '<dd>' . $this->t('Compare the current schema against the last generation snapshot.') . '</dd>';
    $output .= '<dt><code>drush gqcc:validate</code></dt>';
    $output .= '<dd>' . $this->t('Verify that scaffold files on disk are in sync with the live schema.') . '</dd>';
    $output .= '</dl>';
    $output .= '<p>' . $this->t('Configure default behaviour at <a href=":url">Configuration → Development → GraphQL Compose Codegen</a>.', [
      ':url' => '/admin/config/development/graphql-compose-codegen',
    ]) . '</p>';

    return $output;
  }

  /**
   * Implements hook_runtime_requirements().
   *
   * @return array<string, array<string, mixed>>
   *   The module's runtime requirements.
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $requirements = [];
    $warningSeverity = DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      '11.2.0',
      static fn(): RequirementSeverity => RequirementSeverity::Warning,
      static fn(): int => REQUIREMENT_WARNING,
    );

    $nodeBundles = $this->entityTypeBundleInfo->getBundleInfo('node');
    if (empty($nodeBundles)) {
      $requirements['graphql_compose_codegen_bundles'] = [
        'title' => $this->t('GraphQL Compose Codegen'),
        'value' => $this->t('No node bundles found'),
        'description' => $this->t('Create at least one node bundle to use graphql_compose_codegen.'),
        'severity' => $warningSeverity,
      ];
    }

    // Verify configured base_type_fields actually exist on at least one node
    // bundle. Stale entries are non-fatal but worth flagging.
    $config = $this->configFactory->get('graphql_compose_codegen.settings');
    $configured = (array) ($config->get('base_type_fields') ?? []);
    if (!empty($nodeBundles) && !empty($configured)) {
      $seen = [];
      foreach (array_keys($nodeBundles) as $bundle) {
        $fieldDefinitions = $this->entityFieldManager
          ->getFieldDefinitions('node', $bundle);
        foreach (array_keys($fieldDefinitions) as $name) {
          $seen[$name] = TRUE;
        }
      }

      $stale = array_values(array_diff($configured, array_keys($seen)));
      if ($stale) {
        $requirements['graphql_compose_codegen_stale_base_fields'] = [
          'title' => $this->t('GraphQL Compose Codegen — stale base type fields'),
          'value' => $this->t('@count stale entries', [
            '@count' => count($stale),
          ]),
          'description' => $this->t('These fields are listed in <code>base_type_fields</code> but do not exist on any node bundle: @list', [
            '@list' => implode(', ', $stale),
          ]),
          'severity' => $warningSeverity,
        ];
      }
    }

    return $requirements;
  }

  /**
   * Implements hook_entity_bundle_create().
   */
  #[Hook('entity_bundle_create')]
  public function entityBundleCreate(string $entity_type_id, string $bundle): void {
    if (!in_array($entity_type_id, ['node', 'paragraph'], TRUE)) {
      return;
    }

    // --overwrite is needed because the shared types/fragments files already
    // exist after the first generate run and would otherwise be skipped.
    $tip = 'drush gqcc:generate --bundles=' . $bundle . ' --output-dir=/path/to/nextjs --overwrite';
    $this->logger->notice(
      'New @type bundle "@bundle" created. Run <code>@tip</code> to scaffold TypeScript types, GraphQL fragments, and a component stub.',
      [
        '@type' => $entity_type_id,
        '@bundle' => $bundle,
        '@tip' => $tip,
      ],
    );
  }

  /**
   * Implements hook_entity_bundle_delete().
   */
  #[Hook('entity_bundle_delete')]
  public function entityBundleDelete(string $entity_type_id, string $bundle): void {
    if (!in_array($entity_type_id, ['node', 'paragraph'], TRUE)) {
      return;
    }

    $this->logger->warning(
      'The @type bundle "@bundle" was deleted. Manually remove the corresponding TypeScript type, the inline fragment, and the @renderer case from your Next.js project.',
      [
        '@type' => $entity_type_id,
        '@bundle' => $bundle,
        '@renderer' => $entity_type_id === 'node' ? 'NodeRenderer' : 'ParagraphRenderer',
      ],
    );
  }

  /**
   * Implements hook_field_config_insert().
   */
  #[Hook('field_config_insert')]
  public function fieldConfigInsert(FieldConfigInterface $field_config): void {
    $entity_type_id = $field_config->getTargetEntityTypeId();
    if (!in_array($entity_type_id, ['node', 'paragraph'], TRUE)) {
      return;
    }

    $bundle = $field_config->getTargetBundle();
    $type = $field_config->getFieldStorageDefinition()->getType();
    $this->logger->notice(
      'New field "@field" (@field_type) added to @entity_type bundle "@bundle". Run <code>drush gqcc:generate --bundles=@bundle --output-dir=/path/to/nextjs --overwrite</code> to update scaffolding.',
      [
        '@field' => $field_config->getName(),
        '@field_type' => $type,
        '@entity_type' => $entity_type_id,
        '@bundle' => $bundle,
      ],
    );
  }

  /**
   * Implements hook_field_config_delete().
   */
  #[Hook('field_config_delete')]
  public function fieldConfigDelete(FieldConfigInterface $field_config): void {
    $entity_type_id = $field_config->getTargetEntityTypeId();
    if (!in_array($entity_type_id, ['node', 'paragraph'], TRUE)) {
      return;
    }

    $bundle = $field_config->getTargetBundle();
    $this->logger->warning(
      'Field "@field" removed from @entity_type bundle "@bundle". Manually remove references from TypeScript types and GraphQL fragments in your Next.js project.',
      [
        '@field' => $field_config->getName(),
        '@entity_type' => $entity_type_id,
        '@bundle' => $bundle,
      ],
    );
  }

}
