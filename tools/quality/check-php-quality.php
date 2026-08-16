<?php

declare(strict_types=1);

const WARNING_COMPLEXITY = 8;
const MAX_COMPLEXITY = 10;
const MAX_CRAP = 30.0;
const FLOOR_COVERAGE = 75.5;

if ($argc === 2 && $argv[1] === '--self-test') {
    $directory = sys_get_temp_dir().'/wacrm-php-quality-'.bin2hex(random_bytes(6));
    mkdir($directory);
    file_put_contents($directory.'/baseline.txt', "75.5\n");
    file_put_contents($directory.'/coverage.xml', '<?xml version="1.0"?><coverage><project><metrics elements="100" coveredelements="75"/></project></coverage>');
    file_put_contents($directory.'/safe-crap4j.xml', '<?xml version="1.0"?><crap_result><methods/></crap_result>');
    file_put_contents($directory.'/crap4j.xml', '<?xml version="1.0"?><crap_result><methods><method><className>App\\Domain\\Example</className><methodName>risky</methodName><crap>30</crap><complexity>11</complexity></method></methods></crap_result>');
    file_put_contents($directory.'/exceptions.json', json_encode([
        'crap' => [[
            'paths' => ['App\\Domain\\Example'],
            'reason' => 'synthetic allowlist',
            'issue' => 124,
            'expires' => '2099-01-01',
        ]],
    ]));
    file_put_contents($directory.'/expired.json', json_encode([
        'crap' => [[
            'paths' => ['App\\Domain\\Example'],
            'reason' => 'expired synthetic allowlist',
            'issue' => 124,
            'expires' => '2020-01-01',
        ]],
    ]));

    $run = static function (string $coverage, string $crap, array $env) use ($directory): int {
        $command = 'PHP_COVERAGE_BASELINE_PATH='.escapeshellarg($directory.'/baseline.txt');
        foreach ($env as $name => $value) {
            $command .= ' '.$name.'='.escapeshellarg($value);
        }
        $command .= ' '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($coverage).' '.escapeshellarg($crap);

        exec($command, $output, $exitCode);

        return $exitCode;
    };

    $coverageExitCode = $run($directory.'/coverage.xml', $directory.'/safe-crap4j.xml', []);

    file_put_contents($directory.'/coverage.xml', '<?xml version="1.0"?><coverage><project><metrics elements="100" coveredelements="76"/></project></coverage>');
    $crapExitCode = $run($directory.'/coverage.xml', $directory.'/crap4j.xml', []);
    $allowlistedExitCode = $run($directory.'/coverage.xml', $directory.'/crap4j.xml', [
        'QUALITY_EXCEPTIONS_PATH' => $directory.'/exceptions.json',
    ]);
    $expiredExitCode = $run($directory.'/coverage.xml', $directory.'/crap4j.xml', [
        'QUALITY_EXCEPTIONS_PATH' => $directory.'/expired.json',
    ]);

    file_put_contents($directory.'/coverage.xml', '<?xml version="1.0"?><coverage><project><metrics elements="100" coveredelements="80"/></project></coverage>');
    $loweredBaselineExitCode = $run($directory.'/coverage.xml', $directory.'/safe-crap4j.xml', [
        'PHP_COVERAGE_PREVIOUS_BASELINE' => '80.0',
    ]);

    file_put_contents($directory.'/baseline.txt', "80.0\n");
    $greenExitCode = $run($directory.'/coverage.xml', $directory.'/safe-crap4j.xml', [
        'PHP_COVERAGE_PREVIOUS_BASELINE' => '80.0',
    ]);

    foreach (scandir($directory) ?: [] as $file) {
        if ($file !== '.' && $file !== '..') {
            unlink($directory.'/'.$file);
        }
    }
    rmdir($directory);

    if ($coverageExitCode === 0 || $crapExitCode === 0 || $expiredExitCode === 0 || $loweredBaselineExitCode === 0) {
        fwrite(STDERR, "Synthetic PHP quality regressions did not fail the gate.\n");
        exit(1);
    }

    if ($allowlistedExitCode !== 0 || $greenExitCode !== 0) {
        fwrite(STDERR, "Allowlisted or green PHP quality cases failed the gate.\n");
        exit(1);
    }

    echo "Synthetic PHP quality regressions fail the gate as expected.\n";
    exit(0);
}

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php tools/quality/check-php-quality.php <coverage.xml> <crap4j.xml>\n");
    exit(2);
}

[$coverageFile, $crapFile] = array_slice($argv, 1);

