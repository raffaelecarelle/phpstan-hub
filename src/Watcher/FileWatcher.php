<?php

namespace PhpStanHub\Watcher;

use Symfony\Component\Finder\Finder;

class FileWatcher
{
    private array $files = [];

    public function __construct(private readonly array $paths)
    {
        $this->files = $this->findFiles();
    }

    public function hasChanges(): bool
    {
        $currentFiles = $this->findFiles();

        if (count($currentFiles) !== count($this->files)) {
            $this->files = $currentFiles;
            return true;
        }

        foreach ($currentFiles as $path => $mtime) {
            if (!isset($this->files[$path]) || $this->files[$path] !== $mtime) {
                $this->files = $currentFiles;
                return true;
            }
        }

        return false;
    }

    private function findFiles(): array
    {
        if (empty($this->paths)) {
            return [];
        }

        $finder = Finder::create()->files()->in($this->paths)->name('*.php');
        $files = [];
        foreach ($finder as $file) {
            $files[$file->getRealPath()] = $file->getMTime();
        }
        return $files;
    }
}
