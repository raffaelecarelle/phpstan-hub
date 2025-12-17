<?php

$finder = (new PhpCsFixer\Finder())
    ->in(['src', 'tests'])
;

return (new PhpCsFixer\Config())
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP81Migration' => true,
        'no_unused_imports' => true,
        'fully_qualified_strict_types' => true,
    ])
    ->setFinder($finder);