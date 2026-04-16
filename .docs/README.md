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
<!-- Add rows as you create docs:
| [frontend.md](frontend.md) | CSS framework, JS patterns, component library |
| [views.md](views.md) | View/template conventions and layout patterns |
| [permissions.md](permissions.md) | Roles, permissions, access control |
| [settings.md](settings.md) | Config system, settings structure |
| [models.md](models.md) | All models, relationships, scopes |
| [api.md](api.md) | API endpoints, auth, request/response formats |
-->

## How to Generate .claude/ Reference Files

Ask Claude to scan your codebase and generate these:

```
"Scan all migrations and generate .claude/database-schema.md with table schemas"
"Scan route files and generate .claude/routes-reference.md"
"Scan service/controller classes and generate .claude/services-reference.md"
```

Regenerate after major refactors to keep them current.
