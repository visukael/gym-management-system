<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/Transaction.php';

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['owner', 'admin'])) {
    header('Location: ../../login.php');
    exit;
}

$trxModel = new Transaction($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'add_manual_transaction') {
    $label = htmlspecialchars(trim($_POST['label']));
    $description = htmlspecialchars(trim($_POST['description']));
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = htmlspecialchars(trim($_POST['payment_method'] ?? 'cash'));
    $user_id = $_SESSION['user_id'];

    if (empty($label) || empty($description) || $amount <= 0) {
        $_SESSION['error_message'] = "Please fill all required fields for manual transaction (Label, Description, Amount).";
        header("Location: transactions.php");
        exit;
    }

    $result = $trxModel->create([
        'transaction_type' => 'manual',
        'label' => $label,
        'description' => $description,
        'amount' => $amount,
        'discount' => 0,
        'final_amount' => $amount,
        'member_id' => null,
        'product_id' => null,
        'user_id' => $user_id,
        'payment_method' => $payment_method
    ]);

    if ($result) {
        $_SESSION['success_message'] = "Manual transaction added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding manual transaction.";
    }
    header("Location: transactions.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($trxModel->delete($id)) {
        $_SESSION['success_message'] = "Transaction deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting transaction. It might be linked to other data.";
    }
    header("Location: transactions.php");
    exit;
}

$searchTerm = $_GET['search'] ?? '';
$filterType = $_GET['type'] ?? '';
$filterLabel = $_GET['label'] ?? '';
$sortBy = $_GET['sort_by'] ?? '';
$filterPaymentMethod = $_GET['payment_method'] ?? '';

$recordsPerPage = 50;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;
$offset = ($currentPage - 1) * $recordsPerPage;

$filters = [
    'search' => $searchTerm,
    'type' => $filterType,
    'label' => $filterLabel,
    'sortBy' => $sortBy,
    'payment_method' => $filterPaymentMethod
];

$transactions = $trxModel->getFilteredTransactions($filters, $recordsPerPage, $offset);
$totalRecords = $trxModel->countFilteredTransactions($filters);
$totalPages = ceil($totalRecords / $recordsPerPage);

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Unknown');
$userRole = htmlspecialchars(ucfirst($_SESSION['user_role'] ?? '-'));

