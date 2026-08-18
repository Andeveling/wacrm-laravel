# Issue Tracker

Issues live in **GitHub Issues** on `github.com/Andeveling/wacrm-laravel`.

Skills (`to-tickets`, `triage`, `to-spec`, `qa`) use the `gh` CLI to read and write issues.

`develop` is the default branch. A PR merged into `develop` closes the linked issue when the body or a commit says `Closes #N`. That merge deploys the test VPS. `main` is unused for deploy.

## Pull requests as a request surface

**Off.** External PRs are not treated as issues in the triage queue.

To flip this on (useful for open-source projects), change this to **On.** and the triage skill will include open PRs from external contributors.
