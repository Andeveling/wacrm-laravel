<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @return list<SplFileInfo>
 */
function frontendFiles(string $directory): array
{
    return array_values(array_filter(
        File::allFiles(resource_path("js/{$directory}")),
        fn (SplFileInfo $file): bool => in_array($file->getExtension(), ['ts', 'tsx'], true),
    ));
}

/**
 * @return list<string>
 */
function frontendImports(SplFileInfo $file): array
{
    preg_match_all("~(?:from\s+|import\s+|import\()['\"]([^'\"]+)~", $file->getContents(), $matches);

    return array_values(array_unique($matches[1]));
}

function normalizeFrontendPath(string $path): string
{
    $segments = [];

    foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($segments);

            continue;
        }

        $segments[] = $segment;
    }

    return '/'.implode('/', $segments);
}

test('Inertia pages are adapters for frontend modules', function () {
    foreach (frontendFiles('pages') as $file) {
        $source = $file->getContents();
        $applicationImports = array_filter(
            frontendImports($file),
            fn (string $import): bool => str_starts_with($import, '@/'),
        );

        expect(frontendImports($file))->toHaveCount(1);
        expect($applicationImports)->toHaveCount(1);

        foreach ($applicationImports as $import) {
            expect(str_starts_with($import, '@/modules/'))->toBeTrue();
        }

        expect(substr_count($source, "\n"))->toBeLessThan(5);
        expect(preg_match('/\b(useState|useEffect|useForm|router\.)\b/', $source))->toBe(0);
    }
});

test('frontend modules do not import other frontend modules', function () {
    foreach (frontendFiles('modules') as $file) {
        $relativePath = str_replace('\\', '/', $file->getRelativePathname());
        $domain = explode('/', $relativePath)[0];
        $crossDomainImports = [];

        foreach (frontendImports($file) as $import) {
            $targetDomain = null;

            if (preg_match('~^@/modules/([^/]+)~', $import, $match) === 1) {
                $targetDomain = $match[1];
            } elseif (str_starts_with($import, '.')) {
                $target = normalizeFrontendPath($file->getPath().'/'.$import);

                if (preg_match('~/resources/js/modules/([^/]+)~', $target, $match) === 1) {
                    $targetDomain = $match[1];
                }
            }

            if ($targetDomain !== null && $targetDomain !== $domain) {
                $crossDomainImports[] = $import;
            }
        }

        expect($crossDomainImports)->toBeEmpty();
    }
});

test('frontend modules own their domain contracts', function () {
    foreach (frontendFiles('modules') as $file) {
        $source = $file->getContents();

        expect(preg_match("~from '@/types/(accounts|api-keys|automations|broadcasts|contacts|flows|inbox|notifications|pipelines|quick-replies)'~", $source))->toBe(0);
    }
});

test('shared frontend code does not import domain modules', function () {
    foreach (['components', 'hooks', 'layouts', 'lib', 'types'] as $directory) {
        foreach (frontendFiles($directory) as $file) {
            $moduleImports = array_filter(
                frontendImports($file),
                fn (string $import): bool => str_starts_with($import, '@/modules/')
                    || (str_starts_with($import, '.') && str_contains(
                        normalizeFrontendPath($file->getPath().'/'.$import),
                        '/resources/js/modules/',
                    )),
            );

            expect($moduleImports)->toBeEmpty();
        }
    }
});

test('frontend module UI does not import fixtures', function () {
    // ponytail: allowlist open until #86-93 replace these screens' fixtures
    // with real Account data; each entry drops when its ticket closes.
    $pendingRealData = [
        'contacts/ui/custom-fields-panel.tsx',
        'contacts/ui/tag-manager.tsx',
        'automations/ui/automation-logs-screen.tsx',
        'automations/ui/automations-screen.tsx',
        'automations/ui/edit-automation-screen.tsx',
        'broadcasts/ui/broadcast-screen.tsx',
        'broadcasts/ui/broadcasts-screen.tsx',
        'broadcasts/ui/step1-choose-template.tsx',
        'broadcasts/ui/step2-select-audience.tsx',
        'broadcasts/ui/step4-schedule-send.tsx',
        'broadcasts/ui/templates-screen.tsx',
        'dashboard/ui/dashboard-screen.tsx',
        'flows/ui/flow-editor-screen.tsx',
        'flows/ui/flow-runs-screen.tsx',
        'flows/ui/flows-screen.tsx',
        'inbox/ui/inbox-screen.tsx',
        'pipelines/ui/deal-form.tsx',
        'pipelines/ui/pipelines-screen.tsx',
    ];

    foreach (frontendFiles('modules') as $file) {
        $relativePath = str_replace('\\', '/', $file->getRelativePathname());

        if (str_ends_with($relativePath, '.test.ts') || str_ends_with($relativePath, '.test.tsx')) {
            continue;
        }

        if (in_array($relativePath, $pendingRealData, true)) {
            continue;
        }

        $fixtureImports = array_filter(
            frontendImports($file),
            fn (string $import): bool => $import === 'fixtures' || str_ends_with($import, '/fixtures'),
        );

        expect($fixtureImports)->toBeEmpty("{$relativePath} imports fixtures outside a test file.");
    }
});

test('frontend modules use Wayfinder for application URLs', function () {
    $applicationUrl = "~['\"`](/accounts|/agents|/automations|/broadcasts|/contacts|/dashboard|/flows|/inbox|/notifications|/pipelines|/settings)(?:[/\"'`?])~";

    foreach (frontendFiles('modules') as $file) {
        $source = $file->getContents();

        expect(preg_match($applicationUrl, $source))->toBe(0);
        expect(preg_match('~window\.location\.origin\}\s*/api/~', $source))->toBe(0);
    }
});
