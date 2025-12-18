<?php

namespace PhpStanHub\Tests\Command;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the /api/file-content endpoint in ServeCommand
 *
 * These tests verify the file content API functionality without starting the actual server.
 * We test the logic that would be executed when hitting the endpoint.
 */
class ServeCommandFileContentTest extends TestCase
{
    private string $tempDir;
    private string $testFilePath;
    private string $projectRoot;

    protected function setUp(): void
    {
        // Create temporary test directory
        $this->tempDir = sys_get_temp_dir() . '/phpstan_hub_file_content_test_' . uniqid();
        mkdir($this->tempDir);
        $this->projectRoot = $this->tempDir;

        // Create test file with known content
        $this->testFilePath = $this->tempDir . '/TestFile.php';
        file_put_contents($this->testFilePath, "<?php\n\nclass TestClass\n{\n    public function test()\n    {\n        return true;\n    }\n}\n");
    }

    protected function tearDown(): void
    {
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
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testFileExistsWithinProjectRoot(): void
    {
        // Simulate the logic of the /api/file-content endpoint
        $file = $this->testFilePath;
        $realPath = realpath($file);

        $this->assertNotFalse($realPath);
        $this->assertStringStartsWith($this->projectRoot, $realPath);
        $this->assertTrue(file_exists($realPath));
        $this->assertTrue(is_file($realPath));

        $content = file_get_contents($realPath);
        $this->assertStringContainsString('class TestClass', $content);
        $this->assertStringContainsString('public function test()', $content);
    }

    public function testFileOutsideProjectRootShouldBeRejected(): void
    {
        // Create a file outside project root
        $outsideFile = sys_get_temp_dir() . '/outside_file_' . uniqid() . '.php';
        file_put_contents($outsideFile, "<?php echo 'outside';");

        try {
            $realPath = realpath($outsideFile);
            $this->assertNotFalse($realPath);

            // This should fail the security check
            $this->assertFalse(str_starts_with($realPath, $this->projectRoot));
        } finally {
            unlink($outsideFile);
        }
    }

    public function testNonExistentFileShouldBeHandled(): void
    {
        $nonExistentFile = $this->tempDir . '/NonExistent.php';
        $realPath = realpath($nonExistentFile);

        $this->assertFalse($realPath);
    }

    public function testSymbolicLinkAttemptToEscapeProjectRoot(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Symbolic link test not applicable on Windows');
        }

        // Create a file outside project root
        $outsideFile = sys_get_temp_dir() . '/outside_target_' . uniqid() . '.php';
        file_put_contents($outsideFile, "<?php echo 'malicious';");

        // Create symlink inside project root pointing outside
        $symlinkPath = $this->tempDir . '/symlink.php';

        try {
            symlink($outsideFile, $symlinkPath);

            $realPath = realpath($symlinkPath);
            $this->assertNotFalse($realPath);

            // realpath() should resolve to the actual file outside project root
            // Security check should catch this
            $this->assertFalse(str_starts_with($realPath, $this->projectRoot));
        } finally {
            if (is_link($symlinkPath)) {
                unlink($symlinkPath);
            }
            if (file_exists($outsideFile)) {
                unlink($outsideFile);
            }
        }
    }

    public function testDirectoryPathShouldBeRejected(): void
    {
        $dirPath = $this->tempDir . '/subdir';
        mkdir($dirPath);

        $realPath = realpath($dirPath);
        $this->assertNotFalse($realPath);
        $this->assertTrue(str_starts_with($realPath, $this->projectRoot));

        // Should fail the is_file check
        $this->assertFalse(is_file($realPath));
        $this->assertTrue(is_dir($realPath));
    }

    public function testFileContentIsReturnedCorrectly(): void
    {
        $expectedContent = "<?php\n\nclass TestClass\n{\n    public function test()\n    {\n        return true;\n    }\n}\n";

        $content = file_get_contents($this->testFilePath);

        $this->assertEquals($expectedContent, $content);
        $this->assertJson(json_encode(['content' => $content]));
    }

    public function testApiResponseIncludesTokens(): void
    {
        $content = file_get_contents($this->testFilePath);
        $tokens = token_get_all($content);

        // Simulate API response structure
        $response = [
            'content' => $content,
            'tokens' => []
        ];

        // Process tokens like the API does
        $currentLine = 1;
        foreach ($tokens as $token) {
            if (is_array($token)) {
                $tokenText = $token[1];
                $color = '#d1d5db'; // Default color

                if (str_contains($tokenText, "\n")) {
                    $lines = explode("\n", $tokenText);
                    foreach ($lines as $i => $lineText) {
                        $response['tokens'][] = [
                            'text' => $lineText . ($i < count($lines) - 1 ? "\n" : ''),
                            'color' => $color,
                            'line' => $currentLine,
                            'type' => token_name($token[0])
                        ];
                        if ($i < count($lines) - 1) {
                            $currentLine++;
                        }
                    }
                } else {
                    $response['tokens'][] = [
                        'text' => $tokenText,
                        'color' => $color,
                        'line' => $currentLine,
                        'type' => token_name($token[0])
                    ];
                }
            } else {
                $response['tokens'][] = [
                    'text' => $token,
                    'color' => '#f87171',
                    'line' => $currentLine,
                    'type' => 'PUNCTUATION'
                ];
            }
        }

        $this->assertArrayHasKey('content', $response);
        $this->assertArrayHasKey('tokens', $response);
        $this->assertIsArray($response['tokens']);
        $this->assertNotEmpty($response['tokens']);

        // Verify token structure
        $firstToken = $response['tokens'][0];
        $this->assertArrayHasKey('text', $firstToken);
        $this->assertArrayHasKey('color', $firstToken);
        $this->assertArrayHasKey('line', $firstToken);
        $this->assertArrayHasKey('type', $firstToken);

        // Verify JSON encoding works
        $this->assertJson(json_encode($response));
    }

