# MANDATORY Pre-Read Before Writing Any Code

Before generating ANY code, read the relevant reference files.

| What you're working on | Read FIRST |
|------------------------|-----------|
| Tally services / XML | `.docs/tally-integration.md` + `.docs/tally-api-reference.md` |
| XML format changes | `.docs/Demo Samples/` (official Tally XML samples) |
| DB/Models/Migrations | `.claude/database-schema.md` |
| Routes/Controllers | `.claude/routes-reference.md` |
| Services/Validation | `.claude/services-reference.md` |
| Config | `Modules/Tally/config/config.php` |

## Always Read (every task)
1. `tasks/lessons.md` — past corrections to avoid repeating
2. `tasks/todo.md` — current task state

## Source of Truth Files
- `Modules/Tally/config/config.php` — Tally connection config
- `Modules/Tally/app/Services/TallyXmlBuilder.php` — XML request format
- `Modules/Tally/app/Services/TallyXmlParser.php` — XML response parsing
- `.docs/Demo Samples/` — Official TallyPrime XML samples (canonical format)
