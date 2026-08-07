# workflow
- Assistant's primary role is orchestrating pending tasks from the migration map and grilling sessions — implementation is delegated to other agents. Confidence: 0.75
- When checking for pending tasks, grilling sessions, or issues, consult GitHub issues (gh CLI) as the source of truth — not local .scratch/ directories or git branch scanning alone. Confidence: 0.60
- Make atomic commits — one commit per discrete change or issue. Confidence: 0.65
- When delegation to an implement agent fails to launch (broken agent config/model), don't block the work — proceed with the implementation directly, following the repo's recipe and verification steps. Confidence: 0.8
- When the assistant presents a decision frontier (grilling rounds with numbered options and recommendations), the user defers to the assistant's recommendations — expects it to proceed with its own recommended options as settled decisions rather than waiting for item-by-item answers. Confidence: 0.95
- Spec-first workflow: never write any code or tests before the spec phase (user explicitly stops implementation and invokes /to-spec). Writing the spec (using repo docs: issue-tracker, domain glossary, ADRs) must come before any implementation, even before TDD tests-first. Confidence: 0.9
