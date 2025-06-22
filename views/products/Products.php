<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/Product.php';
require_once '../../models/Transaction.php';

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['owner', 'admin'])) {
    header('Location: ../../login.php');
    exit;
}

$productModel = new Product($conn);
$trxModel = new Transaction($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type = $_POST['action_type'] ?? '';
    $user_id = $_SESSION['user_id'];

    if ($action_type === 'submit_product') {
        $id = $_POST['id'] ?? '';
        $name = htmlspecialchars(trim($_POST['name']));
        $description = htmlspecialchars(trim($_POST['description']));
        $price = (float)$_POST['price'];
        $stock = (int)$_POST['stock'];
        $initial_buy_price = (float)($_POST['initial_buy_price'] ?? 0);

        if (empty($name) || $price < 0 || $stock < 0) {
            $_SESSION['error_message'] = "Product name, price, and stock are required and cannot be negative.";
            header("Location: products.php");
            exit;
        }

        if ($id) {
            $stmt = $conn->prepare("UPDATE products SET name=?, description=?, price=?, stock=? WHERE id=?");
            $stmt->bind_param("ssdii", $name, $description, $price, $stock, $id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Product updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating product: " . $conn->error;
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO products (name, description, price, stock) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssdi", $name, $description, $price, $stock);
            if ($stmt->execute()) {
                $newProductId = $conn->insert_id;

                if ($stock > 0 && $initial_buy_price > 0) {
                    $trxModel->create([
                        'transaction_type' => 'stock_add',
                        'label' => 'outcome',
                        'description' => "Initial stock purchase: {$name} x{$stock}",
                        'amount' => $initial_buy_price,
                        'discount' => 0,
                        'final_amount' => $initial_buy_price,
                        'member_id' => null,
                        'product_id' => $newProductId,
                        'user_id' => $user_id,
                        'payment_method' => 'cash'
                    ]);

                    $stmtHist = $conn->prepare("INSERT INTO product_stock_entries (product_id, quantity, buy_price) VALUES (?, ?, ?)");
                    $stmtHist->bind_param("iid", $newProductId, $stock, $initial_buy_price);
                    $stmtHist->execute();
                }
                $_SESSION['success_message'] = "Product added successfully!";
            } else {
                $_SESSION['error_message'] = "Error adding product: " . $conn->error;
            }
        }
    } elseif ($action_type === 'submit_stock') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $qty = (int)($_POST['qty'] ?? 0);
        $buy_total = (float)($_POST['buy_price'] ?? 0);

        if ($product_id <= 0 || $qty <= 0 || $buy_total < 0) {
            $_SESSION['error_message'] = "Invalid product or quantity/buy price for adding stock.";
            header("Location: products.php");
            exit;
        }

        $product = $productModel->getById($product_id);
        if (!$product) {
            $_SESSION['error_message'] = "Product not found for stock addition.";
            header("Location: products.php");
            exit;
        }

        if ($productModel->addStock($product_id, $qty)) {
            $stmtHist = $conn->prepare("INSERT INTO product_stock_entries (product_id, quantity, buy_price) VALUES (?, ?, ?)");
            $stmtHist->bind_param("iid", $product_id, $qty, $buy_total);
            $stmtHist->execute();

            if ($buy_total > 0) {
                $trxModel->create([
                    'transaction_type' => 'stock_add',
                    'label' => 'outcome',
                    'description' => "Stock added: {$product['name']} x{$qty}",
                    'amount' => $buy_total,
                    'discount' => 0,
                    'final_amount' => $buy_total,
                    'member_id' => null,
                    'product_id' => $product_id,
                    'user_id' => $user_id,
                    'payment_method' => 'cash'
                ]);
            }
            $_SESSION['success_message'] = "Stock for " . htmlspecialchars($product['name']) . " added successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to add stock.";
        }
    } elseif ($action_type === 'submit_sale') {
        $selected_products_ids = $_POST['selected_products'] ?? [];
        $quantities_from_form = $_POST['qty'] ?? [];
        $payment_method_sale = htmlspecialchars(trim($_POST['payment_method_sale'] ?? 'cash'));
        $total_sale_amount = 0;
        $products_sold_details = [];

        foreach ($selected_products_ids as $product_id_str) {
            $product_id = (int)$product_id_str;
            $qty_sold = isset($quantities_from_form[$product_id]) ? (int)$quantities_from_form[$product_id] : 0;

            if ($product_id <= 0 || $qty_sold <= 0) continue;

            $product = $productModel->getById($product_id);

            if ($product && $product['stock'] >= $qty_sold) {
                $subTotal = $product['price'] * $qty_sold;
                $total_sale_amount += $subTotal;
                $productModel->removeStock($product_id, $qty_sold);
                $products_sold_details[] = htmlspecialchars($product['name']) . " x" . $qty_sold;
            } else {
                $_SESSION['error_message'] = "Not enough stock for " . htmlspecialchars($product['name']) . " or product not found.";
                header("Location: products.php");
                exit;
            }
        }

        if ($total_sale_amount > 0) {
            $desc = "Sale: " . implode(', ', $products_sold_details);
            $trxModel->create([
                'transaction_type' => 'product_sale',
                'label' => 'income',
                'description' => $desc,
                'amount' => $total_sale_amount,
                'discount' => 0,
                'final_amount' => $total_sale_amount,
                'member_id' => null,
                'product_id' => null,
                'user_id' => $user_id,
                'payment_method' => $payment_method_sale
            ]);
            $_SESSION['success_message'] = "Product sale successfully processed!";
        } else {
            $_SESSION['error_message'] = "No products selected or quantities are invalid for sale.";
        }
    }
    header("Location: products.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($productModel->deleteProduct($id)) {
        $_SESSION['success_message'] = "Product deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting product. It might be referenced in transactions or stock entries.";
    }
    header("Location: products.php");
    exit;
}

