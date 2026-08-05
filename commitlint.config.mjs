/**
 * Conventional Commits, enforced by the commit-msg hook (see lefthook.yml).
 *
 * config-conventional already supplies the type-enum the repo uses
 * (feat, fix, chore, docs, refactor, test, …) and the case/empty rules.
 * Only the header length differs: 72 keeps `git log --oneline` readable,
 * where the preset default of 100 does not.
 */
export default {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'header-max-length': [2, 'always', 72],
  },
};
