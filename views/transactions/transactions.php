<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/Transaction.php';

$trxModel = new Transaction($conn);

// Handle tambah manual transaksi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_manual'])) {
    $label = $_POST['label'];
    $desc = $_POST['description'];
    $amount = $_POST['amount'];
    $user_id = $_SESSION['user_id'];

    $trxModel->create([
        'transaction_type' => 'manual',
        'label' => $label,
        'description' => $desc,
        'amount' => $amount,
        'discount' => 0,
        'final_amount' => $amount,
        'member_id' => null,
        'product_id' => null,
        'user_id' => $user_id
    ]);

    header("Location: transactions.php");
    exit;
}

$transactions = $trxModel->getAll();
?>

<h2>Buku Besar Transaksi</h2>

<!-- Form Transaksi Manual -->
<form method="post" style="margin-bottom: 20px;">
    <h3>Tambah Transaksi Manual</h3>
    <label>Tipe Transaksi:</label><br>
    <select name="label" required>
        <option value="income">Pemasukan</option>
        <option value="outcome">Pengeluaran</option>
    </select><br>

    <label>Deskripsi:</label><br>
    <input type="text" name="description" required><br>

    <label>Jumlah (Rp):</label><br>
    <input type="number" name="amount" required><br><br>

    <button type="submit" name="add_manual">Simpan Transaksi</button>
</form>

<!-- Tabel Transaksi -->
<h3>Riwayat Transaksi</h3>
<table border="1" cellpadding="8">
    <tr>
        <th>Tanggal</th>
        <th>Jenis</th>
        <th>Keterangan</th>
        <th>Label</th>
        <th>Nominal</th>
        <th>Petugas</th>
    </tr>
    <?php while ($row = $transactions->fetch_assoc()): ?>
    <tr>
        <td><?= $row['created_at'] ?></td>
        <td><?= $row['transaction_type'] ?></td>
        <td><?= htmlspecialchars($row['description']) ?></td>
        <td><?= strtoupper($row['label']) ?></td>
        <td>Rp<?= number_format($row['final_amount']) ?></td>
        <td><?= $row['user_id'] ?></td> <!-- Opsional: tampilkan nama jika JOIN -->
    </tr>
    <?php endwhile; ?>
</table>
