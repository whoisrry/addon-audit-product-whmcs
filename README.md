# Product Audit — Addon WHMCS

Addon module **read-only** untuk mengaudit product di WHMCS dan service (`tblhosting`)
yang merujuknya, per status. Berguna untuk merapikan product hidden yang tidak bisa
dihapus karena masih dirujuk service lama. Modul tidak menulis, mengubah, atau menghapus
data apa pun, termasuk saat activate/deactivate.

Target: **WHMCS 8.13.7** (seharusnya kompatibel dengan 8.x; PHP mengikuti versi yang didukung WHMCS 8.13).

## Fitur

- Ringkasan product: ID, nama (dengan label *Hidden*), group, jumlah service per status
  (Active, Suspended, Terminated, Pending, Cancelled, Fraud, Lainnya, Total), dengan paging.
  Centang **is Hidden** untuk hanya menampilkan product hidden; tanpa centang semua product tampil.
- Detail per product: semua service product tersebut (service ID, client ID, nama client,
  domain/service, product, status, tanggal terminate), dengan paging.
  - Pilih status yang ditampilkan lewat checkbox (default semua status, lengkap dengan jumlahnya).
  - Filter rentang tanggal terminate.
  - Service ID membuka halaman service client (`clientsservices.php`), Client ID membuka
    profil client (`clientssummary.php`).
- Export CSV seluruh hasil filter (semua halaman), dengan perlindungan CSV injection.
- Akses dibatasi ke admin role yang terdaftar di konfigurasi modul (fail closed).

## Struktur

```
modules/addons/productaudit/
├── productaudit.php   # _config, _activate, _deactivate, _output, filter, export CSV
└── lib/
    ├── Access.php     # cek role admin (tbladmins)
    ├── Input.php      # validasi $_GET (action, int, flag, status, tanggal)
    ├── Repository.php # semua query SELECT via Capsule
    └── View.php       # render HTML (escape), link admin, pager, helper CSV
docs/
├── QUERIES.md         # daftar query + penjelasan
└── TESTING.md         # checklist pengujian staging
```

## Instalasi

1. Salin `modules/addons/productaudit/` ke `<root WHMCS>/modules/addons/`.
2. Admin area: **System Settings → Addon Modules** → Activate *Product Audit*.
3. Klik **Configure**:
   - **Allowed Admin Role IDs**: ID role yang boleh akses, dipisah koma (lihat
     **System Settings → Administrator Roles**; Full Administrator biasanya ID 1).
     Kosong berarti semua ditolak.
   - **Rows per Page**: 10–200 (default 50).
   - **Access Control** (bawaan WHMCS): centang role yang sama.
4. Buka **Addons → Product Audit**.

> Jika sebelumnya sudah memasang versi 1.0.0 (`hiddenproductaudit`), deactivate modul lama
> dan hapus foldernya, lalu activate `productaudit` dan isi ulang konfigurasinya.

## Bagian yang bergantung versi / perlu diverifikasi

Ditandai `[VERSI]` dan `[VERIFIKASI]` di kode.

- `tblproducts.hidden` bernilai `1` untuk product hidden.
- `tblhosting.termination_date` sebagai tanggal terminate. Dicek via
  `Capsule::schema()->hasColumn()`; jika tidak ada, filter tanggal dinonaktifkan dengan peringatan.
  Nilai bisa kosong/`0000-00-00` untuk service lama atau yang belum di-terminate; service tanpa
  tanggal terminate otomatis tidak tampil saat filter tanggal dipakai.
- `tblhosting.domainstatus` sebagai status service, `tblhosting.packageid` → `tblproducts.id`.
- `tbladmins.roleid` dan `tbladmins.disabled` untuk cek akses.
- `$_SESSION['adminid']` sebagai ID admin yang login.
- URL admin `clientsservices.php?userid=&id=` dan `clientssummary.php?userid=`.
- Export CSV membuang output buffer admin WHMCS lalu `exit`; pastikan file yang
  diunduh bersih (tanpa HTML) di staging.

## Asumsi

- Query dijalankan dengan koneksi database WHMCS sendiri (Capsule); tidak ada kredensial di kode.
- Request bersifat GET dan read-only, sehingga tidak memakai token CSRF.
- Data client yang tampil/diekspor (nama, domain) adalah PII: batasi role yang diizinkan
  dan perlakukan file CSV sesuai kebijakan data internal.

Lihat [docs/QUERIES.md](docs/QUERIES.md) dan [docs/TESTING.md](docs/TESTING.md).
