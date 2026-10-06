# Catatan Perubahan

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan [SemVer](https://semver.org/lang/id/).

## [Belum dirilis]

## [0.1.0] - 2026-10-06

### Ditambahkan
- Layanan Docker: Redis, Mailpit, Horizon, scheduler (MySQL dikecualikan; basis data SQLite).
- Lokal/zona waktu Indonesia, Redis untuk sesi/cache/antrean, Pest 4, Larastan level 6, Pint, uji asap.
- Filament 5.9 / Livewire 4, Horizon, activitylog (UUID), Laravel Boost (MCP), endpoint `/api/health`.
- Uji arsitektur kunci primer UUIDv7, workflow CI.

### Ditambahkan
- Audit awal repositori (`docs/AUDIT-AWAL.md`).
- Hook git Conventional Commits dan pra-commit (`.githooks/`), pembungkus PHP container `bin/ap`.
- Aturan `.gitignore` tambahan dan bagian "Pengembangan" pada README.
