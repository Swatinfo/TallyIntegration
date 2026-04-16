#!/bin/bash
# File suggestion script for Claude Code @ autocomplete
# Customize the key entry points and search paths for your project

# Read query from stdin JSON
QUERY=$(cat | sed -n 's/.*"query"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' 2>/dev/null || echo "")

# If no query, return key entry points
if [ -z "$QUERY" ]; then
    echo "CLAUDE.md"
    echo ".docs/README.md"
    echo ".claude/database-schema.md"
    echo ".claude/routes-reference.md"
    echo ".claude/services-reference.md"
    echo "tasks/lessons.md"
    echo "tasks/todo.md"
    # Add your project's key docs here:
    # echo ".docs/your-feature.md"
    exit 0
fi

PROJECT_DIR="${CLAUDE_PROJECT_DIR:-.}"
cd "$PROJECT_DIR" 2>/dev/null || exit 0

# Search priority locations, filter by query, limit results
# Customize these paths for your project structure
{
    # Controllers / Handlers
    find app/Http/Controllers -name "*.php" ! -name "__*" 2>/dev/null
    # Services / Business Logic
    find app/Services -name "*.php" ! -name "__*" 2>/dev/null
    # Models / Entities
    find app/Models -name "*.php" 2>/dev/null
    # Views / Templates
    find resources/views -name "*.blade.php" 2>/dev/null
    # Routes
    find routes -name "*.php" 2>/dev/null
    # Migrations
    find database/migrations -name "*.php" 2>/dev/null
    # Config
    find config -name "*.php" 2>/dev/null
    # Frontend assets (customize paths)
    # echo "public/css/app.css"
    # echo "public/js/app.js"
    # Middleware
    echo "bootstrap/app.php"
    # Documentation
    find .docs -name "*.md" 2>/dev/null
    find .claude -name "*.md" ! -path "*plans*" 2>/dev/null
    echo "tasks/lessons.md"
    echo "tasks/todo.md"
    echo "CLAUDE.md"
} | grep -i "$QUERY" | head -15
