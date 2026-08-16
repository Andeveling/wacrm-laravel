import { spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { validateQualityExceptions } from './check-quality-exceptions.mjs';

const valid = {
  architecture: [],
  coverage: [],
  mutation: [],
  crap: [],
  phpstan: [],
  duplication: [
    {
      baseline: {
        clones: 0,
        duplicatedLines: 0,
        duplicatedTokens: 0,
        cloneFingerprints: [],
      },
      reason: 'Test baseline.',
      issue: 127,
      expires: '2026-12-31',
    },
  ],
};

describe('quality exception validation', () => {
  it('accepts a complete, future-dated allowlist', () => {
    expect(
      validateQualityExceptions(valid, new Date('2026-01-01T00:00:00Z')),
    ).toEqual([]);
  });

  it('rejects expired and invalid expiry dates', () => {
    const expired = structuredClone(valid);
    expired.coverage.push({
      reason: 'Expired.',
      issue: 127,
      expires: '2020-01-01',
    });
    const invalid = structuredClone(valid);
    invalid.mutation.push({
      reason: 'Invalid.',
      issue: 127,
      expires: '2026-99-99',
    });

    expect(
      validateQualityExceptions(expired, new Date('2026-01-01T00:00:00Z')),
    ).toContain('coverage[0] expired on 2020-01-01.');
    expect(
      validateQualityExceptions(invalid, new Date('2026-01-01T00:00:00Z')),
    ).toContain('mutation[0] needs an ISO expiry date.');
  });

  it('makes the gate fail for an expired exception', () => {
    const directory = mkdtempSync(join(tmpdir(), 'wacrm-expired-exception-'));
    const path = join(directory, 'exceptions.json');
    const expired = structuredClone(valid);
    expired.coverage.push({
      reason: 'Expiry proof.',
      issue: 127,
      expires: '2020-01-01',
    });

    try {
      writeFileSync(path, JSON.stringify(expired));
      const result = spawnSync(
        'node',
        ['scripts/check-quality-exceptions.mjs', path],
        { encoding: 'utf8' },
      );

      expect(result.status).toBe(1);
      expect(result.stderr).toContain('expired on 2020-01-01');
    } finally {
      rmSync(directory, { recursive: true, force: true });
    }
  });
});
