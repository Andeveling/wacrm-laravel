# Taste (Continuously Learned by [CommandCode][cmd])

[cmd]: https://commandcode.ai/

# communication
- Communicate in Spanish (español) at all times. Confidence: 0.85

# project-scope
- Spanish-only product (LATAM-first, Colombia-first). No i18n complexity — postpone multilingual support for years, not now. Confidence: 0.80
- Prioritize Laravel Sail over raw Docker for local development. Confidence: 0.65

# workflow
- Assistant's primary role is orchestrating pending tasks from the migration map and grilling sessions — implementation is delegated to other agents. Confidence: 0.75
- When checking for pending tasks, grilling sessions, or issues, consult GitHub issues (gh CLI) as the source of truth — not local .scratch/ directories or git branch scanning alone. Confidence: 0.60
- Make atomic commits — one commit per discrete change or issue. Confidence: 0.65
- When the assistant presents a decision frontier (grilling rounds with numbered options and recommendations), the user defers to the assistant's recommendations — expects it to proceed with its own recommended options as settled decisions rather than waiting for item-by-item answers. Confidence: 0.8

# architecture
- AI module must be built on laravel-ai-sdk with OpenAI as the provider, following the design at wacrm.tech/docs/ai-assistant. Confidence: 0.70

# frontend
- Use shadcn/ui components (e.g., toasts, toaster) as the UI component library standard. Confidence: 0.60
- When porting features from original-wacrm (Next.js) to the Laravel monolith, prefer parity with the original UX, allowing minor enhancements that don't change the UX (e.g., showing note authors), and follow the repo's established Laravel module pattern rather than the original's architecture. Confidence: 0.7
- Server-side pagination with Laravel + Inertia, user-configurable per-page, via a reusable pageProps-based component usable by any view. Confidence: 0.75
- Use Vite for frontend dev tooling (Laravel + Vite, not other bundlers). Confidence: 0.7
- Apply changes with TDD (tests first) and consult the official Laravel/Inertia docs before implementing. Confidence: 0.7