    public function testTokenizationReturnsCorrectStructure(): void
    {
        $phpCode = "<?php\n\$var = 123;\n";
        $tokens = token_get_all($phpCode);

        $this->assertIsArray($tokens);
        $this->assertNotEmpty($tokens);

        // Verify first token is PHP open tag
        $this->assertIsArray($tokens[0]);
        $this->assertEquals(T_OPEN_TAG, $tokens[0][0]);
    }

    public function testTokenizationPreservesWhitespace(): void
    {
        $phpCode = "<?php\n    \$var = 'test';\n";
        $tokens = token_get_all($phpCode);

        $spacesFound = false;
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_WHITESPACE) {
                // Check if this whitespace contains actual spaces (not just newlines)
                if (str_contains($token[1], ' ')) {
                    $spacesFound = true;
                    break;
                }
            }
        }

        $this->assertTrue($spacesFound, 'Whitespace with spaces should be present and preserved');
    }

    public function testTokenizationIdentifiesKeywords(): void
    {
        $phpCode = "<?php\nclass Test {\n    public function method() {\n        return true;\n    }\n}\n";
        $tokens = token_get_all($phpCode);

        $foundTokenTypes = [];
        foreach ($tokens as $token) {
            if (is_array($token)) {
                $foundTokenTypes[] = $token[0];
            }
        }

        $this->assertContains(T_CLASS, $foundTokenTypes);
        $this->assertContains(T_PUBLIC, $foundTokenTypes);
        $this->assertContains(T_FUNCTION, $foundTokenTypes);
        $this->assertContains(T_RETURN, $foundTokenTypes);
    }

    public function testTokenizationHandlesMultiLineComments(): void
    {
        $phpCode = "<?php\n/*\n * Multi-line\n * comment\n */\n\$var = 1;\n";
        $tokens = token_get_all($phpCode);

        $commentFound = false;
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_COMMENT) {
                $commentFound = true;
                $this->assertStringContainsString('Multi-line', $token[1]);
                $this->assertStringContainsString("\n", $token[1]);
            }
        }

        $this->assertTrue($commentFound, 'Multi-line comment should be found');
    }

    public function testTokenizationIdentifiesVariables(): void
    {
        $phpCode = "<?php\n\$userName = 'John';\n\$userId = 123;\n";
        $tokens = token_get_all($phpCode);

        $variables = [];
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_VARIABLE) {
                $variables[] = $token[1];
            }
        }

        $this->assertContains('$userName', $variables);
        $this->assertContains('$userId', $variables);
    }

    public function testTokenizationIdentifiesStrings(): void
    {
        $phpCode = "<?php\n\$str = 'single';\n\$str2 = \"double\";\n";
        $tokens = token_get_all($phpCode);

        $strings = [];
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $strings[] = $token[1];
            }
        }

        $this->assertContains("'single'", $strings);
        $this->assertContains('"double"', $strings);
    }

    public function testTokenizationHandlesPunctuation(): void
    {
        $phpCode = "<?php\n\$a = (\$b + \$c);\n";
        $tokens = token_get_all($phpCode);

        $punctuation = [];
        foreach ($tokens as $token) {
            if (!is_array($token)) {
                $punctuation[] = $token;
            }
        }

        $this->assertContains('=', $punctuation);
        $this->assertContains('(', $punctuation);
        $this->assertContains(')', $punctuation);
        $this->assertContains('+', $punctuation);
        $this->assertContains(';', $punctuation);
    }

    public function testEmptyFileShouldReturnEmptyContent(): void
    {
        $emptyFile = $this->tempDir . '/empty.php';
        file_put_contents($emptyFile, '');

        $realPath = realpath($emptyFile);
        $this->assertNotFalse($realPath);

        $content = file_get_contents($realPath);
        $this->assertEquals('', $content);
    }

    public function testLargeFileShouldBeReadable(): void
    {
        $largeFile = $this->tempDir . '/large.php';
        $largeContent = str_repeat("<?php\n// Line with code\n", 10000); // ~200KB file
        file_put_contents($largeFile, $largeContent);

        $realPath = realpath($largeFile);
        $this->assertNotFalse($realPath);

        $content = file_get_contents($realPath);
        $this->assertEquals($largeContent, $content);
        $this->assertGreaterThan(100000, strlen($content));
    }

    public function testFileWithSpecialCharactersShouldBeReadable(): void
    {
        $specialFile = $this->tempDir . '/special-chars_123.php';
        $specialContent = "<?php\n\$var = \"Special: àèéìòù € £ ¥\";\n";
        file_put_contents($specialFile, $specialContent);

        $realPath = realpath($specialFile);
        $this->assertNotFalse($realPath);

        $content = file_get_contents($realPath);
        $this->assertEquals($specialContent, $content);
    }

    public function testNestedDirectoryFileAccess(): void
    {
        $nestedDir = $this->tempDir . '/src/Controller/Admin';
        mkdir($nestedDir, 0777, true);

        $nestedFile = $nestedDir . '/UserController.php';
        file_put_contents($nestedFile, "<?php\nnamespace App\\Controller\\Admin;\n");

        $realPath = realpath($nestedFile);
        $this->assertNotFalse($realPath);
        $this->assertTrue(str_starts_with($realPath, $this->projectRoot));

        $content = file_get_contents($realPath);
        $this->assertStringContainsString('namespace App\\Controller\\Admin', $content);
    }
}