if (isset($_GET['missing'])) {
    $id = (int)$_GET['missing'];
    $product = $productModel->getById($id);
    if ($product && $product['stock'] > 0) {
        if ($productModel->removeStock($id, 1)) {
            $trxModel->create([
                'transaction_type' => 'missing_item',
                'label' => 'outcome',
                'description' => "Missing product: {$product['name']} (1 unit)",
                'amount' => $product['price'],
                'discount' => 0,
                'final_amount' => $product['price'],
                'member_id' => null,
                'product_id' => $id,
                'user_id' => $_SESSION['user_id'],
                'payment_method' => 'cash'
            ]);
            $_SESSION['success_message'] = htmlspecialchars($product['name']) . " marked as missing.";
        } else {
            $_SESSION['error_message'] = "Failed to mark product as missing.";
        }
    } else {
        $_SESSION['error_message'] = "Product not found or no stock to mark as missing.";
    }
    header("Location: products.php");
    exit;
}

$editData = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $editData = $productModel->getById($id);
    if ($editData) {
        echo "<script>document.addEventListener('DOMContentLoaded', () => openProductModal('edit', " . json_encode($editData) . "));</script>";
    } else {
        $_SESSION['error_message'] = "Product not found for editing.";
        header("Location: products.php");
        exit;
    }
}

$addStockData = null;
if (isset($_GET['add_stock'])) {
    $id = (int)$_GET['add_stock'];
    $addStockData = $productModel->getById($id);
    if ($addStockData) {
        echo "<script>document.addEventListener('DOMContentLoaded', () => openAddStockModal(" . json_encode($addStockData) . "));</script>";
    } else {
        $_SESSION['error_message'] = "Product not found for adding stock.";
        header("Location: products.php");
        exit;
    }
}

$searchTerm = $_GET['search'] ?? '';
$filterStock = $_GET['stock_status'] ?? '';

$products = $productModel->getFilteredProducts($searchTerm, $filterStock);

$stockLog = $productModel->getRecentStockHistory(10);

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Unknown');
$userRole = htmlspecialchars(ucfirst($_SESSION['user_role'] ?? '-'));

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Gym Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
</head>

