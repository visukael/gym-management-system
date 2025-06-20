<?php
session_start();
require_once '../../config/database.php';

// Redirect user yang tidak berwenang
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['owner', 'admin'])) {
    header('Location: ../../login.php');
    exit;
}

// Ambil pesan sukses/error dari session untuk ditampilkan sebagai toast
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Proses Simpan / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $name = htmlspecialchars(trim($_POST['name']));
    $type = htmlspecialchars(trim($_POST['discount_type'])); // flat / percent
    $value = (float)($_POST['discount_value'] ?? 0);
    $start = htmlspecialchars(trim($_POST['start_date']));
    $end = htmlspecialchars(trim($_POST['end_date']));

    // Validasi input
    if (empty($name) || empty($start) || empty($end) || $value <= 0) {
        $_SESSION['error_message'] = "Please fill all required fields correctly.";
        header("Location: promotions.php");
        exit;
    }

    // Validasi discount_type untuk pastikan flat / percent
    if (!in_array($type, ['flat', 'percent'])) {
        $_SESSION['error_message'] = "Invalid discount type. Must be 'flat' or 'percent'.";
        header("Location: promotions.php");
        exit;
    }

    // Pastikan tanggal mulai tidak lebih besar dari tanggal berakhir
    if (strtotime($start) > strtotime($end)) {
        $_SESSION['error_message'] = "Start date cannot be after end date.";
        header("Location: promotions.php");
        exit;
    }


    if ($id) {
        $stmt = $conn->prepare("UPDATE promotions SET name=?, discount_type=?, discount_value=?, start_date=?, end_date=? WHERE id=?");
        $stmt->bind_param("ssdsi", $name, $type, $value, $start, $end, $id);
        $message = "Promotion updated successfully!";
        $error_msg = "Error updating promotion.";
    } else {
        $stmt = $conn->prepare("INSERT INTO promotions (name, discount_type, discount_value, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssds", $name, $type, $value, $start, $end);
        $message = "Promotion added successfully!";
        $error_msg = "Error adding promotion.";
    }

    if ($stmt->execute()) {
        $_SESSION['success_message'] = $message;
    } else {
        $_SESSION['error_message'] = $error_msg . " " . $conn->error; // Tambahkan error database untuk debugging
    }
    header("Location: promotions.php");
    exit;
}

// Ambil data untuk edit
$editData = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM promotions WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $editData = $result->fetch_assoc();
    } else {
        $_SESSION['error_message'] = "Promotion not found.";
        header("Location: promotions.php");
        exit;
    }
}

// Hapus promo
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM promotions WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Promotion deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting promotion. It might be linked to other data.";
    }
    header("Location: promotions.php");
    exit;
}

