#!/usr/bin/env bash
set -euo pipefail

# Convierte saltos de línea de Windows en scripts dentro de /var/www/html/bin
if command -v dos2unix >/dev/null 2>&1; then
  find /var/www/html/bin -type f -name "*.sh" -exec dos2unix {} \; || true
fi

# Espera a que la base de datos responda (opcional)
if [[ "${WAIT_FOR_DB:-false}" = "true" && -n "${DB_HOST:-}" ]]; then
  echo "[entrypoint] Esperando a la base de datos en ${DB_HOST}:${DB_PORT:-3306}..."
  for i in {1..60}; do
    if php -r "error_reporting(0); @new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASS'));" >/dev/null 2>&1; then
      echo "[entrypoint] Base de datos disponible."
      break
    fi
    echo "[entrypoint] Aún no disponible, reintentando en 3 segundos..."
    sleep 3
  done
fi

# Ejecuta migraciones si se habilita RUN_MIGRATIONS
if [[ "${RUN_MIGRATIONS:-false}" = "true" ]]; then
  echo "[entrypoint] Ejecutando migraciones..."
  php public/index.php manager tools migrate || {
    echo "[entrypoint] El comando de migración devolvió error pero continuaremos."
  }
fi

# Arranca Apache en primer plano
exec apache2-foreground
