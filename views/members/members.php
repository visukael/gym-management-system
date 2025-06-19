<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/Transaction.php';

$trxModel = new Transaction($conn);

// Update status member expired
$today = date('Y-m-d');
$conn->query("UPDATE members SET status = 'inactive' WHERE expired_date < '$today' AND status = 'active'");

// Ambil semua paket
$packages = $conn->query("SELECT * FROM membership_packages");

// Proses Tambah/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $email = $_POST['email'] ?: null;
    $age = $_POST['age'] ?: null;
    $package_id = $_POST['package_id'];
    $promo_id = $_POST['promo_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    // Ambil paket
    $pkgStmt = $conn->prepare("SELECT duration_months, price FROM membership_packages WHERE id = ?");
    $pkgStmt->bind_param("i", $package_id);
    $pkgStmt->execute();
    $pkg = $pkgStmt->get_result()->fetch_assoc();
    $duration = $pkg['duration_months'];
    $base_price = (float) $pkg['price'];

    // Hitung promo
    $discount = 0;
    if (!empty($promo_id)) {
        $promoStmt = $conn->prepare("SELECT discount_type, discount_value FROM promotions WHERE id = ?");
        $promoStmt->bind_param("i", $promo_id);
        $promoStmt->execute();
        $promo = $promoStmt->get_result()->fetch_assoc();

        if ($promo) {
            $type = strtolower(trim($promo['discount_type']));
            $value = (float) preg_replace('/[^0-9.]/', '', $promo['discount_value']);

            // Cek fleksibel terhadap nama diskon
            if (in_array($type, ['flat', 'rp', 'nominal', 'uang'])) {
                $discount = $value;
            } elseif (in_array($type, ['percent', 'persen', '%'])) {
                $discount = ($base_price * $value) / 100;
            }
        }
    }


    $final_price = max(0, $base_price - $discount);
    $join_date = date('Y-m-d');
    $expired_date = date('Y-m-d', strtotime("+$duration months"));
    $status = 'active';

    if ($id) {
        // Update member
        $stmt = $conn->prepare("UPDATE members SET full_name=?, phone=?, address=?, email=?, age=?, package_id=?, promo_id=? WHERE id=?");
        $stmt->bind_param("sssssiii", $full_name, $phone, $address, $email, $age, $package_id, $promo_id, $id);
        $stmt->execute();
    } else {
        // Insert member baru
        $stmt = $conn->prepare("INSERT INTO members (full_name, phone, address, email, age, join_date, expired_date, package_id, promo_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssis", $full_name, $phone, $address, $email, $age, $join_date, $expired_date, $package_id, $promo_id, $status);
        $stmt->execute();
        $member_id = $conn->insert_id;

        // Transaksi otomatis
        $trxModel->create([
            'transaction_type' => 'member_new',
            'label' => 'income',
            'description' => "Pendaftaran member baru: $full_name",
            'amount' => $base_price,
            'discount' => $discount,
            'final_amount' => $final_price,
            'member_id' => $member_id,
            'product_id' => null,
            'user_id' => $user_id
        ]);
    }

    header("Location: members.php");
    exit;
}

// Hapus
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM members WHERE id = $id");
    header("Location: members.php");
    exit;
}

// Edit
$editData = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $res = $conn->query("SELECT * FROM members WHERE id = $id");
    $editData = $res->fetch_assoc();
}

// Ambil semua member
$members = $conn->query("
    SELECT m.*, p.name AS package_name 
    FROM members m 
    JOIN membership_packages p ON m.package_id = p.id 
    ORDER BY m.id DESC
");

// Ambil promo aktif
$promos = $conn->query("
    SELECT promotions.*, mp.name AS package_name 
    FROM promotions 
    JOIN membership_packages mp ON promotions.package_id = mp.id 
    WHERE promotions.start_date <= CURDATE() AND promotions.end_date >= CURDATE()
");
?>

<h2>Manajemen Member</h2>

<form method="post" style="margin-bottom:30px;">
    <input type="hidden" name="id" value="<?= $editData['id'] ?? '' ?>">

    <label>Nama Lengkap:</label><br>
    <input type="text" name="full_name" value="<?= $editData['full_name'] ?? '' ?>" required><br>

    <label>No. Telepon:</label><br>
    <input type="text" name="phone" value="<?= $editData['phone'] ?? '' ?>" required><br>

    <label>Alamat:</label><br>
    <input type="text" name="address" value="<?= $editData['address'] ?? '' ?>" required><br>

    <label>Email:</label><br>
    <input type="email" name="email" value="<?= $editData['email'] ?? '' ?>"><br>

    <label>Umur:</label><br>
    <input type="number" name="age" value="<?= $editData['age'] ?? '' ?>"><br>

    <label>Paket Membership:</label><br>
    <select name="package_id" required>
        <option value="">-- Pilih Paket --</option>
        <?php $packages->data_seek(0);
        while ($pkg = $packages->fetch_assoc()): ?>
            <option value="<?= $pkg['id'] ?>" <?= ($editData['package_id'] ?? '') == $pkg['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($pkg['name']) ?> (Rp<?= number_format($pkg['price']) ?>)
            </option>
        <?php endwhile; ?>
    </select><br>

    <label>Promo (opsional):</label><br>
    <select name="promo_id">
        <option value="">-- Tidak ada promo --</option>
        <?php while ($promo = $promos->fetch_assoc()): ?>
            <option value="<?= $promo['id'] ?>" <?= ($editData['promo_id'] ?? '') == $promo['id'] ? 'selected' : '' ?>>
                <?= $promo['name'] ?> - <?= $promo['package_name'] ?> (<?= $promo['discount_type'] === 'percent' ? $promo['discount_value'] . '%' : 'Rp' . number_format($promo['discount_value']) ?>)
            </option>
        <?php endwhile; ?>
    </select><br><br>

    <button type="submit"><?= $editData ? 'Update Member' : 'Tambah Member' ?></button>
    <?php if ($editData): ?>
        <a href="members.php">Batal</a>
    <?php endif; ?>
</form>

<hr>

<h3>Daftar Member</h3>
<table border="1" cellpadding="8">
    <tr>
        <th>Nama</th>
        <th>Telepon</th>
        <th>Email</th>
        <th>Paket</th>
        <th>Join</th>
        <th>Expired</th>
        <th>Status</th>
        <th>Aksi</th>
    </tr>
    <?php while ($row = $members->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['full_name']) ?></td>
            <td><?= htmlspecialchars($row['phone']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td><?= htmlspecialchars($row['package_name']) ?></td>
            <td><?= $row['join_date'] ?></td>
            <td><?= $row['expired_date'] ?></td>
            <td style="color:<?= $row['status'] === 'active' ? 'green' : 'red' ?>">
                <?= ucfirst($row['status']) ?>
            </td>
            <td>
                <a href="?edit=<?= $row['id'] ?>">Edit</a> |
                <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Hapus member ini?')">Hapus</a>
            </td>
        </tr>
    <?php endwhile; ?>
</table>