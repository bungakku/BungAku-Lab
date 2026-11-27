#!/usr/bin/env bash
# Container entrypoint: dependencies, WordPress core (first run only), web server, then the requested lab command.
set -euo pipefail
cd /lab
[ -d node_modules/@playwright/test ] || npm ci --no-audit --no-fund --loglevel=error
bin/lab install --no-browsers     # idempotent: fetches core and installs WordPress only when missing
bin/lab up
exec bin/lab "$@"
