#!/usr/bin/env bash
# LuckyDraw — quick start with PHP's built-in server (no Apache/Nginx needed)
cd "$(dirname "$0")"
PORT="${PORT:-8080}"
echo
echo "  LuckyDraw is starting on port $PORT ..."
echo "  Open on this machine:  http://localhost:$PORT/"
IP=$(hostname -I 2>/dev/null | awk '{print $1}')
[ -n "$IP" ] && echo "  Open on the network:   http://$IP:$PORT/"
echo "  Press Ctrl+C to stop."
echo
PHP_CLI_SERVER_WORKERS=8 exec php -S "0.0.0.0:$PORT" index.php
