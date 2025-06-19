<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/Transaction.php';

$trxModel = new Transaction($conn);

// Hapus transaksi (opsional)
if (isset($_GET['delete'])) {
    $trxModel->delete($_GET['delete']);
    header("Location: transactions.php");
    exit;
}

// Tambah transaksi manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_manual'])) {
    $label = $_POST['label'];
    $desc = $_POST['description'];
    $amount = (float) $_POST['amount'];
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

// Ambil data transaksi
$transactions = $trxModel->all();
?>

<h2>Data Transaksi</h2>

<form method="post" style="margin-bottom: 30px;">
    <h3>Tambah Transaksi Manual</h3>

    <label>Tipe:</label><br>
    <select name="label" required>
        <option value="income">Pemasukan</option>
        <option value="outcome">Pengeluaran</option>
    </select><br>

    <label>Deskripsi:</label><br>
    <input type="text" name="description" required><br>

    <label>Jumlah (Rp):</label><br>
    <input type="number" name="amount" step="1000" required><br><br>

    <button type="submit" name="add_manual">Simpan Transaksi</button>
</form>

<hr>

<table border="1" cellpadding="8">
    <tr>
        <th>#</th>
        <th>Tanggal</th>
        <th>Tipe</th>
        <th>Deskripsi</th>
        <th>Label</th>
        <th>Harga Asli</th>
        <th>Diskon</th>
        <th>Total Bayar</th>
        <th>Petugas</th>
        <th>Aksi</th>
    </tr>
    <?php $no = 1; while ($row = $transactions->fetch_assoc()): ?>
    <tr>
        <td><?= $no++ ?></td>
        <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
        <td><?= htmlspecialchars($row['transaction_type']) ?></td>
        <td><?= htmlspecialchars($row['description']) ?></td>
        <td style="color:<?= $row['label'] === 'income' ? 'green' : 'red' ?>">
            <?= strtoupper($row['label']) ?>
        </td>
        <td>Rp<?= number_format($row['amount'], 0, ',', '.') ?></td>
        <td>Rp<?= number_format($row['discount'], 0, ',', '.') ?></td>
        <td><strong>Rp<?= number_format($row['final_amount'], 0, ',', '.') ?></strong></td>
        <td><?= htmlspecialchars($row['user_name'] ?? '-') ?></td>
        <td><a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Hapus transaksi ini?')">Hapus</a></td>
    </tr>
    <?php endwhile; ?>
</table>
