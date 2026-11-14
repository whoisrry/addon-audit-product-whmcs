# Daftar Query

Semua query ada di `lib/Repository.php` dan `lib/Access.php`, dibangun dengan
`WHMCS\Database\Capsule` (parameter binding otomatis). **Tidak ada** INSERT, UPDATE,
DELETE, atau DDL. Nama kolom tanggal (`termination_date`) diambil dari daftar tetap
setelah dicek di skema, bukan dari input.

| # | Lokasi | SQL kurang lebih | Tujuan |
|---|--------|------------------|--------|
| Q0 | `Access::isAllowed` | `SELECT roleid, disabled FROM tbladmins WHERE id = ? LIMIT 1` | Cek role dan status admin yang login. |
| Q1 | `Repository::hiddenProductPage` | `SELECT COUNT(p.id) FROM tblproducts p WHERE p.hidden = 1` | Total product hidden untuk paging. |
| Q1b | idem | `SELECT p.id, p.name, g.name AS group_name FROM tblproducts p LEFT JOIN tblproductgroups g ON g.id = p.gid WHERE p.hidden = 1 ORDER BY p.id LIMIT ? OFFSET ?` | Daftar product hidden per halaman. |
| Q2 | idem | `SELECT packageid, domainstatus, COUNT(*) AS total FROM tblhosting WHERE packageid IN (?, ...) GROUP BY packageid, domainstatus` | Jumlah service per status, hanya untuk product di halaman aktif. |
| Q3 | `Repository::product` | `SELECT p.id, p.name, p.hidden, g.name FROM tblproducts p LEFT JOIN tblproductgroups g ON g.id = p.gid WHERE p.id = ? LIMIT 1` | Header halaman detail dan validasi product ID. |
| Q4 | `Repository::terminatedQuery` | `SELECT h.id, h.userid, h.domain, h.packageid, h.domainstatus, h.termination_date AS terminated_at, c.firstname, c.lastname, c.companyname FROM tblhosting h LEFT JOIN tblclients c ON c.id = h.userid WHERE h.packageid = ? AND h.domainstatus = 'Terminated' [AND h.termination_date >= ?] [AND h.termination_date <= ?] ORDER BY h.id` | Daftar service Terminated + filter tanggal. |
| Q4a | `Repository::terminatedPage` | Q4 dengan `COUNT(h.id)` lalu `LIMIT ? OFFSET ?` | Paging halaman detail. |
| Q4b | `Repository::eachTerminated` | Q4 + `AND h.id > ? LIMIT 500` (berulang) | Export CSV per batch (keyset), hemat memori. |
| S1 | `missingTables`, `terminateColumn` | `information_schema` via `Capsule::schema()->hasTable/hasColumn` | Validasi skema saat activate dan saat halaman dibuka. |

## Yang perlu dicek di skema Anda

```sql
SHOW COLUMNS FROM tblproducts LIKE 'hidden';
SHOW COLUMNS FROM tblhosting  WHERE Field IN ('termination_date','domainstatus','packageid','userid','domain');
SHOW COLUMNS FROM tbladmins   WHERE Field IN ('roleid','disabled');
SELECT DISTINCT domainstatus FROM tblhosting;            -- status di luar daftar masuk "Lainnya"
SELECT hidden, COUNT(*) FROM tblproducts GROUP BY hidden; -- pastikan product hidden = 1
SHOW INDEX FROM tblhosting;                               -- pastikan ada index di packageid
```

Untuk data besar, Q2 dan Q4 bergantung pada index `tblhosting.packageid`. Jika lambat,
cek `EXPLAIN` di staging; modul ini sengaja tidak menambah index (read-only).
