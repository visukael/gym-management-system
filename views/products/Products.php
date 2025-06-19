<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/Product.php';
require_once '../../models/Transaction.php';

$productModel = new Product($conn);
$trxModel = new Transaction($conn);

// --- Tambah/Edit Produk ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_product'])) {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $initial_buy_price = $_POST['initial_buy_price'] ?? 0;

    if ($id) {
        $stmt = $conn->prepare("UPDATE products SET name=?, description=?, price=?, stock=? WHERE id=?");
        $stmt->bind_param("ssdii", $name, $desc, $price, $stock, $id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO products (name, description, price, stock) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssdi", $name, $desc, $price, $stock);
        $stmt->execute();
        $newProductId = $conn->insert_id;

        // Catat stok awal hanya jika ada harga beli
        if ($stock > 0 && $initial_buy_price > 0) {
            $trxModel->create([
                'transaction_type' => 'stock_add',
                'label' => 'outcome',
                'description' => "Stok awal: {$name} x{$stock}",
                'amount' => $initial_buy_price,
                'discount' => 0,
                'final_amount' => $initial_buy_price,
                'member_id' => null,
                'product_id' => $newProductId,
                'user_id' => $_SESSION['user_id']
            ]);

            // Tambah ke histori stok
            $stmtHist = $conn->prepare("INSERT INTO product_stock_entries (product_id, quantity, buy_price) VALUES (?, ?, ?)");
            $stmtHist->bind_param("iid", $newProductId, $stock, $initial_buy_price);
            $stmtHist->execute();
        }
    }

    header("Location: products.php");
    exit;
}

// --- Hapus Produk ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM products WHERE id = $id");
    header("Location: products.php");
    exit;
}

// --- Tambah Stok ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_stock'])) {
    $id = $_POST['product_id'];
    $qty = (int) $_POST['qty'];
    $buy_total = (float) $_POST['buy_price'];

    $product = $productModel->getById($id);
    $productModel->addStock($id, $qty);

    // Simpan ke histori stok
    $stmtHist = $conn->prepare("INSERT INTO product_stock_entries (product_id, quantity, buy_price) VALUES (?, ?, ?)");
    $stmtHist->bind_param("iid", $id, $qty, $buy_total);
    $stmtHist->execute();

    if ($buy_total > 0) {
        $trxModel->create([
            'transaction_type' => 'stock_add',
            'label' => 'outcome',
            'description' => "Tambah stok: {$product['name']} x{$qty}",
            'amount' => $buy_total,
            'discount' => 0,
            'final_amount' => $buy_total,
            'member_id' => null,
            'product_id' => $id,
            'user_id' => $_SESSION['user_id']
        ]);
    }

    header("Location: products.php");
    exit;
}

// --- Produk Hilang ---
if (isset($_GET['missing'])) {
    $id = $_GET['missing'];
    $product = $productModel->getById($id);
    if ($product['stock'] > 0) {
        $productModel->removeStock($id, 1);
        $trxModel->create([
            'transaction_type' => 'missing',
            'label' => 'outcome',
            'description' => "Produk hilang: {$product['name']}",
            'amount' => $product['price'],
            'discount' => 0,
            'final_amount' => $product['price'],
            'member_id' => null,
            'product_id' => $id,
            'user_id' => $_SESSION['user_id']
        ]);
    }
    header("Location: products.php");
    exit;
}

// --- Penjualan Produk ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sale'])) {
    $selected = $_POST['selected_products'] ?? [];
    $quantities = $_POST['qty'] ?? [];

    $total = 0;
    $product_list = [];

    foreach ($selected as $product_id) {
        $product = $productModel->getById($product_id);
        $qty = (int) ($quantities[$product_id] ?? 0);

        if ($qty > 0 && $product['stock'] >= $qty) {
            $subTotal = $product['price'] * $qty;
            $total += $subTotal;
            $productModel->removeStock($product_id, $qty);
            $product_list[] = "{$product['name']} x{$qty}";
        }
    }

    if ($total > 0) {
        $desc = implode(', ', $product_list);
        $trxModel->create([
            'transaction_type' => 'product_sale',
            'label' => 'income',
            'description' => $desc,
            'amount' => $total,
            'discount' => 0,
            'final_amount' => $total,
            'member_id' => null,
            'product_id' => null,
            'user_id' => $_SESSION['user_id']
        ]);
    }

    header("Location: products.php");
    exit;
}

