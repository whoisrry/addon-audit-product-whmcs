# Daftar Query

Semua query ada di `lib/Repository.php` dan `lib/Access.php`, dibangun dengan
`WHMCS\Database\Capsule` (parameter binding otomatis). **Tidak ada** INSERT, UPDATE,
DELETE, atau DDL. Nama kolom tanggal (`termination_date`) diambil dari daftar tetap
setelah dicek di skema, bukan dari input. Nilai status dari input hanya diterima jika
ada di whitelist hasil Q5.

| # | Lokasi | SQL kurang lebih | Tujuan |
|---|--------|------------------|--------|
| Q0 | `Access::isAllowed` | `SELECT roleid, disabled FROM tbladmins WHERE id = ? LIMIT 1` | Cek role dan status admin yang login. |
| Q1 | `Repository::productPage` | `SELECT COUNT(p.id) FROM tblproducts p [WHERE p.hidden = 1]` | Total product untuk paging (filter hidden hanya jika *is Hidden* dicentang). |
| Q1b | idem | `SELECT p.id, p.name, p.hidden, g.name AS group_name FROM tblproducts p LEFT JOIN tblproductgroups g ON g.id = p.gid [WHERE p.hidden = 1] ORDER BY p.id LIMIT ? OFFSET ?` | Daftar product per halaman. |
| Q2 | idem | `SELECT packageid, domainstatus, COUNT(*) AS total FROM tblhosting WHERE packageid IN (?, ...) GROUP BY packageid, domainstatus` | Jumlah service per status, hanya untuk product di halaman aktif. |
| Q3 | `Repository::product` | `SELECT p.id, p.name, p.hidden, g.name FROM tblproducts p LEFT JOIN tblproductgroups g ON g.id = p.gid WHERE p.id = ? LIMIT 1` | Header halaman detail dan validasi product ID. |
| Q5 | `Repository::statusCounts` | `SELECT domainstatus, COUNT(*) AS total FROM tblhosting WHERE packageid = ? GROUP BY domainstatus` | Opsi checkbox status + jumlahnya, sekaligus whitelist input `status[]`. |
| Q4 | `Repository::serviceQuery` | `SELECT h.id, h.userid, h.domain, h.packageid, h.domainstatus, h.termination_date AS terminated_at, c.firstname, c.lastname, c.companyname FROM tblhosting h LEFT JOIN tblclients c ON c.id = h.userid WHERE h.packageid = ? [AND h.domainstatus IN (?, ...)] [AND h.termination_date >= ?] [AND h.termination_date <= ?] ORDER BY h.id` | Daftar service product dengan filter status dan tanggal. Jika semua status dipilih, klausa `IN` tidak dipakai. |
| Q4a | `Repository::servicePage` | Q4 dengan `COUNT(h.id)` lalu `LIMIT ? OFFSET ?` | Paging halaman detail. |
| Q4b | `Repository::eachService` | Q4 + `AND h.id > ? LIMIT 500` (berulang) | Export CSV per batch (keyset), hemat memori. |
| S1 | `missingTables`, `terminateColumn` | `information_schema` via `Capsule::schema()->hasTable/hasColumn` | Validasi skema saat activate dan saat halaman dibuka. |

## Yang perlu dicek di skema Anda

```sql
SHOW COLUMNS FROM tblproducts LIKE 'hidden';
SHOW COLUMNS FROM tblhosting  WHERE Field IN ('termination_date','domainstatus','packageid','userid','domain');
SHOW COLUMNS FROM tbladmins   WHERE Field IN ('roleid','disabled');
SELECT DISTINCT domainstatus FROM tblhosting;            -- status di luar daftar standar masuk "Lainnya"
SELECT hidden, COUNT(*) FROM tblproducts GROUP BY hidden; -- pastikan product hidden = 1
SHOW INDEX FROM tblhosting;                               -- pastikan ada index di packageid
```

Untuk data besar, Q2, Q4, dan Q5 bergantung pada index `tblhosting.packageid`. Jika lambat,
cek `EXPLAIN` di staging; modul ini sengaja tidak menambah index (read-only).
