<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen_mcp\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\graphql_compose_codegen\Service\SchemaPreview;
use Drupal\mcp_sentinel\Tool\ConfigScopeToolInterface;
use Drupal\mcp_sentinel\Tool\McpToolScopeResolver;
use Drupal\node\Entity\NodeType;
use Drupal\tool\Tool\ToolDefinition;
use Drupal\tool\Tool\ToolInterface;
use Drupal\tool\Tool\ToolOperation;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Exercises discovery and direct execution against source governance.
 *
 * @group graphql_compose_codegen
 *
 * @runTestsInSeparateProcesses
 */
#[Group('graphql_compose_codegen')]
#[RunTestsInSeparateProcesses]
final class SchemaToolsKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Plugin IDs owned by the optional integration.
   */
  private const TOOL_IDS = [
    'graphql_compose_codegen_inspect',
    'graphql_compose_codegen_diff',
    'graphql_compose_codegen_preview',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'filter', 'text', 'file', 'node',
    'serialization', 'jsonapi', 'tool', 'key', 'image', 'options',
    'path_alias', 'consumers', 'simple_oauth', 'encrypt', 'audit_chain',
    'mcp_sentinel', 'graphql_compose_codegen', 'graphql_compose_codegen_mcp',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('audit_chain', ['audit_chain_log', 'audit_chain_mutex']);
    $this->container->get('database')->insert('audit_chain_mutex')
      ->fields(['id' => 1, 'locked' => 1])->execute();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig([
      'system',
      'node',
      'user',
      'mcp_sentinel',
      'graphql_compose_codegen',
    ]);
    $role = Role::load('mcp_api') ?? Role::create([
      'id' => 'mcp_api',
      'label' => 'MCP API',
    ]);
    $role->grantPermission('access mcp sentinel context')
      ->grantPermission('administer graphql_compose_codegen')->save();
    $this->config('mcp_sentinel.settings')
      ->set('governed_role_fallback', TRUE)->save();
    $this->config('mcp_sentinel.mcp_policy_profile.default')
      ->set('allow_config_read', TRUE)->save();
    NodeType::create(['type' => 'demo', 'name' => 'Demo'])->save();
    $this->setUpCurrentUser(['roles' => ['mcp_api']]);
  }

  /**
   * All three tools execute the real facade and refuse anonymous execution.
   */
  public function testGovernedToolsAndAnonymousDenial(): void {
    $account = $this->container->get('current_user')->getAccount();
    foreach (['inspect', 'diff', 'preview'] as $operation) {
      $this->container->get('current_user')->setAccount($account);
      $tool = $this->tool($operation);
      self::assertTrue($tool->discoveryAccess($account)->isAllowed());
      $tool->setInputValue('bundles', ['demo']);
      self::assertTrue($tool->access());
      $tool->execute();
      self::assertTrue(
        $tool->getResultStatus(),
        (string) $tool->getResultMessage(),
      );
      self::assertNotEmpty($tool->getResult()->getContextValues());
      $anonymous = new AnonymousUserSession();
      $this->container->get('current_user')->setAccount($anonymous);
      self::assertFalse($tool->discoveryAccess($anonymous)->isAllowed());
      self::assertFalse($tool->access());
      $tool->execute();
      self::assertFalse($tool->getResultStatus());
      self::assertEmpty($tool->getResult()->getContextValues());
    }
  }

  /**
   * Content-tier scope and missing codegen permission refuse the tools.
   */
  public function testWrongScopeAndPermissionRefuseDiscoveryAndExecution(): void {
    foreach (self::TOOL_IDS as $plugin_id) {
      $tool = $this->container->get('plugin.manager.tool')
        ->createInstance($plugin_id);
      self::assertInstanceOf(ConfigScopeToolInterface::class, $tool);
      $definition = $tool->getPluginDefinition();
      self::assertInstanceOf(ToolDefinition::class, $definition);
      self::assertSame(ToolOperation::Read, $definition->getOperation());
      self::assertSame(
        'mcp_config_read',
        McpToolScopeResolver::resolveDefinition($definition),
      );
      self::assertNotSame(
        'mcp_read',
        McpToolScopeResolver::resolveDefinition($definition),
      );
    }

    $account = $this->createUser(['access mcp sentinel context']);
    $this->container->get('current_user')->setAccount($account);
    $tool = $this->tool('preview');
    $tool->setInputValue('bundles', ['demo']);
    self::assertFalse($tool->discoveryAccess($account)->isAllowed());
    self::assertFalse($tool->access());
    $tool->execute();
    self::assertFalse($tool->getResultStatus());
    self::assertEmpty($tool->getResult()->getContextValues());
  }

  /**
   * Neither disabled auditing nor config restrictions permit direct execution.
   */
  public function testGovernanceChangesRefuseDirectExecution(): void {
    $tool = $this->tool('preview');
    $tool->setInputValue('bundles', ['demo']);
    $this->config('mcp_sentinel.settings')->set('audit_enabled', FALSE)->save();
    $account = $this->container->get('current_user');
    self::assertFalse($tool->discoveryAccess($account)->isAllowed());
    self::assertFalse($tool->access());
    $tool->execute();
    self::assertFalse($tool->getResultStatus());
    $this->config('mcp_sentinel.settings')->set('audit_enabled', TRUE)->save();
    $this->config('mcp_sentinel.mcp_policy_profile.default')
      ->set('denied_config_types', ['field.field.node.demo'])->save();
    $this->container->get('entity_type.manager')
      ->getStorage('mcp_policy_profile')->resetCache();
    self::assertFalse($tool->discoveryAccess($account)->isAllowed());
    self::assertFalse($tool->access());
    $tool->execute();
    self::assertFalse($tool->getResultStatus());
  }

  /**
   * Invalid or oversized inputs fail without echoing caller values.
   */
  public function testMalformedAndOversizedSelectorsAreRefused(): void {
    $tool = $this->tool('preview');
    $oversized = [];
    for ($index = 0; $index < SchemaPreview::MAX_BUNDLES + 1; $index++) {
      $oversized[] = 'bundle_' . $index;
    }
    foreach ([
      ['not-a-machine-name'],
      ['../demo'],
      ['demo', 'demo'],
      ['unknown'],
      $oversized,
    ] as $selectors) {
      $tool->setInputValue('bundles', $selectors);
      $tool->execute();
      self::assertFalse($tool->getResultStatus());
      $message = (string) $tool->getResultMessage();
      foreach ($selectors as $selector) {
        self::assertStringNotContainsString((string) $selector, $message);
      }
      self::assertEmpty($tool->getResult()->getContextValues());
    }

    $this->expectException(\InvalidArgumentException::class);
    $tool->setInputValue('output_dir', '/tmp/codegen-must-not-write');
  }

  /**
   * Preview matches the generator and never writes files or the snapshot.
   */
  public function testPreviewMatchesGeneratorWithoutMutatingState(): void {
    $snapshot = $this->container->get('graphql_compose_codegen.artefact_snapshot');
    $generator = $this->container->get('graphql_compose_codegen.typescript_generator');
    $target = $this->siteDirectory . '/preview-must-not-create';
    $this->config('graphql_compose_codegen.settings')
      ->set('output_dir', $target)->save();
    $snapshot->record(['old.generated.ts' => 'old content']);
    $before = $snapshot->load();

    $preview = $this->tool('preview');
    $preview->setInputValue('bundles', ['demo']);
    $preview->execute();
    self::assertTrue(
      $preview->getResultStatus(),
      (string) $preview->getResultMessage(),
    );
    $values = $preview->getResult()->getContextValues();
    self::assertSame(
      $generator->buildArtefacts(['demo']),
      $values['artefacts'],
    );
    self::assertSame($before, $snapshot->load());
    self::assertDirectoryDoesNotExist($target);

    $diff = $this->tool('diff');
    $diff->setInputValue('bundles', ['demo']);
    $diff->execute();
    self::assertTrue(
      $diff->getResultStatus(),
      (string) $diff->getResultMessage(),
    );
    $diff_values = $diff->getResult()->getContextValues();
    self::assertTrue($diff_values['has_snapshot']);
    self::assertSame(
      ['old.generated.ts'],
      $diff_values['changes']['removed'],
    );
    self::assertSame($before, $snapshot->load());
    self::assertDirectoryDoesNotExist($target);

    $inspect = $this->tool('inspect');
    $inspect->setInputValue('bundles', ['demo']);
    $inspect->execute();
    self::assertTrue(
      $inspect->getResultStatus(),
      (string) $inspect->getResultMessage(),
    );
    $inspect_values = $inspect->getResult()->getContextValues();
    self::assertArrayHasKey('demo', $inspect_values['nodes']);
    self::assertSame($before, $snapshot->load());
  }

  /**
   * Creates one of the three schema tools.
   */
  private function tool(string $operation): ToolInterface {
    return $this->container->get('plugin.manager.tool')
      ->createInstance('graphql_compose_codegen_' . $operation);
  }

}
