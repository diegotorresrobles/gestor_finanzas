#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
read -r -s -p 'Contraseña del administrador (12 a 72 bytes): ' admin_password
printf '\n'
read -r -s -p 'Repite la contraseña: ' confirmation
printf '\n'
if [[ "$admin_password" != "$confirmation" ]]; then
  printf 'Las contraseñas no coinciden.\n' >&2
  exit 1
fi
printf '%s' "$admin_password" | php deploy/create-admin.php
unset admin_password confirmation
