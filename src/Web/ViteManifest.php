<?php

namespace PhpStanHub\Web;

class ViteManifest
{
    /** @var array<string, mixed> */
    private array $manifest = [];

    public function __construct(private readonly string $manifestPath)
    {
        if (is_file($this->manifestPath)) {
            $decoded = json_decode(file_get_contents($this->manifestPath), true);
            $this->manifest = is_array($decoded) ? $decoded : [];
        }
    }

    public function getScript(): string
    {
        $entry = $this->manifest['assets/js/app.js'] ?? null;
        return ($entry && isset($entry['file'])) ? sprintf('<script type="module" src="/build/%s"></script>', $entry['file']) : '';
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
