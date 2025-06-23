<?php
session_start();
require_once '../../config/database.php';
require_once '../../controllers/MemberController.php';

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['owner', 'admin'])) {
    header('Location: ../../login.php');
    exit;
}

$memberController = new MemberController($conn);
$user_id = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'admin';

if (isset($_GET['get_smallest_member_code'])) {
    $code = $memberController->getSmallestAvailableMemberCode();
    echo json_encode(['member_code' => $code]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type = $_POST['action_type'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action.'];

    if ($action_type === 'extend') {
        $response = $memberController->handleExtendMember($_POST, $user_id);
    } elseif ($action_type === 'add_edit') {
        $memberId = $_POST['id'] ?? '';
        if (!empty($memberId)) {
            $response = $memberController->handleUpdateMember((int)$memberId, $_POST);
        } else {
            $response = $memberController->handleAddMember($_POST, $user_id);
        }
    }

    if ($response['success']) {
        $_SESSION['success_message'] = $response['message'];
    } else {
        $_SESSION['error_message'] = $response['message'];
    }
    header("Location: members.php");
    exit;
}

if (isset($_GET['delete'])) {
    $response = $memberController->handleDeleteMember((int)$_GET['delete']);
    if ($response['success']) {
        $_SESSION['success_message'] = $response['message'];
    } else {
        $_SESSION['error_message'] = $response['message'];
    }
    header("Location: members.php");
    exit;
}

$viewData = $memberController->getMemberViewData($_GET);

$userRoleForAccess = $_SESSION['user_role'] ?? ''; // Tambahkan atau pastikan variabel ini ada
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Unknown');
$userRoleDisplay = htmlspecialchars(ucfirst($userRoleForAccess));

$members = $viewData['members'];
$packages = $viewData['packages'];
$promos = $viewData['promos'];
$editData = $viewData['editData'];
$searchTerm = $viewData['searchTerm'];
$filterStatus = $viewData['filterStatus'];
$orderBy = $viewData['orderBy'];
$currentPage = $viewData['currentPage'];
$totalPages = $viewData['totalPages'];
$totalMembers = $viewData['totalMembers'];

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Unknown');

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
    <title>Manage Members - Gym Dashboard</title>
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
                        <h1 class="text-2xl font-bold text-gray-900">Member Management</h1>
                        <p class="text-gray-500">Add, edit, or extend gym members.</p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6">
                    <form method="GET" action="members.php" class="flex flex-col lg:flex-row gap-4 items-center">
                        <div class="flex flex-col sm:flex-row gap-4 flex-grow">
                            <div class="relative flex-grow">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="search" class="w-5 h-5 text-gray-400"></i>
                                </div>
                                <input type="text" name="search" placeholder="Search by name or member code..." value="<?= htmlspecialchars($searchTerm) ?>"
                                    class="form-input-field pl-10 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                            </div>
                            <div class="w-full sm:w-auto">
                                <select name="status"
                                    class="form-select-field block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                                </select>
                            </div>
                            <div class="w-full sm:w-auto">
                                <select name="order_by"
                                    class="form-select-field block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                                    <option value="member_code_asc" <?= $orderBy === 'member_code_asc' ? 'selected' : '' ?>>Member Code (Asc)</option>
                                    <option value="member_code_desc" <?= $orderBy === 'member_code_desc' ? 'selected' : '' ?>>Member Code (Desc)</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto justify-end">
                            <button type="submit" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 w-full sm:w-auto">
                                <i data-lucide="filter" class="w-4 h-4 mr-2"></i>
                                Apply Filters
                            </button>
                            <?php if (!empty($searchTerm) || !empty($filterStatus) || $orderBy !== 'member_code_asc'): ?>
                                <a href="members.php" class="btn-secondary inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 w-full sm:w-auto">
                                    <i data-lucide="x-circle" class="w-4 h-4 mr-2"></i> Clear Filters
                                </a>
                            <?php endif; ?>
                            <button onclick="openModal('add')" type="button" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 w-full sm:w-auto">
                                <i data-lucide="plus-circle" class="w-4 h-4 mr-2"></i>
                                Add New Member
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h2 class="text-lg font-medium text-gray-800 mb-4">Member List</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member Code</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Package</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Join Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expired Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($members) > 0): ?>
                                    <?php foreach ($members as $row): ?>
                                        <tr class="table-row-hover" id="member-<?= $row['id'] ?>">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['member_code']) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['full_name']) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['phone']) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['email'] ?? '-') ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['package_name']) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y', strtotime($row['join_date'])) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y', strtotime($row['expired_date'])) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    <?php
                                                    if ($row['status'] === 'active') {
                                                        echo 'bg-green-100 text-green-800';
                                                    } else if ($row['status'] === 'inactive') {
                                                        echo 'bg-red-100 text-red-800';
                                                    } else {
                                                        echo 'bg-yellow-100 text-yellow-800';
                                                    }
                                                    ?>">
                                                    <?= ucfirst($row['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-medium">
                                                <button onclick="openModal('edit', <?= htmlspecialchars(json_encode($row)) ?>)" class="action-link text-red-600 hover:text-red-900 mr-3">
                                                    <i data-lucide="edit" class="w-4 h-4 inline-block align-text-bottom"></i> Edit
                                                </button>
                                                <button onclick="openModal('extend', <?= htmlspecialchars(json_encode($row)) ?>)" class="action-link text-blue-600 hover:text-blue-900">
                                                    <i data-lucide="calendar-plus" class="w-4 h-4 inline-block align-text-bottom"></i> Extend
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-center">No members found matching your criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-between items-center mt-6">
                        <span class="text-sm text-gray-700">
                            Showing <?= count($members) ?> of <?= $totalMembers ?> members
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

    <div id="memberModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle" class="text-lg font-medium text-gray-800 mb-4">Member Form</h2>
            <form id="memberForm" method="post" class="space-y-4">
                <input type="hidden" name="action_type" id="actionType" value="add_edit">
                <input type="hidden" name="id" id="memberId">
                <input type="hidden" name="member_id_extend" id="memberIdExtend">

                <div id="addEditFields">
                    <div>
                        <label for="member_code_modal" class="block text-sm font-medium text-gray-700 mb-1">Member Code</label>
                        <input type="text" name="member_code" id="member_code_modal"
                            class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="full_name_modal" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" name="full_name" id="full_name_modal"
                            class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="phone_modal" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="text" name="phone" id="phone_modal"
                            class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="address_modal" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <input type="text" name="address" id="address_modal"
                            class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="email_modal" class="block text-sm font-medium text-gray-700 mb-1">Email (Optional)</label>
                        <input type="email" name="email" id="email_modal"
                            class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="age_modal" class="block text-sm font-medium text-gray-700 mb-1">Age (Optional)</label>
                        <input type="number" name="age" id="age_modal"
                            class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="package_id_main_modal" class="block text-sm font-medium text-gray-700 mb-1">Membership Package</label>
                        <select name="package_id_main" id="package_id_main_modal"
                            class="form-select-field mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                            <option value="">-- Select Package --</option>
                            <?php foreach ($packages as $pkg): ?>
                                <option value="<?= $pkg['id'] ?>">
                                    <?= htmlspecialchars($pkg['name']) ?> (Rp<?= number_format($pkg['price'], 0, ',', '.') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="promo_id_main_modal" class="block text-sm font-medium text-gray-700 mb-1">Promotion (Optional)</label>
                        <select name="promo_id_main" id="promo_id_main_modal"
                            class="form-select-field mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                            <option value="">-- No Promotion --</option>
                            <?php foreach ($promos as $promo): ?>
                                <option value="<?= $promo['id'] ?>">
                                    <?= htmlspecialchars($promo['name']) ?> - <?= htmlspecialchars($promo['package_name'] ?? 'General') ?> (<?= $promo['discount_type'] === 'percent' ? $promo['discount_value'] . '%' : 'Rp' . number_format($promo['discount_value'], 0, ',', '.') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="payment_method_new_member" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select name="payment_method_new_member" id="payment_method_new_member" required
                            class="form-select-field mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                            <option value="cash">Cash</option>
                            <option value="qr">QR</option>
                        </select>
                    </div>

                    <div id="owner_date_fields" class="<?= ($_SESSION['user_role'] === 'owner') ? '' : 'hidden' ?>">
                        <div>
                            <label for="join_date_modal" class="block text-sm font-medium text-gray-700 mb-1">Join Date</label>
                            <input type="date" name="join_date_modal" id="join_date_modal"
                                class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="expired_date_modal" class="block text-sm font-medium text-gray-700 mb-1">Expired Date</label>
                            <input type="date" name="expired_date_modal" id="expired_date_modal"
                                class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                        </div>
                    </div>

                </div>

                <div id="extendFields" class="hidden">
                    <p class="text-gray-700 text-sm mb-2">Current Expired Date: <span id="currentExpiredDate" class="font-semibold text-red-600"></span></p>
                    <div>
                        <label for="package_id_extend" class="block text-sm font-medium text-gray-700 mb-1">Select New Package</label>
                        <select name="package_id_extend" id="package_id_extend"
                            class="form-select-field mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                            <option value="">-- Select Package --</option>
                            <?php foreach ($packages as $pkg): ?>
                                <option value="<?= $pkg['id'] ?>">
                                    <?= htmlspecialchars($pkg['name']) ?> (Rp<?= number_format($pkg['price'], 0, ',', '.') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="promo_id_extend" class="block text-sm font-medium text-gray-700 mb-1">Promotion (Optional)</label>
                        <select name="promo_id_extend" id="promo_id_extend"
                            class="form-select-field mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                            <option value="">-- No Promotion --</option>
                            <?php foreach ($promos as $promo): ?>
                                <option value="<?= $promo['id'] ?>">
                                    <?= htmlspecialchars($promo['name']) ?> - <?= htmlspecialchars($promo['package_name'] ?? 'General') ?> (<?= $promo['discount_type'] === 'percent' ? $promo['discount_value'] . '%' : 'Rp' . number_format($promo['discount_value'], 0, ',', '.') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="payment_method_extend" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select name="payment_method_extend" id="payment_method_extend" required
                            class="form-select-field mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                            <option value="cash">Cash</option>
                            <option value="qr">QR</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center space-x-3 mt-6">
                    <button type="submit" id="submitButton" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i data-lucide="plus-circle" class="w-4 h-4 mr-2"></i>
                        Add Member
                    </button>
                    <button type="button" onclick="closeModal()" class="btn-secondary inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i data-lucide="x-circle" class="w-4 h-4 mr-2"></i>
                        Cancel
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

        const modal = document.getElementById('memberModal');
        const modalTitle = document.getElementById('modalTitle');
        const memberForm = document.getElementById('memberForm');
        const actionType = document.getElementById('actionType');
        const memberId = document.getElementById('memberId');
        const memberIdExtend = document.getElementById('memberIdExtend');

        const addEditFields = document.getElementById('addEditFields');
        const extendFields = document.getElementById('extendFields');
        const currentExpiredDateSpan = document.getElementById('currentExpiredDate');

        const member_code_modal = document.getElementById('member_code_modal');
        const full_name_modal = document.getElementById('full_name_modal');
        const phone_modal = document.getElementById('phone_modal');
        const address_modal = document.getElementById('address_modal');
        const email_modal = document.getElementById('email_modal');
        const age_modal = document.getElementById('age_modal');
        const package_id_main_modal = document.getElementById('package_id_main_modal');
        const promo_id_main_modal = document.getElementById('promo_id_main_modal');
        const payment_method_new_member = document.getElementById('payment_method_new_member');

        const join_date_modal = document.getElementById('join_date_modal');
        const expired_date_modal = document.getElementById('expired_date_modal');
        const ownerDateFields = document.getElementById('owner_date_fields');

        const package_id_extend = document.getElementById('package_id_extend');
        const promo_id_extend = document.getElementById('promo_id_extend');
        const payment_method_extend = document.getElementById('payment_method_extend');

        const submitButton = document.getElementById('submitButton');
        const toastContainer = document.getElementById('toastContainer');

        function openModal(mode, memberData = null) {
            memberForm.reset();
            memberId.value = '';
            memberIdExtend.value = '';
            memberForm.querySelectorAll('[required]').forEach(el => el.removeAttribute('required'));

            addEditFields.classList.add('hidden');
            extendFields.classList.add('hidden');

            ownerDateFields.classList.add('hidden');
            join_date_modal.removeAttribute('required');
            expired_date_modal.removeAttribute('required');

            if (mode === 'add') {
                modalTitle.textContent = 'Add New Member';
                actionType.value = 'add_edit';
                submitButton.innerHTML = '<i data-lucide="plus-circle" class="w-4 h-4 mr-2"></i> Add Member';

                addEditFields.classList.remove('hidden');

                member_code_modal.setAttribute('required', '');
                full_name_modal.setAttribute('required', '');
                phone_modal.setAttribute('required', '');
                address_modal.setAttribute('required', '');
                package_id_main_modal.setAttribute('required', '');
                payment_method_new_member.setAttribute('required', '');

                fetch('members.php?get_smallest_member_code=true')
                    .then(response => response.json())
                    .then(data => {
                        if (data.member_code) {
                            member_code_modal.value = data.member_code;
                        } else {
                            member_code_modal.value = '';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching smallest member code:', error);
                        member_code_modal.value = '';
                    });

            } else if (mode === 'edit' && memberData) {
                modalTitle.textContent = 'Edit Member';
                actionType.value = 'add_edit';
                submitButton.innerHTML = '<i data-lucide="save" class="w-4 h-4 mr-2"></i> Update Member';
                memberId.value = memberData.id;

                addEditFields.classList.remove('hidden');

                member_code_modal.setAttribute('required', '');
                full_name_modal.setAttribute('required', '');
                phone_modal.setAttribute('required', '');
                address_modal.setAttribute('required', '');
                package_id_main_modal.setAttribute('required', '');

                member_code_modal.value = memberData.member_code;
                full_name_modal.value = memberData.full_name;
                phone_modal.value = memberData.phone;
                address_modal.value = memberData.address;
                email_modal.value = memberData.email;
                age_modal.value = memberData.age;
                package_id_main_modal.value = memberData.package_id;
                promo_id_main_modal.value = memberData.promo_id || '';

                if ('<?= $userRole ?>' === 'owner') {
                    ownerDateFields.classList.remove('hidden');
                    join_date_modal.value = memberData.join_date;
                    expired_date_modal.value = memberData.expired_date;
                }

            } else if (mode === 'extend' && memberData) {
                modalTitle.textContent = 'Extend Membership for ' + memberData.full_name;
                actionType.value = 'extend';
                submitButton.innerHTML = '<i data-lucide="calendar-plus" class="w-4 h-4 mr-2"></i> Extend Membership';
                memberIdExtend.value = memberData.id;

                extendFields.classList.remove('hidden');

                package_id_extend.setAttribute('required', '');
                payment_method_extend.setAttribute('required', '');

                currentExpiredDateSpan.textContent = new Date(memberData.expired_date).toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                });

                package_id_extend.value = memberData.package_id;
                promo_id_extend.value = memberData.promo_id || '';
            }

            modal.style.display = 'flex';
            lucide.createIcons();
        }

        function closeModal() {
            modal.style.display = 'none';
            const url = new URL(window.location.href);
            if (url.searchParams.has('edit')) {
                url.searchParams.delete('edit');
                window.history.replaceState({}, document.title, url.toString());
            }
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

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