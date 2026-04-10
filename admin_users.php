<?php
require_once 'bootstrap.php';
ensure_logged_in();

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    die('Akses ditolak');
}

$conn = db();
if (!$conn) {
    die('Koneksi database gagal.');
}

if (!table_exists($conn, 'users')) {
    die('Tabel users tidak ditemukan.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? 'dokter');

        if ($username === '' || $password === '') {
            $_SESSION['error'] = 'Username dan password wajib diisi.';
            header('Location: admin_users.php');
            exit;
        }

        $cek = db_fetch_one("SELECT id FROM users WHERE username = ?", [$username]);
        if ($cek) {
            $_SESSION['error'] = 'Username sudah dipakai.';
            header('Location: admin_users.php');
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $cols = [];
        $holders = [];
        $params = [];

        if (column_exists($conn, 'users', 'nama_lengkap')) {
            $cols[] = 'nama_lengkap';
            $holders[] = '?';
            $params[] = $nama_lengkap;
        }

        if (column_exists($conn, 'users', 'nama')) {
            $cols[] = 'nama';
            $holders[] = '?';
            $params[] = ($nama_lengkap !== '' ? $nama_lengkap : $username);
        }

        $cols[] = 'username';
        $holders[] = '?';
        $params[] = $username;

        $cols[] = 'password';
        $holders[] = '?';
        $params[] = $hash;

        if (column_exists($conn, 'users', 'role')) {
            $cols[] = 'role';
            $holders[] = '?';
            $params[] = $role;
        }

        $sql = "INSERT INTO users (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $holders) . ")";
        $ok = db_insert($sql, $params);

        if ($ok !== false) {
            $_SESSION['success'] = 'User berhasil ditambahkan.';
        } else {
            $_SESSION['error'] = 'Gagal menyimpan user. Cek struktur tabel users.';
        }

        header('Location: admin_users.php');
        exit;
    }

    if ($aksi === 'update_role') {
        $id = (int)($_POST['id'] ?? 0);
        $role = trim($_POST['role'] ?? 'dokter');

        if (!column_exists($conn, 'users', 'role')) {
            $_SESSION['error'] = 'Kolom role belum ada di tabel users.';
            header('Location: admin_users.php');
            exit;
        }

        $ok = db_run("UPDATE users SET role = ? WHERE id = ?", [$role, $id]);
        $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Role user berhasil diupdate.' : 'Gagal update role.';
        header('Location: admin_users.php');
        exit;
    }

    if ($aksi === 'reset_password') {
        $id = (int)($_POST['id'] ?? 0);
        $password_baru = trim($_POST['password_baru'] ?? '');

        if ($password_baru === '') {
            $_SESSION['error'] = 'Password baru wajib diisi.';
            header('Location: admin_users.php');
            exit;
        }

        $hash = password_hash($password_baru, PASSWORD_DEFAULT);
        $ok = db_run("UPDATE users SET password = ? WHERE id = ?", [$hash, $id]);

        $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Password user berhasil direset.' : 'Gagal reset password.';
        header('Location: admin_users.php');
        exit;
    }
}

if (isset($_GET['hapus'])) {
    $id = (int)($_GET['hapus'] ?? 0);

    // cegah hapus diri sendiri bila perlu
    if ($id === (int)($_SESSION['user_id'] ?? 0)) {
        $_SESSION['error'] = 'User yang sedang login tidak bisa dihapus.';
        header('Location: admin_users.php');
        exit;
    }

    $ok = db_run("DELETE FROM users WHERE id = ?", [$id]);
    $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'User berhasil dihapus.' : 'Gagal menghapus user.';
    header('Location: admin_users.php');
    exit;
}

$users = db_fetch_all("SELECT * FROM users ORDER BY id DESC");
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - Users</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:#f4f7fb;color:#0f172a}
.wrap{max-width:1300px;margin:24px auto;padding:0 16px}
.card{background:#fff;border-radius:22px;padding:22px;box-shadow:0 12px 28px rgba(15,23,42,.08);margin-bottom:18px}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.btn{display:inline-block;text-decoration:none;background:#0f172a;color:#fff;padding:10px 14px;border-radius:10px;font-weight:700}
.btn.secondary{background:#475569}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.full{grid-column:1/-1}
input,select,button{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:12px}
button{background:#0f172a;color:#fff;border:none;cursor:pointer;font-weight:700}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#e2e8f0;font-size:12px}
.action-row{display:flex;gap:8px;flex-wrap:wrap}
.action-row form{display:inline-block}
.small{font-size:13px;color:#64748b}
@media(max-width:1000px){.grid{grid-template-columns:1fr 1fr}}
@media(max-width:640px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">

    <div class="top">
        <div>
            <h1 style="margin:0">Kelola User</h1>
            <div class="small">Manajemen akun admin dan dokter</div>
        </div>
        <div>
            <a class="btn secondary" href="admin_panel.php">Admin Panel</a>
            <a class="btn" href="dashboard.php">Dashboard</a>
        </div>
    </div>

    <div class="card">
        <?php flash_message(); ?>

        <h2 style="margin-top:0">Tambah User Baru</h2>
        <form method="post">
            <input type="hidden" name="aksi" value="tambah">

            <div class="grid">
                <div>
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap">
                </div>
                <div>
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>
                <div>
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <div>
                    <label>Role</label>
                    <select name="role">
                        <option value="admin">admin</option>
                        <option value="dokter">dokter</option>
                    </select>
                </div>
                <div class="full">
                    <button type="submit">Simpan User</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Daftar User</h2>

        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Pengaturan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><?= e($u['nama_lengkap'] ?? $u['nama'] ?? '-') ?></td>
                    <td><?= e($u['username'] ?? '-') ?></td>
                    <td><span class="badge"><?= e($u['role'] ?? '-') ?></span></td>
                    <td>
                        <div class="action-row">
                            <?php if (column_exists($conn, 'users', 'role')): ?>
                            <form method="post">
                                <input type="hidden" name="aksi" value="update_role">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <select name="role" style="min-width:130px;">
                                    <option value="admin" <?= (($u['role'] ?? '') === 'admin') ? 'selected' : '' ?>>admin</option>
                                    <option value="dokter" <?= (($u['role'] ?? '') === 'dokter') ? 'selected' : '' ?>>dokter</option>
                                </select>
                                <button type="submit" style="margin-top:8px;">Update Role</button>
                            </form>
                            <?php endif; ?>

                            <form method="post">
                                <input type="hidden" name="aksi" value="reset_password">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <input type="password" name="password_baru" placeholder="Password baru">
                                <button type="submit" style="margin-top:8px;">Reset Password</button>
                            </form>

                            <div>
                                <a class="btn secondary" href="?hapus=<?= (int)$u['id'] ?>" onclick="return confirm('Hapus user ini?')">Hapus</a>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (!$users): ?>
                <tr>
                    <td colspan="5">Belum ada user.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
