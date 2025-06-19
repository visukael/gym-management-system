<?php
session_start();
require_once '../../config/database.php';

// Ambil paket
$packages = $conn->query("SELECT id, name FROM membership_packages");

// Proses Simpan / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'];
    $package_id = $_POST['package_id'];
    $type = $_POST['discount_type']; // flat / percent
    $value = (float) $_POST['discount_value'];
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];

    // Validasi discount_type untuk pastikan flat / percent
    $type = strtolower(trim($type));
    if (!in_array($type, ['flat', 'percent'])) {
        die("Jenis diskon tidak valid.");
    }

    if ($id) {
        $stmt = $conn->prepare("UPDATE promotions SET name=?, package_id=?, discount_type=?, discount_value=?, start_date=?, end_date=? WHERE id=?");
        $stmt->bind_param("sisdssi", $name, $package_id, $type, $value, $start, $end, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO promotions (name, package_id, discount_type, discount_value, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisdss", $name, $package_id, $type, $value, $start, $end);
    }

    $stmt->execute();
    header("Location: promotions.php");
    exit;
}

// Ambil data untuk edit
$editData = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM promotions WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}

// Hapus promo
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM promotions WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: promotions.php");
    exit;
}

// Ambil semua promo
$promos = $conn->query("
    SELECT p.*, mp.name AS package_name 
    FROM promotions p
    JOIN membership_packages mp ON p.package_id = mp.id
    ORDER BY p.id DESC
");
?>

<h2>Manajemen Promo</h2>

<form method="post" style="margin-bottom:30px;">
    <input type="hidden" name="id" value="<?= $editData['id'] ?? '' ?>">

    <label>Nama Promo:</label><br>
    <input type="text" name="name" value="<?= $editData['name'] ?? '' ?>" required><br>

    <label>Paket:</label><br>
    <select name="package_id" required>
        <option value="">-- Pilih Paket --</option>
        <?php $packages->data_seek(0); while ($p = $packages->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>" <?= ($editData['package_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['name']) ?>
            </option>
        <?php endwhile; ?>
    </select><br>

    <label>Jenis Diskon:</label><br>
    <select name="discount_type" required>
        <option value="flat" <?= ($editData['discount_type'] ?? '') === 'flat' ? 'selected' : '' ?>>Flat (Rp)</option>
        <option value="percent" <?= ($editData['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Persen (%)</option>
    </select><br>

    <label>Nilai Diskon:</label><br>
    <input type="number" name="discount_value" min="0" step="1" value="<?= $editData['discount_value'] ?? '' ?>" required><br>

    <label>Mulai Promo:</label><br>
    <input type="date" name="start_date" value="<?= $editData['start_date'] ?? '' ?>" required><br>

    <label>Berakhir Promo:</label><br>
    <input type="date" name="end_date" value="<?= $editData['end_date'] ?? '' ?>" required><br><br>

    <button type="submit"><?= $editData ? 'Update' : 'Tambah' ?> Promo</button>
    <?php if ($editData): ?>
        <a href="promotions.php">Batal</a>
    <?php endif; ?>
</form>

<hr>

<h3>Daftar Promo Aktif</h3>
<table border="1" cellpadding="8">
    <tr>
        <th>Nama</th>
        <th>Paket</th>
        <th>Jenis</th>
        <th>Diskon</th>
        <th>Periode</th>
        <th>Aksi</th>
    </tr>
    <?php while ($row = $promos->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= htmlspecialchars($row['package_name']) ?></td>
        <td><?= ucfirst($row['discount_type']) ?></td>
        <td>
            <?= $row['discount_type'] === 'percent'
                ? $row['discount_value'] . '%'
                : 'Rp' . number_format($row['discount_value'], 0, ',', '.') ?>
        </td>
        <td><?= $row['start_date'] ?> - <?= $row['end_date'] ?></td>
        <td>
            <a href="?edit=<?= $row['id'] ?>">Edit</a> |
            <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Yakin ingin hapus promo?')">Hapus</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
