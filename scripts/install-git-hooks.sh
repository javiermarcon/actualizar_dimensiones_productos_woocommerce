#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(git rev-parse --show-toplevel)"
cd "${ROOT_DIR}"

chmod +x .githooks/pre-commit
chmod +x scripts/lint-staged-php.sh
chmod +x scripts/lint-staged-phpcs.sh
chmod +x scripts/lint-staged-phpstan.sh
chmod +x scripts/install-git-hooks.sh

git config core.hooksPath .githooks

echo "Git hooks instalados. core.hooksPath=.githooks"
