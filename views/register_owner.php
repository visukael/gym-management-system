<?php
session_start();
require_once '../config/database.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($name === '' || $username === '' || $password === '' || $confirm_password === '') {
        $errors[] = 'Semua field harus diisi.';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Password tidak cocok.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = 'Username sudah terdaftar.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $role = 'admin'; 

            $insert = $conn->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)");
            $insert->bind_param('ssss', $name, $username, $hashedPassword, $role);

            if ($insert->execute()) {
                $success = 'Registrasi berhasil. Silakan login.';
            } else {
                $errors[] = 'Gagal menyimpan ke database.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Register - Gym Management</title>
    <style>
        body { font-family: Arial; padding: 30px; background-color: #f2f2f2; }
        form { max-width: 400px; margin: auto; background: white; padding: 20px; border-radius: 8px; }
        input[type="text"], input[type="password"] {
            width: 100%; padding: 10px; margin: 8px 0; box-sizing: border-box;
        }
        input[type="submit"] {
            background-color: #28a745; color: white; border: none; padding: 10px;
            cursor: pointer; width: 100%;
        }
        .error { color: red; margin-bottom: 10px; }
        .success { color: green; margin-bottom: 10px; }
    </style>
</head>
<body>
    <h2 align="center">Register Admin</h2>
    <form method="POST" action="">
        <?php if (!empty($errors)): ?>
            <div class="error"><?= implode('<br>', $errors) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>

        <label>Nama Lengkap</label>
        <input type="text" name="name" required>

        <label>Username</label>
        <input type="text" name="username" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <label>Konfirmasi Password</label>
        <input type="password" name="confirm_password" required>

        <input type="submit" value="Register">
    </form>
</body>
</html>
