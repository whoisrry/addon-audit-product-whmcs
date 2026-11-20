# Daftar Query

Query baca ada di `lib/Repository.php` dan `lib/Auth.php`; query tulis (DELETE) hanya ada di
`lib/ProductDeleter.php`. Semua dibangun dengan `WHMCS\Database\Capsule` (parameter binding
otomatis). Tidak ada INSERT, UPDATE, atau DDL dari modul; `logActivity()` WHMCS menulis ke
`tblactivitylog` lewat fungsi bawaan WHMCS. Nama kolom tanggal (`termination_date`) diambil dari daftar tetap
setelah dicek di skema, bukan dari input. Nilai status dari input hanya diterima jika
ada di whitelist hasil Q5.

| # | Lokasi | SQL kurang lebih | Tujuan |
|---|--------|------------------|--------|
| Q6 | `Repository::groups` | `SELECT id, name FROM tblproductgroups ORDER BY name` | Opsi dropdown group, sekaligus whitelist input `gid`. |
| Q1 | `Repository::productPage` | `SELECT COUNT(p.id) FROM tblproducts p WHERE 1 [AND p.hidden = 1] [AND p.gid = ?] [AND (p.name LIKE ? [OR p.id = ?])]` | Total product untuk paging. Setiap filter hanya dipakai jika diisi; `OR p.id = ?` hanya jika kata kunci berupa angka. Wildcard `%`/`_` di kata kunci di-escape. |
| Q1b | idem | `SELECT p.id, p.name, p.hidden, g.name AS group_name FROM tblproducts p LEFT JOIN tblproductgroups g ON g.id = p.gid WHERE <filter Q1> ORDER BY p.id LIMIT ? OFFSET ?` | Daftar product per halaman. |
| Q2 | idem | `SELECT packageid, domainstatus, COUNT(*) AS total FROM tblhosting WHERE packageid IN (?, ...) GROUP BY packageid, domainstatus` | Jumlah service per status, hanya untuk product di halaman aktif. |
| Q3 | `Repository::product` | `SELECT p.id, p.name, p.hidden, g.name FROM tblproducts p LEFT JOIN tblproductgroups g ON g.id = p.gid WHERE p.id = ? LIMIT 1` | Header halaman detail dan validasi product ID. |
| Q5 | `Repository::statusCounts` | `SELECT domainstatus, COUNT(*) AS total FROM tblhosting WHERE packageid = ? GROUP BY domainstatus` | Opsi checkbox status + jumlahnya, sekaligus whitelist input `status[]`. |
| Q4 | `Repository::serviceQuery` | `SELECT h.id, h.userid, h.domain, h.packageid, h.domainstatus, h.termination_date AS terminated_at, c.firstname, c.lastname, c.companyname FROM tblhosting h LEFT JOIN tblclients c ON c.id = h.userid WHERE h.packageid = ? [AND h.domainstatus IN (?, ...)] [AND h.termination_date >= ?] [AND h.termination_date <= ?] ORDER BY h.id` | Daftar service product dengan filter status dan tanggal. Jika semua status dipilih, klausa `IN` tidak dipakai. |
| Q4a | `Repository::servicePage` | Q4 dengan `COUNT(h.id)` lalu `LIMIT ? OFFSET ?` | Paging halaman detail. |
| Q4b | `Repository::eachService` | Q4 + `AND h.id > ? LIMIT 500` (berulang) | Export CSV per batch (keyset), hemat memori. |
| Q7 | `Repository::serviceCount` | `SELECT COUNT(*) FROM tblhosting WHERE packageid = ?` | Menentukan apakah halaman konfirmasi hapus boleh menampilkan tombol "Ya, hapus". |
| A1 | `Auth::passwordMatches` | `SELECT passwordhash, password, disabled FROM tbladmins WHERE id = ? LIMIT 1` (kolom yang ada saja) | Konfirmasi password admin; hash dicocokkan di PHP dengan `password_verify()`. |
| S1 | `missingTables`, `terminateColumn`, `passwordMatches`, `ProductDeleter` | `information_schema` via `Capsule::schema()->hasTable/hasColumn` | Validasi skema; tabel/kolom yang tidak ada dilewati. |

## Query tulis (hapus product)

Dijalankan `ProductDeleter::delete($pid)` hanya setelah konfirmasi password, konfirmasi Ya,
dan token CSRF valid. Semua dalam **satu transaksi**; bila ada error, semua dibatalkan.

| # | SQL kurang lebih | Keterangan |
|---|------------------|------------|
| D0 | `SELECT id, name FROM tblproducts WHERE id = ? FOR UPDATE` | Kunci baris product. |
| D0b | `SELECT COUNT(*) FROM tblhosting WHERE packageid = ? FOR UPDATE` | Cek ulang: jika > 0, batal tanpa perubahan. |
| D1 | `SELECT id FROM tblcustomfields WHERE type = 'product' AND relid = ?` | Ambil custom field milik product. |
| D2 | `DELETE FROM tblcustomfieldsvalues WHERE fieldid IN (?, ...)` | Nilai custom field (seharusnya kosong karena tidak ada service). |
| D3 | `DELETE FROM tblcustomfields WHERE id IN (?, ...)` | Definisi custom field product. |
| D4 | `DELETE FROM tblpricing WHERE relid = ? AND type = 'product'` | Harga product. |
| D5 | `DELETE FROM tblproductconfiglinks WHERE pid = ?` | Link ke configurable option group. |
| D6 | `DELETE FROM tblproduct_upgrade_products WHERE product_id = ?` / `... WHERE upgrade_product_id = ?` | Relasi upgrade/downgrade dua arah. |
| D7 | `DELETE FROM tblproduct_downloads WHERE product_id = ?` | Link download product. |
| D8 | `DELETE FROM tblproducts_slugs WHERE product_id = ?` | Slug URL product (WHMCS 8.x). |
| D9 | `DELETE FROM tblproducts WHERE id = ?` | Product itu sendiri. |

D4–D8 dilewati bila tabel/kolomnya tidak ada. [VERIFIKASI] daftar ini terhadap penghapusan
bawaan WHMCS di `configproducts.php` pada staging.

## Yang perlu dicek di skema Anda

```sql
SHOW COLUMNS FROM tblproducts LIKE 'hidden';
SHOW COLUMNS FROM tblhosting  WHERE Field IN ('termination_date','domainstatus','packageid','userid','domain');
SELECT DISTINCT domainstatus FROM tblhosting;            -- status di luar daftar standar masuk "Lainnya"
SELECT hidden, COUNT(*) FROM tblproducts GROUP BY hidden; -- pastikan product hidden = 1
SHOW INDEX FROM tblhosting;                               -- pastikan ada index di packageid
SHOW COLUMNS FROM tbladmins WHERE Field IN ('password','passwordhash','disabled');
SHOW TABLES LIKE 'tblproduct%';                           -- bandingkan dengan daftar D4-D8
SHOW TABLE STATUS WHERE Name IN ('tblproducts','tblpricing','tblcustomfields'); -- Engine harus InnoDB agar transaksi berlaku
```

Untuk data besar, Q2, Q4, Q5, dan Q7 bergantung pada index `tblhosting.packageid`. Jika lambat,
cek `EXPLAIN` di staging; modul ini sengaja tidak menambah index.
