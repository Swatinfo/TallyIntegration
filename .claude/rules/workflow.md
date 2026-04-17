# Workflow Rules

## Task Management
1. **Plan first**: Write plan to `tasks/todo.md` with checkable items BEFORE starting implementation
2. **Track progress live**: Mark items complete as EACH step completes — not all at once after
3. **Capture lessons**: Update `tasks/lessons.md` after ANY user correction or discovered pattern
4. **Verify before done**: Test before marking a task complete
5. **Keep docs in sync**: Update reference files as part of the same change

## Planning
- Enter plan mode for ANY non-trivial task (3+ steps or architectural decisions)
- If something goes sideways, STOP and re-plan immediately
- Write detailed specs upfront to reduce ambiguity

## Subagents
- Use subagents for research, exploration, and parallel analysis
- One task per subagent for focused execution
- Keep main context window clean

## Quality
- Ask: "Would a staff engineer approve this?"
- For simple, obvious fixes, skip over-engineering
- Run tests, check logs, demonstrate correctness

## Bug Fixing
- Given a bug report: just fix it
- Point at logs, errors, failing tests — then resolve
- Zero context switching required from the user

## Documentation Sync Checklist
After any code change, update if affected:
- `.claude/database-schema.md` — table schemas, columns
- `.claude/routes-reference.md` — route definitions, methods
- `.claude/services-reference.md` — service methods, validation, business logic
- `.docs/models.md` — if models changed
- `.docs/permissions.md` — if permissions or roles changed
- Relevant `.docs/` file — feature behavior, UI patterns, validation rules
- `tasks/lessons.md` — new patterns or corrections
- `tasks/todo.md` — task progress

## Dual-Location Doc Sync (HARD REQUIREMENT)

The Tally module ships with **two** sets of documentation that must stay in lockstep:

- **Project docs** in `.docs/` and `.claude/` — consumed by Claude Code and by any engineer reading the repo
- **Module docs** in `Modules/Tally/docs/` — travel with the module when it's copied into a downstream Laravel app (the module must be self-documenting because it's portable)

**When source in `Modules/Tally/` changes, update BOTH locations in the same change.** A docs update is not complete until both are in sync.

### Mapping table

| Changed source | Update in `.docs/` + `.claude/` | Update in `Modules/Tally/docs/` |
|---|---|---|
| Migrations (`Modules/Tally/database/migrations/`) | `.claude/database-schema.md` | `CONFIGURATION.md` if env/config impacted |
| Models (`Modules/Tally/app/Models/`) | `.claude/database-schema.md` (model table) + `.claude/services-reference.md` | — |
| Routes (`Modules/Tally/routes/api.php`) | `.claude/routes-reference.md` + `.docs/tally-integration.md` (API endpoints section) | `API-USAGE.md` |
| Controllers (`Modules/Tally/app/Http/Controllers/`) | `.claude/routes-reference.md` (Form Request map) | `API-USAGE.md` |
| Form Requests / Middleware | `.claude/routes-reference.md` | `TROUBLESHOOTING.md` if new failure mode |
| Services — XML/HTTP/Parser | `.claude/services-reference.md` + `.docs/tally-api-reference.md` | `TROUBLESHOOTING.md` if error-path changed |
| Services — Masters / Vouchers / Reports | `.claude/services-reference.md` | `API-USAGE.md`, `QUICK-START.md` if call signature changed |
| Jobs / Events | `.claude/services-reference.md` + `.docs/tally-integration.md` (Sync Engine section) | `TROUBLESHOOTING.md` (sync-not-running scenarios) |
| Config (`Modules/Tally/config/config.php`) | `.docs/tally-integration.md` | `CONFIGURATION.md` |
| Permissions (`TallyPermission` enum) | `.claude/database-schema.md` + `.claude/routes-reference.md` | `CONFIGURATION.md` (permissions section) |
| Install / scaffolding changes | `.docs/README.md` | `INSTALLATION-FRESH.md` + `INSTALLATION-EXISTING.md` |
| Large/cross-cutting feature | Relevant `.docs/*.md` | `QUICK-START.md` + `API-USAGE.md` |

### Shortcuts the user may say

| User says | Claude does |
|---|---|
| "update docs" | Update both locations per the table above without asking |
| "update the module docs" | Update `Modules/Tally/docs/` only |
| "update the project docs" | Update `.docs/` + `.claude/` only |
| "sync docs" | Diff both locations against current source; bring both up to date |

### Verification

- Each reference file in `.claude/` ends with or opens with a `Last verified: YYYY-MM-DD` stamp — update it when you touch the file.
- For route changes: run `php artisan route:list --path=api/tally` and confirm the count matches the doc.
- For schema changes: run `php artisan migrate:status` and confirm the migration list matches.
