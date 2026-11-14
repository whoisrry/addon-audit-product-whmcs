# Checklist Pengujian Staging (WHMCS 8.13.7)

Jalankan di staging dengan salinan database produksi yang sudah dianonimkan bila memungkinkan.

## Instalasi

- [ ] Salin folder ke `modules/addons/hiddenproductaudit/`; permission file sama dengan addon lain.
- [ ] `php -l` untuk semua file `.php` di modul tidak menghasilkan error.
- [ ] Modul muncul di **System Settings → Addon Modules**.

## Aktivasi

- [ ] Activate berhasil dengan pesan "Tidak ada tabel yang dibuat atau diubah".
- [ ] Bandingkan `SHOW TABLES` sebelum/sesudah activate: tidak ada tabel baru.
- [ ] Jika muncul peringatan kolom tanggal terminate, catat dan cek `docs/QUERIES.md`.
- [ ] Deactivate lalu activate ulang berjalan normal, tanpa perubahan data.

## Akses

- [ ] Admin dengan role terdaftar di *Allowed Admin Role IDs* bisa membuka modul.
- [ ] Admin dengan role lain mendapat "Akses ditolak" (termasuk jika role diberi Access Control bawaan tetapi tidak terdaftar).
- [ ] *Allowed Admin Role IDs* kosong → semua admin ditolak.
- [ ] Admin yang di-disable tidak bisa akses.
- [ ] Tanpa login admin, `addonmodules.php?module=hiddenproductaudit` diarahkan ke login WHMCS.
- [ ] Akses langsung `modules/addons/hiddenproductaudit/hiddenproductaudit.php` → "cannot be accessed directly".
- [ ] File di `lib/` diakses langsung tidak menampilkan data (hanya definisi class). Pertimbangkan blokir `modules/addons/*/lib/` di web server.

## Data kosong

- [ ] Tanpa product hidden: tampil "Tidak ada product hidden untuk ditampilkan".
- [ ] Product hidden tanpa service: semua kolom status 0.
- [ ] Detail product tanpa service Terminated: tampil pesan kosong; export menghasilkan CSV berisi header saja.
- [ ] `pid` tidak ada (mis. 999999) → "Product #... tidak ditemukan".

## Validasi input

- [ ] `pid=abc`, `pid=-1`, `page=0`, `page[]=1`, `action=foo` → diabaikan/fallback ke default, tanpa error PHP.
- [ ] `from=2024-02-30` atau format lain ditolak (filter tidak diterapkan).
- [ ] `from` > `to` → muncul peringatan dan filter tanggal diabaikan.
- [ ] Nama product/client yang mengandung `<script>` tampil sebagai teks (tidak dieksekusi).

## Data besar (paging)

- [ ] Ringkasan dengan >100 product hidden terbagi per halaman sesuai *Rows per Page*.
- [ ] Detail product dengan ribuan service Terminated: navigasi pertama/sebelumnya/berikutnya/terakhir benar dan filter tanggal tetap terbawa.
- [ ] `page` melebihi jumlah halaman → tabel kosong, tanpa error.
- [ ] Waktu muat halaman wajar; cek `EXPLAIN` untuk Q2/Q4 bila lambat.

## Export CSV

- [ ] Tombol Export mengunduh file `.csv` (bukan halaman HTML) dan tidak mengandung markup template WHMCS.
- [ ] Jumlah baris CSV = jumlah service di halaman detail dengan filter yang sama (semua halaman, bukan hanya halaman aktif).
- [ ] Filter tanggal ikut diterapkan pada export.
- [ ] Karakter non-ASCII tampil benar di Excel (UTF-8 BOM).
- [ ] Nilai yang diawali `=`, `+`, `-`, `@` diawali tanda `'` (proteksi formula injection).
- [ ] Admin tanpa izin yang membuka URL export langsung mendapat "Akses ditolak".

## Verifikasi tidak ada query tulis

- [ ] Review kode: `grep -rniE "insert|update|delete|truncate|drop|alter|create|->save\(|->increment|->decrement" modules/addons/hiddenproductaudit` hanya cocok di komentar/teks UI.
- [ ] Aktifkan MySQL general log di staging (`SET GLOBAL general_log = 'ON'`), jalankan semua skenario di atas, lalu pastikan query yang berasal dari modul hanya `SELECT` / query `information_schema`. Matikan lagi log setelahnya.
- [ ] Alternatif: jalankan dengan user DB staging yang hanya punya hak `SELECT` pada tabel terkait untuk sesi uji modul.
- [ ] Checksum/`COUNT(*)` `tblproducts`, `tblhosting`, `tblclients` sebelum dan sesudah pengujian sama.
