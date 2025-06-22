<?php
session_start();
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $duration = $_POST['duration_months'];
    $price = $_POST['price'];

    if (isset($_POST['id']) && $_POST['id'] != '') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("UPDATE membership_packages SET name=?, duration_months=?, price=? WHERE id=?");
        $stmt->bind_param("sidi", $name, $duration, $price, $id);
        $stmt->execute();
        $message = "Paket berhasil diubah!";
    } else {
        $stmt = $conn->prepare("INSERT INTO membership_packages (name, duration_months, price) VALUES (?, ?, ?)");
        $stmt->bind_param("sid", $name, $duration, $price);
        $stmt->execute();
        $message = "Paket baru berhasil ditambahkan!";
    }
    header("Location: membership_packages.php");
    exit;
}

$editData = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM membership_packages WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM membership_packages WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: membership_packages.php");
    exit;
}

$result = $conn->query("SELECT * FROM membership_packages ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manajemen Paket Membership</title>
</head>
<body>
    <h2>Manajemen Paket Membership</h2>

    <form method="post" style="margin-bottom:20px;">
        <input type="hidden" name="id" value="<?= $editData['id'] ?? '' ?>">
        <label>Nama Paket:</label><br>
        <input type="text" name="name" value="<?= $editData['name'] ?? '' ?>" required><br>

        <label>Durasi (bulan):</label><br>
        <input type="number" name="duration_months" value="<?= $editData['duration_months'] ?? '' ?>" required><br>

        <label>Harga (Rp):</label><br>
        <input type="number" name="price" value="<?= $editData['price'] ?? '' ?>" required><br><br>

        <button type="submit"><?= $editData ? 'Update Paket' : 'Tambah Paket' ?></button>
        <?php if ($editData): ?>
            <a href="membership_packages.php">Batal Edit</a>
        <?php endif; ?>
    </form>

    <table border="1" cellpadding="8">
        <tr>
            <th>Nama Paket</th>
            <th>Durasi (bulan)</th>
            <th>Harga</th>
            <th>Aksi</th>
        </tr>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= $row['duration_months'] ?> bulan</td>
            <td>Rp<?= number_format($row['price']) ?></td>
            <td>
                <a href="?edit=<?= $row['id'] ?>">Edit</a> |
                <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Hapus paket ini?')">Hapus</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

</body>
</html>
