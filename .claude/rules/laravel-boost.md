# Laravel Boost Guidelines

<!-- 
  OPTIONAL: Only include this file if your project uses Laravel with the laravel-boost MCP server.
  Delete this file for non-Laravel projects.
-->

## Project-Specific Overrides (take precedence over Boost defaults below)

<!-- Customize these for your project -->
- **Validation style**: [e.g., Inline validation in controllers / Form Request classes]
- **Build step**: [e.g., No build step — local vendor files / Vite + npm]
- **Factories/Seeders**: [e.g., Only create when explicitly asked / Always use factories]
- **Config approach**: [e.g., Use ConfigService / Use config() helper directly]

---

<laravel-boost-guidelines>
=== foundation rules ===

### Conventions
- Follow existing code conventions. Check sibling files for structure, approach, and naming.
- Use descriptive names. Check for existing components before creating new ones.
- Stick to existing directory structure; only change dependencies with approval.
- Create documentation files only if explicitly requested.

=== boost rules ===

### Laravel Boost MCP Tools
- Use `list-artisan-commands` to check available Artisan command parameters
- Use `get-absolute-url` when sharing project URLs
- Use `tinker` for PHP debugging / Eloquent queries; `database-query` for read-only DB access
- Use `database-schema` to inspect table structure before writing migrations
- Use `browser-logs` for recent browser errors/exceptions
- Use `search-docs` before making code changes — passes installed package versions automatically
  - Use broad topic queries: `['rate limiting', 'routing']` (no package names in queries)

=== php rules ===

### PHP
- Always use curly braces for control structures, even single-line bodies
- Use PHP 8 constructor property promotion; no empty zero-param constructors
- Always use explicit return types and type hints
- Enum keys should be TitleCase

=== laravel/core rules ===

### Laravel Core
- Use `php artisan make:` with `--no-interaction` to create files
- Prefer Eloquent relationships over raw queries; avoid `DB::`, prefer `Model::query()`
- Use eager loading to prevent N+1 problems
- Use named routes and `route()` for URL generation
- Use `config('key')` not `env('KEY')` outside config files
- Use `ShouldQueue` for time-consuming operations

### Testing
- Use factories with custom states for test models
- Run minimal tests with `--filter` before finalizing

=== pint/core rules ===

### Pint
- Run `vendor/bin/pint --dirty --format agent` after modifying PHP files

</laravel-boost-guidelines>
