# Changelog

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/).

## [Unreleased]

## [1.0.0] - 2026-11-14

### Added

- Addon module `hiddenproductaudit` (read-only) untuk WHMCS 8.13.7.
- Ringkasan product hidden dengan jumlah service per status dan paging.
- Halaman detail service Terminated per product dengan filter tanggal terminate dan paging.
- Export CSV seluruh hasil filter, dengan proteksi CSV/formula injection.
- Pembatasan akses berdasarkan admin role (fail closed).
- Deteksi kolom tanggal terminate via `Capsule::schema()`.
- Dokumentasi query (`docs/QUERIES.md`) dan checklist pengujian staging (`docs/TESTING.md`).