$products = $productModel->getAll();
?>

<h2>Manajemen Produk</h2>

<!-- Form Tambah/Edit Produk -->
<form method="post" style="margin-bottom:30px;">
    <input type="hidden" name="id" value="<?= $_GET['edit'] ?? '' ?>">
    <label>Nama Produk:</label><br>
    <input type="text" name="name" required><br>

    <label>Deskripsi:</label><br>
    <textarea name="description"></textarea><br>

    <label>Harga Jual (Rp):</label><br>
    <input type="number" name="price" required><br>

    <label>Stok Awal:</label><br>
    <input type="number" name="stock" required><br>

    <label>Total Harga Beli Awal (Rp):</label><br>
    <input type="number" name="initial_buy_price" value="0"><br><br>

    <button type="submit" name="submit_product">Simpan Produk</button>
</form>

<hr>

<!-- Form Tambah Stok -->
<?php if (isset($_GET['add_stock'])): 
    $product = $productModel->getById($_GET['add_stock']);
?>
<h3>Tambah Stok: <?= htmlspecialchars($product['name']) ?></h3>
<form method="post" style="margin-bottom: 30px;">
    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
    <label>Jumlah Stok:</label><br>
    <input type="number" name="qty" required><br>

    <label>Total Harga Pembelian (Rp):</label><br>
    <input type="number" name="buy_price" value="0" required><br><br>

    <button type="submit" name="submit_stock">Tambahkan Stok</button>
    <a href="products.php">Batal</a>
</form>
<?php endif; ?>

<!-- Daftar Produk -->
<h3>Daftar Produk</h3>
<table border="1" cellpadding="8">
    <tr>
        <th>Nama</th>
        <th>Harga Jual</th>
        <th>Stok</th>
        <th>Aksi</th>
    </tr>
    <?php foreach ($products as $row): ?>
    <tr>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td>Rp<?= number_format($row['price']) ?></td>
        <td><?= $row['stock'] ?></td>
        <td>
            <a href="?edit=<?= $row['id'] ?>">Edit</a> |
            <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Hapus produk ini?')">Hapus</a> |
            <a href="?add_stock=<?= $row['id'] ?>">Tambah Stok</a> |
            <a href="?missing=<?= $row['id'] ?>" onclick="return confirm('Tandai produk ini hilang?')">❌ Hilang</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<hr>
<h3>Penjualan Produk</h3>
<form method="post">
    <table border="1" cellpadding="6">
        <tr>
            <th>Pilih</th>
            <th>Produk</th>
            <th>Harga</th>
            <th>Jumlah</th>
        </tr>
        <?php foreach ($products as $row): ?>
        <tr>
            <td><input type="checkbox" name="selected_products[]" value="<?= $row['id'] ?>"></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td>Rp<?= number_format($row['price']) ?></td>
            <td>
                <input type="number" name="qty[<?= $row['id'] ?>]" min="1" max="<?= $row['stock'] ?>" value="1" style="width:60px;">
            </td>
        </tr>
        <?php endforeach; ?>
    </table><br>
    <button type="submit" name="submit_sale">Proses Penjualan</button>
</form>

<hr>
<h3>Riwayat Penambahan Stok</h3>
<table border="1" cellpadding="6">
    <tr>
        <th>Produk</th>
        <th>Jumlah</th>
        <th>Harga Beli</th>
        <th>Tanggal</th>
    </tr>
    <?php
    $stockLog = $conn->query("
        SELECT s.*, p.name AS product_name
        FROM product_stock_entries s
        JOIN products p ON s.product_id = p.id
        ORDER BY s.created_at DESC
    ");
    while ($row = $stockLog->fetch_assoc()):
    ?>
    <tr>
        <td><?= htmlspecialchars($row['product_name']) ?></td>
        <td><?= $row['quantity'] ?></td>
        <td>Rp<?= number_format($row['buy_price'], 0, ',', '.') ?></td>
        <td><?= $row['created_at'] ?></td>
    </tr>
    <?php endwhile; ?>
</table>
