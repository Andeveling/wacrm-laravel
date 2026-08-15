<?php

declare(strict_types=1);

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Domain tests participate in Pest's mutation selection only when they name
 * the production behavior they protect. Keep this contract executable so a
 * new Domain test cannot silently become invisible to mutation testing.
 */
test('every Domain test declares its production intent', function (): void {
    $domainTests = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__.'/../../Domain'),
    );

    foreach ($domainTests as $file) {
        if (! $file instanceof SplFileInfo
            || $file->getExtension() !== 'php'
            || ! str_ends_with($file->getFilename(), 'Test.php')) {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        expect($source)
            ->toMatch('/\b(?:covers|mutates)\s*\(/', $file->getPathname().' has no Pest intent declaration.');
    }
});
