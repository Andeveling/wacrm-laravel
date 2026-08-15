<?php

declare(strict_types=1);

const WARNING_COMPLEXITY = 8;
const MAX_COMPLEXITY = 10;
const MAX_CRAP = 30.0;
const FLOOR_COVERAGE = 75.5;

if ($argc === 2 && $argv[1] === '--self-test') {
    $directory = sys_get_temp_dir().'/wacrm-php-quality-'.bin2hex(random_bytes(6));
    mkdir($directory);
    file_put_contents($directory.'/coverage.xml', '<?xml version="1.0"?><coverage><project><metrics elements="100" coveredelements="75"/></project></coverage>');
    file_put_contents($directory.'/safe-crap4j.xml', '<?xml version="1.0"?><crap_result><methods/></crap_result>');
    file_put_contents($directory.'/crap4j.xml', '<?xml version="1.0"?><crap_result><methods><method><className>App\\Domain\\Example</className><methodName>risky</methodName><crap>30</crap><complexity>11</complexity></method></methods></crap_result>');

    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($directory.'/coverage.xml').' '.escapeshellarg($directory.'/safe-crap4j.xml');
    exec($command, $output, $coverageExitCode);

    file_put_contents($directory.'/coverage.xml', '<?xml version="1.0"?><coverage><project><metrics elements="100" coveredelements="76"/></project></coverage>');
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($directory.'/coverage.xml').' '.escapeshellarg($directory.'/crap4j.xml');
    exec($command, $output, $crapExitCode);

    unlink($directory.'/coverage.xml');
    unlink($directory.'/safe-crap4j.xml');
    unlink($directory.'/crap4j.xml');
    rmdir($directory);

    if ($coverageExitCode === 0 || $crapExitCode === 0) {
        fwrite(STDERR, "Synthetic PHP quality regressions did not fail the gate.\n");
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

$baseline = (float) trim((string) file_get_contents(__DIR__.'/php-coverage-baseline.txt'));

if ($baseline < FLOOR_COVERAGE) {
    fwrite(STDERR, sprintf("Coverage ratchet %.1f%% cannot fall below the committed %.1f%% floor.\n", $baseline, FLOOR_COVERAGE));
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

$crap = simplexml_load_file($crapFile);

foreach ($crap?->methods?->method ?? [] as $method) {
    $class = (string) $method->className;

    if (! str_starts_with($class, 'App\\Domain\\')) {
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
