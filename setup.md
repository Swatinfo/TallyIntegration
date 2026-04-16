# Claude Code Project Setup Guide

Complete guide to setting up Claude Code for a new project using this template.

---

## Prerequisites

### 1. Install Claude Code

```bash
# Via npm (recommended)
npm install -g @anthropic-ai/claude-code

# Or via Homebrew (macOS)
brew install claude-code
```

### 2. IDE Extensions (Optional but Recommended)

- **VS Code**: Install "Claude Code" extension from marketplace
- **JetBrains**: Install "Claude Code" plugin from JetBrains marketplace
- Both provide inline Claude Code access within your IDE

### 3. Laravel 13 Setup (Laravel Projects Only)

#### Step 1 — Install PHP 8.3+, Composer & Laravel Installer

The fastest way is via `php.new`, which installs everything in one command:

**macOS / Linux:**

```bash
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.4)"
```

**Windows (run as Administrator):**

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.4'))
```

> Restart your terminal after running. Re-run the same command to update PHP/Composer/installer in future.

If you already have PHP 8.3+ and Composer, just install the installer:

```bash
composer global require laravel/installer
```

#### Step 2 — Create a New Laravel 13 Project

```bash
# Recommended: interactive setup (picks starter kit, auth, DB, test stack)
laravel new my-app

# Or pin explicitly to Laravel 13
composer create-project laravel/laravel:^13.0 my-app
```

> **Laravel 13 requires PHP 8.3 or higher** (released March 17, 2026).

#### Step 3 — Install Laravel Boost MCP

```bash
# Install as a dev dependency
composer require --dev laravel/boost

# Run the installer (auto-detects your IDE and AI agents)
php artisan boost:install

# Verify MCP works
php artisan boost:mcp
```

For non-Laravel projects, delete `.mcp.json` and `.claude/rules/laravel-boost.md`.

---

## Quick Start

### Step 1: Copy Files to Your Project Root

```bash
# Copy the entire template structure to your project
cp -r fresh_claude_setup/.claude /path/to/your/project/
cp -r fresh_claude_setup/.docs /path/to/your/project/
cp -r fresh_claude_setup/tasks /path/to/your/project/
cp fresh_claude_setup/CLAUDE.md /path/to/your/project/
cp fresh_claude_setup/.claudeignore /path/to/your/project/
cp fresh_claude_setup/.mcp.json /path/to/your/project/  # Laravel only
```

### Step 2: Merge .gitignore Entries

```bash
# DON'T replace your .gitignore — merge the Claude entries into it
# At minimum, add these lines to your existing .gitignore:
#   .claude/settings.local.json
#   .claude/plans/
```

See `.gitignore.template` for the full recommended list.

### Step 3: Make file-suggestions.sh Executable

```bash
chmod +x /path/to/your/project/.claude/file-suggestions.sh
```

### Step 4: Customize Everything (see detailed guide below)

---

## File Structure Explained

```
your-project/
├── CLAUDE.md                          # Main project instructions (always loaded)
├── .claudeignore                      # Files/dirs Claude should ignore (like .gitignore for Claude)
├── .gitignore.template                # Gitignore entries to merge into your .gitignore
├── .mcp.json                          # MCP server definitions (optional)
├── .claude/
│   ├── settings.json                  # SHARED project permissions (commit to git)
│   ├── settings.local.json            # LOCAL user permissions + hooks (gitignore)
│   ├── file-suggestions.sh            # @ autocomplete for file references
│   ├── plans/                         # Session working plans (auto-created)
│   └── rules/                         # Auto-loaded rule files
│       ├── workflow.md                # Task management process
│       ├── pre-read-gate.md           # "Read docs before coding" enforcement
│       ├── coding-feedback.md         # Coding conventions & preferences
│       ├── project-context.md         # Domain knowledge & business rules
│       └── laravel-boost.md           # Laravel-specific (delete if not Laravel)
├── .docs/                             # Project documentation (Claude reads on demand)
│   └── README.md                      # Index + guide for generating reference docs
└── tasks/
    ├── lessons.md                     # Corrections & patterns (grows over time)
    └── todo.md                        # Current task tracker (updated live)
