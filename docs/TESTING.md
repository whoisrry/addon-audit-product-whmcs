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

## Verifikasi tidak ada query tulis

- [ ] Review kode: `grep -rniE "insert|update|delete|truncate|drop|alter|create|->save\(|->increment|->decrement" modules/addons/productaudit` hanya cocok di komentar/teks UI.
- [ ] Aktifkan MySQL general log di staging (`SET GLOBAL general_log = 'ON'`), jalankan semua skenario di atas, lalu pastikan query yang berasal dari modul hanya `SELECT` / query `information_schema`. Matikan lagi log setelahnya.
- [ ] Alternatif: jalankan dengan user DB staging yang hanya punya hak `SELECT` pada tabel terkait untuk sesi uji modul.
- [ ] Checksum/`COUNT(*)` `tblproducts`, `tblhosting`, `tblclients` sebelum dan sesudah pengujian sama.
