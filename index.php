<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, nama, password FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            $valid = false;

            if (!empty($user['password']) && password_verify($password, $user['password'])) {
                $valid = true;
            } elseif (!empty($user['password']) && $password === $user['password']) {
                $valid = true;
            }

            if ($valid) {
                $_SESSION['login'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama'] = $user['nama'] ?? $user['username'];

                header("Location: dashboard.php");
                exit;
            }
        }

        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Klinik</title>
    <style>
        body{font-family:Arial,sans-serif;background:#1d4ed8;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}
        .box{background:#fff;padding:30px;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08);width:100%;max-width:380px}
        h2{margin-top:0;text-align:center}
        input{width:100%;padding:12px;margin:8px 0 14px 0;border:1px solid #ccc;border-radius:8px;box-sizing:border-box}
        button{width:100%;padding:12px;border:none;background:#2563eb;color:#fff;border-radius:8px;cursor:pointer}
        .err{background:#fee2e2;color:#991b1b;padding:10px;border-radius:8px;margin-bottom:12px}
        .footer{text-align:center;margin-top:12px;color:#555}
    </style>
</head>
<body>
    <div class="box">
        <h2>Login Klinik</h2>

        <?php if ($error !== ''): ?>
            <div class="err"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit">Masuk</button>
        </form>

        <div class="footer">Sistem Klinik Gigi</div>
    </div>
</body>
</html>
