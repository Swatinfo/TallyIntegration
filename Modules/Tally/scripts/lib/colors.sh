#!/usr/bin/env bash
# Modules/Tally/scripts/lib/colors.sh — ANSI colour helpers
# Disabled automatically when stdout is not a TTY (e.g. piped to file).

if [[ -t 1 ]]; then
    RESET=$'\033[0m'
    BOLD=$'\033[1m'
    DIM=$'\033[2m'
    RED=$'\033[31m'
    GREEN=$'\033[32m'
    YELLOW=$'\033[33m'
    BLUE=$'\033[34m'
    MAGENTA=$'\033[35m'
    CYAN=$'\033[36m'
    GREY=$'\033[90m'
else
    RESET=""; BOLD=""; DIM=""; RED=""; GREEN=""
    YELLOW=""; BLUE=""; MAGENTA=""; CYAN=""; GREY=""
fi

c_pass()  { printf "%s" "${GREEN}✓${RESET}"; }
c_fail()  { printf "%s" "${RED}✗${RESET}"; }
c_warn()  { printf "%s" "${YELLOW}!${RESET}"; }
c_info()  { printf "%s" "${CYAN}•${RESET}"; }
