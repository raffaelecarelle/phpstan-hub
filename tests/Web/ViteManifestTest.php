<?php

namespace PhpStanHub\Tests\Web;

use PhpStanHub\Web\ViteManifest;
use PHPUnit\Framework\TestCase;

class ViteManifestTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpstan_hub_vite_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            rmdir($this->tempDir);
        }
    }

    public function testGetScriptReturnsEmptyStringWhenManifestDoesNotExist(): void
    {
        $viteManifest = new ViteManifest($this->tempDir . '/manifest.json');

        $this->assertSame('', $viteManifest->getScript());
    }

    public function testGetStylesReturnsEmptyStringWhenManifestDoesNotExist(): void
    {
        $viteManifest = new ViteManifest($this->tempDir . '/manifest.json');

        $this->assertSame('', $viteManifest->getStyles());
    }

    public function testGetScriptReturnsCorrectTag(): void
    {
        $manifestData = [
            'assets/js/app.js' => [
                'file' => 'assets/app-abc123.js',
                'css' => [],
            ],
        ];

        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode($manifestData));

        $viteManifest = new ViteManifest($manifestPath);

        $expected = '<script type="module" src="/build/assets/app-abc123.js"></script>';
        $this->assertSame($expected, $viteManifest->getScript());
    }

    public function testGetStylesReturnsEmptyStringWhenNoCssInManifest(): void
    {
        $manifestData = [
            'assets/js/app.js' => [
                'file' => 'assets/app-abc123.js',
            ],
        ];

        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode($manifestData));

        $viteManifest = new ViteManifest($manifestPath);

        $this->assertSame('', $viteManifest->getStyles());
    }

    public function testGetStylesReturnsEmptyStringWhenCssArrayIsEmpty(): void
    {
        $manifestData = [
            'assets/js/app.js' => [
                'file' => 'assets/app-abc123.js',
                'css' => [],
            ],
        ];

        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode($manifestData));

        $viteManifest = new ViteManifest($manifestPath);

        $this->assertSame('', $viteManifest->getStyles());
    }

    public function testGetStylesReturnsCorrectTagForSingleCss(): void
    {
        $manifestData = [
            'assets/js/app.js' => [
                'file' => 'assets/app-abc123.js',
                'css' => ['assets/app-xyz789.css'],
            ],
        ];

        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode($manifestData));

        $viteManifest = new ViteManifest($manifestPath);

        $expected = '<link rel="stylesheet" href="/build/assets/app-xyz789.css">';
        $this->assertSame($expected, $viteManifest->getStyles());
    }

    public function testGetStylesReturnsCorrectTagsForMultipleCss(): void
    {
        $manifestData = [
            'assets/js/app.js' => [
                'file' => 'assets/app-abc123.js',
                'css' => [
                    'assets/app-xyz789.css',
                    'assets/vendor-def456.css',
                    'assets/theme-ghi012.css',
                ],
            ],
        ];

        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode($manifestData));

        $viteManifest = new ViteManifest($manifestPath);

        $expected = '<link rel="stylesheet" href="/build/assets/app-xyz789.css">'
            . '<link rel="stylesheet" href="/build/assets/vendor-def456.css">'
            . '<link rel="stylesheet" href="/build/assets/theme-ghi012.css">';

        $this->assertSame($expected, $viteManifest->getStyles());
    }

    public function testGetScriptReturnsEmptyStringWhenEntryNotFound(): void
    {
        $manifestData = [
            'assets/js/other.js' => [
                'file' => 'assets/other-abc123.js',
            ],
        ];

        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode($manifestData));

        $viteManifest = new ViteManifest($manifestPath);

        $this->assertSame('', $viteManifest->getScript());
    }

    public function testGetStylesReturnsEmptyStringWhenEntryNotFound(): void
    {
        $manifestData = [
            'assets/js/other.js' => [
                'file' => 'assets/other-abc123.js',
                'css' => ['assets/other-xyz789.css'],
            ],
        ];

        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode($manifestData));

        $viteManifest = new ViteManifest($manifestPath);

        $this->assertSame('', $viteManifest->getStyles());
    }

    public function testHandlesInvalidJson(): void
    {
        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, 'invalid json{]');

        $viteManifest = new ViteManifest($manifestPath);

        $this->assertSame('', $viteManifest->getScript());
        $this->assertSame('', $viteManifest->getStyles());
    }

    public function testHandlesEmptyManifest(): void
    {
        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, '{}');

        $viteManifest = new ViteManifest($manifestPath);

        $this->assertSame('', $viteManifest->getScript());
        $this->assertSame('', $viteManifest->getStyles());
    }

    public function testHandlesManifestWithMissingFileKey(): void
    {
        $manifestData = [
            'assets/js/app.js' => [
                'css' => ['assets/app-xyz789.css'],
            ],
        ];

        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode($manifestData));

        $viteManifest = new ViteManifest($manifestPath);

        // Should handle gracefully when 'file' key is missing
        $script = $viteManifest->getScript();
        $this->assertIsString($script);
    }

    public function testGetScriptWithSpecialCharactersInPath(): void
    {
        $manifestData = [
            'assets/js/app.js' => [
                'file' => 'assets/app-abc123-!@#$%^&*().js',
                'css' => [],
            ],
        ];

        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode($manifestData));

        $viteManifest = new ViteManifest($manifestPath);

        $expected = '<script type="module" src="/build/assets/app-abc123-!@#$%^&*().js"></script>';
        $this->assertSame($expected, $viteManifest->getScript());
    }

    public function testGetStylesWithSpecialCharactersInPath(): void
    {
        $manifestData = [
            'assets/js/app.js' => [
                'file' => 'assets/app-abc123.js',
                'css' => ['assets/app-xyz789-!@#.css'],
            ],
        ];

        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode($manifestData));

        $viteManifest = new ViteManifest($manifestPath);

        $expected = '<link rel="stylesheet" href="/build/assets/app-xyz789-!@#.css">';
        $this->assertSame($expected, $viteManifest->getStyles());
    }

    public function testManifestPathIsFile(): void
    {
        $manifestData = [
            'assets/js/app.js' => [
                'file' => 'assets/app-abc123.js',
            ],
        ];

        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode($manifestData));

        $viteManifest = new ViteManifest($manifestPath);

        $this->assertNotEmpty($viteManifest->getScript());
    }

    public function testManifestPathIsDirectory(): void
    {
        // Pass directory path instead of file path
        $viteManifest = new ViteManifest($this->tempDir);

        $this->assertSame('', $viteManifest->getScript());
        $this->assertSame('', $viteManifest->getStyles());
    }

    public function testGetScriptAndGetStylesCanBeCalledMultipleTimes(): void
    {
        $manifestData = [
            'assets/js/app.js' => [
                'file' => 'assets/app-abc123.js',
                'css' => ['assets/app-xyz789.css'],
            ],
        ];

        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode($manifestData));

        $viteManifest = new ViteManifest($manifestPath);

        // Call multiple times to ensure idempotence
        $script1 = $viteManifest->getScript();
        $script2 = $viteManifest->getScript();
        $script3 = $viteManifest->getScript();

        $this->assertSame($script1, $script2);
        $this->assertSame($script2, $script3);

        $styles1 = $viteManifest->getStyles();
        $styles2 = $viteManifest->getStyles();
        $styles3 = $viteManifest->getStyles();

        $this->assertSame($styles1, $styles2);
        $this->assertSame($styles2, $styles3);
    }

    public function testCompleteWorkflow(): void
    {
        $manifestData = [
            'assets/js/app.js' => [
                'file' => 'assets/app-12345.js',
                'css' => [
                    'assets/app-67890.css',
                    'assets/vendor-abcde.css',
                ],
            ],
        ];

        $manifestPath = $this->tempDir . '/manifest.json';
        file_put_contents($manifestPath, json_encode($manifestData));

        $viteManifest = new ViteManifest($manifestPath);

        $script = $viteManifest->getScript();
        $styles = $viteManifest->getStyles();

        $this->assertStringContainsString('assets/app-12345.js', $script);
        $this->assertStringContainsString('type="module"', $script);
        $this->assertStringContainsString('/build/', $script);

        $this->assertStringContainsString('assets/app-67890.css', $styles);
        $this->assertStringContainsString('assets/vendor-abcde.css', $styles);
        $this->assertStringContainsString('rel="stylesheet"', $styles);
        $this->assertStringContainsString('/build/', $styles);
    }
}
