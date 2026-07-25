<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for graphql_compose_codegen.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * Pattern matching a valid TypeScript identifier.
   */
  private const TS_IDENTIFIER_RE = '/^[A-Za-z_$][A-Za-z0-9_$]*$/';

  public function __construct(
    // Protected and mutable: FormBase carries DependencySerializationTrait,
    // which cannot restore private or readonly properties on unserialize.
    protected EntityTypeBundleInfoInterface $bundleInfo,
    protected EntityFieldManagerInterface $fieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'graphql_compose_codegen_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['graphql_compose_codegen.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('graphql_compose_codegen.settings');

    $form['base_ts_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base TypeScript type name'),
      '#description' => $this->t('Name of the shared base TS type used in generated <code>extends</code> clauses. Default: <code>NodeCommonFields</code>.'),
      '#default_value' => (string) ($config->get('base_ts_type') ?? 'NodeCommonFields'),
      '#required' => TRUE,
      '#maxlength' => 64,
    ];

    $form['base_type_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Base type fields'),
      '#description' => $this->t('Fields that live in your shared base TypeScript type. One per line. Excluded from per-bundle generated types.'),
      '#default_value' => implode("\n", (array) ($config->get('base_type_fields') ?? [])),
      '#rows' => 12,
    ];

    $form['output_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default output directory'),
      '#description' => $this->t('Default output directory for generated files. Relative to Drupal root or absolute. Can be overridden with <code>--output-dir</code> on the command line.'),
      '#default_value' => (string) ($config->get('output_dir') ?? ''),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $baseTsType = trim((string) $form_state->getValue('base_ts_type'));
    if (!preg_match(self::TS_IDENTIFIER_RE, $baseTsType)) {
      $form_state->setErrorByName(
        'base_ts_type',
        $this->t('Base TS type must be a valid TypeScript identifier (letters, digits, $, _; cannot start with a digit).')
      );
    }

    // Soft-validate base_type_fields: warn (do not fail) on unknown names.
    $fields = $this->parseFieldList((string) $form_state->getValue('base_type_fields'));
    if ($fields) {
      $known = [];
      foreach (array_keys($this->bundleInfo->getBundleInfo('node')) as $bundle) {
        foreach (array_keys($this->fieldManager->getFieldDefinitions('node', $bundle)) as $name) {
          $known[$name] = TRUE;
        }
      }
      $unknown = array_values(array_diff($fields, array_keys($known)));
      if ($unknown) {
        $this->messenger()->addWarning($this->t('These field names are not on any current node bundle: @list. They will still be saved.', [
          '@list' => implode(', ', $unknown),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('graphql_compose_codegen.settings')
      ->set('base_ts_type', trim((string) $form_state->getValue('base_ts_type')))
      ->set('base_type_fields', $this->parseFieldList((string) $form_state->getValue('base_type_fields')))
      ->set('output_dir', trim((string) $form_state->getValue('output_dir')))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Splits a textarea value into a trimmed, filtered list of field names.
   */
  private function parseFieldList(string $raw): array {
    $lines = preg_split('/\R/', $raw) ?: [];
    return array_values(array_filter(array_map('trim', $lines)));
  }

}
