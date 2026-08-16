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

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const exceptions = JSON.parse(
    readFileSync(resolve('quality-exceptions.json'), 'utf8'),
  );
  const baseline = exceptions.duplication[0].baseline;
  const output = mkdtempSync(join(tmpdir(), 'wacrm-jscpd-'));

  try {
    execFileSync('node_modules/.bin/jscpd', ['--output', output], {
      stdio: 'inherit',
    });

    const report = JSON.parse(
      readFileSync(join(output, 'jscpd-report.json'), 'utf8'),
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
    rmSync(output, { recursive: true, force: true });
  }
}
