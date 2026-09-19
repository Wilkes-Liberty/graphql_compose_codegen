<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_codegen_mcp\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\graphql_compose_codegen\Service\SchemaPreview;
use Drupal\mcp_sentinel\McpPolicyProfileInterface;
use Drupal\mcp_sentinel\Plugin\tool\Tool\McpEntityToolTrait;
use Drupal\mcp_sentinel\Plugin\tool\Tool\McpGovernedToolBase;
use Drupal\mcp_sentinel\Service\McpExfiltrationGuard;
use Drupal\mcp_sentinel\Tool\ConfigScopeToolInterface;
use Drupal\tool\ExecutableResult;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shares source access checks for Codegen's read-only configuration tools.
 */
abstract class SchemaToolBase extends McpGovernedToolBase implements ConfigScopeToolInterface {

  use McpEntityToolTrait;

  protected const OPERATION = '';

  /**
   * Bounded read-only schema facade.
   */
  protected SchemaPreview $preview;

  /**
   * MCP Sentinel response-size cap.
   */
  protected McpExfiltrationGuard $exfiltrationGuard;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->preview = $container->get('graphql_compose_codegen.schema_preview');
    $instance->exfiltrationGuard = $container->get('mcp_sentinel.exfiltration_guard');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkGovernedDiscoveryAccess(AccountInterface $account): AccessResultInterface {
    return $this->checkGovernedAccess([], $account);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkGovernedAccess(array $values, AccountInterface $account): AccessResultInterface {
    $access = AccessResult::allowedIfHasPermission($account, 'administer graphql_compose_codegen');
    $profile = $this->governancePolicyResolver?->resolve($account);
    if (!$profile || !$profile->allowsConfigRead()) {
      return AccessResult::forbidden('Schema configuration read is not allowed.')->setCacheMaxAge(0);
    }
    // Alias generation reads across bundles. A partial configuration view
    // would produce an incorrect contract, so refuse rather than redact it.
    $prefixes = ['graphql_compose', 'field.field.', 'field.storage.', 'node.type.', 'paragraphs.paragraphs_type.'];
    foreach ($profile->getDeniedConfigTypes() as $denied) {
      foreach ($prefixes as $prefix) {
        if ($denied !== '' && (str_starts_with($prefix, $denied) || str_starts_with($denied, $prefix))) {
          return AccessResult::forbidden('Schema dependencies are restricted by policy.')->setCacheMaxAge(0);
        }
      }
    }
    return $access->setCacheMaxAge(0);
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    try {
      // ToolBase::execute() does not call access(). Recheck for PHP callers.
      if (!$this->checkAccess($values, $this->currentUser)) {
        return ExecutableResult::failure($this->t('Schema access refused.'));
      }
      if (array_diff(array_keys($values), ['bundles', 'skip_fields'])) {
        return ExecutableResult::failure($this->t('Unsupported schema input.'));
      }
      $profile = $this->governancePolicyResolver->resolve($this->currentUser);
      if ($profile === NULL) {
        return ExecutableResult::failure($this->t('Schema access refused.'));
      }
      if ($limited = $this->checkRateLimit($profile, $this->getPluginId())) {
        return $limited;
      }
      $result = $this->preview->run(static::OPERATION, $values['bundles'] ?? [], $values['skip_fields'] ?? []);
      $serialized = json_encode($result, JSON_THROW_ON_ERROR);
      if ($limited = $this->refuseOversizedResponse($serialized, $profile)) {
        return $limited;
      }
      return ExecutableResult::success($this->t('Schema operation completed.'), $result);
    }
    catch (\Throwable $exception) {
      // Record diagnostics without schema values or exception messages.
      $this->logger->warning('Codegen tool failed with @type at @source:@line.', [
        '@type' => get_class($exception),
        '@source' => basename($exception->getFile()),
        '@line' => $exception->getLine(),
      ]);
      // Do not relay mapper errors, schema values, or filesystem paths.
      return ExecutableResult::failure($this->t('Schema operation refused. Check selectors and schema limits.'));
    }
  }

  /**
   * Refuses a payload that exceeds the profile's response-size cap.
   *
   * Calls the exfiltration guard directly. MCP Sentinel 2.22.1 removed
   * McpEntityToolTrait::checkResponseSizeCap(); the guard service remains.
   *
   * @param string $serialized
   *   JSON-encoded tool result.
   * @param \Drupal\mcp_sentinel\McpPolicyProfileInterface $profile
   *   Active governance profile.
   *
   * @return \Drupal\tool\ExecutableResult|null
   *   Failure result when over cap, NULL when within limits.
   */
  private function refuseOversizedResponse(string $serialized, McpPolicyProfileInterface $profile): ?ExecutableResult {
    $bytes = strlen($serialized);
    if ($this->exfiltrationGuard->exceedsResponseSizeCap($bytes, $profile)) {
      return ExecutableResult::failure(
        $this->t(
          'Response size @bytes bytes exceeds the MCP Sentinel cap of @cap bytes for this profile. Narrow your query.',
          [
            '@bytes' => $bytes,
            '@cap' => $this->exfiltrationGuard->effectiveResponseSizeCap($profile),
          ]
        )
      );
    }
    return NULL;
  }

}
