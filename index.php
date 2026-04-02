<?php
session_start();

$conn = new mysqli("localhost", "root", "", "simklinik");
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

$error = "";

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, nama, username, password, role FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && $password === 'password') {
        $_SESSION['login'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nama'] = $user['nama'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Username atau password salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login SIM Klinik Gigi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#eef2f7;">
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height:100vh;">
            <div class="col-md-5 col-lg-4">
                <div class="card border-0 shadow rounded-4">
                    <div class="card-body p-4">
                        <h1 class="h2 text-center fw-bold mb-2">SIM Klinik Gigi</h1>
                        <p class="text-center text-muted mb-4">Login sistem</p>

                        <?php if ($error !== ""): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control form-control-lg" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control form-control-lg" required>
                            </div>

                            <button type="submit" name="login" class="btn btn-primary btn-lg w-100">
                                Masuk
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>