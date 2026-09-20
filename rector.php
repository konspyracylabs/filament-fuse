<?php

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withPhpSets()
    ->withSkip([
        // The Laravel and Filament ecosystems do not use strict_types: filament/* ships
        // 0 of 596 source files with it, laravel/framework 2 of 1688, and harris21/laravel-fuse
        // 0 of 21. Matching the surrounding ecosystem matters more here than the marginal
        // safety win, so this rule stays off rather than making the package an outlier.
        SafeDeclareStrictTypesRector::class,
    ]);
