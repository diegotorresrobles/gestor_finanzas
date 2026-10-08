#!/usr/bin/env bash
# Optional: DOM Cloud Kit/Pro with the docker feature already enabled.
set -euo pipefail
cd "$(dirname "$0")/.."
app_directory="$PWD/backend"
php_binary="$(command -v php)"
if [[ "$app_directory" == *' '* || "$php_binary" == *' '* ]]; then
  printf 'Las rutas del servidor deben estar libres de espacios para esta unidad.\n' >&2
  exit 1
fi
systemctl --user show-environment >/dev/null
mkdir -p "$HOME/.config/systemd/user"
cat > "$HOME/.config/systemd/user/dtr-websocket.service" <<EOF
[Unit]
Description=DTR private WebSocket notifications
After=network.target

[Service]
Type=simple
WorkingDirectory=$app_directory
ExecStart=$php_binary $app_directory/bin/websocket.php
Restart=always
RestartSec=5
UMask=0077

[Install]
WantedBy=default.target
EOF
systemctl --user daemon-reload
systemctl --user enable --now dtr-websocket.service
systemctl --user is-active dtr-websocket.service
printf 'Configura /ws en Nginx y WS_URL antes de usar WebSocket.\n'
