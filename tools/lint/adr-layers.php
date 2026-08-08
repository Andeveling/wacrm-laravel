<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;

/**
 * ADR 0001 enforced by the toolchain instead of by documentation.
 *
 * docs/adr/0001-adopt-adr-pattern.md describes the pattern;
 * until this script existed, nothing but goodwill made anyone follow
 * them. Every convention below is now a failing commit, so the
 * architecture holds whether the next change comes from a human, an
 * agent, or an agent that never read the ADR.
 *
 * Six rules:
 *   1. No class anywhere under app/ is named *Controller.
 *   2. Every route handled by app code points at App\Domain\<Ctx>\Actions\<X>.
 *   3. Actions are final and expose only __invoke.
 *   4. Responders are final, named *Responder, and never touch the DB.
 *   5. Results are final readonly, named *Result, and never import HTTP.
 *   6. Bounded contexts only contain the known ADR layers.
 *
 * Rule 2 boots the framework to read the real route table — a grep over
 * routes/ would miss routes registered from a provider or a package.
 * Nothing here touches the database, so it runs on a bare checkout.
 *
 * Usage:
 *   php tools/lint/adr-layers.php
 *
 * Exit 0 = clean. Exit 1 = violations found.
 */
require __DIR__.'/../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$appRoot = __DIR__.'/../../app';

/**
 * Every class under app/, as FQCN => absolute path. PSR-4 maps `App\` to
 * `app/`, so the path IS the namespace — no classmap dump needed, which
 * keeps this honest about files that exist but are never autoloaded.
 *
 * @return array<class-string, string>
 */
function appClasses(string $root): array
{
    static $classes = null;

    if ($classes !== null) {
        return $classes;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY,
    );

    $classes = [];
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($root) + 1, -4);
        $classes['App\\'.str_replace('/', '\\', $relative)] = $file->getPathname();
    }

    ksort($classes);

    return $classes;
}

/**
 * The classes of one ADR layer across every bounded context, e.g.
 * `App\Domain\Contacts\Actions\*` plus `App\Domain\Meta\Actions\*` for
 * layer `Actions`.
 *
 * @return array<class-string, string>
 */
function domainLayer(string $root, string $layer): array
{
    return array_filter(
        appClasses($root),
        fn (string $fqcn): bool => preg_match('#^App\\\\Domain\\\\\w+\\\\'.$layer.'\\\\#', $fqcn) === 1,
        ARRAY_FILTER_USE_KEY,
    );
}

/**
 * The `use` imports of a file. Grouped imports and `use function` /
 * `use const` are out of scope — the codebase uses neither, and a
 * dependency sneaking in through one would still have to name its
 * namespace here.
 *
 * @return list<string>
 */
function importsOf(string $path): array
{
    preg_match_all('/^use\s+(?!function\s|const\s)([^\s;]+)\s*;/m', (string) file_get_contents($path), $matches);

    return $matches[1];
}

/** @var list<string> $violations */
$violations = [];

// Rule 1 — the whole point of the migration: HTTP entry points are
// Actions in a bounded context, not methods bolted onto a controller.
// Matching on the class name means renaming the directory is no loophole.
foreach (array_keys(appClasses($appRoot)) as $fqcn) {
    if (str_ends_with($fqcn, 'Controller')) {
        $violations[] = "{$fqcn}: controllers are gone — make it an App\\Domain\\<Context>\\Actions\\<X> invokable";
    }
}

// Rule 2 — anchored and `@`-free on purpose: it rejects both a route
// pointing outside app/Domain and a `Class@method` reference, which is
// how a multi-purpose controller would creep back in.
foreach (Route::getRoutes() as $route) {
    $action = $route->getAction('controller');

    // Closures and `Route::inertia` have no controller string; vendor
    // handlers (Fortify, Passkeys) are outside our namespace.
    if (! is_string($action) || ! str_starts_with($action, 'App\\')) {
        continue;
    }

    if (preg_match('#^App\\\\Domain\\\\\w+\\\\Actions\\\\\w+$#', $action) !== 1) {
        $uri = $route->methods()[0].' /'.$route->uri();
        $violations[] = "{$uri}: handled by {$action} — routes must point at a single-Action class";
    }
}

// Rule 3 — ADR 0001 rule 1: an Action with a second public method is a
// controller wearing a different name.
foreach (array_keys(domainLayer($appRoot, 'Actions')) as $fqcn) {
    $class = new ReflectionClass($fqcn);

    if (! $class->isFinal()) {
        $violations[] = "{$fqcn}: Actions must be final";
    }

    if (! $class->hasMethod('__invoke')) {
        $violations[] = "{$fqcn}: Actions must define __invoke";
    }

    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (! in_array($method->getName(), ['__construct', '__invoke'], true)) {
            $violations[] = "{$fqcn}::{$method->getName()}(): an Action exposes only __invoke — split it";
        }
    }
}

// Rule 4 — ADR 0001 rule 3: a Responder that reaches for a model or the
// query builder is re-deciding what the Action already decided.
foreach (domainLayer($appRoot, 'Responders') as $fqcn => $path) {
    if (! str_ends_with($fqcn, 'Responder')) {
        $violations[] = "{$fqcn}: Responder class names must end in Responder";
    }

    if (! (new ReflectionClass($fqcn))->isFinal()) {
        $violations[] = "{$fqcn}: Responders must be final";
    }

    foreach (importsOf($path) as $import) {
        if (str_starts_with($import, 'App\\Models') || str_starts_with($import, 'Illuminate\\Database')) {
            $violations[] = "{$fqcn}: imports {$import} — Responders are transport only";
        }
    }
}

// Rule 5 — a Result that imports a response type has stopped being a
// value object; that decision belongs to the Responder.
foreach (domainLayer($appRoot, 'Results') as $fqcn => $path) {
    $class = new ReflectionClass($fqcn);

    if (! str_ends_with($fqcn, 'Result')) {
        $violations[] = "{$fqcn}: Result class names must end in Result";
    }

    if (! $class->isFinal() || ! $class->isReadOnly()) {
        $violations[] = "{$fqcn}: Results must be `final readonly`";
    }

    foreach (importsOf($path) as $import) {
        if (str_starts_with($import, 'Illuminate\\Http') || str_starts_with($import, 'Inertia\\')) {
            $violations[] = "{$fqcn}: imports {$import} — Results know nothing about HTTP";
        }
    }
}

// Rule 6 — Services is not in ADR 0001's diagram but earns its place: it
// holds logic two Actions share (InvitationIssuer backs both the legacy
// and the account-scoped invite). Anything outside this list is a new
// layer, and a new layer needs an ADR before it needs code.
$allowedLayers = ['Actions', 'Responders', 'Results', 'Services', 'Support'];

foreach (array_keys(appClasses($appRoot)) as $fqcn) {
    if (preg_match('#^App\\\\Domain\\\\(\w+)\\\\(\w+)\\\\#', $fqcn, $matches) !== 1) {
        continue;
    }

    if (! in_array($matches[2], $allowedLayers, true)) {
        $violations[] = "{$fqcn}: unknown layer [{$matches[2]}] in context [{$matches[1]}] — allowed: ".implode(', ', $allowedLayers);
    }
}

if ($violations === []) {
    fwrite(STDOUT, "ADR layer check passed: app/ matches ADR 0001.\n");

    exit(0);
}

fwrite(STDERR, 'ADR layer check failed ('.count($violations)." violations):\n");
foreach ($violations as $violation) {
    fwrite(STDERR, "  - {$violation}\n");
}
fwrite(STDERR, "\nSee docs/adr/0001-adopt-adr-pattern.md\n");

exit(1);
