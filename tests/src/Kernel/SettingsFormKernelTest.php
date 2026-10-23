<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\graphql_compose_codegen\Form\SettingsForm;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the configure: settings form.
 *
 * The admin UI had no kernel or functional coverage. These cases
 * instantiate the form the same way the route does (class resolver →
 * create()) so a missing ConfigFormBase parent::__construct() fails
 * here instead of only at Drupal 11.2+ deprecation time.
 *
 * @runTestsInSeparateProcesses
 *
 * @group graphql_compose_codegen
 */
#[Group('graphql_compose_codegen')]
#[RunTestsInSeparateProcesses]
final class SettingsFormKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'graphql_compose_codegen',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['node', 'graphql_compose_codegen']);
    NodeType::create(['type' => 'demo', 'name' => 'Demo'])->save();
  }

  /**
   * Class resolver injection stores the typed config manager and saves.
   */
  public function testClassResolverWiresParentAndSubmitPersists(): void {
    $form = $this->settingsForm();
    $this->assertTypedConfigWired($form);

    $form_state = $this->submitSettings($form, [
      'base_ts_type' => 'SharedFields',
      'base_type_fields' => "title\npath",
      'output_dir' => 'frontend/generated',
    ]);

    self::assertFalse($form_state->hasAnyErrors(), print_r($form_state->getErrors(), TRUE));

    $config = $this->config('graphql_compose_codegen.settings');
    self::assertSame('SharedFields', $config->get('base_ts_type'));
    self::assertSame(['title', 'path'], $config->get('base_type_fields'));
    self::assertSame('frontend/generated', $config->get('output_dir'));
  }

  /**
   * A non-identifier base TS type must not write config.
   */
  public function testInvalidBaseTsTypeIsRejected(): void {
    $form_state = $this->submitSettings($this->settingsForm(), [
      // Leading digit is valid in a machine name, not a TS identifier.
      'base_ts_type' => '1Invalid',
      'base_type_fields' => 'title',
      'output_dir' => '',
    ]);

    self::assertTrue($form_state->hasAnyErrors());
    self::assertArrayHasKey('base_ts_type', $form_state->getErrors());

    // Fail-closed: install defaults must stay put when validation fails.
    $config = $this->config('graphql_compose_codegen.settings');
    self::assertSame('NodeCommonFields', $config->get('base_ts_type'));
    self::assertSame(['title', 'path', 'body'], $config->get('base_type_fields'));
    self::assertSame('', $config->get('output_dir'));
  }

  /**
   * Unknown field names warn but are still stored (soft-validate).
   */
  public function testUnknownBaseTypeFieldsWarnAndStillSave(): void {
    $form_state = $this->submitSettings($this->settingsForm(), [
      'base_ts_type' => 'NodeCommonFields',
      // Title is a node base field; the other name is not on any bundle.
      'base_type_fields' => "title\nfield_does_not_exist",
      'output_dir' => '',
    ]);

    self::assertFalse($form_state->hasAnyErrors(), print_r($form_state->getErrors(), TRUE));
    self::assertSame(
      ['title', 'field_does_not_exist'],
      $this->config('graphql_compose_codegen.settings')->get('base_type_fields'),
    );

    $warnings = \Drupal::messenger()->messagesByType(MessengerInterface::TYPE_WARNING);
    self::assertNotEmpty($warnings);
    self::assertStringContainsString('field_does_not_exist', (string) reset($warnings));
  }

  /**
   * Instantiates the form through the class resolver (the route path).
   */
  private function settingsForm(): SettingsForm {
    $form = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(SettingsForm::class);
    self::assertInstanceOf(SettingsForm::class, $form);
    return $form;
  }

  /**
   * Submits programmed values through Form API.
   *
   * @param \Drupal\graphql_compose_codegen\Form\SettingsForm $form
   *   The resolved form object.
   * @param array<string, string> $values
   *   Raw form values (textarea fields stay as strings).
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state after programmed submit.
   */
  private function submitSettings(SettingsForm $form, array $values): FormStateInterface {
    $form_state = new FormState();
    $form_state->setValues($values);
    $this->container->get('form_builder')->submitForm($form, $form_state);
    return $form_state;
  }

  /**
   * Asserts parent::__construct() stored the typed config manager.
   *
   * The lazy typedConfigManager() fallback is deprecated on Drupal
   * 11.2+. A create() that never calls the parent leaves this unset.
   */
  private function assertTypedConfigWired(SettingsForm $form): void {
    $property = new \ReflectionProperty(ConfigFormBase::class, 'typedConfigManager');
    $property->setAccessible(TRUE);
    self::assertInstanceOf(
      TypedConfigManagerInterface::class,
      $property->getValue($form),
    );
  }

}
