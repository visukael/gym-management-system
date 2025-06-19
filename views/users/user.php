<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/User.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'owner') {
    header('Location: ../../login.php');
    exit;
}

$userModel = new User($conn);

// Tambah user baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];

    $userModel->create($name, $username, $password, $role);
    header("Location: user.php");
    exit;
}

// Update user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $id = intval($_POST['id']);
    $name = $_POST['name'];
    $username = $_POST['username'];
    $role = $_POST['role'];

    $userModel->update($id, $name, $username, $role);
    header("Location: user.php");
    exit;
}

// Ambil user untuk diedit
$editUser = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editUser = $userModel->getById($editId);
}

// Hapus user
if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    if ($deleteId !== $_SESSION['user_id']) {
        $userModel->delete($deleteId);
    }
    header("Location: user.php");
    exit;
}

// Ambil semua user
$users = $userModel->getAll();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Kelola Akun</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        form { margin-top: 20px; }
        input, select, button { padding: 8px; margin: 5px 0; width: 100%; }
        .btn { width: auto; }
    </style>
</head>
<body>

<h2><?= $editUser ? 'Edit User' : 'Tambah User' ?></h2>

<form method="POST">
    <?php if ($editUser): ?>
        <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
    <?php endif; ?>

    <label>Nama:</label>
    <input type="text" name="name" required value="<?= $editUser['name'] ?? '' ?>">

    <label>Username:</label>
    <input type="text" name="username" required value="<?= $editUser['username'] ?? '' ?>">

    <?php if (!$editUser): ?>
        <label>Password:</label>
        <input type="password" name="password" required>
    <?php endif; ?>

    <label>Role:</label>
    <select name="role" required>
        <option value="admin" <?= isset($editUser) && $editUser['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
        <option value="owner" <?= isset($editUser) && $editUser['role'] == 'owner' ? 'selected' : '' ?>>Owner</option>
    </select>

    <button type="submit" name="<?= $editUser ? 'update_user' : 'add_user' ?>" class="btn">
        <?= $editUser ? 'Update User' : 'Tambah User' ?>
    </button>
    <?php if ($editUser): ?>
        <a href="user.php" class="btn">Batal</a>
    <?php endif; ?>
</form>

<h3>Daftar Pengguna</h3>
<table>
    <thead>
        <tr>
            <th>Nama</th>
            <th>Username</th>
            <th>Role</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['role']) ?></td>
                <td>
                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <a href="?edit=<?= $u['id'] ?>">Edit</a> |
                        <a href="?delete=<?= $u['id'] ?>" onclick="return confirm('Hapus user ini?')">Hapus</a>
                    <?php else: ?>
                        (Diri sendiri)
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
    </tbody>
</table>

</body>
</html>
