<?php
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
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
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
                $_SESSION['user_id'] = $user['id'] ?? 0;
                $_SESSION['nama'] = $user['nama'] ?? ($user['username'] ?? 'User');
                $_SESSION['username'] = $user['username'] ?? '';

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
    <title>Login - <?= htmlspecialchars(NAMA_KLINIK) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{
            background: linear-gradient(135deg,#e0f2fe,#f8fafc);
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            font-family:Arial,sans-serif;
        }
        .login-card{
            width:100%;
            max-width:430px;
            background:#fff;
            border-radius:20px;
            box-shadow:0 15px 40px rgba(0,0,0,.08);
            padding:32px;
        }
        .brand{
            text-align:center;
            margin-bottom:24px;
        }
        .brand img{
            width:72px;
            height:72px;
            object-fit:contain;
            margin-bottom:10px;
        }
        .brand h2{
            font-size:24px;
            font-weight:700;
            margin:0;
            color:#0f172a;
        }
        .brand p{
            color:#64748b;
            margin-top:6px;
            font-size:14px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">
            <?php if (file_exists(LOGO_KLINIK)): ?>
                <img src="assets/logo-klinik.png" alt="Logo Klinik">
            <?php endif; ?>
            <h2><?= htmlspecialchars(NAMA_KLINIK) ?></h2>
            <p><?= htmlspecialchars(TAGLINE_KLINIK) ?></p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Masuk</button>
        </form>
    </div>
</body>
</html>
