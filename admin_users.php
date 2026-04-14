<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

if (($_SESSION['role'] ?? '') !== 'admin') {
    die('Akses ditolak');
}

$conn = db();
if (!$conn) {
    die('Koneksi database gagal.');
}

if (!table_exists($conn, 'users')) {
    die('Tabel users tidak ditemukan.');
}

function user_avatar_column(mysqli $conn): ?string
{
    foreach (['avatar', 'foto', 'photo', 'profile_photo'] as $col) {
        if (column_exists($conn, 'users', $col)) {
            return $col;
        }
    }
    return null;
}

function ensure_user_upload_dir(): string
{
    $dir = __DIR__ . '/uploads/users';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function save_user_avatar(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [true, null, ''];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [false, null, 'Upload foto gagal.'];
    }

    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [false, null, 'File upload tidak valid.'];
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $mime = mime_content_type($tmp) ?: '';
    if (!isset($allowed[$mime])) {
        return [false, null, 'Foto harus JPG, PNG, atau WEBP.'];
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return [false, null, 'Ukuran foto maksimal 2 MB.'];
    }

    $dir = ensure_user_upload_dir();
    $ext = $allowed[$mime];
    $name = 'user_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . '/' . $name;

    if (!move_uploaded_file($tmp, $dest)) {
        return [false, null, 'Gagal menyimpan foto ke server.'];
    }

    return [true, 'uploads/users/' . $name, ''];
}

function delete_user_avatar_file(?string $path): void
{
    if (!$path) return;
    $full = __DIR__ . '/' . ltrim($path, '/');
    if (is_file($full)) {
        @unlink($full);
    }
}

function avatar_url(?string $path, string $name = ''): string
{
    if ($path && is_file(__DIR__ . '/' . ltrim($path, '/'))) {
        return $path;
    }
    $initial = strtoupper(substr(trim($name) !== '' ? trim($name) : 'U', 0, 1));
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120"><defs><linearGradient id="g" x1="0" x2="1" y1="0" y2="1"><stop stop-color="#1d4ed8"/><stop offset="1" stop-color="#0f172a"/></linearGradient></defs><rect width="100%" height="100%" rx="24" fill="url(#g)"/><text x="50%" y="56%" font-family="Arial" font-size="52" font-weight="700" text-anchor="middle" fill="white">' . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') . '</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

$avatarColumn = user_avatar_column($conn);

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

        [$okUpload, $avatarPath, $uploadError] = save_user_avatar($_FILES['avatar'] ?? []);
        if (!$okUpload) {
            $_SESSION['error'] = $uploadError;
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

        if ($avatarColumn) {
            $cols[] = $avatarColumn;
            $holders[] = '?';
            $params[] = $avatarPath ?? '';
        }

        $sql = "INSERT INTO users (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $holders) . ")";
        $ok = db_insert($sql, $params);

        if ($ok !== false) {
            $_SESSION['success'] = $avatarColumn
                ? 'User berhasil ditambahkan.'
                : 'User berhasil ditambahkan. Tambahkan kolom avatar pada tabel users agar foto bisa disimpan.';
        } else {
            if ($avatarPath) delete_user_avatar_file($avatarPath);
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

    if ($aksi === 'update_avatar') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$avatarColumn) {
            $_SESSION['error'] = 'Kolom avatar belum ada di tabel users.';
            header('Location: admin_users.php');
            exit;
        }

        $user = db_fetch_one("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$user) {
            $_SESSION['error'] = 'User tidak ditemukan.';
            header('Location: admin_users.php');
            exit;
        }

        [$okUpload, $avatarPath, $uploadError] = save_user_avatar($_FILES['avatar_baru'] ?? []);
        if (!$okUpload) {
            $_SESSION['error'] = $uploadError;
            header('Location: admin_users.php');
            exit;
        }

        if (!$avatarPath) {
            $_SESSION['error'] = 'Pilih file foto terlebih dahulu.';
            header('Location: admin_users.php');
            exit;
        }

        $oldPath = $user[$avatarColumn] ?? '';
        $ok = db_run("UPDATE users SET {$avatarColumn} = ? WHERE id = ?", [$avatarPath, $id]);
        if ($ok) {
            delete_user_avatar_file($oldPath);
            $_SESSION['success'] = 'Foto user berhasil diupdate.';
        } else {
            delete_user_avatar_file($avatarPath);
            $_SESSION['error'] = 'Gagal update foto user.';
        }
        header('Location: admin_users.php');
        exit;
    }
}

