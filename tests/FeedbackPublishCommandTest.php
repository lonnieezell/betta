<?php

declare(strict_types=1);

/**
 * This file is part of Myth/Betta.
 *
 * (c) Your Name <you@example.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockInputOutput;

/**
 * @internal
 */
final class FeedbackPublishCommandTest extends CIUnitTestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/betta_publish_test_' . uniqid('', true);
        mkdir($this->tmpDir . '/Views/vendor/betta', 0777, true);
        mkdir($this->tmpDir . '/Config', 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDir($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // No flags
    // -------------------------------------------------------------------------

    public function testNoFlagsShowsError(): void
    {
        $output = $this->runCommand('feedback:publish');

        $this->assertStringContainsString('--views', $output);
        $this->assertStringContainsString('--config', $output);
    }

    // -------------------------------------------------------------------------
    // --views
    // -------------------------------------------------------------------------

    public function testViewsFlagCopiesAllThreeViewFiles(): void
    {
        $this->runCommand('feedback:publish --views');

        $destDir = $this->tmpDir . '/Views/vendor/betta/';
        $this->assertFileExists($destDir . 'form.php');
        $this->assertFileExists($destDir . 'page.php');
        $this->assertFileExists($destDir . 'closed.php');
    }

    public function testViewsFlagCopiedFilesMatchSource(): void
    {
        $this->runCommand('feedback:publish --views');

        $srcDir  = realpath(__DIR__ . '/../src/Views') . '/';
        $destDir = $this->tmpDir . '/Views/vendor/betta/';

        foreach (['form.php', 'page.php', 'closed.php'] as $file) {
            $this->assertSame(file_get_contents($srcDir . $file), file_get_contents($destDir . $file));
        }
    }

    public function testViewsFlagSkipsFileWhenUserDeclinesOverwrite(): void
    {
        $destDir = $this->tmpDir . '/Views/vendor/betta/';
        file_put_contents($destDir . 'form.php', 'original content');

        $this->runCommand('feedback:publish --views', "n\n");

        $this->assertSame('original content', file_get_contents($destDir . 'form.php'));
    }

    public function testViewsFlagOverwritesFileWhenUserConfirms(): void
    {
        $destDir = $this->tmpDir . '/Views/vendor/betta/';
        file_put_contents($destDir . 'form.php', 'original content');

        $this->runCommand('feedback:publish --views', "y\n");

        $srcDir = realpath(__DIR__ . '/../src/Views') . '/';
        $this->assertSame(file_get_contents($srcDir . 'form.php'), file_get_contents($destDir . 'form.php'));
    }

    public function testViewsFlagReportsEachCopiedFile(): void
    {
        $output = $this->runCommand('feedback:publish --views');

        $this->assertStringContainsString('form.php', $output);
        $this->assertStringContainsString('page.php', $output);
        $this->assertStringContainsString('closed.php', $output);
    }

    // -------------------------------------------------------------------------
    // --config
    // -------------------------------------------------------------------------

    public function testConfigFlagWritesConfigFile(): void
    {
        $this->runCommand('feedback:publish --config');

        $this->assertFileExists($this->tmpDir . '/Config/Betta.php');
    }

    public function testConfigFileIsSyntacticallyValid(): void
    {
        $this->runCommand('feedback:publish --config');

        $path   = $this->tmpDir . '/Config/Betta.php';
        $output = null;
        $result = null;
        exec(PHP_BINARY . ' -l ' . escapeshellarg($path), $output, $result);

        $this->assertSame(0, $result, 'Config file failed PHP syntax check: ' . implode("\n", $output));
    }

    public function testConfigFileExtendsPackageConfig(): void
    {
        $this->runCommand('feedback:publish --config');

        $content = (string) file_get_contents($this->tmpDir . '/Config/Betta.php');
        $this->assertStringContainsString('Myth\Betta\Config\Betta', $content);
        $this->assertStringContainsString('extends', $content);
    }

    public function testConfigFileContainsAllProperties(): void
    {
        $this->runCommand('feedback:publish --config');

        $content = (string) file_get_contents($this->tmpDir . '/Config/Betta.php');
        $this->assertStringContainsString('routePrefix', $content);
        $this->assertStringContainsString('acceptSubmissions', $content);
        $this->assertStringContainsString('analyzeBatchSize', $content);
    }

    public function testConfigFileIsInConfigNamespace(): void
    {
        $this->runCommand('feedback:publish --config');

        $content = (string) file_get_contents($this->tmpDir . '/Config/Betta.php');
        $this->assertStringContainsString('namespace Config', $content);
    }

    public function testConfigFlagSkipsFileWhenUserDeclinesOverwrite(): void
    {
        $destFile = $this->tmpDir . '/Config/Betta.php';
        file_put_contents($destFile, '<?php // original');

        $this->runCommand('feedback:publish --config', "n\n");

        $this->assertSame('<?php // original', file_get_contents($destFile));
    }

    public function testConfigFlagOverwritesFileWhenUserConfirms(): void
    {
        $destFile = $this->tmpDir . '/Config/Betta.php';
        file_put_contents($destFile, '<?php // original');

        $this->runCommand('feedback:publish --config', "y\n");

        $this->assertStringNotContainsString('// original', (string) file_get_contents($destFile));
    }

    public function testConfigFlagReportsWrittenFile(): void
    {
        $output = $this->runCommand('feedback:publish --config');

        $this->assertStringContainsString('Betta.php', $output);
    }

    // -------------------------------------------------------------------------
    // Both flags together
    // -------------------------------------------------------------------------

    public function testBothFlagsPublishViewsAndConfig(): void
    {
        $this->runCommand('feedback:publish --views --config');

        $destDir = $this->tmpDir . '/Views/vendor/betta/';
        $this->assertFileExists($destDir . 'form.php');
        $this->assertFileExists($destDir . 'page.php');
        $this->assertFileExists($destDir . 'closed.php');
        $this->assertFileExists($this->tmpDir . '/Config/Betta.php');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Runs a spark command, automatically appending --app-path pointing to the
     * isolated temp directory so the command never touches APPPATH.
     */
    private function runCommand(string $cmd, string $input = ''): string
    {
        $io = new MockInputOutput();

        if ($input !== '') {
            $io->setInputs(array_values(array_filter(explode("\n", $input), static fn (string $s): bool => $s !== '')));
        }

        // Inject the temp dir as the app path (space-separated — CI4 only supports that form)
        $cmd .= ' --app-path ' . $this->tmpDir . '/';

        CLI::setInputOutput($io); // @phpstan-ignore staticMethod.internal
        command($cmd);
        CLI::resetInputOutput(); // @phpstan-ignore staticMethod.internal

        return $io->getOutput();
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
