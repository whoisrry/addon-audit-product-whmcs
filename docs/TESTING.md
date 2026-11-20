# Checklist Pengujian Staging (WHMCS 8.13.7)

Jalankan di staging dengan salinan database produksi yang sudah dianonimkan bila memungkinkan.

## Instalasi

- [ ] Salin folder ke `modules/addons/productaudit/`; permission file sama dengan addon lain.
- [ ] Jika versi lama (`hiddenproductaudit`) terpasang: deactivate lalu hapus foldernya.
- [ ] `php -l` untuk semua file `.php` di modul tidak menghasilkan error.
- [ ] Modul muncul di **System Settings → Addon Modules** dengan nama *Product Audit*.

## Aktivasi

- [ ] Activate berhasil dengan pesan "Tidak ada tabel yang dibuat atau diubah".
- [ ] Bandingkan `SHOW TABLES` sebelum/sesudah activate: tidak ada tabel baru.
- [ ] Jika muncul peringatan kolom tanggal terminate, catat dan cek `docs/QUERIES.md`.
- [ ] Deactivate lalu activate ulang berjalan normal, tanpa perubahan data.

## Akses

- [ ] Admin dengan role yang dicentang di *Access Control* bisa membuka modul dari menu Addons.
- [ ] Admin dengan role yang tidak dicentang tidak melihat menu modul, dan membuka `addonmodules.php?module=productaudit` langsung ditolak WHMCS.
- [ ] Hal yang sama berlaku untuk URL detail dan export (`&action=detail&pid=...`, `&action=export&pid=...`).
- [ ] Tanpa login admin, `addonmodules.php?module=productaudit` diarahkan ke login WHMCS.
- [ ] Akses langsung `modules/addons/productaudit/productaudit.php` → "cannot be accessed directly".
- [ ] File di `lib/` diakses langsung tidak menampilkan data (hanya definisi class). Pertimbangkan blokir `modules/addons/*/lib/` di web server.

## Konfirmasi password

- [ ] Membuka modul pertama kali menampilkan form *Konfirmasi Password*, belum ada data product yang tampil.
- [ ] Membuka URL detail/delete langsung juga meminta password dulu, lalu setelah benar langsung masuk ke halaman yang dituju.
- [ ] Password benar → modul terbuka; password salah → "Password salah" dan tercatat di Activity Log.
- [ ] 5 kali salah → dikunci 5 menit; password benar pun ditolak selama terkunci.
- [ ] Setelah 15 menit tanpa aktivitas di modul, password diminta lagi.
- [ ] Login sebagai admin lain di browser yang sama → password diminta lagi (konfirmasi terikat admin ID).
- [ ] Logout lalu login ulang → password diminta lagi.
- [ ] Password tidak muncul di Activity Log, log web server (POST body), maupun HTML halaman.
- [ ] Admin dengan login SSO/OAuth tanpa password lokal: pastikan perilakunya sesuai kebijakan (tidak bisa masuk).
- [ ] Kirim POST form password dengan `pa_csrf` salah → "Formulir kedaluwarsa".

## Tombol Delete & konfirmasi

- [ ] Tombol Delete muncul di sebelah Detail untuk setiap product.
- [ ] Product dengan Total service > 0 → tombol Delete abu-abu (disabled), tooltip menyebut jumlah service.
- [ ] Product dengan Total = 0 → tombol Delete aktif; klik membuka halaman konfirmasi (bukan pop-up JS).
- [ ] Halaman konfirmasi menampilkan ID, nama, group, status hidden, jumlah service 0, peringatan permanen, tombol **Ya, hapus** dan **Tidak, batal**.
- [ ] **Tidak, batal** kembali ke ringkasan dengan filter sebelumnya; product tidak berubah.
- [ ] **Ya, hapus** → pesan sukses hijau, product hilang dari daftar, tercatat di Activity Log beserta jumlah data terkait.
- [ ] Buka `&action=delete&pid=<product yang punya service>` langsung → pesan "tidak bisa dihapus" tanpa tombol Ya.
- [ ] Buka halaman konfirmasi, lalu (di tab lain) buat order service untuk product itu, lalu klik Ya → ditolak "masih dirujuk N service".
- [ ] Klik Ya dua kali / refresh halaman hasil POST → percobaan kedua menampilkan "tidak ditemukan atau sudah dihapus", tanpa error.
- [ ] POST hapus dengan `pa_csrf` salah atau tanpa konfirmasi password → tidak ada yang terhapus.
- [ ] Setelah hapus, cek data turunan product sudah bersih: `tblpricing (type='product')`, `tblcustomfields (type='product')`, `tblproductconfiglinks`, `tblproduct_upgrade_products`, `tblproduct_downloads`, `tblproducts_slugs`.
- [ ] Bandingkan dengan menghapus product uji serupa lewat **Products/Services** bawaan WHMCS (general log aktif): catat tabel yang dihapus WHMCS tetapi tidak oleh modul.
- [ ] Halaman order/cart client dan **Products/Services** admin tetap normal setelah product dihapus.

## Ringkasan & checkbox is Hidden

