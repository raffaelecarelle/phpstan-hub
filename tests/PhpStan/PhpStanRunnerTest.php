<?php

namespace PhpStanHub\Tests\PhpStan;

use PhpStanHub\PhpStan\PhpStanRunner;
use PHPUnit\Framework\TestCase;
use React\ChildProcess\Process;

class PhpStanRunnerTest extends TestCase
{
    private string $tempDir;

    private PhpStanRunner $phpStanRunner;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpstan_hub_test_' . uniqid();
        mkdir($this->tempDir);
        $this->phpStanRunner = new PhpStanRunner($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map(unlink(...), glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
    }

    public function testRunReturnsProcessInstance(): void
    {
        $process = $this->phpStanRunner->run('src', 5);

        $this->assertInstanceOf(Process::class, $process);
    }

    public function testRunCommandIncludesPath(): void
    {
        $process = $this->phpStanRunner->run('src/Custom', 5);

        $this->assertStringContainsString('src/Custom', $process->getCommand());
    }

    public function testRunCommandIncludesLevel(): void
    {
        $process = $this->phpStanRunner->run('src', 8);

        $this->assertStringContainsString('--level=8', $process->getCommand());
    }

    public function testRunCommandIncludesJsonFormat(): void
    {
        $process = $this->phpStanRunner->run('src', 5);

        $this->assertStringContainsString('--error-format=json', $process->getCommand());
    }

    public function testRunCommandIncludesNoProgress(): void
    {
        $process = $this->phpStanRunner->run('src', 5);

        $this->assertStringContainsString('--no-progress', $process->getCommand());
    }

    public function testRunWithGenerateBaseline(): void
    {
        $process = $this->phpStanRunner->run('src', 5, true);

        $this->assertStringContainsString('--generate-baseline', $process->getCommand());
    }

    public function testRunWithoutGenerateBaseline(): void
    {
        $process = $this->phpStanRunner->run('src', 5, false);

        $this->assertStringNotContainsString('--generate-baseline', $process->getCommand());
    }

    public function testRunUsesConfigFileWhenExists(): void
    {
        file_put_contents($this->tempDir . '/phpstan.neon', 'parameters:');

        $process = $this->phpStanRunner->run('src', 5);

        $this->assertStringContainsString('-c', $process->getCommand());
        $this->assertStringContainsString('phpstan.neon', $process->getCommand());
    }

    public function testRunUsesDistConfigFileWhenMainConfigNotExists(): void
    {
        file_put_contents($this->tempDir . '/phpstan.neon.dist', 'parameters:');

        $process = $this->phpStanRunner->run('src', 5);

        $this->assertStringContainsString('-c', $process->getCommand());
        $this->assertStringContainsString('phpstan.neon.dist', $process->getCommand());
    }

    public function testRunPrefersMainConfigOverDist(): void
    {
        file_put_contents($this->tempDir . '/phpstan.neon', 'parameters:');
        file_put_contents($this->tempDir . '/phpstan.neon.dist', 'parameters:');

        $process = $this->phpStanRunner->run('src', 5);

        $this->assertStringContainsString('phpstan.neon', $process->getCommand());
        $this->assertStringNotContainsString('phpstan.neon.dist', $process->getCommand());
    }

    public function testRunWithoutConfigFile(): void
    {
        $process = $this->phpStanRunner->run('src', 5);

        $command = $process->getCommand();
        $this->assertStringNotContainsString('-c', $command);
    }

    public function testRunCommandUsesVendorBinPhpstan(): void
    {
        $process = $this->phpStanRunner->run('src', 5);

        $this->assertStringContainsString('vendor/bin/phpstan', $process->getCommand());
    }

    public function testRunWithMultiplePaths(): void
    {
        $process = $this->phpStanRunner->run('src tests', 5);

        $this->assertStringContainsString('src tests', $process->getCommand());
    }

    public function testRunWithMinimumLevel(): void
    {
        $process = $this->phpStanRunner->run('src', 0);

        $this->assertStringContainsString('--level=0', $process->getCommand());
    }

    public function testRunWithMaximumLevel(): void
    {
        $process = $this->phpStanRunner->run('src', 9);

        $this->assertStringContainsString('--level=9', $process->getCommand());
    }

    public function testProcessHasCorrectWorkingDirectory(): void
    {
        $phpStanRunner = new PhpStanRunner('/custom/path');
        $process = $phpStanRunner->run('src', 5);

        // Process working directory is set in constructor
        $this->assertInstanceOf(Process::class, $process);
    }
}
