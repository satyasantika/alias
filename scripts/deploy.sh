#!/usr/bin/env bash
#
# ALIAS — skrip rilis produksi (dijalankan DI SERVER, dari akar repositori).
#
# Dipanggil oleh job `deploy` di .github/workflows/ci.yml (setelah CI hijau) atau
# oleh cron (--tarik). Skrip ikut ter-versioning bersama kode, jadi tidak ada lagi
# salinan manual di ~/docker-apps/deploy.sh yang bisa menyimpang dari repo.
#
# Pemakaian:
#   bash scripts/deploy.sh                 # rilis commit yang sedang ter-checkout
#   bash scripts/deploy.sh --tarik         # fetch origin/main; rilis hanya bila ada commit baru (untuk cron)
#   bash scripts/deploy.sh --rollback TAG  # balik ke image lama (tanpa build ulang)
#   bash scripts/deploy.sh --list          # tampilkan tag image yang tersedia
#
# Urutan rilis: kunci → build → migrasi (ALIAS_MIGRASI=1, image baru, sebelum ganti
# container) → up -d → health check (/up) → pangkas image lama.
# Idempoten; keluar non-zero bila ada langkah gagal.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

COMPOSE=(docker compose -f compose.produksi.yaml --env-file .env.production)
CABANG="${ALIAS_CABANG:-main}"
KEEP_IMAGE_TAGS="${ALIAS_KEEP_IMAGE_TAGS:-5}"
HEALTH_ATTEMPTS=24   # 24 x 5 dtk = 2 menit
HEALTH_INTERVAL=5

log() { printf '\n==> %s\n' "$*"; }

# Satu rilis pada satu waktu (CD dan cron tidak boleh saling menyalip di tengah migrasi).
kunci() {
  # --tarik meneruskan kunci ke proses exec-nya (fd 9 diwariskan); jangan meminta ulang.
  [[ "${ALIAS_TERKUNCI:-0}" == "1" ]] && return 0
  exec 9>"${TMPDIR:-/tmp}/alias-deploy.lock"
  if ! flock -n 9; then
    echo "Rilis lain sedang berjalan; keluar." >&2
    exit 75
  fi
}

# Port loopback web dibaca dari .env.production (ALIAS_PORT); reverse proxy fakultas memotong awalan /alias.
url_kesehatan() {
  local port
  port="$(sed -n 's/^ALIAS_PORT=\([0-9]\{1,5\}\).*/\1/p' .env.production 2>/dev/null | tail -n1)"
  echo "http://127.0.0.1:${port:-8018}/api/health"
}

# Alias punya /api/health sendiri (DB + job tertunda/gagal + detak penjadwal), lebih dalam dari /up bawaan Laravel.
tunggu_sehat() {
  local url attempt
  url="$(url_kesehatan)"
  log "health check → $url"

  for ((attempt = 1; attempt <= HEALTH_ATTEMPTS; attempt++)); do
    if curl -fsS --max-time 10 "$url" 2>/dev/null | grep -q '"ok"'; then
      printf 'OK: aplikasi sehat (percobaan %d).\n' "$attempt"
      return 0
    fi
    sleep "$HEALTH_INTERVAL"
  done

  echo "GAGAL: $url tidak sehat setelah $((HEALTH_ATTEMPTS * HEALTH_INTERVAL)) detik." >&2
  echo "--- 50 baris terakhir log alias-php ---" >&2
  "${COMPOSE[@]}" logs --tail=50 alias-php >&2 || true
  return 1
}

daftar_tag() {
  docker image ls alias-php --format '{{.Tag}}\t{{.CreatedSince}}\t{{.Size}}' | sort
}

# Rollback = nyalakan ulang container dari image lama; tidak ada build, hitungan detik.
rollback() {
  local tag="${1:?Pemakaian: deploy.sh --rollback <tag>}"

  for img in alias-php alias-php-web; do
    if ! docker image inspect "$img:$tag" >/dev/null 2>&1; then
      echo "GAGAL: image $img:$tag tidak ada. Tag tersedia:" >&2
      daftar_tag >&2
      exit 1
    fi
  done

  log "rollback ke $tag"
  ALIAS_VERSI="$tag" "${COMPOSE[@]}" up -d --no-build --remove-orphans
  tunggu_sehat

  cat <<EOF

CATATAN: rollback hanya mengembalikan KODE, bukan skema database.
Working tree git masih di commit baru; sinkronkan bila rollback ini permanen.
EOF
}

# Sisakan beberapa tag terakhir supaya rollback tetap mungkin.
pangkas_image() {
  local img tag
  for img in alias-php alias-php-web; do
    docker image ls "$img" --format '{{.Tag}} {{.CreatedAt}}' \
      | grep -vE '^(latest|<none>) ' \
      | sort -k2 -r \
      | tail -n +"$((KEEP_IMAGE_TAGS + 1))" \
      | awk '{print $1}' \
      | while read -r tag; do
          docker image rm "$img:$tag" >/dev/null 2>&1 || true
        done
  done
  docker image prune -f >/dev/null 2>&1 || true
}

rilis() {
  local sha versi
  sha="$(git rev-parse --short HEAD)"
  versi="$(git describe --tags --always)"
  log "rilis $sha ($versi) — $(git log -1 --pretty=%s)"

  log "build image (alias-php + alias-php-web)"
  ALIAS_VERSI="$sha" "${COMPOSE[@]}" build

  # Migrasi memakai image BARU, sebelum container diganti. ALIAS_MIGRASI=1 memberi tahu entrypoint.sh
  # untuk menjalankan `migrate --force`; migrasi harus kompatibel mundur (rilis lama masih melayani
  # permintaan sampai up -d selesai).
  log "migrasi basis data"
  ALIAS_VERSI="$sha" ALIAS_MIGRASI=1 "${COMPOSE[@]}" run --rm --no-deps -T alias-php php artisan migrate --force --no-interaction

  log "up -d"
  ALIAS_VERSI="$sha" "${COMPOSE[@]}" up -d --remove-orphans

  # Tag "latest" mengikuti rilis aktif supaya `docker compose up` tanpa variabel tidak mundur versi.
  docker tag "alias-php:$sha" alias-php:latest
  docker tag "alias-php-web:$sha" alias-php-web:latest

  tunggu_sehat
  pangkas_image

  log "status"
  "${COMPOSE[@]}" ps

  cat <<EOF

Selesai. Rilis $sha ($versi) aktif.
Rollback cepat:  bash scripts/deploy.sh --rollback <tag>
Tag tersedia:
$(daftar_tag)
EOF
}

# Mode cron: ambil origin/main; keluar diam-diam bila tidak ada commit baru.
tarik() {
  git fetch --prune origin "$CABANG"

  local lokal jauh
  lokal="$(git rev-parse HEAD)"
  jauh="$(git rev-parse "origin/$CABANG")"

  if [[ "$lokal" == "$jauh" ]]; then
    echo "Sudah terbaru ($(git rev-parse --short HEAD))."
    return 0
  fi

  log "commit baru: $(git rev-parse --short HEAD) → $(git rev-parse --short "origin/$CABANG")"
  git reset --hard "origin/$CABANG"
  # Skrip ini mungkin ikut berubah; jalankan versi terbaru untuk rilis.
  ALIAS_TERKUNCI=1 exec bash scripts/deploy.sh
}

case "${1:-}" in
  --list)     daftar_tag ;;
  --rollback) kunci; rollback "${2:-}" ;;
  --tarik)    kunci; tarik ;;
  '')         kunci; rilis ;;
  *)          echo "Argumen tidak dikenal: $1" >&2; exit 2 ;;
esac