<body class="bg-gray-50 text-gray-900">
    <div class="flex h-screen overflow-hidden">
        <div class="hidden md:flex md:flex-shrink-0">
            <div class="flex flex-col w-64 border-r border-gray-200 bg-white">
                <div class="flex items-center justify-center h-16 px-4 border-b border-gray-200">
                    <div class="flex items-center">
                        <span class="ml-2 text-xl font-bold text-red-600">FORZA</span>
                    </div>
                </div>
                <div class="flex flex-col flex-grow px-4 py-4 overflow-y-auto">
                    <nav class="flex-1 space-y-2">
                        <a href="../dashboard.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item">
                            <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Dashboard
                        </a>
                        <a href="../members/members.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item">
                            <i data-lucide="users" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Members
                        </a>
                        <a href="products.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item active">
                            <i data-lucide="shopping-bag" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Products
                        </a>
                        <a href="../transactions/transactions.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item">
                            <i data-lucide="credit-card" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Transactions
                        </a>
                        <a href="../attendance/attendance.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item">
                            <i data-lucide="calendar-check" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Attendance
                        </a>
                        <a href="../promotions/promotions.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item">
                            <i data-lucide="percent" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Promotions
                        </a>
                        <a href="../users/user.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item">
                            <i data-lucide="settings" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Manage Users
                        </a>
                        <a href="../../logout.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item">
                            <i data-lucide="log-out" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Logout
                        </a>
                    </nav>
                    <div class="mt-auto">
                        <a href="../settings/setting.php" class="flex items-center p-4 mt-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer">
                            <div class="flex-shrink-0">
                                <img class="w-10 h-10 rounded-full" src="https://ui-avatars.com/api/?name=<?= urlencode($userName) ?>&background=ef4444&color=fff" alt="Profile">
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900"><?= $userName ?></p>
                                <p class="text-xs text-gray-500"><?= $userRole ?></p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col flex-1 overflow-hidden">
            <div class="flex items-center justify-between h-16 px-4 border-b border-gray-200 bg-white">
                <div class="flex items-center md:hidden">
                    <button class="text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-500 rounded-md p-1" onclick="toggleSidebar()">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
                <div class="flex items-center space-x-4 ml-auto">
                    <button class="p-2 text-gray-500 rounded-full hover:bg-gray-100 hover:text-red-500 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                    </button>
                    <img class="w-8 h-8 rounded-full cursor-pointer hover:shadow-md transition-shadow" src="https://ui-avatars.com/api/?name=<?= urlencode($userName) ?>&background=ef4444&color=fff" alt="Profile">
                </div>
            </div>

            <div class="flex-1 overflow-auto p-4 md:p-6 bg-gray-50">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Product Management</h1>
                        <p class="text-gray-500">Add, edit, manage stock, and sell products.</p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6">
                    <form method="GET" action="products.php" class="flex flex-col lg:flex-row gap-4 items-center">
                        <div class="flex flex-col sm:flex-row gap-4 flex-grow">
                            <div class="relative flex-grow">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="search" class="w-5 h-5 text-gray-400"></i>
                                </div>
                                <input type="text" name="search" placeholder="Search by name or description..." value="<?= htmlspecialchars($searchTerm) ?>"
                                    class="form-input-field pl-10 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                            </div>
                            <div class="w-full sm:w-auto">
                                <select name="stock_status"
                                    class="form-select-field block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                                    <option value="">All Stock</option>
                                    <option value="in_stock" <?= $filterStock === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                                    <option value="low" <?= $filterStock === 'low' ? 'selected' : '' ?>>Low Stock</option>
                                    <option value="out_of_stock" <?= $filterStock === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto justify-end">
                            <button type="submit" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 w-full sm:w-auto">
                                <i data-lucide="filter" class="w-4 h-4 mr-2"></i>
                                Apply Filters
                            </button>
                            <?php if (!empty($searchTerm) || !empty($filterStock)): ?>
                                <a href="products.php" class="btn-secondary inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 w-full sm:w-auto">
                                    <i data-lucide="x-circle" class="w-4 h-4 mr-2"></i> Clear Filters
                                </a>
                            <?php endif; ?>
                            <button onclick="openProductModal('add')" type="button" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 w-full sm:w-auto">
                                <i data-lucide="plus-circle" class="w-4 h-4 mr-2"></i>
                                Add New Product
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h2 class="text-lg font-medium text-gray-800 mb-4">Product List</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sale Price</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Buy Price</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($products) > 0): ?>
                                    <?php foreach ($products as $row): ?>
                                        <tr class="table-row-hover" id="product-<?= $row['id'] ?>">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= $row['id'] ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['name']) ?></td>
                                            <td class="px-4 py-3 max-w-xs truncate text-sm text-gray-500" title="<?= htmlspecialchars($row['description']) ?>">
                                                <?= htmlspecialchars($row['description']) ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">Rp<?= number_format($row['price'], 0, ',', '.') ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?= $row['avg_buy_price'] ? 'Rp' . number_format($row['avg_buy_price'], 0, ',', '.') : '-' ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <span class="status-badge
                                                    <?php
                                                    if ($row['stock'] <= 0) echo 'status-out';
                                                    else if ($row['stock'] <= 5) echo 'status-low';
                                                    else echo 'status-in';
                                                    ?>">
                                                    <?= $row['stock'] ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-medium">
                                                <button onclick="openProductModal('edit', <?= htmlspecialchars(json_encode($row)) ?>)" class="action-link text-red-600 hover:text-red-900 mr-3">
                                                    <i data-lucide="edit" class="w-4 h-4 inline-block align-text-bottom"></i> Edit
                                                </button>
                                                <button onclick="openAddStockModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="action-link text-blue-600 hover:text-blue-900 mr-3">
                                                    <i data-lucide="package-plus" class="w-4 h-4 inline-block align-text-bottom"></i> Add Stock
                                                </button>
                                                <button onclick="confirmMissing(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name']) ?>')" class="action-link text-yellow-600 hover:text-yellow-900 mr-3">
                                                    <i data-lucide="alert-triangle" class="w-4 h-4 inline-block align-text-bottom"></i> Missing
                                                </button>
                                                <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name']) ?>')" class="action-link text-gray-600 hover:text-gray-900">
                                                    <i data-lucide="trash-2" class="w-4 h-4 inline-block align-text-bottom"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-center">No products found matching your criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mt-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-medium text-gray-800">Product Sale</h2>
                        <button onclick="openSaleModal()" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <i data-lucide="shopping-cart" class="w-4 h-4 mr-2"></i> Process Sale
                        </button>
                    </div>
                    <div class="text-sm text-gray-500">
                        Click "Process Sale" to select multiple products and quantities for a single transaction.
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mt-6">
                    <h2 class="text-lg font-medium text-gray-800 mb-4">Recent Stock History</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Buy Price</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($stockLog) > 0): ?>
                                    <?php foreach ($stockLog as $entry): ?>
                                    <tr class="table-row-hover">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($entry['product_name']) ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= $entry['quantity'] ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">Rp<?= number_format($entry['buy_price'], 0, ',', '.') ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y H:i', strtotime($entry['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-center">No recent stock entries found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div id="productFormModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal('productFormModal')">&times;</span>
            <h2 id="productModalTitle" class="text-lg font-medium text-gray-800 mb-4">Add New Product</h2>
            <form id="productForm" method="post" class="space-y-4">
                <input type="hidden" name="action_type" value="submit_product">
                <input type="hidden" name="id" id="product_id_modal">

                <div>
                    <label for="product_name_modal" class="block text-sm font-medium text-gray-700 mb-1">Product Name</label>
                    <input type="text" name="name" id="product_name_modal" required
                        class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                </div>

                <div>
                    <label for="product_description_modal" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="product_description_modal" rows="3"
                        class="form-textarea-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm"></textarea>
                </div>

                <div>
                    <label for="product_price_modal" class="block text-sm font-medium text-gray-700 mb-1">Sale Price (Rp)</label>
                    <input type="number" name="price" id="product_price_modal" required min="0" step="100"
                        class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                </div>

                <div id="initial_stock_fields">
                    <div>
                        <label for="product_stock_modal" class="block text-sm font-medium text-gray-700 mb-1">Initial Stock</label>
                        <input type="number" name="stock" id="product_stock_modal" required min="0"
                            class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="product_initial_buy_price_modal" class="block text-sm font-medium text-gray-700 mb-1">Total Initial Buy Price (Rp)</label>
                        <input type="number" name="initial_buy_price" id="product_initial_buy_price_modal" min="0" step="100" value="0"
                            class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                    </div>
                </div>
                
                <div class="flex items-center space-x-3 mt-6">
                    <button type="submit" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i data-lucide="save" class="w-4 h-4 mr-2"></i> Save Product
                    </button>
                    <button type="button" onclick="closeModal('productFormModal')" class="btn-secondary inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i data-lucide="x-circle" class="w-4 h-4 mr-2"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="addStockModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal('addStockModal')">&times;</span>
            <h2 id="addStockModalTitle" class="text-lg font-medium text-gray-800 mb-4">Add Stock</h2>
            <form id="addStockForm" method="post" class="space-y-4">
                <input type="hidden" name="action_type" value="submit_stock">
                <input type="hidden" name="product_id" id="add_stock_product_id">
                <p class="text-gray-700 text-sm mb-2">Adding stock for: <span id="add_stock_product_name" class="font-semibold text-red-600"></span></p>

                <div>
                    <label for="add_stock_qty" class="block text-sm font-medium text-gray-700 mb-1">Quantity to Add</label>
                    <input type="number" name="qty" id="add_stock_qty" required min="1"
                        class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                </div>

                <div>
                    <label for="add_stock_buy_price" class="block text-sm font-medium text-gray-700 mb-1">Total Buy Price (for this quantity, Rp)</label>
                    <input type="number" name="buy_price" id="add_stock_buy_price" required min="0" step="100"
                        class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                </div>
                
                <div class="flex items-center space-x-3 mt-6">
                    <button type="submit" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i data-lucide="plus-circle" class="w-4 h-4 mr-2"></i> Add Stock
                    </button>
                    <button type="button" onclick="closeModal('addStockModal')" class="btn-secondary inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i data-lucide="x-circle" class="w-4 h-4 mr-2"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="saleModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal('saleModal')">&times;</span>
            <h2 class="text-lg font-medium text-gray-800 mb-4">Process Product Sale</h2>
            <form id="saleForm" method="post" class="space-y-4">
                <input type="hidden" name="action_type" value="submit_sale">
                <div class="overflow-x-auto max-h-80">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Select</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Available Stock</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($products as $row): ?>
                            <tr class="table-row-hover">
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <input type="checkbox" name="selected_products[]" value="<?= $row['id'] ?>" data-max-stock="<?= $row['stock'] ?>"
                                            onchange="toggleSaleQtyInput(this, <?= $row['id'] ?>)" <?= $row['stock'] <= 0 ? 'disabled' : '' ?>>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['name']) ?></td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">Rp<?= number_format($row['price'], 0, ',', '.') ?></td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <span class="status-badge <?= $row['stock'] <= 0 ? 'status-out' : ($row['stock'] <= 5 ? 'status-low' : 'status-in') ?>">
                                        <?= $row['stock'] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <input type="number" name="qty[<?= $row['id'] ?>]" min="1" max="<?= $row['stock'] ?>" value="1"
                                            class="form-input-field w-20 px-2 py-1 text-sm" disabled>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-3 text-center text-sm text-gray-500">No products available for sale.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4">
                    <label for="payment_method_sale" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                    <select name="payment_method_sale" id="payment_method_sale" required
                        class="form-select-field mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                        <option value="cash">Cash</option>
                        <option value="qr">QR</option>
                    </select>
                </div>

                <div class="flex items-center space-x-3 mt-6">
                    <button type="submit" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i data-lucide="shopping-cart" class="w-4 h-4 mr-2"></i> Complete Sale
                    </button>
                    <button type="button" onclick="closeModal('saleModal')" class="btn-secondary inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i data-lucide="x-circle" class="w-4 h-4 mr-2"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="toastContainer" class="toast-container"></div>

    <script>
        lucide.createIcons();

        function toggleSidebar() {
            const sidebar = document.querySelector('.md\\:flex-shrink-0');
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('absolute');
            sidebar.classList.toggle('inset-y-0');
            sidebar.classList.toggle('left-0');
            sidebar.classList.toggle('z-40');
        }

        const productFormModal = document.getElementById('productFormModal');
        const addStockModal = document.getElementById('addStockModal');
        const saleModal = document.getElementById('saleModal');

        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            const url = new URL(window.location.href);
            if (url.searchParams.has('edit') || url.searchParams.has('add_stock')) {
                url.searchParams.delete('edit');
                url.searchParams.delete('add_stock');
                window.history.replaceState({}, document.title, url.toString());
            }
        }

        window.onclick = function(event) {
            if (event.target == productFormModal) closeModal('productFormModal');
            if (event.target == addStockModal) closeModal('addStockModal');
            if (event.target == saleModal) closeModal('saleModal');
        }

        const productModalTitle = document.getElementById('productModalTitle');
        const productForm = document.getElementById('productForm');
        const productIdModal = document.getElementById('product_id_modal');
        const productNameModal = document.getElementById('product_name_modal');
        const productDescriptionModal = document.getElementById('product_description_modal');
        const productPriceModal = document.getElementById('product_price_modal');
        const productStockModal = document.getElementById('product_stock_modal');
        const productInitialBuyPriceModal = document.getElementById('product_initial_buy_price_modal');
        const initialStockFields = document.getElementById('initial_stock_fields');

        function openProductModal(mode, productData = null) {
            productForm.reset();
            productIdModal.value = '';
            productForm.querySelectorAll('[required]').forEach(el => el.removeAttribute('required'));

            if (mode === 'add') {
                productModalTitle.textContent = 'Add New Product';
                initialStockFields.style.display = 'block';
                
                productNameModal.setAttribute('required', '');
                productPriceModal.setAttribute('required', '');
                productStockModal.setAttribute('required', '');
                productInitialBuyPriceModal.setAttribute('required', '');

            } else if (mode === 'edit' && productData) {
                productModalTitle.textContent = 'Edit Product: ' + productData.name;
                productIdModal.value = productData.id;
                productNameModal.value = productData.name;
                productDescriptionModal.value = productData.description;
                productPriceModal.value = productData.price;
                productStockModal.value = productData.stock;
                initialStockFields.style.display = 'none';

                productNameModal.setAttribute('required', '');
                productPriceModal.setAttribute('required', '');
            }
            openModal('productFormModal');
        }

        const addStockProductId = document.getElementById('add_stock_product_id');
        const addStockProductName = document.getElementById('add_stock_product_name');
        const addStockQtyInput = document.getElementById('add_stock_qty');
        const addStockBuyPriceInput = document.getElementById('add_stock_buy_price');
        const addStockForm = document.getElementById('addStockForm');


        function openAddStockModal(productData) {
            addStockForm.reset();
            addStockProductId.value = productData.id;
            addStockProductName.textContent = productData.name;
            
            addStockQtyInput.setAttribute('required', '');
            addStockBuyPriceInput.setAttribute('required', '');

            openModal('addStockModal');
        }

        const saleForm = document.getElementById('saleForm');
        function openSaleModal() {
            saleForm.reset();
            saleForm.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            saleForm.querySelectorAll('input[type="number"]').forEach(input => {
                input.disabled = true;
                input.value = 1;
            });
            document.getElementById('payment_method_sale').value = 'cash';
            openModal('saleModal');
        }

        function toggleSaleQtyInput(checkbox, productId) {
            const qtyInput = saleForm.querySelector(`input[name='qty[${productId}]']`);
            if (qtyInput) {
                qtyInput.disabled = !checkbox.checked;
                if (checkbox.checked) {
                    qtyInput.focus();
                } else {
                    qtyInput.value = 1;
                }
            }
        }

        function confirmDelete(id, name) {
            if (confirm(`Are you sure you want to delete product: ${name}? This action cannot be undone.`)) {
                window.location.href = `products.php?delete=${id}`;
            }
        }

        function confirmMissing(id, name) {
            if (confirm(`Are you sure you want to mark 1 unit of "${name}" as missing? This will reduce stock.`)) {
                window.location.href = `products.php?missing=${id}`;
            }
        }

        const toastContainer = document.getElementById('toastContainer');
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = 'toast ' + type;
            toast.textContent = message;
            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3500);
        }

        <?php if ($success_message): ?>
            showToast('<?= $success_message ?>', 'success');
        <?php endif; ?>
        <?php if ($error_message): ?>
            showToast('<?= $error_message ?>', 'error');
        <?php endif; ?>
    </script>
</body>

</html>