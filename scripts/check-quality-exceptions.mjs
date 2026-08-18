import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const schema = JSON.parse(
  readFileSync(
    resolve(
      dirname(fileURLToPath(import.meta.url)),
      '../schemas/quality-exceptions.schema.json',
    ),
    'utf8',
  ),
);

const categories = Object.keys(schema.properties).filter(
  (key) => key !== '$schema',
);

function isIsoDate(value) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value ?? '')) {
    return false;
  }

  const date = new Date(`${value}T00:00:00Z`);

  return (
    !Number.isNaN(date.valueOf()) && date.toISOString().slice(0, 10) === value
  );
}

function validateCategoryKeys(exceptions) {
  const errors = [];

  for (const key of Object.keys(exceptions)) {
    if (key !== '$schema' && !categories.includes(key)) {
      errors.push(`${key} is not a quality-exception category.`);
    }
  }

  return errors;
}

function validateException(exception, label, today) {
  const errors = [];

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

  return errors;
}

function validateCategoryExceptions(exceptions, today) {
  const errors = [];

  for (const category of categories) {
    if (!Array.isArray(exceptions[category])) {
      errors.push(`${category} must be an array.`);
      continue;
    }

    for (const [index, exception] of exceptions[category].entries()) {
      errors.push(
        ...validateException(exception, `${category}[${index}]`, today),
      );
    }
  }

  return errors;
}

function validateDuplicationBaseline(exceptions) {
  const baseline = exceptions.duplication?.[0]?.baseline;

  const hasValidCounters =
    baseline &&
    ['clones', 'duplicatedLines', 'duplicatedTokens'].every(
      (key) => Number.isInteger(baseline[key]) && baseline[key] >= 0,
    );
  const hasValidFingerprints =
    Array.isArray(baseline?.cloneFingerprints) &&
    baseline.cloneFingerprints.every((fingerprint) =>
      /^[a-f0-9]{64}$/.test(fingerprint),
    );

  if (!hasValidCounters || !hasValidFingerprints) {
    return ['duplication[0] needs a non-negative baseline.'];
  }

  return [];
}

export function validateQualityExceptions(exceptions, today = new Date()) {
  return [
    ...validateCategoryKeys(exceptions),
    ...validateCategoryExceptions(exceptions, today),
    ...validateDuplicationBaseline(exceptions),
  ];
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
