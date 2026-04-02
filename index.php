<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

echo "INDEX LOAD OK<br>"; // debug sementara

if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "FORM POST<br>"; // debug

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        echo "CHECK USER<br>"; // debug

        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) {
            die("QUERY ERROR: " . $conn->error);
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            echo "USER FOUND<br>"; // debug

            if ($password === $user['password'] || password_verify($password, $user['password'])) {
                $_SESSION['login'] = true;
                $_SESSION['user_id'] = $user['id'];

                header("Location: dashboard.php");
                exit;
            }
        }

        $error = 'Username atau password salah.';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Klinik</title>
</head>
<body>

<h2>Login</h2>

<?php if ($error): ?>
<p style="color:red"><?= $error ?></p>
<?php endif; ?>

<form method="POST">
    <input type="text" name="username" placeholder="Username"><br><br>
    <input type="password" name="password" placeholder="Password"><br><br>
    <button type="submit">Login</button>
</form>

</body>
</html>
