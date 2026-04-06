<?php
require_once 'config.php';

mysqli_report(MYSQLI_REPORT_OFF);

header('Content-Type: text/html; charset=utf-8');

echo "<pre>";
echo "=== FIX DATABASE START ===\n\n";

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Koneksi database tidak valid.\n");
}

function out($text = '')
{
    echo $text . "\n";
}

function tableExists($conn, $table)
{
    $tableEsc = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$tableEsc'");
    return $res && $res->num_rows > 0;
}

function columnInfo($conn, $table, $column)
{
    $tableEsc = $conn->real_escape_string($table);
    $colEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$tableEsc` LIKE '$colEsc'");
    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc();
    }
    return null;
}

function hasPrimaryKey($conn, $table)
{
    $tableEsc = $conn->real_escape_string($table);
    $res = $conn->query("SHOW KEYS FROM `$tableEsc` WHERE Key_name = 'PRIMARY'");
    return $res && $res->num_rows > 0;
}

function runQuery($conn, $sql, $label)
{
    out("Menjalankan: $label");
    $ok = $conn->query($sql);
    if ($ok) {
        out("OK: $label");
        return true;
    }

    out("GAGAL: $label");
    out("ERROR: " . $conn->error);
    out("");
    return false;
}

function ensureIdColumn($conn, $table)
{
    out("---- CEK KOLOM id di tabel `$table` ----");

    $info = columnInfo($conn, $table, 'id');
    if ($info) {
        out("Kolom id sudah ada.");
        return true;
    }

    out("Kolom id belum ada. Menambahkan kolom id...");
    return runQuery(
        $conn,
        "ALTER TABLE `$table` ADD COLUMN `id` INT NULL",
        "$table tambah kolom id"
    );
}

function fillNullIds($conn, $table)
{
    out("---- CEK NULL id di tabel `$table` ----");

    $res = $conn->query("SELECT COUNT(*) AS jml FROM `$table` WHERE id IS NULL OR id = 0");
    if (!$res) {
        out("Tidak bisa cek NULL id: " . $conn->error);
        return false;
    }

    $row = $res->fetch_assoc();
    $jml = (int)($row['jml'] ?? 0);

    if ($jml === 0) {
        out("Tidak ada id NULL / 0.");
        return true;
    }

    out("Ditemukan $jml baris dengan id NULL / 0.");

    // isi id unik berdasarkan urutan baris
    $res2 = $conn->query("SELECT * FROM `$table` ORDER BY id ASC");
    if (!$res2) {
        out("Gagal membaca data tabel: " . $conn->error);
        return false;
    }

    $n = 1;
    $updates = 0;

    while ($r = $res2->fetch_assoc()) {
        $currentId = isset($r['id']) ? (int)$r['id'] : 0;

        if ($currentId <= 0) {
            // cari kolom pembeda untuk update
            // prioritas: id NULL tidak aman tanpa pembeda, jadi pakai semua kolom lain
            $whereParts = [];
            foreach ($r as $k => $v) {
                if ($k === 'id') {
                    $whereParts[] = "`id` IS NULL";
                    continue;
                }

                if ($v === null) {
                    $whereParts[] = "`$k` IS NULL";
                } else {
                    $safe = $conn->real_escape_string((string)$v);
                    $whereParts[] = "`$k` = '$safe'";
                }
            }

            $sql = "UPDATE `$table` SET `id` = $n WHERE " . implode(' AND ', $whereParts) . " LIMIT 1";
            if ($conn->query($sql)) {
                $updates++;
            }
        }

        $n++;
    }

    out("Selesai mengisi id kosong. Updated rows: $updates");
    return true;
}

function makeIdsUnique($conn, $table)
{
    out("---- CEK DUPLIKAT id di tabel `$table` ----");

    $res = $conn->query("
        SELECT id, COUNT(*) AS jml
        FROM `$table`
        GROUP BY id
        HAVING COUNT(*) > 1
    ");

    if (!$res) {
        out("Gagal cek duplikat id: " . $conn->error);
        return false;
    }

    if ($res->num_rows === 0) {
        out("Tidak ada duplikat id.");
        return true;
    }

    out("Ada duplikat id. Memperbaiki...");

    $all = $conn->query("SELECT * FROM `$table` ORDER BY id ASC");
    if (!$all) {
        out("Gagal baca semua data: " . $conn->error);
        return false;
    }

    $seen = [];
    $nextIdRes = $conn->query("SELECT COALESCE(MAX(id),0)+1 AS next_id FROM `$table`");
    $nextIdRow = $nextIdRes ? $nextIdRes->fetch_assoc() : ['next_id' => 1];
    $nextId = (int)$nextIdRow['next_id'];

    while ($row = $all->fetch_assoc()) {
        $id = (int)($row['id'] ?? 0);

        if ($id <= 0 || isset($seen[$id])) {
            $whereParts = [];
            foreach ($row as $k => $v) {
                if ($k === 'id') {
                    $whereParts[] = "`id` = " . (int)$id;
                    continue;
                }

                if ($v === null) {
                    $whereParts[] = "`$k` IS NULL";
                } else {
                    $safe = $conn->real_escape_string((string)$v);
                    $whereParts[] = "`$k` = '$safe'";
                }
            }

            $sql = "UPDATE `$table` SET `id` = $nextId WHERE " . implode(' AND ', $whereParts) . " LIMIT 1";
            if ($conn->query($sql)) {
                out("Baris duplicate diubah ke id $nextId");
                $seen[$nextId] = true;
                $nextId++;
            }
        } else {
            $seen[$id] = true;
        }
    }

    out("Perbaikan duplikat selesai.");
    return true;
}

function ensurePrimaryKeyAndAutoIncrement($conn, $table)
{
    out("==== FIX TABEL `$table` ====");

    if (!tableExists($conn, $table)) {
        out("Tabel tidak ada, skip.\n");
        return;
    }

    if (!ensureIdColumn($conn, $table)) {
        out("Gagal saat memastikan kolom id.\n");
        return;
    }

    fillNullIds($conn, $table);
    makeIdsUnique($conn, $table);

    if (!hasPrimaryKey($conn, $table)) {
        runQuery($conn, "ALTER TABLE `$table` ADD PRIMARY KEY (`id`)", "$table tambah PRIMARY KEY(id)");
    } else {
        out("PRIMARY KEY sudah ada.");
    }

    $info = columnInfo($conn, $table, 'id');
    $extra = strtolower($info['Extra'] ?? '');

    if (strpos($extra, 'auto_increment') === false) {
        runQuery($conn, "ALTER TABLE `$table` MODIFY COLUMN `id` INT NOT NULL AUTO_INCREMENT", "$table set AUTO_INCREMENT");
    } else {
        out("AUTO_INCREMENT sudah ada.");
    }

    out("");
}

$tables = [
    'pasien',
    'kunjungan',
    'tindakan',
    'icd10',
    'odontogram_tindakan',
    'invoice',
    'invoice_items',
    'resume_medis',
    'surat_sakit',
    'keuangan'
];

foreach ($tables as $table) {
    ensurePrimaryKeyAndAutoIncrement($conn, $table);
}

out("=== FIX DATABASE DONE ===");
echo "</pre>";
