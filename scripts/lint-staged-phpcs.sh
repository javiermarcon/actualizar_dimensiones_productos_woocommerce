#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(git rev-parse --show-toplevel)"
cd "${ROOT_DIR}"

MODE="${1:-staged}"

PHPCS_BIN="${ROOT_DIR}/vendor/bin/phpcs"
if [[ ! -x "${PHPCS_BIN}" ]]; then
  echo "pre-commit: phpcs no disponible, se omite este control." >&2
  exit 0
fi

if [[ "${MODE}" == "--all" ]]; then
  mapfile -t PHP_FILES < <(git ls-files '*.php' ':!:vendor/*' ':!:build/*' ':!:wp-admin/*')
else
  mapfile -t PHP_FILES < <(git diff --cached --name-only --diff-filter=ACMR | grep -E '\.php$' || true)
fi

FILTERED_FILES=()
for file in "${PHP_FILES[@]}"; do
  case "${file}" in
    includes/*|tests/*|actualizar_dimensiones_productos_woocommerce.php)
      FILTERED_FILES+=("${file}")
      ;;
    *)
      ;;
  esac
done
PHP_FILES=("${FILTERED_FILES[@]}")

if [[ ${#PHP_FILES[@]} -eq 0 ]]; then
  echo "pre-commit: sin archivos PHP para validar con phpcs."
  exit 0
fi

echo "pre-commit: ejecutando phpcs en ${#PHP_FILES[@]} archivo(s)..."
"${PHPCS_BIN}" --standard="${ROOT_DIR}/phpcs.xml" "${PHP_FILES[@]}"
echo "pre-commit: phpcs OK"
