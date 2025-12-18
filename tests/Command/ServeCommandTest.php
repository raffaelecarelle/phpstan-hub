<?php

namespace PhpStanHub\Tests\Command;

use ReflectionClass;
use PhpStanHub\Web\ViteManifest;
use PhpStanHub\Command\ServeCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;

class ServeCommandTest extends TestCase
{
    public $originalCwd;

    private string $tempDir;

    private Application $application;

    private ServeCommand $serveCommand;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpstan_hub_serve_test_' . uniqid();
        mkdir($this->tempDir);

        // Change to temp directory for testing
        $this->originalCwd = getcwd();
        chdir($this->tempDir);

        $this->application = new Application();
        $this->serveCommand = new ServeCommand();
        $this->application->add($this->serveCommand);
    }

    protected function tearDown(): void
    {
        // Restore original directory
        if (property_exists($this, 'originalCwd') && $this->originalCwd !== null) {
            chdir($this->originalCwd);
        }

        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    public function testCommandIsConfigured(): void
    {
        $this->assertSame('serve', $this->serveCommand->getName());
        $this->assertSame('Starts the PhpStanHub server.', $this->serveCommand->getDescription());
    }

    public function testCommandHasWatchOption(): void
    {
        $inputDefinition = $this->serveCommand->getDefinition();

        $this->assertTrue($inputDefinition->hasOption('watch'));

        $inputOption = $inputDefinition->getOption('watch');
        $this->assertFalse($inputOption->isValueRequired());
        $this->assertSame('w', $inputOption->getShortcut());
    }

    public function testGetPhpStanConfigReturnsDefaultsWhenNoConfigFile(): void
    {
        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('getPhpStanConfig');

        $config = $reflectionMethod->invoke($this->serveCommand, $this->tempDir);

        $this->assertIsArray($config);
        $this->assertArrayHasKey('level', $config);
        $this->assertArrayHasKey('paths', $config);
        $this->assertArrayHasKey('editorUrl', $config);
        $this->assertArrayHasKey('projectRoot', $config);

        $this->assertSame(5, $config['level']);
        $this->assertSame(['src'], $config['paths']);
        $this->assertSame($this->tempDir, $config['projectRoot']);
    }

    public function testGetPhpStanConfigReadsFromNeonFile(): void
    {
        $neonContent = <<<NEON
            parameters:
                level: 8
                paths:
                    - app
                    - lib
                editorUrl: 'phpstorm://open?file=%%file%%&line=%%line%%'
            NEON;

        file_put_contents($this->tempDir . '/phpstan.neon', $neonContent);

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('getPhpStanConfig');

        $config = $reflectionMethod->invoke($this->serveCommand, $this->tempDir);

        $this->assertSame(8, $config['level']);
        $this->assertSame(['app', 'lib'], $config['paths']);
        $this->assertSame('phpstorm://open?file=%%file%%&line=%%line%%', $config['editorUrl']);
    }

    public function testGetPhpStanConfigPrefersDistFile(): void
    {
        file_put_contents($this->tempDir . '/phpstan.neon.dist', 'parameters: {level: 7}');
        file_put_contents($this->tempDir . '/phpstan.neon', 'parameters: {level: 3}');

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('getPhpStanConfig');

        $config = $reflectionMethod->invoke($this->serveCommand, $this->tempDir);

        $this->assertSame(7, $config['level']);
    }

    public function testGetPhpStanConfigUsesDistFileWhenNeonNotExists(): void
    {
        file_put_contents($this->tempDir . '/phpstan.neon.dist', 'parameters: {level: 6}');

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('getPhpStanConfig');

        $config = $reflectionMethod->invoke($this->serveCommand, $this->tempDir);

        $this->assertSame(6, $config['level']);
    }

    public function testGetPhpStanConfigHandlesInvalidNeon(): void
    {
        file_put_contents($this->tempDir . '/phpstan.neon', 'invalid neon content {][');

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('getPhpStanConfig');

        $config = $reflectionMethod->invoke($this->serveCommand, $this->tempDir);

        // Should return defaults on error
        $this->assertSame(5, $config['level']);
    }

    public function testGetPhpStanConfigReadsHostProjectRoot(): void
    {
        $neonContent = <<<NEON
            parameters:
                phpstanHub:
                    hostProjectRoot: /host/path/to/project
            NEON;

        file_put_contents($this->tempDir . '/phpstan.neon', $neonContent);

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('getPhpStanConfig');

        $config = $reflectionMethod->invoke($this->serveCommand, $this->tempDir);

        $this->assertSame('/host/path/to/project', $config['hostProjectRoot']);
    }

    public function testGetDefaultPathsReadsFromComposerJson(): void
    {
        $composerData = [
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'app/',
                    'Domain\\' => 'domain/',
                ],
            ],
        ];

        file_put_contents($this->tempDir . '/composer.json', json_encode($composerData));

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('getDefaultPaths');

        $paths = $reflectionMethod->invoke($this->serveCommand, $this->tempDir);

        $this->assertIsArray($paths);
        $this->assertContains('app', $paths);
        $this->assertContains('domain', $paths);
    }

    public function testGetDefaultPathsIncludesAutoloadDev(): void
    {
        $composerData = [
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'app/',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    'Tests\\' => 'tests/',
                ],
            ],
        ];

        file_put_contents($this->tempDir . '/composer.json', json_encode($composerData));

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('getDefaultPaths');

        $paths = $reflectionMethod->invoke($this->serveCommand, $this->tempDir);

        $this->assertContains('app', $paths);
        $this->assertContains('tests', $paths);
    }

    public function testGetDefaultPathsHandlesArrayPaths(): void
    {
        $composerData = [
            'autoload' => [
                'psr-4' => [
                    'App\\' => ['app/', 'src/'],
                ],
            ],
        ];

        file_put_contents($this->tempDir . '/composer.json', json_encode($composerData));

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('getDefaultPaths');

        $paths = $reflectionMethod->invoke($this->serveCommand, $this->tempDir);

        $this->assertContains('app', $paths);
        $this->assertContains('src', $paths);
    }

    public function testGetDefaultPathsRemovesDuplicates(): void
    {
        $composerData = [
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/',
                    'Domain\\' => 'src/',
                ],
            ],
        ];

        file_put_contents($this->tempDir . '/composer.json', json_encode($composerData));

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('getDefaultPaths');

        $paths = $reflectionMethod->invoke($this->serveCommand, $this->tempDir);

        $this->assertSame(1, count(array_filter($paths, fn ($p) => $p === 'src')));
    }

    public function testGetDefaultPathsTrimsTrailingSlashes(): void
    {
        $composerData = [
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'app///',
                ],
            ],
        ];

        file_put_contents($this->tempDir . '/composer.json', json_encode($composerData));

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('getDefaultPaths');

        $paths = $reflectionMethod->invoke($this->serveCommand, $this->tempDir);

        $this->assertContains('app', $paths);
        $this->assertNotContains('app/', $paths);
    }

    public function testGetDefaultPathsFallsBackToSrcWhenComposerJsonNotFound(): void
    {
        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('getDefaultPaths');

        $paths = $reflectionMethod->invoke($this->serveCommand, $this->tempDir);

        $this->assertSame(['src'], $paths);
    }

    public function testGetDefaultPathsHandlesInvalidComposerJson(): void
    {
        file_put_contents($this->tempDir . '/composer.json', 'invalid json {][');

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('getDefaultPaths');

        $paths = $reflectionMethod->invoke($this->serveCommand, $this->tempDir);

        $this->assertSame(['src'], $paths);
    }

    public function testCreateIndexResponseInDevMode(): void
    {
        mkdir($this->tempDir . '/public/build/.vite', 0o777, true);

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('createIndexResponse');

        $viteManifestMock = $this->createMock(ViteManifest::class);

        $response = $reflectionMethod->invoke($this->serveCommand, true, $viteManifestMock);

        $body = (string)$response->getBody();

        $this->assertStringContainsString('http://localhost:5173/@vite/client', $body);
        $this->assertStringContainsString('http://localhost:5173/assets/js/app.js', $body);
        $this->assertStringContainsString('<div id="app"></div>', $body);
        $this->assertStringContainsString('PhpStanHub', $body);
    }

    public function testCreateIndexResponseInProductionMode(): void
    {
        mkdir($this->tempDir . '/public/build/.vite', 0o777, true);

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('createIndexResponse');

        $viteManifestMock = $this->createMock(ViteManifest::class);
        $viteManifestMock->method('getStyles')->willReturn('<link rel="stylesheet" href="/build/app.css">');
        $viteManifestMock->method('getScript')->willReturn('<script src="/build/app.js"></script>');

        $response = $reflectionMethod->invoke($this->serveCommand, false, $viteManifestMock);

        $body = (string)$response->getBody();

        $this->assertStringContainsString('<link rel="stylesheet" href="/build/app.css">', $body);
        $this->assertStringContainsString('<script src="/build/app.js"></script>', $body);
        $this->assertStringNotContainsString('localhost:5173', $body);
    }

    public function testAddErrorToPhpStanConfigCreatesIgnoreEntry(): void
    {
        file_put_contents($this->tempDir . '/phpstan.neon', 'parameters: {}');

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('addErrorToPhpStanConfig');

        $reflectionMethod->invoke($this->serveCommand, $this->tempDir, 'Some error message', 'src/File.php');

        $content = file_get_contents($this->tempDir . '/phpstan.neon');

        $this->assertStringContainsString('ignoreErrors', $content);
        $this->assertStringContainsString('Some error message', $content);
        $this->assertStringContainsString('src/File.php', $content);
    }

    public function testAddErrorToPhpStanConfigCreatesFileIfNotExists(): void
    {
        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('addErrorToPhpStanConfig');

        $reflectionMethod->invoke($this->serveCommand, $this->tempDir, 'Error message', 'src/File.php');

        $this->assertFileExists($this->tempDir . '/phpstan.neon');
    }

    public function testAddErrorToPhpStanConfigEscapesRegexCharacters(): void
    {
        file_put_contents($this->tempDir . '/phpstan.neon', 'parameters: {}');

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('addErrorToPhpStanConfig');

        $reflectionMethod->invoke($this->serveCommand, $this->tempDir, 'Error with (parentheses)', 'src/File.php');

        $content = file_get_contents($this->tempDir . '/phpstan.neon');

        $this->assertStringContainsString('\(', $content);
        $this->assertStringContainsString('\)', $content);
    }

    public function testAddErrorToPhpStanConfigAppendsToExistingIgnores(): void
    {
        $neonContent = <<<NEON
            parameters:
                ignoreErrors:
                    -
                        message: '#Existing error#'
                        path: src/Existing.php
            NEON;

        file_put_contents($this->tempDir . '/phpstan.neon', $neonContent);

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('addErrorToPhpStanConfig');

        $reflectionMethod->invoke($this->serveCommand, $this->tempDir, 'New error', 'src/New.php');

        $content = file_get_contents($this->tempDir . '/phpstan.neon');

        $this->assertStringContainsString('Existing error', $content);
        $this->assertStringContainsString('New error', $content);
    }

    public function testAddErrorToPhpStanConfigUsesDistFileAsFallback(): void
    {
        file_put_contents($this->tempDir . '/phpstan.neon.dist', 'parameters: {}');

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('addErrorToPhpStanConfig');

        $reflectionMethod->invoke($this->serveCommand, $this->tempDir, 'Error message', 'src/File.php');

        $this->assertFileExists($this->tempDir . '/phpstan.neon.dist');

        $content = file_get_contents($this->tempDir . '/phpstan.neon.dist');
        $this->assertStringContainsString('Error message', $content);
    }

    public function testGetPhpStanConfigHandlesSinglePathString(): void
    {
        $neonContent = 'parameters: {paths: src}';

        file_put_contents($this->tempDir . '/phpstan.neon', $neonContent);

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('getPhpStanConfig');

        $config = $reflectionMethod->invoke($this->serveCommand, $this->tempDir);

        $this->assertIsArray($config['paths']);
        $this->assertSame(['src'], $config['paths']);
    }

    public function testGetPhpStanConfigIncludesAvailablePaths(): void
    {
        $composerData = [
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'app/',
                ],
            ],
        ];

        file_put_contents($this->tempDir . '/composer.json', json_encode($composerData));
        file_put_contents($this->tempDir . '/phpstan.neon', 'parameters: {paths: [custom]}');

        $reflectionClass = new ReflectionClass($this->serveCommand);
        $reflectionMethod = $reflectionClass->getMethod('getPhpStanConfig');

        $config = $reflectionMethod->invoke($this->serveCommand, $this->tempDir);

        $this->assertSame(['custom'], $config['paths']);
        $this->assertSame(['app'], $config['availablePaths']);
    }
}
