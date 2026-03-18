#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(git rev-parse --show-toplevel)"
cd "${ROOT_DIR}"

MODE="${1:-staged}"

if ! command -v php >/dev/null 2>&1; then
  echo "ERROR: php no esta disponible en PATH." >&2
  exit 1
fi

if [[ "${MODE}" == "--all" ]]; then
  mapfile -t PHP_FILES < <(git ls-files '*.php' ':!:vendor/*' ':!:build/*' ':!:wp-admin/*')
else
  mapfile -t PHP_FILES < <(git diff --cached --name-only --diff-filter=ACMR | grep -E '\.php$' || true)
fi

if [[ ${#PHP_FILES[@]} -eq 0 ]]; then
  echo "pre-commit: sin archivos PHP para validar."
  exit 0
fi

echo "pre-commit: validando sintaxis PHP en ${#PHP_FILES[@]} archivo(s)..."
for file in "${PHP_FILES[@]}"; do
  if [[ -f "${file}" ]]; then
    php -l "${file}" >/dev/null
  fi
done

echo "pre-commit: OK"
