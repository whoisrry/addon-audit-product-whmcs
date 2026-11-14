# Hidden Product Audit — Addon WHMCS

Addon module **read-only** untuk mengaudit product yang di-hidden di WHMCS dan service
(`tblhosting`) berstatus Terminated yang masih merujuknya. Modul tidak menulis, mengubah,
atau menghapus data apa pun, termasuk saat activate/deactivate.

Target: **WHMCS 8.13.7** (seharusnya kompatibel dengan 8.x; PHP mengikuti versi yang didukung WHMCS 8.13).

## Fitur

- Ringkasan product hidden: ID, nama, group, jumlah service per status
  (Active, Suspended, Terminated, Pending, Cancelled, Fraud, Lainnya, Total), dengan paging.
- Detail per product: daftar service Terminated (service ID, client ID, nama client,
  domain/service, product, status, tanggal terminate), dengan paging.
- Filter rentang tanggal terminate di halaman detail.
- Export CSV seluruh hasil filter (semua halaman), dengan perlindungan CSV injection.
- Akses dibatasi ke admin role yang terdaftar di konfigurasi modul (fail closed).

## Struktur

```
modules/addons/hiddenproductaudit/
├── hiddenproductaudit.php   # _config, _activate, _deactivate, _output, export CSV
└── lib/
    ├── Access.php           # cek role admin (tbladmins)
    ├── Input.php            # validasi $_GET (action, int, tanggal)
    ├── Repository.php       # semua query SELECT via Capsule
    └── View.php             # render HTML (escape), pager, helper CSV
docs/
├── QUERIES.md               # daftar query + penjelasan
└── TESTING.md               # checklist pengujian staging
```

## Instalasi

1. Salin `modules/addons/hiddenproductaudit/` ke `<root WHMCS>/modules/addons/`.
2. Admin area: **System Settings → Addon Modules** → Activate *Hidden Product Audit*.
3. Klik **Configure**:
   - **Allowed Admin Role IDs**: ID role yang boleh akses, dipisah koma (lihat
     **System Settings → Administrator Roles**; Full Administrator biasanya ID 1).
     Kosong berarti semua ditolak.
   - **Rows per Page**: 10–200 (default 50).
   - **Access Control** (bawaan WHMCS): centang role yang sama.
4. Buka **Addons → Hidden Product Audit**.

## Bagian yang bergantung versi / perlu diverifikasi

Ditandai `[VERSI]` dan `[VERIFIKASI]` di kode.

- `tblproducts.hidden` bernilai `1` untuk product hidden.
- `tblhosting.termination_date` sebagai tanggal terminate. Dicek via
  `Capsule::schema()->hasColumn()`; jika tidak ada, filter tanggal dinonaktifkan dengan peringatan.
  Nilai bisa kosong/`0000-00-00` untuk service lama yang di-terminate sebelum kolom ini terisi.
- `tblhosting.domainstatus` sebagai status service, `tblhosting.packageid` → `tblproducts.id`.
- `tbladmins.roleid` dan `tbladmins.disabled` untuk cek akses.
- `$_SESSION['adminid']` sebagai ID admin yang login.
- Export CSV membuang output buffer admin WHMCS lalu `exit`; pastikan file yang
  diunduh bersih (tanpa HTML) di staging.

## Asumsi

- Query dijalankan dengan koneksi database WHMCS sendiri (Capsule); tidak ada kredensial di kode.
- Request bersifat GET dan read-only, sehingga tidak memakai token CSRF.
- Data client yang tampil/diekspor (nama, domain) adalah PII: batasi role yang diizinkan
  dan perlakukan file CSV sesuai kebijakan data internal.

Lihat [docs/QUERIES.md](docs/QUERIES.md) dan [docs/TESTING.md](docs/TESTING.md).
