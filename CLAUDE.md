# TOY & JOY Claude Instructions

Read `AGENTS.md`, `AI_INDEX.md`, and the current `.ai/` task-state files before changing the project. `AGENTS.md` is the mandatory source of truth for agent behavior.

## Database rule

- Use only XAMPP MySQL/MariaDB at `127.0.0.1:3306`, managed through phpMyAdmin.
- SQLite is prohibited. Do not create SQLite files, add SQLite configuration, run migrations/seeders/tests against SQLite, or accept SQLite backup fixtures.
- Use `DB_CONNECTION=mysql`, a dedicated schema name, and the XAMPP credentials from the active `.env`.
- Before destructive database work, list and verify the exact schema names in phpMyAdmin or MySQL, then record what was removed.
- Historical `.ai/` reports may mention the former SQLite setup as factual history; do not use those legacy references as current configuration.

## Laravel direction

- Keep the single Laravel modular monolith, Blade/Livewire screens, Flux UI, Tailwind CSS, and Arabic-first RTL/English LTR behavior.
- Do not introduce a separate frontend, API architecture, or unsupported framework.
- Do not claim tests or browser checks that were not actually run. Follow the current task's verification directive.
