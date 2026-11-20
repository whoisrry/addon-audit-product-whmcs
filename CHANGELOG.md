# Changelog

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/).

## [Unreleased]

## [1.4.0] - 2026-11-20

### Added

- Konfirmasi password admin saat membuka modul (seperti `configgeneral.php`), berlaku
  15 menit sejak aktivitas terakhir, terkunci 5 menit setelah 5 kali salah.
- Tombol **Delete** di sebelah **Detail**, hanya aktif jika product tidak punya service sama sekali.
- Halaman konfirmasi hapus **Ya, hapus / Tidak, batal** tanpa JavaScript; hapus via POST
  dengan token CSRF, dalam satu transaksi, beserta data turunan product.
- Pencatatan ke Activity Log WHMCS untuk password salah dan product yang dihapus.

### Changed

- Modul tidak lagi sepenuhnya read-only: satu-satunya operasi tulis adalah hapus product
  tanpa service (`lib/ProductDeleter.php`).

## [1.3.0] - 2026-11-15

### Added

- Pencarian di halaman ringkasan: kata kunci nama atau ID product, dropdown product group,
  dan checkbox **is Hidden** (tetap ada), dengan tombol Cari dan Reset.
- Filter ringkasan ikut terbawa ke paging dan ke halaman detail, sehingga tombol
  "Kembali ke ringkasan" kembali ke hasil pencarian yang sama.

## [1.2.0] - 2026-11-14

### Removed

- Field konfigurasi **Allowed Admin Role IDs** dan cek role tambahan (`lib/Access.php`).
  Akses kini hanya diatur lewat checklist **Access Control** bawaan WHMCS; setelah update,
  pastikan role yang diinginkan sudah dicentang di sana.

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
