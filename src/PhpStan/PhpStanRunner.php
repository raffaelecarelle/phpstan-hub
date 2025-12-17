<?php

namespace PhpStanHub\PhpStan;

use React\ChildProcess\Process;
use function var_dump;

class PhpStanRunner
{
    private string $cwd;

    public function __construct(string $cwd)
    {
        $this->cwd = $cwd;
    }

    public function run(string $paths, int $level, bool $generateBaseline = false): Process
    {
        $configFile = $this->findConfigFile();
        $configOption = $configFile ? sprintf('-c %s', escapeshellarg($configFile)) : '';

        $command = sprintf(
            'vendor/bin/phpstan analyse %s --level=%d --error-format=json --no-progress %s',
            $paths,
            $level,
            $configOption
        );

        if ($generateBaseline) {
            $command .= ' --generate-baseline';
        }

        return new Process(trim($command), $this->cwd);
    }

    private function findConfigFile(): ?string
    {
        $configFiles = ['phpstan.neon', 'phpstan.neon.dist'];
        foreach ($configFiles as $configFile) {
            if (file_exists($this->cwd . '/' . $configFile)) {
                return $configFile;
            }
        }
        return null;
    }
}
