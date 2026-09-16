<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen_mcp\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\node\Entity\NodeType;
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
    $this->installConfig(['system', 'node', 'user', 'mcp_sentinel', 'graphql_compose_codegen']);
    $role = Role::load('mcp_api') ?? Role::create(['id' => 'mcp_api', 'label' => 'MCP API']);
    $role->grantPermission('access mcp sentinel context')
      ->grantPermission('administer graphql_compose_codegen')->save();
    $this->config('mcp_sentinel.settings')->set('governed_role_fallback', TRUE)->save();
    $this->config('mcp_sentinel.mcp_policy_profile.default')->set('allow_config_read', TRUE)->save();
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
      $tool = $this->container->get('plugin.manager.tool')->createInstance('graphql_compose_codegen_' . $operation);
      $tool->setInputValue('bundles', ['demo']);
      self::assertTrue($tool->access());
      $tool->execute();
      self::assertTrue($tool->getResultStatus(), (string) $tool->getResultMessage());
      self::assertNotEmpty($tool->getResult()->getContextValues());
      $this->container->get('current_user')->setAccount(new AnonymousUserSession());
      self::assertFalse($tool->access());
      $tool->execute();
      self::assertFalse($tool->getResultStatus());
      self::assertEmpty($tool->getResult()->getContextValues());
    }
  }

  /**
   * Neither disabled auditing nor config restrictions permit direct execution.
   */
  public function testGovernanceChangesRefuseDirectExecution(): void {
    $tool = $this->container->get('plugin.manager.tool')->createInstance('graphql_compose_codegen_preview');
    $tool->setInputValue('bundles', ['demo']);
    $this->config('mcp_sentinel.settings')->set('audit_enabled', FALSE)->save();
    self::assertFalse($tool->access());
    $tool->execute();
    self::assertFalse($tool->getResultStatus());
    $this->config('mcp_sentinel.settings')->set('audit_enabled', TRUE)->save();
    $this->config('mcp_sentinel.mcp_policy_profile.default')->set('denied_config_types', ['field.field.node.demo'])->save();
    $this->container->get('entity_type.manager')->getStorage('mcp_policy_profile')->resetCache();
    self::assertFalse($tool->access());
    $tool->execute();
    self::assertFalse($tool->getResultStatus());
  }

  /**
   * Invalid inputs return a failure without echoing caller values.
   */
  public function testInvalidSelectorsDoNotLeakIntoFailures(): void {
    $tool = $this->container->get('plugin.manager.tool')->createInstance('graphql_compose_codegen_preview');
    $tool->setInputValue('bundles', ['not-a-machine-name']);
    $tool->execute();
    self::assertFalse($tool->getResultStatus());
    self::assertStringNotContainsString('not-a-machine-name', (string) $tool->getResultMessage());
    self::assertEmpty($tool->getResult()->getContextValues());
  }

}
