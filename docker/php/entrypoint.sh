#!/bin/sh
# Menyiapkan cache framework dengan konfigurasi runtime (env_file) sebelum proses utama berjalan.
set -e
cd /var/www/html

php artisan package:discover --ansi >/dev/null 2>&1 || true

if [ "${APP_ENV:-production}" = "production" ]; then
  php artisan optimize >/dev/null 2>&1 || echo "peringatan: php artisan optimize gagal (periksa .env.production)"
  php artisan filament:optimize >/dev/null 2>&1 || true
fi

# Migrasi hanya dijalankan oleh service pertama yang diminta (ALIAS_MIGRASI=1), bukan oleh setiap container.
if [ "${ALIAS_MIGRASI:-0}" = "1" ]; then
  php artisan migrate --force
fi

exec "$@"
