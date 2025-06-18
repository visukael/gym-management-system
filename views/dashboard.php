<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: views/login.php");
    exit;
}

require_once '../config/database.php';
require_once '../controllers/DashboardController.php';

$dashboard = new DashboardController($conn);
$stats = $dashboard->getStats();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h2>Dashboard</h2>
    <p>Halo, <?= htmlspecialchars($_SESSION['user_name']) ?> (<?= $_SESSION['user_role'] ?>)</p>
    <a href="views/logout.php">Logout</a>

    <hr>

    <h3>Ringkasan Data:</h3>
    <ul>
        <li><strong>Total Income:</strong> Rp<?= number_format($stats['total_income']) ?></li>
        <li><strong>Total Outcome:</strong> Rp<?= number_format($stats['total_outcome']) ?></li>
        <li><strong>Total Member Aktif:</strong> <?= $stats['total_members'] ?> orang</li>
        <li><strongProduk Terjual:</strong> <?= $stats['products_sold'] ?> transaksi</li>
    </ul>
</body>
</html>
