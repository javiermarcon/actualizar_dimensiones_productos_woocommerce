#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(git rev-parse --show-toplevel)"
cd "${ROOT_DIR}"

MODE="${1:-staged}"

PHPSTAN_BIN="${ROOT_DIR}/vendor/bin/phpstan"
if [[ ! -x "${PHPSTAN_BIN}" ]]; then
  echo "pre-commit: phpstan no disponible, se omite este control." >&2
  exit 0
fi

if [[ "${MODE}" == "--all" ]]; then
  mapfile -t PHP_FILES < <(git ls-files '*.php' ':!:vendor/*' ':!:build/*' ':!:wp-admin/*')
else
  mapfile -t PHP_FILES < <(git diff --cached --name-only --diff-filter=ACMR | grep -E '\.php$' || true)
fi

if [[ ${#PHP_FILES[@]} -eq 0 ]]; then
  echo "pre-commit: sin archivos PHP para validar con phpstan."
  exit 0
fi

echo "pre-commit: ejecutando phpstan en ${#PHP_FILES[@]} archivo(s)..."
"${PHPSTAN_BIN}" analyse --debug --no-progress --memory-limit=512M --configuration="${ROOT_DIR}/phpstan.neon" "${PHP_FILES[@]}"
echo "pre-commit: phpstan OK"
