<?php
session_start();
require_once __DIR__ . '/config.php';

// Redirect jika sudah login
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

        if (!$stmt) {
            $error = 'Terjadi kesalahan sistem.';
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user) {
                // Support password hash & plain (sementara)
                if (
                    (!empty($user['password']) && password_verify($password, $user['password'])) ||
                    $password === $user['password']
                ) {
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
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Klinik</title>

    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #2563eb, #1e3a8a);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .card {
            background: #fff;
            padding: 35px;
            border-radius: 16px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        h2 {
            margin-top: 0;
            text-align: center;
            margin-bottom: 25px;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 10px;
            border: 1px solid #ddd;
            font-size: 14px;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            border: none;
            color: white;
            border-radius: 10px;
            font-size: 15px;
            cursor: pointer;
        }

        button:hover {
            background: #1e40af;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
        }

        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>

<div class="card">
    <h2>Login Klinik</h2>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>

        <button type="submit">Masuk</button>
    </form>

    <div class="footer">
        Sistem Klinik Gigi
    </div>
</div>

</body>
</html>
