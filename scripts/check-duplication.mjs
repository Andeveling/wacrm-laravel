import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export function cloneFingerprint(clone) {
  const files = [clone.firstFile.name, clone.secondFile.name].sort();

  return createHash('sha256')
    .update(`${clone.fragment}\0${files.join('\0')}`)
    .digest('hex');
}

export function mergeDuplicationReports(metricReport, ...thresholdReports) {
  const byFingerprint = new Map(
    metricReport.duplicates.map((clone) => [cloneFingerprint(clone), clone]),
  );

  for (const report of thresholdReports) {
    for (const clone of report.duplicates) {
      byFingerprint.set(cloneFingerprint(clone), clone);
    }
  }

  return {
    statistics: metricReport.statistics,
    duplicates: [...byFingerprint.values()],
  };
}

export function duplicationRegressions(report, baseline) {
  const metricRegressions = ['clones', 'duplicatedLines', 'duplicatedTokens']
    .filter((metric) => report.statistics.total[metric] > baseline[metric])
    .map(
      (metric) =>
        `${metric} increased from ${baseline[metric]} to ${report.statistics.total[metric]}`,
    );
  const accepted = new Set(baseline.cloneFingerprints);
  const newClones = report.duplicates
    .map(cloneFingerprint)
    .filter((fingerprint) => !accepted.has(fingerprint));

  return [
    ...metricRegressions,
    ...newClones.map((fingerprint) => `new clone ${fingerprint}`),
  ];
}

function loadReport(output) {
  return JSON.parse(readFileSync(join(output, 'jscpd-report.json'), 'utf8'));
}

function runJscpd(output, minLines, minTokens) {
  execFileSync(
    'node_modules/.bin/jscpd',
    [
      '--output',
      output,
      '--min-lines',
      String(minLines),
      '--min-tokens',
      String(minTokens),
    ],
    { stdio: 'inherit' },
  );

  return loadReport(output);
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const exceptions = JSON.parse(
    readFileSync(resolve('quality-exceptions.json'), 'utf8'),
  );
  const baseline = exceptions.duplication[0].baseline;
  const metricOutput = mkdtempSync(join(tmpdir(), 'wacrm-jscpd-metrics-'));
  const lineOutput = mkdtempSync(join(tmpdir(), 'wacrm-jscpd-lines-'));
  const tokenOutput = mkdtempSync(join(tmpdir(), 'wacrm-jscpd-tokens-'));

  try {
    const report = mergeDuplicationReports(
      runJscpd(metricOutput, 5, 50),
      runJscpd(lineOutput, 5, 1),
      runJscpd(tokenOutput, 1, 50),
    );
    const regressions = duplicationRegressions(report, baseline);

    if (regressions.length > 0) {
      console.error(
        `Duplication check failed:\n${regressions.map((error) => `  - ${error}`).join('\n')}`,
      );
      process.exitCode = 1;
    } else {
      console.log('Duplication check passed: the baseline did not increase.');
    }
  } finally {
    rmSync(metricOutput, { recursive: true, force: true });
    rmSync(lineOutput, { recursive: true, force: true });
    rmSync(tokenOutput, { recursive: true, force: true });
  }
}