```

### What Goes Where

| File | Purpose | Git? |
|------|---------|------|
| `CLAUDE.md` | Main instructions, always in Claude's context | Yes |
| `.claudeignore` | Files/dirs Claude should skip (saves context) | Yes |
| `.claude/settings.json` | Shared permissions for all team members | Yes |
| `.claude/settings.local.json` | Your personal permissions, hooks, MCP | **No** (gitignore) |
| `.claude/rules/*.md` | Auto-loaded rules (conventions, context) | Yes |
| `.claude/file-suggestions.sh` | @ autocomplete script | Yes |
| `.claude/plans/` | Session working files, auto-generated | **No** (gitignore) |
| `.docs/*.md` | Reference docs Claude reads before coding | Yes |
| `.mcp.json` | MCP server definitions | Yes |
| `tasks/lessons.md` | Accumulated lessons from corrections | Yes |
| `tasks/todo.md` | Live task tracker | Yes |
| `.gitignore.template` | Gitignore entries to merge (not used directly) | No (reference only) |

### Add to `.gitignore`

```gitignore
# Claude Code local settings
.claude/settings.local.json
.claude/plans/
```

---

## Detailed Customization Guide

### 1. CLAUDE.md — Project Instructions

This is the most important file. Claude reads it at the start of every conversation.

**Must customize:**

- `## Project Overview` — What your project does (1-3 lines)
- `## Tech Stack` — Your actual stack
- `## Development Commands` — Real commands that work
- `## Key Conventions` — Project-wide rules
- `## Mandatory Pre-Read Gate` — Map feature areas to `.docs/` files
- `## Source of Truth Files` — Canonical files for CSS, JS, config

**Keep under 200 lines.** Claude loads this every turn — bloated CLAUDE.md wastes context.

### 2. Rules Files (.claude/rules/)

These are auto-loaded into every conversation alongside CLAUDE.md.

#### `workflow.md` — Ready to use as-is

Task management process. Works for any project. No changes needed unless you have different workflow preferences.

#### `pre-read-gate.md` — Must customize

Maps "what you're working on" → "what to read first". Add a row for each major feature area. This prevents Claude from coding blindly.

#### `coding-feedback.md` — Grows over time

Start mostly empty. Each time you correct Claude ("don't use Tailwind, we use Bootstrap"), add the correction here with a date. Over time this becomes your project's coding bible.

#### `project-context.md` — Must customize

Domain knowledge that isn't obvious from code: business rules, user roles, calculation formulas, brand guidelines. Fill this in so Claude understands your domain.

#### `laravel-boost.md` — Laravel only

Delete for non-Laravel projects. For Laravel projects, customize the "Project-Specific Overrides" section.

### 3. Settings Files

#### `settings.json` (shared, committed to git)

```jsonc
{
    "permissions": {
        "allow": [
            // Commands Claude can run without asking
            "Bash(php artisan *)",
            "Bash(npm *)",
            // Add your common commands
        ],
        "deny": [
            // NEVER allow these (safety)
            "Bash(rm -rf *)",
            "Bash(git push --force *)",
            "Bash(git reset --hard *)"
        ]
    },
    "plansDirectory": ".claude/plans"
    // Add MCP servers here if using project-level MCP
}
```

**Customize `allow` list** for your stack:

- Python: `"Bash(python *)"`, `"Bash(pip *)"`, `"Bash(pytest *)"`, `"Bash(ruff *)"`
- Node: `"Bash(npm *)"`, `"Bash(npx *)"`, `"Bash(node *)"`, `"Bash(jest *)"`
- Go: `"Bash(go *)"`, `"Bash(golint *)"`, `"Bash(go test *)"`
- Ruby: `"Bash(bundle *)"`, `"Bash(rails *)"`, `"Bash(rake *)"`
- Rust: `"Bash(cargo *)"`, `"Bash(rustc *)"`

#### `settings.local.json` (personal, gitignored)

This is YOUR local setup. Key sections:

**Permissions** — Same as settings.json but for your personal comfort level. Add `"Edit"` and `"Write"` to `allow` if you trust Claude to edit without asking.

**Hooks** — The automation magic. Three hook types:

```jsonc
{
  "hooks": {
    // BEFORE Claude uses a tool — prevent mistakes
    "PreToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [{
          "type": "command",
          "command": "echo 'Read docs first!'",
          "if": "Edit(*.blade.php)",  // Only for specific file patterns
          "statusMessage": "Checking..."
        }]
      }
    ],
    
    // AFTER Claude uses a tool — catch errors
    "PostToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [{
          "type": "command",
          "command": "echo 'Check for banned patterns'",
          "if": "Edit(*.blade.php)",
          "statusMessage": "Validating..."
        }]
      }
    ],
    
    // When YOU submit a prompt — context reminders
    "UserPromptSubmit": [
      {
        "matcher": "keyword1|keyword2",  // Matches your prompt text
        "hooks": [{
          "type": "command",
          "command": "echo 'REMINDER: Read X before working on Y'"
        }]
      }
    ]
  }
}
```

**Hook ideas by project type:**

| Project Type | PreToolUse | PostToolUse | UserPromptSubmit |
|-------------|-----------|------------|-----------------|
| Laravel/Blade | Check pre-read for views | Detect Tailwind in Bootstrap project | Remind docs for feature keywords |
| React/Next.js | Check pre-read for components | Detect CSS-in-JS in Tailwind project | Remind about state management patterns |
| Python/Django | Check pre-read for views | Detect type hint issues | Remind about Django conventions |
| API-only | Check pre-read for endpoints | Validate response format | Remind about auth/rate-limit docs |

### 4. File Suggestions Script

Edit `.claude/file-suggestions.sh` to list YOUR project's key files:

```bash
# No-query defaults (shown when user types @ with no text)
if [ -z "$QUERY" ]; then
    echo "CLAUDE.md"
    echo "src/main.ts"           # Your main entry point
    echo ".docs/README.md"
    echo "tasks/lessons.md"
    exit 0
fi

# Search paths (customize for your project structure)
{
    find src -name "*.ts" 2>/dev/null          # Source files
    find tests -name "*.test.ts" 2>/dev/null   # Test files
    find .docs -name "*.md" 2>/dev/null        # Docs
} | grep -i "$QUERY" | head -15
```

### 5. MCP Servers (.mcp.json)

MCP (Model Context Protocol) servers give Claude access to external tools.

```jsonc
{
    "mcpServers": {
        // Laravel Boost — database, tinker, docs search
        "laravel-boost": {
            "command": "php",
            "args": ["artisan", "boost:mcp"]
        }
        
        // Add other MCP servers as needed:
        // "postgres": { "command": "...", "args": [...] }
        // "docker": { "command": "...", "args": [...] }
    }
}
```

Delete `.mcp.json` entirely if you don't use any MCP servers.

---

## Creating .docs/ Reference Files

The `.docs/` directory is where you document your project for Claude. Create these as needed:

### Recommended Starting Set

```bash
mkdir -p .docs
```

| File | When to Create | What to Include |
|------|---------------|-----------------|
| `README.md` | Always | Index linking to all other docs |
| `frontend.md` | If has UI | CSS framework, JS patterns, component library |
| `views.md` | If has templates | View conventions, layout patterns |
| `permissions.md` | If has auth | Roles, permissions, access control rules |
| `settings.md` | If has config UI | Config system, settings structure |
| `models.md` | If has ORM | All models, relationships, scopes |
| `database.md` | If has DB | Connection setup, migration conventions |
| `api.md` | If has API | Endpoints, auth, request/response formats |

### .claude/ Reference Files (Auto-Generated)

These are generated from your codebase, not written by hand:

| File | How to Generate |
|------|----------------|
| `database-schema.md` | Ask Claude: "Scan all migrations and generate .claude/database-schema.md" |
| `routes-reference.md` | Ask Claude: "Scan route files and generate .claude/routes-reference.md" |
| `services-reference.md` | Ask Claude: "Scan service classes and generate .claude/services-reference.md" |

**Pro tip:** After major changes, ask Claude to regenerate these: "Regenerate .claude/database-schema.md from current migrations"

---

## How the System Works Together

### The Feedback Loop

```
1. You give Claude a task
   ↓
2. UserPromptSubmit hook fires → reminds Claude to read docs
   ↓
3. Claude reads .docs/ files + tasks/lessons.md (pre-read gate)
   ↓
4. PreToolUse hook fires before edits → enforces reading
   ↓
5. Claude writes code
   ↓
6. PostToolUse hook fires → catches convention violations
   ↓
7. Claude updates tasks/todo.md with progress
   ↓
8. You review and correct if needed
   ↓
9. Corrections saved to tasks/lessons.md → prevents repeat mistakes
   ↓
10. Next task benefits from accumulated lessons
```

### Plan Mode

For complex tasks, Claude enters "plan mode":

1. Writes plan to `tasks/todo.md` with checkable items
2. Creates detailed plan in `.claude/plans/[name].md`
3. Executes step by step, checking items as complete
4. You can see progress in real-time

### The Lessons System

`tasks/lessons.md` is the most powerful file over time:

- Every correction you make gets recorded with a date
- Claude reads it at the start of every task
- Mistakes are never repeated
- Patterns accumulate into a project-specific coding guide

---

## Adapting for Different Project Types

### React / Next.js

1. Delete `laravel-boost.md` and `.mcp.json`
2. Update `settings.json` permissions: `npm`, `npx`, `node`, `jest`
3. Update `pre-read-gate.md`: components, hooks, pages, API routes
4. Update `coding-feedback.md`: React patterns, state management, styling approach
5. Update `file-suggestions.sh`: scan `src/`, `pages/`, `components/`
6. PostToolUse hook: detect banned patterns (e.g., class components in hooks project)

### Python / Django / FastAPI

1. Delete `laravel-boost.md` and `.mcp.json`
2. Update `settings.json` permissions: `python`, `pip`, `pytest`, `ruff`, `mypy`
3. Update `pre-read-gate.md`: views, serializers, models, migrations
4. Update `file-suggestions.sh`: scan Python-specific dirs
5. PostToolUse hook: detect type hint issues, banned imports

### Node.js / Express API

1. Delete `laravel-boost.md` and `.mcp.json`
2. Update `settings.json` permissions: `npm`, `node`, `jest`, `tsc`
3. Update `pre-read-gate.md`: routes, middleware, controllers, models
4. Update `file-suggestions.sh`: scan `src/`, `routes/`, `models/`

### Go

1. Delete `laravel-boost.md` and `.mcp.json`
2. Update `settings.json` permissions: `go`, `go test`, `golint`, `make`
3. Update `pre-read-gate.md`: handlers, services, models, middleware
4. Update `file-suggestions.sh`: scan `cmd/`, `internal/`, `pkg/`

---

## Tips & Best Practices

### Do's

- Keep `CLAUDE.md` under 200 lines — it's loaded every turn
- Put detailed docs in `.docs/` files (read on demand, not always)
- Update `tasks/lessons.md` immediately when Claude makes a mistake
- Use `settings.json` (committed) for team-wide rules, `settings.local.json` (gitignored) for personal preferences
- Regenerate `.claude/` reference files after major refactors

### Don'ts

- Don't put code examples in `CLAUDE.md` — too much context waste
- Don't skip the pre-read gate — it prevents 80% of "Claude didn't know about X" issues
- Don't let `tasks/todo.md` grow forever — archive completed tasks
- Don't commit `settings.local.json` — it has personal paths and permissions
- Don't put project-specific content in `~/.claude/` (global config) — keep it in `.claude/` (project config)

### First Session Checklist

After copying files to your new project:

- [ ] Fill in `CLAUDE.md` with real project info
- [ ] Fill in `.claude/rules/project-context.md` with domain knowledge
- [ ] Update `.claude/rules/pre-read-gate.md` with your feature areas
- [ ] Update permissions in `.claude/settings.json` for your stack
- [ ] Customize hooks in `.claude/settings.local.json` for your patterns
- [ ] Update `.claude/file-suggestions.sh` with your project paths
- [ ] Create `.docs/README.md` as docs index
- [ ] Ask Claude to generate `.claude/database-schema.md` (if applicable)
- [ ] Ask Claude to generate `.claude/routes-reference.md` (if applicable)
- [ ] Ask Claude to generate `.claude/services-reference.md` (if applicable)
- [ ] Delete `.mcp.json` and `laravel-boost.md` (if not Laravel)
- [ ] Add `.claude/settings.local.json` and `.claude/plans/` to `.gitignore`
- [ ] Run `chmod +x .claude/file-suggestions.sh`

---

## Useful Claude Code Commands

```bash
# Start Claude Code in your project
claude

# Start with a specific task
claude "explain the auth system"

# Resume last conversation
claude --continue

# Run a one-shot command
claude -p "generate database-schema.md from migrations"

# Use plan mode (inside Claude Code)
/plan

# Init CLAUDE.md interactively (alternative to this template)
/init
```

### Slash Commands (Inside Claude Code)

| Command | Purpose |
|---------|---------|
| `/init` | Initialize CLAUDE.md interactively |
| `/review` | Review a pull request |
| `/commit` | Create a git commit |
| `/help` | Get help with Claude Code |
| `/compact` | Compress conversation context |

---

## Need Help?

- **Claude Code docs**: Run `/help` inside Claude Code
- **Laravel 13 docs**: <https://laravel.com/docs/13.x>
- **Report issues**: <https://github.com/anthropics/claude-code/issues>
- **MCP servers**: <https://modelcontextprotocol.io/>
