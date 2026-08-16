import { spawnSync } from 'node:child_process';
import { rmSync, writeFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
  duplicationRegressions,
  mergeDuplicationReports,
} from './check-duplication.mjs';

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

  it('keeps AND metrics while unioning OR clones', () => {
    const lineClone = {
      fragment: 'five short lines',
      lines: 5,
      tokens: 20,
      firstFile: { name: 'app/One.php' },
      secondFile: { name: 'app/Two.php' },
    };
    const andReport = {
      statistics: {
        total: { clones: 60, duplicatedLines: 1016, duplicatedTokens: 5419 },
      },
      duplicates: [],
    };

    const merged = mergeDuplicationReports(andReport, {
      statistics: {
        total: { clones: 1, duplicatedLines: 5, duplicatedTokens: 20 },
      },
      duplicates: [lineClone],
    });

    expect(merged.statistics.total).toEqual(andReport.statistics.total);
    expect(merged.duplicates).toHaveLength(1);
  });

  it('makes the gate fail for a five-line clone below the token threshold', () => {
    const firstPath = 'app/Models/TemporaryQualityCloneA.php';
    const secondPath = 'app/Models/TemporaryQualityCloneB.php';
    const cloneBody = `        $alpha = 1;
        $beta = 2;
        $gamma = 3;
        $delta = 4;
        $epsilon = 5;
`;

    try {
      writeFileSync(
        firstPath,
        `<?php
class TemporaryQualityCloneA
{
    public function uniqueA(): string
    {
        return 'clone-a-only';
    }

    public function copy(): void
    {
${cloneBody}    }
}
`,
      );
      writeFileSync(
        secondPath,
        `<?php
class TemporaryQualityCloneB
{
    public function uniqueB(): string
    {
        return 'clone-b-only';
    }

    public function copy(): void
    {
${cloneBody}    }
}
`,
      );
      const result = spawnSync('node', ['scripts/check-duplication.mjs'], {
        encoding: 'utf8',
      });

      expect(result.status).toBe(1);
      expect(`${result.stdout}${result.stderr}`).toContain(
        'Duplication check failed',
      );
    } finally {
      rmSync(firstPath, { force: true });
      rmSync(secondPath, { force: true });
    }
  });
});
