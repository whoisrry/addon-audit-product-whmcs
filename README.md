# Product Audit — Addon WHMCS

Addon module untuk mengaudit product di WHMCS dan service (`tblhosting`) yang merujuknya,
per status, serta menghapus product yang sudah tidak dirujuk service apa pun. Berguna untuk
merapikan product hidden yang tidak dijual lagi.

Selain hapus product (dan data turunannya) lewat tombol **Delete**, modul tidak mengubah
data apa pun. Activate/deactivate tidak membuat atau mengubah tabel. Konfirmasi password
dan hapus product dicatat di Activity Log WHMCS.

Target: **WHMCS 8.13.7** (seharusnya kompatibel dengan 8.x; PHP mengikuti versi yang didukung WHMCS 8.13).

## Fitur

- Ringkasan product: ID, nama (dengan label *Hidden*), group, jumlah service per status
  (Active, Suspended, Terminated, Pending, Cancelled, Fraud, Lainnya, Total), dengan paging.
  - Cari berdasarkan nama product (sebagian kata) atau ID product.
  - Filter per product group (dropdown).
  - Centang **is Hidden** untuk hanya menampilkan product hidden; tanpa centang semua product tampil.
  - Filter bisa digabung, ikut terbawa ke paging, dan dipertahankan saat kembali dari halaman detail.
- Detail per product: semua service product tersebut (service ID, client ID, nama client,
  domain/service, product, status, tanggal terminate), dengan paging.
  - Pilih status yang ditampilkan lewat checkbox (default semua status, lengkap dengan jumlahnya).
  - Filter rentang tanggal terminate.
  - Service ID membuka halaman service client (`clientsservices.php`), Client ID membuka
    profil client (`clientssummary.php`).
- Export CSV seluruh hasil filter (semua halaman), dengan perlindungan CSV injection.
- Tombol **Delete** di sebelah **Detail**: hanya aktif jika product tidak punya service sama
  sekali (Total = 0). Klik Delete membuka halaman konfirmasi **Ya, hapus / Tidak, batal**
  (tanpa JavaScript). Jumlah service dicek ulang di dalam transaksi sebelum menghapus.
- Akses dibatasi lewat checklist **Access Control** bawaan WHMCS (per admin role), lalu admin
  wajib memasukkan ulang password-nya saat membuka modul (seperti `configgeneral.php`).
  Konfirmasi berlaku 15 menit sejak aktivitas terakhir; 5 kali salah → dikunci 5 menit.

## Struktur

```
modules/addons/productaudit/
├── productaudit.php       # _config, _activate, _deactivate, _output, filter, export CSV
└── lib/
    ├── Auth.php           # konfirmasi password admin, token CSRF, Activity Log
    ├── Input.php          # validasi $_GET/$_POST (action, int, teks, flag, status, tanggal)
    ├── ProductDeleter.php # SATU-SATUNYA kode yang menulis: hapus product tanpa service
    ├── Repository.php     # query SELECT via Capsule
    └── View.php           # render HTML (escape), form, link admin, pager, helper CSV
docs/
├── QUERIES.md         # daftar query + penjelasan
└── TESTING.md         # checklist pengujian staging
```

## Instalasi

1. Salin `modules/addons/productaudit/` ke `<root WHMCS>/modules/addons/`.
2. Admin area: **System Settings → Addon Modules** → Activate *Product Audit*.
3. Klik **Configure**:
   - **Rows per Page**: 10–200 (default 50).
   - **Access Control**: centang admin role yang boleh membuka modul. Role yang tidak
     dicentang tidak melihat menu modul dan ditolak WHMCS saat membuka URL-nya.
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
- Pembatasan akses sepenuhnya mengandalkan Access Control bawaan WHMCS untuk addon module,
  termasuk URL export CSV (`addonmodules.php?module=productaudit&action=export`).
- URL admin `clientsservices.php?userid=&id=` dan `clientssummary.php?userid=`.
- Export CSV membuang output buffer admin WHMCS lalu `exit`; pastikan file yang
  diunduh bersih (tanpa HTML) di staging.
- Konfirmasi password mencocokkan input dengan hash di `tbladmins.passwordhash` dan/atau
  `tbladmins.password` via `password_verify()`. Admin yang hanya login via SSO/OAuth (tanpa
  password lokal) tidak bisa melewati konfirmasi ini.
- Daftar tabel turunan yang ikut dihapus (`ProductDeleter::RELATED` + `tblcustomfields`/
  `tblcustomfieldsvalues`): bandingkan dengan penghapusan bawaan WHMCS di `configproducts.php`
  pada staging (aktifkan MySQL general log, hapus product uji dari kedua sisi, bandingkan query).
  Referensi teks seperti `tblpromotions.appliesto` tidak diubah.
- `$_SESSION['adminid']`, `generate_token('plain')`, dan `logActivity()` dari WHMCS.

## Keselamatan hapus product

- Hapus bersifat permanen. Ambil backup database sebelum dipakai di production dan uji dulu di staging.
- Penghapusan di production mengikuti prosedur change management internal (persetujuan,
  rencana rollback, verifikasi setelahnya).
- Batasi role di Access Control hanya untuk admin yang memang berwenang menghapus product.

## Asumsi

- Query dijalankan dengan koneksi database WHMCS sendiri (Capsule); tidak ada kredensial di kode.
- Halaman tampil memakai GET. Konfirmasi password dan hapus memakai POST dengan token CSRF
  modul (di session) ditambah token bawaan WHMCS.
- Status konfirmasi password disimpan di session PHP WHMCS (`$_SESSION['productaudit']`),
  bukan di database. Password tidak disimpan maupun dicatat.
- Data client yang tampil/diekspor (nama, domain) adalah PII: batasi role yang diizinkan
  dan perlakukan file CSV sesuai kebijakan data internal.

Lihat [docs/QUERIES.md](docs/QUERIES.md) dan [docs/TESTING.md](docs/TESTING.md).
