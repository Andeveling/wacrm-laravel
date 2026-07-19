<?php

declare(strict_types=1);

/**
 * Flags inline fully-qualified class chains that bypass the `use` statement
 * convention — e.g. `\App\Http\Controllers\X::class`, `Foo::method()`,
 * `Foo::CONST`. Token-aware so strings, comments and docblocks are skipped;
 * `use` import blocks are skipped.
 *
 * Tokens emitted as `T_NAME_FULLY_QUALIFIED` (PHP 8+) by `token_get_all`
 * are matched directly — the whole `\App\Foo\Bar` chain is a single token.
 * ponytail: only flags `FQCN::ident`. `new \App\Foo()` without `::` is not
 * covered; extend when needed.
 *
 * Usage:
 *   php tools/lint/no-inline-fqcn.php [path ...]
 *
 * Default paths: app routes tests database bootstrap
 * Exit 0 = clean. Exit 1 = violations found.
 */
$paths = $argc > 1 ? array_slice($argv, 1) : ['app', 'routes', 'tests', 'database', 'bootstrap'];

$violations = [];
foreach ($paths as $path) {
    if (is_file($path)) {
        if (str_ends_with($path, '.php')) {
            scan($path, $violations);
        }

        continue;
    }
    if (! is_dir($path)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY,
    );
    foreach ($it as $info) {
        if ($info->isFile() && str_ends_with($info->getFilename(), '.php')) {
            scan($info->getPathname(), $violations);
        }
    }
}

if ($violations !== []) {
    fwrite(STDERR, "Inline FQCNs found. Always use `use` statements:\n\n");
    foreach ($violations as $v) {
        fwrite(STDERR, sprintf("  %s:%d  %s\n", $v['file'], $v['line'], $v['snippet']));
    }
    fwrite(STDERR, sprintf("\n%d violation(s).\n", count($violations)));
    exit(1);
}

echo "OK\n";
exit(0);

/**
 * @param  array<int, array{file:string,line:int,snippet:string}>  $violations
 */
function scan(string $file, array &$violations): void
{
    $source = file_get_contents($file);
    $tokens = token_get_all($source);

    $count = count($tokens);
    $i = 0;
    $useBlockEnds = [];

    while ($i < $count) {
        while ($useBlockEnds !== [] && $useBlockEnds[0] === $i) {
            array_shift($useBlockEnds);
        }

        $tok = $tokens[$i];

        if (is_array($tok)) {
            $kind = $tok[0];

            if ($kind === T_USE) {
                $end = findUseStatementEnd($tokens, $i, $count);
                $useBlockEnds[] = $end;
                $i = $end + 1;

                continue;
            }

            if (in_array($kind, [
                T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING,
                T_INLINE_HTML, T_OPEN_TAG, T_CLOSE_TAG, T_WHITESPACE,
            ], true)) {
                $i++;

                continue;
            }

            if ($kind === T_NAME_FULLY_QUALIFIED) {
                $match = matchFqcnStaticAccess($tokens, $i, $count);
                if ($match !== null) {
                    $violations[] = [
                        'file' => $file,
                        'line' => $match['line'],
                        'snippet' => $match['snippet'],
                    ];
                }
            }
        }

        $i++;
    }
}

/**
 * Given `$start` indexes a `T_NAME_FULLY_QUALIFIED`, returns [line, snippet]
 * if it's followed (after whitespace) by `::ident`; null otherwise.
 */
function matchFqcnStaticAccess(array $tokens, int $start, int $count): ?array
{
    $fqcn = $tokens[$start][1];

    $i = $start + 1;
    while ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
        $i++;
    }
    if ($i >= $count || ! is_array($tokens[$i]) || $tokens[$i][0] !== T_DOUBLE_COLON) {
        return null;
    }
    $i++;
    while ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
        $i++;
    }
    $identTok = $tokens[$i];
    if (! is_array($identTok)) {
        return null;
    }
    // After `::`, accept either T_STRING (`::method`, `::CONST`) or T_CLASS
    // (PHP 8+ tokenizes `::class` as `T_DOUBLE_COLON T_CLASS`).
    if (! in_array($identTok[0], [T_STRING, T_CLASS], true)) {
        return null;
    }
    $ident = $identTok[1];

    return [
        'line' => $tokens[$start][2],
        'snippet' => $fqcn.'::'.$ident,
    ];
}

/**
 * Walks forward from $useStart to find the `;` that ends the statement.
 * Handles comma-separated (`use Foo\Bar, Foo\Baz as Baz;`) and grouped
 * (`use Foo\{Bar, Baz};`) imports.
 */
function findUseStatementEnd(array $tokens, int $useStart, int $count): int
{
    for ($i = $useStart + 1; $i < $count; $i++) {
        $tok = $tokens[$i];
        if (is_string($tok) && $tok === ';') {
            return $i;
        }
    }

    return $count - 1;
}
