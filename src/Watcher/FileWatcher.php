<?php

namespace PhpStanHub\Watcher;

use Symfony\Component\Finder\Finder;

class FileWatcher
{
    /** @var array<string, int> */
    private array $files = [];

    /**
     * @param string[] $paths
     * @param string[] $names
     */
    public function __construct(
        private readonly array $paths,
        private readonly array $names = ['*.php']
    ) {
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

    /**
     * @return array<string, int>
     */
    private function findFiles(): array
    {
        if ($this->paths === []) {
            return [];
        }

        $finder = Finder::create()->files()->in($this->paths);
        foreach ($this->names as $name) {
            $finder->name($name);
        }

        $files = [];
        foreach ($finder as $file) {
            $files[$file->getRealPath()] = $file->getMTime();
        }

        return $files;
    }
}
