<?php

namespace PhpStanHub\Web;

use function var_dump;

class ViteManifest
{
    private array $manifest = [];

    public function __construct(private readonly string $manifestPath)
    {
        if (is_file($this->manifestPath)) {
            $this->manifest = json_decode(file_get_contents($this->manifestPath), true);
        }
    }

    public function getScript(): string
    {
        $entry = $this->manifest['assets/js/app.js'] ?? null;
        return $entry ? sprintf('<script type="module" src="/build/%s"></script>', $entry['file']) : '';
    }

    public function getStyles(): string
    {
        $entry = $this->manifest['assets/js/app.js'] ?? null;
        if (!$entry || !isset($entry['css'])) {
            return '';
        }

        $styles = '';
        foreach ($entry['css'] as $cssFile) {
            $styles .= sprintf('<link rel="stylesheet" href="/build/%s">', $cssFile);
        }
        return $styles;
    }
}