$userRoleForAccess = $_SESSION['user_role'] ?? '';
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Unknown');
$userRoleDisplay = htmlspecialchars(ucfirst($userRoleForAccess));

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
    <title>Manage Transactions - Gym Dashboard</title>
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
                        <a href="../dashboard.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : '' ?>">
                            <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Dashboard
                        </a>
                        <a href="../members/members.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'members.php') ? 'active' : '' ?>">
                            <i data-lucide="users" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Members
                        </a>
                        <a href="../products/products.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'products.php') ? 'active' : '' ?>">
                            <i data-lucide="shopping-bag" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Products
                        </a>
                        <a href="../transactions/transactions.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'transactions.php') ? 'active' : '' ?>">
                            <i data-lucide="credit-card" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Transactions
                        </a>
                        <a href="../attendance/attendance.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'attendance.php') ? 'active' : '' ?>">
                            <i data-lucide="calendar-check" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Attendance
                        </a>
                        <a href="../promotions/promotions.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'promotions.php') ? 'active' : '' ?>">
                            <i data-lucide="percent" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Promotions
                        </a>

                        <?php if ($userRoleForAccess === 'owner'): ?>
                            <a href="user.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'user.php') ? 'active' : '' ?>">
                                <i data-lucide="settings" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                                Manage Users
                            </a>
                        <?php endif; ?>

                        <a href="../logout.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item">
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
                                <p class="text-xs text-gray-500"><?= $userRoleDisplay ?></p>
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
                        <h1 class="text-2xl font-bold text-gray-900">Transaction History</h1>
                        <p class="text-gray-500">View and manage all financial transactions.</p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6">
                    <form method="GET" action="transactions.php" class="flex flex-col lg:flex-row gap-4 items-center">
                        <div class="flex flex-col sm:flex-row gap-4 flex-grow">
                            <div class="relative flex-grow">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="search" class="w-5 h-5 text-gray-400"></i>
                                </div>
                                <input type="text" name="search" placeholder="Search description..." value="<?= htmlspecialchars($searchTerm) ?>"
                                    class="form-input-field pl-10 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                            </div>
                            <div class="w-full sm:w-auto">
                                <select name="type"
                                    class="form-select-field block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                                    <option value="">All Types</option>
                                    <option value="manual" <?= $filterType === 'manual' ? 'selected' : '' ?>>Manual</option>
                                    <option value="member_new" <?= $filterType === 'member_new' ? 'selected' : '' ?>>New Member</option>
                                    <option value="member_extend" <?= $filterType === 'member_extend' ? 'selected' : '' ?>>Member Extend</option>
                                    <option value="product_sale" <?= $filterType === 'product_sale' ? 'selected' : '' ?>>Product Sale</option>
                                    <option value="stock_add" <?= $filterType === 'stock_add' ? 'selected' : '' ?>>Stock Add</option>
                                    <option value="missing_item" <?= $filterType === 'missing_item' ? 'selected' : '' ?>>Missing Item</option>
                                </select>
                            </div>
                            <div class="w-full sm:w-auto">
                                <select name="label"
                                    class="form-select-field block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                                    <option value="">All Labels</option>
                                    <option value="income" <?= $filterLabel === 'income' ? 'selected' : '' ?>>Income</option>
                                    <option value="outcome" <?= $filterLabel === 'outcome' ? 'selected' : '' ?>>Outcome</option>
                                </select>
                            </div>
                            <div class="w-full sm:w-auto">
                                <select name="sort_by"
                                    class="form-select-field block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                                    <option value="">All Time</option>
                                    <option value="today" <?= $sortBy === 'today' ? 'selected' : '' ?>>Today</option>
                                    <option value="this_week" <?= $sortBy === 'this_week' ? 'selected' : '' ?>>This Week</option>
                                    <option value="this_month" <?= $sortBy === 'this_month' ? 'selected' : '' ?>>This Month</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto justify-end">
                            <button type="submit" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 w-full sm:w-auto">
                                <i data-lucide="filter" class="w-4 h-4 mr-2"></i>
                                Apply Filters
                            </button>
                            <?php if (!empty($searchTerm) || !empty($filterType) || !empty($filterLabel) || !empty($sortBy)): ?>
                                <a href="transactions.php" class="btn-secondary inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 w-full sm:w-auto">
                                    Clear Filters
                                </a>
                            <?php endif; ?>
                            <button onclick="openManualTransactionModal()" type="button" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 w-full sm:w-auto">
                                <i data-lucide="plus-circle" class="w-4 h-4 mr-2"></i>
                                Add Manual Transaction
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h2 class="text-lg font-medium text-gray-800 mb-4">Transaction List</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Label</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Original Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Final Amount</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($transactions) > 0): ?>
                                    <?php foreach ($transactions as $row): ?>
                                        <tr class="table-row-hover" id="transaction-<?= $row['id'] ?>">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= $row['id'] ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y H:i', strtotime($row['created_at'])) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?= htmlspecialchars(str_replace('_', ' ', ucfirst($row['transaction_type']))) ?>
                                            </td>
                                            <td class="px-4 py-3 max-w-xs truncate text-sm text-gray-500" title="<?= htmlspecialchars($row['description']) ?>">
                                                <?= htmlspecialchars($row['description']) ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <span class="label-badge <?= $row['label'] === 'income' ? 'label-income' : 'label-outcome' ?>">
                                                    <?= strtoupper($row['label']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?= htmlspecialchars(ucfirst($row['payment_method'] ?? 'N/A')) ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">Rp<?= number_format($row['amount'], 0, ',', '.') ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">Rp<?= number_format($row['discount'], 0, ',', '.') ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold">Rp<?= number_format($row['final_amount'], 0, ',', '.') ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-medium">
                                                <button onclick="confirmDelete(<?= $row['id'] ?>)" class="action-link text-gray-600 hover:text-gray-900">
                                                    <i data-lucide="trash-2" class="w-4 h-4 inline-block align-text-bottom"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-center">No transactions found matching your criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-between items-center mt-6">
                        <span class="text-sm text-gray-700">
                            Showing <?= count($transactions) ?> of <?= $totalRecords ?> transactions
                        </span>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($currentPage > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage - 1])) ?>"
                                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <i data-lucide="chevron-left" class="h-5 w-5"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                                    class="<?= $i === $currentPage ? 'z-10 bg-red-50 border-red-500 text-red-600' : 'bg-white border-gray-300 text-gray-700' ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium hover:bg-gray-50">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage + 1])) ?>"
                                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <i data-lucide="chevron-right" class="h-5 w-5"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="manualTransactionModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal('manualTransactionModal')">&times;</span>
            <h2 class="text-lg font-medium text-gray-800 mb-4">Add Manual Transaction</h2>
            <form id="manualTransactionForm" method="post" class="space-y-4">
                <input type="hidden" name="action_type" value="add_manual_transaction">

                <div>
                    <label for="manual_label" class="block text-sm font-medium text-gray-700 mb-1">Label</label>
                    <select name="label" id="manual_label" required
                        class="form-select-field mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                        <option value="income">Income</option>
                        <option value="outcome">Outcome</option>
                    </select>
                </div>

                <div>
                    <label for="manual_description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="manual_description" rows="3" required
                        class="form-textarea-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm"></textarea>
                </div>

                <div>
                    <label for="manual_amount" class="block text-sm font-medium text-gray-700 mb-1">Amount (Rp)</label>
                    <input type="number" name="amount" id="manual_amount" required min="100" step="100"
                        class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                </div>

                <div>
                    <label for="manual_payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                    <select name="payment_method" id="manual_payment_method" required
                        class="form-select-field mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                        <option value="cash">Cash</option>
                        <option value="qr">QR</option>
                    </select>
                </div>

                <div class="flex items-center space-x-3 mt-6">
                    <button type="submit" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i data-lucide="plus-circle" class="w-4 h-4 mr-2"></i> Add Transaction
                    </button>
                    <button type="button" onclick="closeModal('manualTransactionModal')" class="btn-secondary inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
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

        const manualTransactionModal = document.getElementById('manualTransactionModal');

        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target === manualTransactionModal) closeModal('manualTransactionModal');
        }

        const manualTransactionForm = document.getElementById('manualTransactionForm');

        function openManualTransactionModal() {
            manualTransactionForm.reset();
            document.getElementById('manual_label').setAttribute('required', '');
            document.getElementById('manual_description').setAttribute('required', '');
            document.getElementById('manual_amount').setAttribute('required', '');
            document.getElementById('manual_payment_method').setAttribute('required', '');
            openModal('manualTransactionModal');
        }

        function confirmDelete(id) {
            if (confirm(`Are you sure you want to delete this transaction (ID: ${id})? This action cannot be undone.`)) {
                window.location.href = `transactions.php?delete=${id}`;
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