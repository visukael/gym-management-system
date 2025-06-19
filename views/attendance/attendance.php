<?php
session_start();
require_once '../../config/database.php';
require_once '../../controllers/AttendanceController.php';

$attendanceController = new AttendanceController($conn);
$user_id = $_SESSION['user_id'] ?? 1;

// Proses presensi masuk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'])) {
    $member_id = $_POST['member_id'];
    $attendanceController->record($member_id, $user_id);
    header("Location: attendance.php");
    exit;
}

// Ambil semua member aktif
$members = $conn->query("SELECT id, full_name FROM members WHERE status = 'active'");

// Filter berdasarkan tanggal (opsional)
$selected_date = $_GET['date'] ?? date('Y-m-d');
$attendances = $attendanceController->byDate($selected_date);
?>

<h2>Presensi Member</h2>

<!-- Form Presensi -->
<form method="post" style="margin-bottom: 30px;">
    <label>Pilih Member yang Hadir Hari Ini:</label><br>
    <select name="member_id" required>
        <option value="">-- Pilih Member --</option>
        <?php while ($m = $members->fetch_assoc()): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
        <?php endwhile; ?>
    </select>
    <button type="submit">Catat Kehadiran</button>
</form>

<!-- Filter Tanggal -->
<form method="get" style="margin-bottom: 20px;">
    <label>Lihat Presensi Tanggal:</label>
    <input type="date" name="date" value="<?= $selected_date ?>">
    <button type="submit">Lihat</button>
</form>

<!-- Tabel Presensi -->
<h3>Data Presensi: <?= date('d M Y', strtotime($selected_date)) ?></h3>
<table border="1" cellpadding="8">
    <tr>
        <th>Member</th>
        <th>Waktu Check-in</th>
        <th>Dicatat Oleh</th>
    </tr>
    <?php while ($row = $attendances->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['full_name']) ?></td>
        <td><?= date('H:i:s d M Y', strtotime($row['checkin_time'])) ?></td>
        <td><?= htmlspecialchars($row['admin_name'] ?? '-') ?></td>
    </tr>
    <?php endwhile; ?>
</table>
