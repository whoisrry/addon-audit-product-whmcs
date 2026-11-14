Saya menjalankan WHMCS dan ingin dibuatkan addon module untuk audit product/service yang hidden. Tolong buatkan kode lengkapnya.

Konteks masalah

Ada lebih dari 100 product yang sudah di-hidden dan tidak dijual lagi, tetapi tidak bisa dihapus karena masih dirujuk oleh record service (tblhosting) yang berstatus Terminated. Saya tidak ingin menghapus atau mengubah record service tersebut. Tujuan modul ini hanya untuk melihat data dan merapikan daftar product yang benar-benar dijual.

Persyaratan fungsional

Halaman admin berisi ringkasan product hidden: product ID, nama product, group, dan jumlah service per status (Active, Suspended, Terminated, Pending, Cancelled, dan lainnya).
Halaman detail per product yang menampilkan daftar service Terminated, dengan kolom: service ID, client ID, nama client, domain atau nama service, product, status, dan tanggal terminate.
Filter sederhana: product ID tertentu dan rentang tanggal terminate.
Tombol export CSV untuk seluruh daftar yang sedang ditampilkan.
Paging untuk data besar.

Persyaratan teknis

Target: WHMCS 8.x (sebutkan versi jika memungkinkan, dan tandai bagian yang bergantung versi).
Gunakan WHMCS\Database\Capsule untuk query.
Struktur file mengikuti standar addon module: modules/addons/<nama>/<nama>.php dengan fungsi _config, _activate, _deactivate, dan _output.
Gunakan WHMCS\Module\Addon\... atau helper yang sesuai, dan escape semua output HTML untuk mencegah XSS.
Validasi dan sanitasi semua input dari $_GET atau $_POST, dan gunakan parameter binding untuk query.
Akses hanya untuk admin yang memiliki izin yang sesuai. Gunakan pengecekan role admin WHMCS.
Tidak ada operasi tulis ke database. Modul harus benar-benar read-only.
Jika ada perubahan skema atau nama kolom antar versi WHMCS, tambahkan komentar dan cek Capsule::schema() bila perlu.

Batasan dan keselamatan

Jangan mengandalkan hook yang mengubah data.
Jangan menyertakan kredensial, API key, atau data pribadi dalam kode.
Sertakan komentar singkat pada bagian query yang perlu diverifikasi di skema versi saya.

Yang saya minta sebagai output

Struktur folder dan daftar file.
Kode lengkap untuk setiap file.
Daftar query yang dipakai beserta penjelasan singkat.
Checklist pengujian di staging, mencakup: instalasi, aktivasi, akses non-admin ditolak, data kosong, data besar (paging), export CSV, dan verifikasi bahwa tidak ada query tulis.
Catatan asumsi dan hal yang harus saya cek sendiri di sistem saya.
