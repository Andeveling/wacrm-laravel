import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const categories = [
  'architecture',
  'coverage',
  'mutation',
  'crap',
  'duplication',
  'phpstan',
];

function isIsoDate(value) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value ?? '')) {
    return false;
  }

  const date = new Date(`${value}T00:00:00Z`);

  return (
    !Number.isNaN(date.valueOf()) && date.toISOString().slice(0, 10) === value
  );
}

export function validateQualityExceptions(exceptions, today = new Date()) {
  const errors = [];

  for (const category of categories) {
    if (!Array.isArray(exceptions[category])) {
      errors.push(`${category} must be an array.`);
      continue;
    }

    for (const [index, exception] of exceptions[category].entries()) {
      const label = `${category}[${index}]`;

      if (typeof exception.reason !== 'string' || exception.reason === '') {
        errors.push(`${label} needs a reason.`);
      }

      if (!Number.isInteger(exception.issue) || exception.issue < 1) {
        errors.push(`${label} needs a tracking issue.`);
      }

      if (!isIsoDate(exception.expires)) {
        errors.push(`${label} needs an ISO expiry date.`);
      } else if (new Date(`${exception.expires}T00:00:00Z`) < today) {
        errors.push(`${label} expired on ${exception.expires}.`);
      }
    }
  }

  const baseline = exceptions.duplication?.[0]?.baseline;
  if (
    !baseline ||
    !['clones', 'duplicatedLines', 'duplicatedTokens'].every(
      (key) => Number.isInteger(baseline[key]) && baseline[key] >= 0,
    ) ||
    !Array.isArray(baseline.cloneFingerprints) ||
    !baseline.cloneFingerprints.every((fingerprint) =>
      /^[a-f0-9]{64}$/.test(fingerprint),
    )
  ) {
    errors.push('duplication[0] needs a non-negative baseline.');
  }

  return errors;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const path = resolve(process.argv[2] ?? 'quality-exceptions.json');
  const errors = validateQualityExceptions(
    JSON.parse(readFileSync(path, 'utf8')),
  );

  if (errors.length > 0) {
    console.error(
      `Quality exceptions check failed:\n${errors.map((error) => `  - ${error}`).join('\n')}`,
    );
    process.exit(1);
  }

  console.log('Quality exceptions check passed.');
}
