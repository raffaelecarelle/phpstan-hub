<?php

namespace PhpStanHub\Tests\Watcher;

use PhpStanHub\Watcher\FileWatcher;
use PHPUnit\Framework\TestCase;

class FileWatcherTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpstan_hub_watcher_test_' . uniqid();
        mkdir($this->tempDir);
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
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testWatcherDetectsNoChangesInitially(): void
    {
        file_put_contents($this->tempDir . '/test.php', '<?php echo "test";');

        $watcher = new FileWatcher([$this->tempDir]);

        $this->assertFalse($watcher->hasChanges());
    }

    public function testWatcherDetectsModifiedFile(): void
    {
        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, '<?php echo "test";');

        $watcher = new FileWatcher([$this->tempDir]);

        sleep(1); // Ensure modification time changes
        touch($filePath);

        $this->assertTrue($watcher->hasChanges());
    }

    public function testWatcherDetectsNewFile(): void
    {
        file_put_contents($this->tempDir . '/existing.php', '<?php echo "test";');

        $watcher = new FileWatcher([$this->tempDir]);

        file_put_contents($this->tempDir . '/new.php', '<?php echo "new";');

        $this->assertTrue($watcher->hasChanges());
    }

    public function testWatcherDetectsDeletedFile(): void
    {
        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, '<?php echo "test";');

        $watcher = new FileWatcher([$this->tempDir]);

        unlink($filePath);

        $this->assertTrue($watcher->hasChanges());
    }

    public function testWatcherHandlesEmptyDirectory(): void
    {
        $watcher = new FileWatcher([$this->tempDir]);

        $this->assertFalse($watcher->hasChanges());
    }

    public function testWatcherOnlyWatchesPhpFiles(): void
    {
        file_put_contents($this->tempDir . '/test.php', '<?php echo "test";');

        $watcher = new FileWatcher([$this->tempDir]);

        // Create non-PHP file
        file_put_contents($this->tempDir . '/test.txt', 'text content');

        $this->assertFalse($watcher->hasChanges());
    }

    public function testWatcherWithMultiplePaths(): void
    {
        $tempDir2 = sys_get_temp_dir() . '/phpstan_hub_watcher_test2_' . uniqid();
        mkdir($tempDir2);

        file_put_contents($this->tempDir . '/test1.php', '<?php echo "test1";');
        file_put_contents($tempDir2 . '/test2.php', '<?php echo "test2";');

        $watcher = new FileWatcher([$this->tempDir, $tempDir2]);

        $this->assertFalse($watcher->hasChanges());

        // Modify file in second directory
        sleep(1);
        touch($tempDir2 . '/test2.php');

        $this->assertTrue($watcher->hasChanges());

        // Cleanup
        unlink($tempDir2 . '/test2.php');
        rmdir($tempDir2);
    }

    public function testWatcherWithEmptyPaths(): void
    {
        $watcher = new FileWatcher([]);

        $this->assertFalse($watcher->hasChanges());
    }

    public function testWatcherResetsStateAfterDetectingChanges(): void
    {
        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, '<?php echo "test";');

        $watcher = new FileWatcher([$this->tempDir]);

        sleep(1);
        touch($filePath);

        // First check detects changes
        $this->assertTrue($watcher->hasChanges());

        // Second check without new changes should return false
        $this->assertFalse($watcher->hasChanges());
    }

    public function testWatcherTracksMultipleFiles(): void
    {
        file_put_contents($this->tempDir . '/test1.php', '<?php echo "test1";');
        file_put_contents($this->tempDir . '/test2.php', '<?php echo "test2";');
        file_put_contents($this->tempDir . '/test3.php', '<?php echo "test3";');

        $watcher = new FileWatcher([$this->tempDir]);

        sleep(1);
        touch($this->tempDir . '/test2.php');

        $this->assertTrue($watcher->hasChanges());
    }

    public function testWatcherHandlesSubdirectories(): void
    {
        $subDir = $this->tempDir . '/subdir';
        mkdir($subDir);

        file_put_contents($subDir . '/nested.php', '<?php echo "nested";');

        $watcher = new FileWatcher([$this->tempDir]);

        sleep(1);
        touch($subDir . '/nested.php');

        $this->assertTrue($watcher->hasChanges());

        // Cleanup
        unlink($subDir . '/nested.php');
        rmdir($subDir);
    }

    public function testWatcherDetectsContentChange(): void
    {
        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, '<?php echo "test";');

        $watcher = new FileWatcher([$this->tempDir]);

        sleep(1);
        file_put_contents($filePath, '<?php echo "modified";');

        $this->assertTrue($watcher->hasChanges());
    }

    public function testWatcherHandlesMultipleConsecutiveChanges(): void
    {
        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, '<?php echo "test";');

        $watcher = new FileWatcher([$this->tempDir]);

        // First change
        sleep(1);
        touch($filePath);
        $this->assertTrue($watcher->hasChanges());

        // Second change
        sleep(1);
        touch($filePath);
        $this->assertTrue($watcher->hasChanges());

        // Third change
        sleep(1);
        touch($filePath);
        $this->assertTrue($watcher->hasChanges());
    }
}
