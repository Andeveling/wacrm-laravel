<?php

declare(strict_types=1);

/**
 * ADR 0004 enforced by the toolchain instead of by documentation.
 *
 * The ADR assigns every behaviour to the highest public seam that can
 * observe it, and states that Domain and Unit cover pure PHP rules with
 * no database. Nothing checked that, so a DB-backed test could sit in
 * Unit indefinitely — one did.
 *
 * Five rules:
 *   1. Every directory under tests/ is a registered testsuite or a known
 *      support directory. A stray one never runs.
 *   2. Every file inside a suite is named *Test.php.
 *   3. Domain and Unit never touch the database.
 *   4. No *ControllerTest — app/ has no controllers (ADR 0001).
 *   5. Feature and Domain subdirectories name a real bounded context or
 *      an allowed cross-cutting seam.
 *
 * Pure filesystem plus phpunit.xml; no framework boot, no database.
 *
 * Usage:
 *   php tools/lint/test-layout.php
 *
 * Exit 0 = clean. Exit 1 = violations found.
 */
$root = dirname(__DIR__, 2);
$testsRoot = $root.'/tests';

/**
 * Directories that hold shared helpers rather than tests, so they are
 * deliberately absent from phpunit.xml.
 */
const SUPPORT_DIRS = ['Concerns', 'Fixtures'];

/**
 * Seams that are not bounded contexts: cross-cutting surfaces that no
 * single app/Domain context owns.
 */
const CROSS_CUTTING_SEAMS = ['Api', 'Auth', 'Concerns', 'Jobs'];

/**
 * Traits and helpers that mean "this test talks to the database".
 * ::factory() and assertDatabase* are matched separately as calls.
 */
const DATABASE_MARKERS = [
    'RefreshDatabase',
    'LazilyRefreshDatabase',
    'DatabaseMigrations',
    'DatabaseTruncation',
];

/**
 * Directories registered as testsuites in phpunit.xml, relative to
 * tests/ — the single source of truth for what actually runs.
 *
 * @return list<string>
 */
function registeredSuites(string $root): array
{
    $xml = simplexml_load_file($root.'/phpunit.xml');

    if ($xml === false) {
        fwrite(STDERR, "Could not parse phpunit.xml.\n");
        exit(1);
    }

    $suites = [];
    foreach ($xml->testsuites->testsuite as $suite) {
        foreach ($suite->directory as $directory) {
            $suites[] = basename(trim((string) $directory));
        }
    }

    return $suites;
}

/**
 * Every PHP file below a directory.
 *
 * @return list<string>
 */
function phpFilesIn(string $directory): array
{
    if (! is_dir($directory)) {
        return [];
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY,
    );

    $paths = [];
    foreach ($files as $file) {
        if ($file->getExtension() === 'php') {
            $paths[] = $file->getPathname();
        }
    }

    sort($paths);

    return $paths;
}

/** The bounded contexts that exist under app/Domain. */
function boundedContexts(string $root): array
{
    return array_map('basename', glob($root.'/app/Domain/*', GLOB_ONLYDIR) ?: []);
}

$suites = registeredSuites($root);
$contexts = boundedContexts($root);

/** @var list<string> $violations */
$violations = [];

// Rule 1 — a directory that is neither a testsuite nor a support folder
// holds tests nobody runs. Silent dead weight is worse than none.
foreach (glob($testsRoot.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
    $name = basename($directory);

    if (! in_array($name, $suites, true) && ! in_array($name, SUPPORT_DIRS, true)) {
        $violations[] = "tests/{$name}: not a testsuite in phpunit.xml and not a support directory — these tests never run";
    }
}

foreach ($suites as $suite) {
    foreach (phpFilesIn($testsRoot.'/'.$suite) as $path) {
        $relative = substr($path, strlen($root) + 1);
        $basename = basename($path);

        // Rule 2 — Pest only collects *Test.php inside a suite, so a
        // helper parked here is silently skipped. tests/Concerns and
        // tests/Fixtures are where shared code belongs.
        if (! str_ends_with($basename, 'Test.php')) {
            $violations[] = "{$relative}: files inside a suite must be named *Test.php — move helpers to tests/Concerns or tests/Fixtures";
        }

        // Rule 4 — mirrors rule 1 of the ADR layer lint: controllers are
        // gone, so a test still named after one is stale.
        if (str_ends_with($basename, 'ControllerTest.php')) {
            $violations[] = "{$relative}: named after a controller, and app/ has no controllers — name it after the Action or the seam";
        }

        // Rule 3 — ADR 0004: Domain and Unit cover pure PHP rules. A
        // database here makes the fast suites slow and order-dependent.
        if (in_array($suite, ['Domain', 'Unit'], true)) {
            $source = (string) file_get_contents($path);

            foreach (DATABASE_MARKERS as $marker) {
                if (str_contains($source, $marker)) {
                    $violations[] = "{$relative}: uses {$marker} — the {$suite} suite runs without a database (ADR 0004); move it to Feature";
                }
            }

            if (str_contains($source, '::factory()')) {
                $violations[] = "{$relative}: builds models with ::factory() — the {$suite} suite runs without a database (ADR 0004); move it to Feature";
            }

            if (preg_match('/assertDatabase\w+\(/', $source) === 1) {
                $violations[] = "{$relative}: asserts against the database — the {$suite} suite runs without a database (ADR 0004); move it to Feature";
            }
        }
    }
}

// Rule 5 — Feature and Domain mirror the bounded contexts of app/Domain.
// A directory that matches no context and no cross-cutting seam is
// either a typo or a context that was renamed and left a stale folder.
foreach (['Feature', 'Domain'] as $suite) {
    foreach (glob($testsRoot.'/'.$suite.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
        $name = basename($directory);

        if (! in_array($name, $contexts, true) && ! in_array($name, CROSS_CUTTING_SEAMS, true)) {
            $violations[] = "tests/{$suite}/{$name}: neither a bounded context under app/Domain nor an allowed seam (".implode(', ', CROSS_CUTTING_SEAMS).')';
        }
    }
}

if ($violations === []) {
    fwrite(STDOUT, "Test layout check passed: tests/ matches ADR 0004.\n");

    exit(0);
}

fwrite(STDERR, 'Test layout check failed ('.count($violations)." violations):\n");
foreach ($violations as $violation) {
    fwrite(STDERR, "  - {$violation}\n");
}
fwrite(STDERR, "\nSee docs/adr/0004-pruebas-en-seams-publicos-con-postgresql.md\n");

exit(1);
