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

# architecture
- AI module must be built on laravel-ai-sdk with OpenAI as the provider, following the design at wacrm.tech/docs/ai-assistant. Confidence: 0.70

# frontend
- Use shadcn/ui components (e.g., toasts, toaster) as the UI component library standard. Confidence: 0.60