if (isset($_GET['hapus'])) {
    $id = (int)($_GET['hapus'] ?? 0);

    if ($id === (int)($_SESSION['user_id'] ?? 0)) {
        $_SESSION['error'] = 'User yang sedang login tidak bisa dihapus.';
        header('Location: admin_users.php');
        exit;
    }

    $user = db_fetch_one("SELECT * FROM users WHERE id = ?", [$id]);
    $oldPath = $user && $avatarColumn ? ($user[$avatarColumn] ?? '') : '';

    $ok = db_run("DELETE FROM users WHERE id = ?", [$id]);
    if ($ok) delete_user_avatar_file($oldPath);
    $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'User berhasil dihapus.' : 'Gagal menghapus user.';
    header('Location: admin_users.php');
    exit;
}

$users = db_fetch_all("SELECT * FROM users ORDER BY id DESC");
$totalUsers = count($users);
$totalAdmin = 0;
$totalDokter = 0;
foreach ($users as $u) {
    if (($u['role'] ?? '') === 'admin') $totalAdmin++;
    if (($u['role'] ?? '') === 'dokter') $totalDokter++;
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - Users</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:linear-gradient(135deg,#eff6ff 0%,#f8fafc 50%,#eef2ff 100%);color:#0f172a}
.wrap{max-width:1320px;margin:24px auto;padding:0 16px}
.hero{background:linear-gradient(135deg,#0f172a,#1d4ed8);color:#fff;border-radius:26px;padding:26px 28px;box-shadow:0 18px 40px rgba(15,23,42,.18);margin-bottom:18px}
.hero-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
.hero h1{margin:0 0 8px;font-size:34px}
.hero p{margin:0;color:rgba(255,255,255,.86)}
.hero-badges{display:flex;gap:12px;flex-wrap:wrap;margin-top:20px}
.hero-badge{background:rgba(255,255,255,.12);backdrop-filter:blur(8px);padding:12px 16px;border-radius:16px;min-width:160px}
.hero-badge strong{display:block;font-size:22px;color:#fff}
.top-actions{display:flex;gap:10px;flex-wrap:wrap}
.card{background:#fff;border-radius:24px;padding:22px;box-shadow:0 14px 30px rgba(15,23,42,.08);margin-bottom:18px;border:1px solid rgba(148,163,184,.14)}
.btn{display:inline-block;text-decoration:none;background:#0f172a;color:#fff;padding:10px 14px;border-radius:12px;font-weight:700;border:none;cursor:pointer}
.btn.secondary{background:#475569}
.btn.danger{background:#b91c1c}
.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}
.full{grid-column:1/-1}
input,select,button{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:14px}
input[type="file"]{padding:10px;background:#f8fafc}
button{background:linear-gradient(135deg,#0f172a,#1d4ed8);color:#fff;border:none;cursor:pointer;font-weight:700;box-shadow:0 10px 20px rgba(29,78,216,.15)}
.table-wrap{overflow:auto}
.table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border-radius:18px}
.table th,.table td{padding:14px 12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top;background:#fff}
.table th{background:linear-gradient(135deg,#dbeafe,#eff6ff);font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:#1e3a8a}
.table tbody tr:nth-child(even) td{background:#f8fbff}
.badge{display:inline-block;padding:7px 12px;border-radius:999px;font-size:12px;font-weight:700}
.badge.admin{background:#dbeafe;color:#1d4ed8}
.badge.dokter{background:#dcfce7;color:#166534}
.badge.default{background:#e2e8f0;color:#334155}
.action-stack{display:grid;gap:10px}
.small{font-size:13px;color:#64748b}
.section-title{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.section-title h2{margin:0}
.user-cell{display:flex;align-items:center;gap:12px;min-width:220px}
.avatar{width:54px;height:54px;border-radius:18px;object-fit:cover;border:3px solid #dbeafe;box-shadow:0 8px 18px rgba(59,130,246,.12);background:#fff}
.form-card{background:linear-gradient(135deg,#ffffff,#f8fbff)}
.help{margin-top:12px;padding:12px 14px;border-radius:14px;background:#eff6ff;color:#1d4ed8;font-size:13px}
.inline-form{display:grid;gap:8px}
@media(max-width:1150px){.grid{grid-template-columns:1fr 1fr}}
@media(max-width:760px){.grid{grid-template-columns:1fr}.hero h1{font-size:28px}.hero-badge{min-width:unset;width:100%}}
</style>
</head>
<body>
<div class="wrap">

    <div class="hero">
        <div class="hero-top">
            <div>
                <h1>Admin Users Dashboard</h1>
                <p>Kelola akun admin dan dokter, upload avatar, ubah role, reset password, dan rapikan akses user.</p>
            </div>
            <div class="top-actions">
                <a class="btn secondary" href="admin_panel.php">Admin Panel</a>
                <a class="btn" href="dashboard.php">Dashboard</a>
            </div>
        </div>
        <div class="hero-badges">
            <div class="hero-badge"><span>Total Users</span><strong><?= $totalUsers ?></strong></div>
            <div class="hero-badge"><span>Admin</span><strong><?= $totalAdmin ?></strong></div>
            <div class="hero-badge"><span>Dokter</span><strong><?= $totalDokter ?></strong></div>
        </div>
    </div>

    <div class="card form-card">
        <?php flash_message(); ?>
        <div class="section-title">
            <h2>Tambah User Baru</h2>
            <div class="small">Avatar opsional. Format: JPG, PNG, WEBP maksimal 2 MB.</div>
        </div>
        <form method="post" enctype="multipart/form-data">
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
                <div>
                    <label>Avatar / Foto</label>
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
                </div>
                <div class="full">
                    <button type="submit">Simpan User</button>
                </div>
            </div>
        </form>
        <?php if (!$avatarColumn): ?>
            <div class="help">Tambahkan kolom <strong>avatar</strong> pada tabel <strong>users</strong> agar path foto user bisa tersimpan.</div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="section-title">
            <h2>Daftar User</h2>
            <div class="small">Klik update untuk mengubah role, foto, atau password.</div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Pengaturan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <?php
                        $displayName = $u['nama_lengkap'] ?? $u['nama'] ?? '-';
                        $role = $u['role'] ?? '-';
                        $avatarPath = $avatarColumn ? ($u[$avatarColumn] ?? '') : '';
                        $roleClass = $role === 'admin' ? 'admin' : ($role === 'dokter' ? 'dokter' : 'default');
                    ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td>
                            <div class="user-cell">
                                <img class="avatar" src="<?= e(avatar_url($avatarPath, $displayName)) ?>" alt="Avatar <?= e($displayName) ?>">
                                <div>
                                    <strong><?= e($displayName) ?></strong><br>
                                    <span class="small">ID User: <?= (int)$u['id'] ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?= e($u['username'] ?? '-') ?></td>
                        <td><span class="badge <?= e($roleClass) ?>"><?= e($role) ?></span></td>
                        <td>
                            <div class="action-stack">
                                <?php if (column_exists($conn, 'users', 'role')): ?>
                                <form class="inline-form" method="post">
                                    <input type="hidden" name="aksi" value="update_role">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <select name="role">
                                        <option value="admin" <?= (($u['role'] ?? '') === 'admin') ? 'selected' : '' ?>>admin</option>
                                        <option value="dokter" <?= (($u['role'] ?? '') === 'dokter') ? 'selected' : '' ?>>dokter</option>
                                    </select>
                                    <button type="submit">Update Role</button>
                                </form>
                                <?php endif; ?>

                                <form class="inline-form" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="aksi" value="update_avatar">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <input type="file" name="avatar_baru" accept="image/jpeg,image/png,image/webp">
                                    <button type="submit">Update Foto</button>
                                </form>

                                <form class="inline-form" method="post">
                                    <input type="hidden" name="aksi" value="reset_password">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <input type="password" name="password_baru" placeholder="Password baru">
                                    <button type="submit">Reset Password</button>
                                </form>

                                <div>
                                    <a class="btn danger" href="?hapus=<?= (int)$u['id'] ?>" onclick="return confirm('Hapus user ini?')">Hapus</a>
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

</div>
</body>
</html>
