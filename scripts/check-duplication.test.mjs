import { spawnSync } from 'node:child_process';
import { copyFileSync, rmSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { duplicationRegressions } from './check-duplication.mjs';

const clone = {
  fragment: 'five duplicated lines of source code',
  firstFile: { name: 'app/One.php' },
  secondFile: { name: 'app/Two.php' },
};

describe('duplication regression detection', () => {
  it('rejects a synthetic clone even when aggregate metrics stay flat', () => {
    const report = {
      statistics: {
        total: { clones: 0, duplicatedLines: 0, duplicatedTokens: 0 },
      },
      duplicates: [clone],
    };
    const baseline = {
      clones: 0,
      duplicatedLines: 0,
      duplicatedTokens: 0,
      cloneFingerprints: [],
    };

    expect(duplicationRegressions(report, baseline)).toHaveLength(1);
  });

  it('rejects every aggregate regression', () => {
    const report = {
      statistics: {
        total: { clones: 1, duplicatedLines: 5, duplicatedTokens: 50 },
      },
      duplicates: [],
    };
    const baseline = {
      clones: 0,
      duplicatedLines: 0,
      duplicatedTokens: 0,
      cloneFingerprints: [],
    };

    expect(duplicationRegressions(report, baseline)).toHaveLength(3);
  });

  it('makes the gate fail for a synthetic five-line clone', () => {
    const clonePath = 'app/Models/TemporaryQualityClone.php';

    try {
      copyFileSync('app/Models/Tag.php', clonePath);
      const result = spawnSync('node', ['scripts/check-duplication.mjs'], {
        encoding: 'utf8',
      });

      expect(result.status).toBe(1);
      expect(result.stderr).toContain('Duplication check failed');
    } finally {
      rmSync(clonePath, { force: true });
    }
  });
});
