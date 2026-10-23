<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose_codegen\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\graphql_compose_codegen\Drush\Commands\CodegenCommands;
use Drupal\graphql_compose_codegen\Service\ArtefactSnapshot;
use Drupal\graphql_compose_codegen\Service\TypeScriptGenerator;
use Drupal\node\Entity\NodeType;
use Drush\Log\DrushLoggerManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Kernel tests for generator output, snapshot honesty, and gqcc:validate.
 *
 * @group graphql_compose_codegen
 *
 * @runTestsInSeparateProcesses
 */
#[Group('graphql_compose_codegen')]
#[RunTestsInSeparateProcesses]
final class CodegenCommandsKernelTest extends KernelTestBase {

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

    NodeType::create([
      'type' => 'demo',
      'name' => 'Demo',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_tagline',
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_tagline',
      'entity_type' => 'node',
      'bundle' => 'demo',
      'required' => TRUE,
    ])->save();
  }

  /**
   * Tests that type definitions include the fields from the demo bundle.
   */
  public function testTypeDefinitionsIncludeBundleFields(): void {
    /** @var \Drupal\graphql_compose_codegen\Service\TypeScriptGenerator $gen */
    $gen = $this->container->get('graphql_compose_codegen.typescript_generator');
    $artefacts = $gen->buildArtefacts();
    self::assertArrayHasKey('types.generated.d.ts', $artefacts);
    $out = $artefacts['types.generated.d.ts'];

    self::assertStringContainsString('export type DrupalDemo', $out);
    self::assertStringContainsString('__typename: "NodeDemo"', $out);
    self::assertStringContainsString('tagline: string', $out);
  }

  /**
   * Tests that fragments include the spread for the demo bundle.
   */
  public function testFragmentsIncludeBundleSpread(): void {
    /** @var \Drupal\graphql_compose_codegen\Service\TypeScriptGenerator $gen */
    $gen = $this->container->get('graphql_compose_codegen.typescript_generator');
    $artefacts = $gen->buildArtefacts();
    self::assertArrayHasKey('fragments.generated.ts', $artefacts);
    $out = $artefacts['fragments.generated.ts'];

    self::assertStringContainsString('... on NodeDemo {', $out);
    self::assertStringContainsString('${COMMON_NODE_FIELDS}', $out);
    self::assertStringContainsString('tagline', $out);
  }

  /**
   * Tests that a skipped write must snapshot disk, not the desired set.
   *
   * When emitFiles() leaves existing files untouched, recording the desired
   * hashes makes gqcc:diff go green against stale scaffold.
   */
  public function testSkippedWriteSnapshotMustReflectDisk(): void {
    /** @var \Drupal\graphql_compose_codegen\Service\TypeScriptGenerator $gen */
    $gen = $this->container->get('graphql_compose_codegen.typescript_generator');
    /** @var \Drupal\graphql_compose_codegen\Service\ArtefactSnapshot $snapshot */
    $snapshot = $this->container->get('graphql_compose_codegen.artefact_snapshot');
    $artefacts = $gen->buildArtefacts(['demo']);
    self::assertNotEmpty($artefacts);

    $siteDir = $this->siteDirectory;
    if (!str_starts_with($siteDir, '/')) {
      $siteDir = DRUPAL_ROOT . '/' . $siteDir;
    }
    $genDir = $siteDir . '/gqcc-ui/generated';

    foreach (array_keys($artefacts) as $rel) {
      $abs = $genDir . '/' . $rel;
      $dir = dirname($abs);
      if (!is_dir($dir) && !mkdir($dir, 0777, TRUE) && !is_dir($dir)) {
        self::fail("Could not create {$dir}");
      }
      file_put_contents($abs, "stale scaffold\n");
    }

    // The old generate() path: snapshot the desired set after a no-op write.
    $snapshot->record($artefacts);
    $lie = $snapshot->diff($artefacts);
    self::assertSame([], $lie['added']);
    self::assertSame([], $lie['removed']);
    self::assertSame(
      [],
      $lie['changed'],
      'Recording the desired set hides skipped writes from diff.',
    );

    // generate() now records on-disk content instead.
    $snapshot->recordFromDisk($genDir, $artefacts);
    $stored = $snapshot->load();
    self::assertIsArray($stored);

    foreach (array_keys($artefacts) as $rel) {
      self::assertSame(
        sha1('stale scaffold'),
        $stored['hashes'][$rel] ?? NULL,
        "Snapshot for {$rel} must hash the stale on-disk file.",
      );
      self::assertNotSame(
        sha1($artefacts[$rel]),
        $stored['hashes'][$rel] ?? NULL,
        "Snapshot for {$rel} must not hash the desired (unwritten) set.",
      );
    }

    $diff = $snapshot->diff($artefacts);
    self::assertNotSame(
      [],
      $diff['changed'],
      'Diff must not go green when generate skipped writes.',
    );
  }

  /**
   * A skipped field must be absent from the node component stub.
   *
   * The stub lists each field in a comment. Rebuilding type entries with an
   * empty skip list would leave the field in that comment while types and
   * fragments honored --skip-fields.
   */
  public function testSkippedFieldIsAbsentFromNodeComponentStub(): void {
    /** @var \Drupal\graphql_compose_codegen\Service\TypeScriptGenerator $gen */
    $gen = $this->container->get('graphql_compose_codegen.typescript_generator');

    $present = $gen->buildArtefacts(['demo']);
    self::assertArrayHasKey('components/Demo.generated.tsx', $present);
    self::assertStringContainsString(
      '{/* tagline: string */}',
      $present['components/Demo.generated.tsx'],
    );

    $skipped = $gen->buildArtefacts(['demo'], ['field_tagline']);
    self::assertArrayHasKey('components/Demo.generated.tsx', $skipped);
    $stub = $skipped['components/Demo.generated.tsx'];
    self::assertStringNotContainsString('{/* tagline:', $stub);
    self::assertStringContainsString('{/* (no extra fields) */}', $stub);
    self::assertStringNotContainsString(
      'field_tagline',
      $skipped['types.generated.d.ts'],
    );
  }

  /**
   * A leftover file under generated/ must fail validate (not print-and-pass).
   *
   * The generate command never deletes removed artefacts. Counting only
   * missing + stale left CI green after EXTRA: lines for a deleted bundle.
   */
  public function testValidateFailsOnLeftoverArtefact(): void {
    $artefacts = $this->artefacts();
    $paths = $this->writeGenerated($artefacts);
    // Deleted-bundle leftover: not in $current, still on disk.
    $leftover = 'components/Retired.generated.tsx';
    $this->writeFile($paths['gen'] . '/' . $leftover, "// leftover stub\n");

    $report = $this->snapshot()->compareDisk($paths['gen'], $artefacts);
    self::assertSame([], $report['missing']);
    self::assertSame([], $report['stale']);
    self::assertSame([$leftover], $report['extra']);

    $result = $this->runValidate($paths['root']);
    self::assertSame(CodegenCommands::EXIT_FAILURE, $result['status']);
    self::assertStringContainsString('EXTRA:   ' . $leftover, $result['output']);
    self::assertStringNotContainsString(
      'in sync with the live schema',
      $result['logs'],
    );
  }

  /**
   * A missing expected artefact must still fail validate.
   */
  public function testValidateFailsOnMissingArtefact(): void {
    $artefacts = $this->artefacts();
    $paths = $this->writeGenerated($artefacts);
    $missing = array_key_first($artefacts);
    self::assertIsString($missing);
    self::assertTrue(unlink($paths['gen'] . '/' . $missing));

    $report = $this->snapshot()->compareDisk($paths['gen'], $artefacts);
    self::assertSame([$missing], $report['missing']);
    self::assertSame([], $report['stale']);
    self::assertSame([], $report['extra']);

    $result = $this->runValidate($paths['root']);
    self::assertSame(CodegenCommands::EXIT_FAILURE, $result['status']);
    self::assertStringContainsString('MISSING: ' . $missing, $result['output']);
    self::assertStringNotContainsString(
      'in sync with the live schema',
      $result['logs'],
    );
  }

  /**
   * A stale expected artefact must still fail validate.
   */
  public function testValidateFailsOnStaleArtefact(): void {
    $artefacts = $this->artefacts();
    $paths = $this->writeGenerated($artefacts);
    $stale = array_key_first($artefacts);
    self::assertIsString($stale);
    $this->writeFile($paths['gen'] . '/' . $stale, "stale scaffold\n");

    $report = $this->snapshot()->compareDisk($paths['gen'], $artefacts);
    self::assertSame([], $report['missing']);
    self::assertSame([$stale], $report['stale']);
    self::assertSame([], $report['extra']);

    $result = $this->runValidate($paths['root']);
    self::assertSame(CodegenCommands::EXIT_FAILURE, $result['status']);
    self::assertStringContainsString('STALE:   ' . $stale, $result['output']);
    self::assertStringNotContainsString(
      'in sync with the live schema',
      $result['logs'],
    );
  }

  /**
   * Matching disk with no leftovers must still succeed.
   */
  public function testValidateSucceedsWhenDiskMatches(): void {
    $artefacts = $this->artefacts();
    $paths = $this->writeGenerated($artefacts);

    $report = $this->snapshot()->compareDisk($paths['gen'], $artefacts);
    self::assertSame([], $report['missing']);
    self::assertSame([], $report['stale']);
    self::assertSame([], $report['extra']);

    $result = $this->runValidate($paths['root']);
    self::assertSame(CodegenCommands::EXIT_SUCCESS, $result['status']);
    self::assertStringContainsString(
      'in sync with the live schema',
      $result['logs'],
    );
  }

  /**
   * Extras through a symlink generated/ must use the same $rel as $current.
   *
   * Realpath on a file under a symlink generated/ does not start with
   * the lexical $genDir, so every expected file looked extra.
   */
  public function testCompareDiskExtrasUseLexicalPathsThroughSymlink(): void {
    $artefacts = $this->artefacts();
    $site = $this->absoluteSiteDir();
    $realGen = $site . '/gqcc-real/generated';
    $linkRoot = $site . '/gqcc-link';
    $linkGen = $linkRoot . '/generated';
    $this->writeArtefactsTo($realGen, $artefacts);
    // generated/ is the symlink; compareDisk() is given the link path.
    self::assertTrue(mkdir($linkRoot, 0777, TRUE) || is_dir($linkRoot));
    self::assertTrue(symlink($realGen, $linkGen));
    self::assertTrue(is_link($linkGen));

    $report = $this->snapshot()->compareDisk($linkGen, $artefacts);
    self::assertSame(
      [],
      $report['missing'],
      'Expected files exist via the symlink.',
    );
    self::assertSame([], $report['stale']);
    self::assertSame(
      [],
      $report['extra'],
      'Expected files must not look extra when generated/ is a symlink.',
    );

    $leftover = 'components/Retired.generated.tsx';
    $this->writeFile($linkGen . '/' . $leftover, "// leftover stub\n");
    $report = $this->snapshot()->compareDisk($linkGen, $artefacts);
    self::assertSame([$leftover], $report['extra']);
    // getRealPath() extras were absolute; $current keys never start with /.
    self::assertFalse(
      str_starts_with($report['extra'][0], '/'),
      'Extra paths must stay relative, not realpath() absolute.',
    );

    $result = $this->runValidate($linkRoot);
    self::assertSame(CodegenCommands::EXIT_FAILURE, $result['status']);
    self::assertStringContainsString('EXTRA:   ' . $leftover, $result['output']);
    self::assertStringNotContainsString(
      'in sync with the live schema',
      $result['logs'],
    );
  }

  /**
   * Expected artefacts for the demo fixture.
   *
   * @return array<string, string>
   *   Relative path → generator content.
   */
  private function artefacts(): array {
    $artefacts = $this->generator()->buildArtefacts(['demo']);
    self::assertNotEmpty($artefacts);
    return $artefacts;
  }

  /**
   * Writes $artefacts under {site}/gqcc-ui/generated.
   *
   * @param array<string, string> $artefacts
   *   Relative path → file content.
   *
   * @return array{root: string, gen: string}
   *   Next.js root (validate --output-dir) and the generated directory.
   */
  private function writeGenerated(array $artefacts): array {
    $root = $this->absoluteSiteDir() . '/gqcc-ui';
    $gen = $root . '/generated';
    $this->writeArtefactsTo($gen, $artefacts);
    return ['root' => $root, 'gen' => $gen];
  }

  /**
   * Writes each artefact under $genDir, creating parent directories.
   *
   * @param string $genDir
   *   Absolute generated directory.
   * @param array<string, string> $artefacts
   *   Relative path → file content.
   */
  private function writeArtefactsTo(string $genDir, array $artefacts): void {
    foreach ($artefacts as $rel => $content) {
      $this->writeFile($genDir . '/' . $rel, $content . "\n");
    }
  }

  /**
   * Writes $contents to $absolute, creating parent directories.
   *
   * @param string $absolute
   *   Absolute file path.
   * @param string $contents
   *   File contents.
   */
  private function writeFile(string $absolute, string $contents): void {
    $dir = dirname($absolute);
    if (!is_dir($dir) && !mkdir($dir, 0777, TRUE) && !is_dir($dir)) {
      self::fail("Could not create {$dir}");
    }
    self::assertNotFalse(file_put_contents($absolute, $contents));
  }

  /**
   * Absolute site directory (KernelTestBase may store a relative path).
   *
   * @return string
   *   Absolute path to this test's site directory.
   */
  private function absoluteSiteDir(): string {
    $siteDir = $this->siteDirectory;
    if (!str_starts_with($siteDir, '/')) {
      $siteDir = DRUPAL_ROOT . '/' . $siteDir;
    }
    return $siteDir;
  }

  /**
   * Runs validate() against $outputDir and captures exit, stdout, logs.
   *
   * @param string $outputDir
   *   Next.js project root (artefacts live in {outputDir}/generated).
   *
   * @return array{status: int, output: string, logs: string}
   *   Exit code, captured writeln() output, and logger success/error text.
   */
  private function runValidate(string $outputDir): array {
    $output = new BufferedOutput();
    $logger = new RecordingDrushLogger();

    $commands = new CodegenCommands(
      $this->container->get('graphql_compose_codegen.schema_inspector'),
      $this->generator(),
      $this->snapshot(),
      $this->container->get('graphql_compose_codegen.path_guard'),
      $this->container->get('module_handler'),
      $this->container->get('config.factory'),
    );
    // Robo IO (Drush 12/13) writes through setOutput(); input is unused
    // by validate() but io() construction reads it if a style is built.
    $commands->setOutput($output);
    $commands->setInput(new ArrayInput([]));
    // logger() is typed as DrushLoggerManager on Drush 13.
    $manager = new DrushLoggerManager();
    $manager->add('test', $logger);
    $commands->setLogger($manager);

    $status = $commands->validate([
      'bundles' => 'demo',
      'output-dir' => $outputDir,
      'skip-fields' => '',
      // siteDirectory can sit outside the composer project root in CI.
      'allow-external' => TRUE,
    ]);

    return [
      'status' => $status,
      'output' => $output->fetch(),
      'logs' => implode("\n", $logger->lines),
    ];
  }

  /**
   * The generator service.
   *
   * @return \Drupal\graphql_compose_codegen\Service\TypeScriptGenerator
   *   The TypeScript generator.
   */
  private function generator(): TypeScriptGenerator {
    return $this->container->get('graphql_compose_codegen.typescript_generator');
  }

  /**
   * The snapshot service.
   *
   * @return \Drupal\graphql_compose_codegen\Service\ArtefactSnapshot
   *   The artefact snapshot service.
   */
  private function snapshot(): ArtefactSnapshot {
    return $this->container->get('graphql_compose_codegen.artefact_snapshot');
  }

}