// Ambil semua promo
// Query sekarang tidak JOIN ke membership_packages karena promo berlaku universal
$promos_query = $conn->query("
    SELECT p.*
    FROM promotions p
    ORDER BY p.id DESC
");

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Unknown');
$userRole = htmlspecialchars(ucfirst($_SESSION['user_role'] ?? '-'));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Promotions - Gym Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
        }

        .sidebar-item {
            transition: background-color 0.2s, color 0.2s;
        }

        .sidebar-item:hover {
            background-color: #fef2f2;
            color: #ef4444;
        }

        .sidebar-item:hover .sidebar-icon {
            color: #ef4444;
        }

        .sidebar-item.active {
            background-color: #fef2f2;
            color: #ef4444;
        }

        .sidebar-item.active .sidebar-icon {
            color: #ef4444;
        }

        .table-row-hover:hover {
            background-color: #f9fafb;
        }

        .form-input-field:focus,
        .form-select-field:focus,
        .form-textarea-field:focus {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
            outline: none;
        }

        .form-input-field:hover,
        .form-select-field:hover,
        .form-textarea-field:hover {
            border-color: #ef4444;
        }

        .btn-primary {
            transition: background-color 0.2s, box-shadow 0.2s;
        }

        .btn-primary:hover {
            background-color: #dc2626;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .btn-secondary {
            transition: background-color 0.2s, color 0.2s, border-color 0.2s;
        }

        .btn-secondary:hover {
            background-color: #fef2f2;
            color: #b91c1c;
            border-color: #ef4444;
        }

        .action-link {
            transition: color 0.2s;
        }
        .action-link:hover {
            color: #ef4444;
        }
        .action-link.text-blue-600:hover {
            color: #2563eb;
        }
        .action-link.text-red-600:hover {
            color: #dc2626;
        }
        .action-link.text-yellow-600:hover {
            color: #ca8a04;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }

        .toast {
            background-color: #333;
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            opacity: 0;
            transform: translateY(-20px);
            animation: slideIn 0.5s forwards, fadeOut 0.5s 2.5s forwards;
            pointer-events: all;
            min-width: 250px;
            text-align: center;
        }

        .toast.success {
            background-color: #10b981;
        }

        .toast.error {
            background-color: #ef4444;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
    </style>
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
                        <a href="../products/products.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item">
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
                        <a href="promotions.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item active">
                            <i data-lucide="percent" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Promotions
                        </a>
                        <?php if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['owner', 'admin'])): ?>
                            <a href="../users/user.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item">
                                <i data-lucide="settings" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                                Manage Users
                            </a>
                        <?php endif; ?>
                        <a href="../../logout.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item">
                            <i data-lucide="log-out" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Logout
                        </a>
                    </nav>
                    <div class="mt-auto">
                        <div class="p-4 mt-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <img class="w-10 h-10 rounded-full" src="https://ui-avatars.com/api/?name=<?= urlencode($userName) ?>&background=ef4444&color=fff" alt="Profile">
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium"><?= $userName ?></p>
                                    <p class="text-xs text-gray-500"><?= $userRole ?></p>
                                </div>
                            </div>
                        </div>
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
                        <h1 class="text-2xl font-bold text-gray-900">Manage Promotions</h1>
                        <p class="text-gray-500">Create and oversee discounts for your gym services.</p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6">
                    <h2 class="text-lg font-medium text-gray-800 mb-4"><?= $editData ? 'Edit Promotion' : 'Add New Promotion' ?></h2>
                    <form method="post" action="promotions.php" class="space-y-4">
                        <input type="hidden" name="id" value="<?= $editData['id'] ?? '' ?>">

                        <div>
                            <label for="promo_name" class="block text-sm font-medium text-gray-700 mb-1">Promotion Name</label>
                            <input type="text" name="name" id="promo_name" value="<?= htmlspecialchars($editData['name'] ?? '') ?>" required
                                class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="discount_type" class="block text-sm font-medium text-gray-700 mb-1">Discount Type</label>
                            <select name="discount_type" id="discount_type" required
                                class="form-select-field mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                                <option value="flat" <?= ($editData['discount_type'] ?? '') === 'flat' ? 'selected' : '' ?>>Flat (Rp)</option>
                                <option value="percent" <?= ($editData['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Percent (%)</option>
                            </select>
                        </div>

                        <div>
                            <label for="discount_value" class="block text-sm font-medium text-gray-700 mb-1">Discount Value</label>
                            <input type="number" name="discount_value" id="discount_value" min="0" step="1" value="<?= htmlspecialchars($editData['discount_value'] ?? '') ?>" required
                                class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($editData['start_date'] ?? date('Y-m-d')) ?>" required
                                class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($editData['end_date'] ?? date('Y-m-d', strtotime('+1 month'))) ?>" required
                                class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                        </div>
                        
                        <div class="flex items-center space-x-3 pt-4">
                            <button type="submit" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <i data-lucide="<?= $editData ? 'save' : 'plus-circle' ?>" class="w-4 h-4 mr-2"></i> <?= $editData ? 'Update' : 'Add' ?> Promotion
                            </button>
                            <?php if ($editData): ?>
                                <a href="promotions.php" class="btn-secondary inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    <i data-lucide="x-circle" class="w-4 h-4 mr-2"></i> Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h2 class="text-lg font-medium text-gray-800 mb-4">Active Promotions List</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($promos_query->num_rows > 0): ?>
                                    <?php while ($row = $promos_query->fetch_assoc()): ?>
                                        <tr class="table-row-hover" id="promo-<?= $row['id'] ?>">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($row['name']) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= ucfirst($row['discount_type']) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                <?= $row['discount_type'] === 'percent'
                                                    ? htmlspecialchars($row['discount_value']) . '%'
                                                    : 'Rp' . number_format($row['discount_value'], 0, ',', '.') ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('d M Y', strtotime($row['start_date'])) ?> - <?= date('d M Y', strtotime($row['end_date'])) ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-medium">
                                                <a href="?edit=<?= $row['id'] ?>" class="action-link text-blue-600 hover:text-blue-900 mr-3">
                                                    <i data-lucide="edit" class="w-4 h-4 inline-block align-text-bottom"></i> Edit
                                                </a>
                                                <button onclick="confirmDelete(<?= $row['id'] ?>)" class="action-link text-red-600 hover:text-red-900">
                                                    <i data-lucide="trash-2" class="w-4 h-4 inline-block align-text-bottom"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-center">No promotions found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
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

        // --- Confirmation Functions ---
        function confirmDelete(id) {
            if (confirm(`Are you sure you want to delete this promotion (ID: ${id})? This action cannot be undone.`)) {
                window.location.href = `promotions.php?delete=${id}`;
            }
        }

        // --- Toast Notifications ---
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

        // Display PHP session messages on page load
        <?php if ($success_message): ?>
            showToast('<?= $success_message ?>', 'success');
        <?php endif; ?>
        <?php if ($error_message): ?>
            showToast('<?= $error_message ?>', 'error');
        <?php endif; ?>
    </script>
</body>

</html>