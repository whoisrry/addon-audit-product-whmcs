# Changelog

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/).

## [Unreleased]

## [1.1.0] - 2026-11-14

### Changed

- Nama modul menjadi **Product Audit**; slug/folder menjadi `productaudit`
  (namespace `WHMCS\Module\Addon\ProductAudit`). Instalasi 1.0.0 perlu di-deactivate dan diganti.
- Ringkasan menampilkan semua product secara default; checkbox **is Hidden** untuk
  hanya menampilkan product hidden. Product hidden diberi label *Hidden*.
- Halaman detail menampilkan semua service product (bukan hanya Terminated), dengan
  pilihan status lewat checkbox beserta jumlah per status. Filter status ikut ke paging dan export CSV.

### Added

- Service ID tertaut ke halaman service client (`clientsservices.php`) dan Client ID
  tertaut ke profil client (`clientssummary.php`).

## [1.0.0] - 2026-11-14

### Added

- Addon module `hiddenproductaudit` (read-only) untuk WHMCS 8.13.7.
- Ringkasan product hidden dengan jumlah service per status dan paging.
- Halaman detail service Terminated per product dengan filter tanggal terminate dan paging.
- Export CSV seluruh hasil filter, dengan proteksi CSV/formula injection.
- Pembatasan akses berdasarkan admin role (fail closed).
- Deteksi kolom tanggal terminate via `Capsule::schema()`.
- Dokumentasi query (`docs/QUERIES.md`) dan checklist pengujian staging (`docs/TESTING.md`).
