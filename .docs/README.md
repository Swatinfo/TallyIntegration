# Project Documentation Index

Reference documentation for Claude Code. Each file covers one area of the project.

## Reference Files (.claude/)

| File | Purpose |
|------|---------|
| [database-schema.md](../.claude/database-schema.md) | All tables, columns, indexes — generated from migrations |
| [routes-reference.md](../.claude/routes-reference.md) | All routes, controllers, middleware — generated from route files |
| [services-reference.md](../.claude/services-reference.md) | All services, methods, validation — generated from service classes |

## Feature Documentation (.docs/)

| File | Purpose |
|------|---------|
| **This file** | Documentation index |
| [tally-integration.md](tally-integration.md) | Tally integration architecture, services, usage examples, API endpoints |
| [tally-api-reference.md](tally-api-reference.md) | TallyPrime XML API format, request/response examples, report names |

## How to Generate .claude/ Reference Files

Ask Claude to scan your codebase and generate these:

```
"Scan all migrations and generate .claude/database-schema.md with table schemas"
"Scan route files and generate .claude/routes-reference.md"
"Scan service/controller classes and generate .claude/services-reference.md"
```

Regenerate after major refactors to keep them current.
