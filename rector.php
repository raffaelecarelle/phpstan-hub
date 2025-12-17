<?php

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPhpVersion(80100)
    ->withPaths(['src', 'tests'])
    ->withImportNames()
    ->withSets([
        \Rector\Set\ValueObject\LevelSetList::UP_TO_PHP_81,
        \Rector\Set\ValueObject\SetList::CODE_QUALITY,
        \Rector\Set\ValueObject\SetList::CODING_STYLE,
        \Rector\Set\ValueObject\SetList::DEAD_CODE,
        \Rector\Set\ValueObject\SetList::EARLY_RETURN,
        \Rector\Set\ValueObject\SetList::INSTANCEOF,
        \Rector\Set\ValueObject\SetList::NAMING,
    ]);