- [ ] Tanpa centang *is Hidden*: semua product tampil; total = `SELECT COUNT(*) FROM tblproducts`.
- [ ] Dengan centang: hanya product hidden; total = `SELECT COUNT(*) FROM tblproducts WHERE hidden = 1`.
- [ ] Product hidden diberi label *Hidden* di kedua mode.
- [ ] Status centang tetap terbawa saat pindah halaman.

## Pencarian & filter ringkasan

- [ ] Kata kunci sebagian nama (mis. "hosting") menampilkan product yang namanya mengandung kata itu, tidak peka huruf besar/kecil (sesuai collation DB).
- [ ] Kata kunci angka (mis. "12") menampilkan product dengan ID 12 dan product yang namanya mengandung "12".
- [ ] Kata kunci `%` atau `_` dicari sebagai karakter biasa, bukan wildcard.
- [ ] Dropdown group hanya menampilkan product di group tersebut; `gid` yang tidak ada diabaikan.
- [ ] Kombinasi kata kunci + group + is Hidden memberi hasil irisan yang benar.
- [ ] Filter tetap terbawa saat paging, saat membuka Detail, dan saat klik "Kembali ke ringkasan".
- [ ] Tombol Reset mengosongkan semua filter.
- [ ] Kata kunci berisi `<script>` atau tanda kutip tampil kembali di kotak pencarian sebagai teks.

## Detail & filter status

- [ ] Default membuka detail: semua status tercentang, semua service product tampil.
- [ ] Jumlah di samping tiap status sama dengan `SELECT domainstatus, COUNT(*) FROM tblhosting WHERE packageid = ? GROUP BY domainstatus`.
- [ ] Pilih sebagian status (mis. Active + Suspended) → hanya status itu yang tampil; pilihan terbawa ke paging dan export.
- [ ] Semua checkbox dikosongkan lalu Terapkan → peringatan, dan semua status ditampilkan.
- [ ] `status[]=Foo` atau status yang tidak ada di product → diabaikan.
- [ ] Filter tanggal terminate hanya menampilkan service yang punya tanggal terminate dalam rentang.

## Link Service ID & Client ID

- [ ] Klik Service ID membuka halaman service yang tepat di `clientsservices.php` (client dan service sesuai).
- [ ] Klik Client ID membuka profil client yang tepat di `clientssummary.php`.
- [ ] Service dengan client yang sudah tidak ada (`userid` 0 / terhapus) tampil tanpa link dan tanpa error.

## Data kosong

- [ ] Tanpa product (atau tanpa product hidden saat dicentang): tampil "Tidak ada product untuk ditampilkan".
- [ ] Product tanpa service: semua kolom status 0; detail menampilkan pesan kosong; export berisi header saja.
- [ ] `pid` tidak ada (mis. 999999) → "Product #... tidak ditemukan".

## Validasi input

- [ ] `pid=abc`, `pid=-1`, `page=0`, `page[]=1`, `action=foo`, `hidden=yes` → diabaikan/fallback ke default, tanpa error PHP.
- [ ] `from=2024-02-30` atau format lain ditolak (filter tidak diterapkan).
- [ ] `from` > `to` → muncul peringatan dan filter tanggal diabaikan.
- [ ] Nama product/client/domain/status yang mengandung `<script>` tampil sebagai teks (tidak dieksekusi).

## Data besar (paging)

- [ ] Ringkasan dengan ratusan product terbagi per halaman sesuai *Rows per Page*.
- [ ] Detail product dengan ribuan service: navigasi pertama/sebelumnya/berikutnya/terakhir benar dan filter status/tanggal tetap terbawa.
- [ ] `page` melebihi jumlah halaman → tabel kosong, tanpa error.
- [ ] Waktu muat halaman wajar; cek `EXPLAIN` untuk Q2/Q4/Q5 bila lambat.

## Export CSV

- [ ] Tombol Export mengunduh file `.csv` (bukan halaman HTML) dan tidak mengandung markup template WHMCS.
- [ ] Jumlah baris CSV = jumlah service di halaman detail dengan filter yang sama (semua halaman, bukan hanya halaman aktif).
- [ ] Filter status dan tanggal ikut diterapkan pada export.
- [ ] Karakter non-ASCII tampil benar di Excel (UTF-8 BOM).
- [ ] Nilai yang diawali `=`, `+`, `-`, `@` diawali tanda `'` (proteksi formula injection).
- [ ] Admin dengan role yang tidak dicentang di *Access Control* tidak bisa mengunduh lewat URL export langsung.

## Verifikasi query tulis hanya dari fitur Delete

- [ ] Review kode: `grep -rniE "insert|update\(|->delete\(|truncate|drop|alter|create|->save\(|->increment|->decrement" modules/addons/productaudit` hanya cocok di `lib/ProductDeleter.php` dan komentar/teks UI.
- [ ] Aktifkan MySQL general log di staging (`SET GLOBAL general_log = 'ON'`), jalankan semua skenario selain Delete, lalu pastikan query dari modul hanya `SELECT` / `information_schema` (plus `INSERT INTO tblactivitylog` dari `logActivity` untuk password salah). Matikan lagi log setelahnya.
- [ ] Skenario Delete: query tulis hanya `DELETE` sesuai daftar D2–D9 di `docs/QUERIES.md`, di dalam satu transaksi.
- [ ] Checksum/`COUNT(*)` `tblhosting` dan `tblclients` sebelum dan sesudah semua pengujian sama.