foreach ([$coverageFile, $crapFile] as $report) {
    if (! is_file($report)) {
        fwrite(STDERR, "Missing PHP quality report: {$report}\n");
        exit(2);
    }
}

$baselinePath = getenv('PHP_COVERAGE_BASELINE_PATH') ?: __DIR__.'/php-coverage-baseline.txt';
$baseline = (float) trim((string) file_get_contents($baselinePath));

if ($baseline < FLOOR_COVERAGE) {
    fwrite(STDERR, sprintf("Coverage ratchet %.1f%% cannot fall below the committed %.1f%% floor.\n", $baseline, FLOOR_COVERAGE));
    exit(2);
}

$previousBaseline = previousCoverageBaseline();

if ($previousBaseline !== null && $baseline < $previousBaseline) {
    fwrite(STDERR, sprintf("Coverage ratchet %.1f%% cannot fall below the previously committed %.1f%%.\n", $baseline, $previousBaseline));
    exit(2);
}

$coverage = simplexml_load_file($coverageFile);
$metrics = $coverage?->project?->metrics;
$elements = (int) ($metrics['elements'] ?? 0);
$coveredElements = (int) ($metrics['coveredelements'] ?? 0);

if ($elements === 0) {
    fwrite(STDERR, "Coverage report has no measured elements.\n");
    exit(2);
}

$percentage = 100 * $coveredElements / $elements;
$failures = [];

if ($percentage < $baseline) {
    $failures[] = sprintf('PHP coverage %.2f%% is below the %.1f%% ratchet.', $percentage, $baseline);
}

$allowlisted = activeCrapAllowlist();
$crap = simplexml_load_file($crapFile);

foreach ($crap?->methods?->method ?? [] as $method) {
    $class = (string) $method->className;

    if (! str_starts_with($class, 'App\\Domain\\') || isAllowlisted($class, $allowlisted)) {
        continue;
    }

    $name = $class.'::'.(string) $method->methodName;
    $complexity = (int) $method->complexity;
    $crapScore = (float) $method->crap;

    if ($complexity >= WARNING_COMPLEXITY) {
        fwrite(STDERR, "WARNING: {$name} has cyclomatic complexity {$complexity}.\n");
    }

    if ($crapScore >= MAX_CRAP) {
        $failures[] = "{$name} has CRAP {$crapScore} (maximum is < ".MAX_CRAP.').';
    }

    if ($complexity > MAX_COMPLEXITY) {
        $failures[] = "{$name} has cyclomatic complexity {$complexity} (maximum is ".MAX_COMPLEXITY.').';
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}

printf("PHP quality gate passed: %.2f%% coverage (ratchet %.1f%%).\n", $percentage, $baseline);

/**
 * @return list<string>
 */
function activeCrapAllowlist(): array
{
    $path = getenv('QUALITY_EXCEPTIONS_PATH') ?: dirname(__DIR__, 2).'/quality-exceptions.json';

    if (! is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (! is_array($decoded) || ! is_array($decoded['crap'] ?? null)) {
        return [];
    }

    $today = (new DateTimeImmutable('today', new DateTimeZone('UTC')))->format('Y-m-d');
    $paths = [];

    foreach ($decoded['crap'] as $exception) {
        if (! is_array($exception) || ! is_array($exception['paths'] ?? null)) {
            continue;
        }

        $expires = $exception['expires'] ?? null;

        if (! is_string($expires) || $expires < $today) {
            continue;
        }

        foreach ($exception['paths'] as $class) {
            if (is_string($class) && $class !== '') {
                $paths[] = $class;
            }
        }
    }

    return $paths;
}

/**
 * @param  list<string>  $allowlisted
 */
function isAllowlisted(string $class, array $allowlisted): bool
{
    foreach ($allowlisted as $path) {
        if ($class === $path || str_starts_with($class, rtrim($path, '\\').'\\')) {
            return true;
        }
    }

    return false;
}

function previousCoverageBaseline(): ?float
{
    $override = getenv('PHP_COVERAGE_PREVIOUS_BASELINE');

    if (is_string($override) && $override !== '') {
        return (float) $override;
    }

    $ref = getenv('PHP_COVERAGE_BASELINE_REF') ?: 'origin/develop';
    $command = 'git show '.escapeshellarg($ref.':tools/quality/php-coverage-baseline.txt').' 2>/dev/null';
    $output = [];
    exec($command, $output, $exitCode);

    if ($exitCode !== 0 || $output === []) {
        return null;
    }

    return (float) trim(implode("\n", $output));
}
