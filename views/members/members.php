<?php
session_start();
require_once '../../config/database.php';
require_once '../../models/Transaction.php';

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['owner', 'admin'])) {
    header('Location: ../../login.php');
    exit;
}

$trxModel = new Transaction($conn);

$today = date('Y-m-d');
$conn->query("UPDATE members SET status = 'inactive' WHERE expired_date < '$today' AND status = 'active'");

$packages_result = $conn->query("SELECT * FROM membership_packages ORDER BY name ASC");
$packages = [];
while ($row = $packages_result->fetch_assoc()) {
    $packages[] = $row;
}

$promos_result = $conn->query("
    SELECT promotions.*, mp.name AS package_name
    FROM promotions
    LEFT JOIN membership_packages mp ON promotions.package_id = mp.id
    WHERE promotions.start_date <= CURDATE() AND promotions.end_date >= CURDATE()
    ORDER BY promotions.name ASC
");
$promos = [];
while ($row = $promos_result->fetch_assoc()) {
    $promos[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type = $_POST['action_type'] ?? 'add_edit';
    $user_id = $_SESSION['user_id'];

    if ($action_type === 'extend') {
        $member_id_to_extend = (int)($_POST['member_id_extend'] ?? 0);
        $package_id_extend = (int)($_POST['package_id_extend'] ?? 0);
        $promo_id_extend = !empty($_POST['promo_id_extend']) ? (int)$_POST['promo_id_extend'] : null;

        if (empty($member_id_to_extend) || empty($package_id_extend)) {
            $_SESSION['error_message'] = "Missing required fields for extension. Please select a member and a package.";
            header("Location: members.php");
            exit;
        }

        $memberStmt = $conn->prepare("SELECT expired_date, full_name FROM members WHERE id = ?");
        $memberStmt->bind_param("i", $member_id_to_extend);
        $memberStmt->execute();
        $current_member = $memberStmt->get_result()->fetch_assoc();
        if (!$current_member) {
            $_SESSION['error_message'] = "Member not found for extension.";
            header("Location: members.php");
            exit;
        }
        $member_name = $current_member['full_name'];

        $pkg = null;
        foreach ($packages as $p) {
            if ($p['id'] == $package_id_extend) {
                $pkg = $p;
                break;
            }
        }
        if (!$pkg) {
            $_SESSION['error_message'] = "Invalid package selected for extension.";
            header("Location: members.php");
            exit;
        }
        $duration = $pkg['duration_months'];
        $base_price = (float) $pkg['price'];
        $package_name = $pkg['name'];

        $discount = 0;
        if (!empty($promo_id_extend)) {
            $promo_data = null;
            foreach ($promos as $pr) {
                if ($pr['id'] == $promo_id_extend) {
                    $promo_data = $pr;
                    break;
                }
            }

            if ($promo_data) {
                $type = strtolower(trim($promo_data['discount_type']));
                $value = (float) preg_replace('/[^0-9.]/', '', $promo_data['discount_value']);

                if (in_array($type, ['flat', 'rp', 'nominal', 'uang'])) {
                    $discount = $value;
                } elseif (in_array($type, ['percent', 'persen', '%'])) {
                    $discount = ($base_price * $value) / 100;
                }
            }
        }
        $final_price = max(0, $base_price - $discount);

        $current_expired_date = $current_member['expired_date'];
        $start_date_for_extension = (strtotime($current_expired_date) < strtotime($today)) ? $today : $current_expired_date;
        $new_expired_date = date('Y-m-d', strtotime("+$duration months", strtotime($start_date_for_extension)));

        $updateMemberStmt = $conn->prepare("UPDATE members SET expired_date = ?, package_id = ?, promo_id = ?, status = 'active' WHERE id = ?");
        $updateMemberStmt->bind_param("siii", $new_expired_date, $package_id_extend, $promo_id_extend, $member_id_to_extend);

        if ($updateMemberStmt->execute()) {
            $trxModel->create([
                'transaction_type' => 'member_extend',
                'label' => 'income',
                'description' => "Membership extension for: " . $member_name . " (Package: " . $package_name . ")",
                'amount' => $base_price,
                'discount' => $discount,
                'final_amount' => $final_price,
                'member_id' => $member_id_to_extend,
                'product_id' => null,
                'user_id' => $user_id
            ]);
            $_SESSION['success_message'] = "Member membership extended successfully!";
        } else {
            $_SESSION['error_message'] = "Error extending member membership: " . $conn->error;
        }
    } else { // add_edit
        $id = $_POST['id'] ?? '';
        $full_name = htmlspecialchars(trim($_POST['full_name']));
        $phone = htmlspecialchars(trim($_POST['phone']));
        $address = htmlspecialchars(trim($_POST['address']));
        $email = !empty($_POST['email']) ? htmlspecialchars(trim($_POST['email'])) : null;
        $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
        $package_id = (int)$_POST['package_id_main'];
        $promo_id = !empty($_POST['promo_id_main']) ? (int)$_POST['promo_id_main'] : null;

        if (empty($full_name) || empty($phone) || empty($address) || empty($package_id)) {
            $_SESSION['error_message'] = "Please fill all required fields for Add/Edit.";
            header("Location: members.php");
            exit;
        }

        $base_price = 0;
        $discount = 0;
        if (empty($id)) {
             $pkg_data = null;
             foreach ($packages as $p) {
                if ($p['id'] == $package_id) {
                    $pkg_data = $p;
                    break;
                }
             }
             if($pkg_data) {
                $base_price = (float) $pkg_data['price'];
             }

             if (!empty($promo_id)) {
                 $promo_data = null;
                 foreach ($promos as $pr) {
                    if ($pr['id'] == $promo_id) {
                        $promo_data = $pr;
                        break;
                    }
                 }
                 if ($promo_data) {
                     $type = strtolower(trim($promo_data['discount_type']));
                     $value = (float) preg_replace('/[^0-9.]/', '', $promo_data['discount_value']);
                     if (in_array($type, ['flat', 'rp', 'nominal', 'uang'])) {
                         $discount = $value;
                     } elseif (in_array($type, ['percent', 'persen', '%'])) {
                         $discount = ($base_price * $value) / 100;
                     }
                 }
             }
        }
        $final_price = max(0, $base_price - $discount);

        if ($id) {
            $stmt = $conn->prepare("UPDATE members SET full_name=?, phone=?, address=?, email=?, age=?, package_id=?, promo_id=? WHERE id=?");
            $stmt->bind_param("ssssiiii", $full_name, $phone, $address, $email, $age, $package_id, $promo_id, $id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Member updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating member: " . $conn->error;
            }
        } else {
            $join_date_new = date('Y-m-d');
            $pkg_new_member = null;
            foreach ($packages as $p) {
                if ($p['id'] == $package_id) {
                    $pkg_new_member = $p;
                    break;
                }
            }
            $duration_new_member = $pkg_new_member['duration_months'];
            $expired_date_new = date('Y-m-d', strtotime("+$duration_new_member months", strtotime($join_date_new)));
            $status_new = 'active';

            $stmt = $conn->prepare("INSERT INTO members (full_name, phone, address, email, age, join_date, expired_date, package_id, promo_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssiissis", $full_name, $phone, $address, $email, $age, $join_date_new, $expired_date_new, $package_id, $promo_id, $status_new);
            if ($stmt->execute()) {
                $member_id = $conn->insert_id;

                $trxModel->create([
                    'transaction_type' => 'member_new',
                    'label' => 'income',
                    'description' => "New member registration: " . $full_name,
                    'amount' => $base_price,
                    'discount' => $discount,
                    'final_amount' => $final_price,
                    'member_id' => $member_id,
                    'product_id' => null,
                    'user_id' => $user_id
                ]);
                $_SESSION['success_message'] = "Member added successfully!";
            } else {
                $_SESSION['error_message'] = "Error adding member: " . $conn->error;
            }
        }
    }

    header("Location: members.php");
    exit;
}

$editData = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $editData = $res->fetch_assoc();
    echo "<script>document.addEventListener('DOMContentLoaded', () => openModal('edit', " . json_encode($editData) . "));</script>";
}

// Search and Filter Logic
$searchTerm = $_GET['search'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$sql = "SELECT m.*, p.name AS package_name
        FROM members m
        JOIN membership_packages p ON m.package_id = p.id
        WHERE 1=1";

$params = [];
$types = "";

if (!empty($searchTerm)) {
    $sql .= " AND (m.full_name LIKE ? OR m.phone LIKE ? OR m.email LIKE ?)";
    $params[] = '%' . $searchTerm . '%';
    $params[] = '%' . $searchTerm . '%';
    $params[] = '%' . $searchTerm . '%';
    $types .= "sss";
}

if (!empty($filterStatus)) {
    $sql .= " AND m.status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

$sql .= " ORDER BY m.id DESC";

$members_stmt = $conn->prepare($sql);

if (!empty($params)) {
    $members_stmt->bind_param($types, ...$params);
}
$members_stmt->execute();
$members_result = $members_stmt->get_result();

$members = [];
while($row = $members_result->fetch_assoc()){
    $members[] = $row;
}

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
    <title>Manage Members - Gym Dashboard</title>
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
        .form-select-field:focus {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
            outline: none;
        }

        .form-input-field:hover,
        .form-select-field:hover {
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

        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .status-pending {
            background-color: #fef9c3;
            color: #854d09;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 24px;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            width: 90%;
            max-width: 600px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .close-button {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
            transition: color 0.2s;
        }

        .close-button:hover,
        .close-button:focus {
            color: #ef4444;
            text-decoration: none;
            cursor: pointer;
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
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
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
                        <a href="members.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item active">
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
                                <input type="text" name="search" placeholder="Search by name, phone, or email..." value="<?= htmlspecialchars($searchTerm) ?>"
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
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto justify-end">
                            <button type="submit" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 w-full sm:w-auto">
                                <i data-lucide="filter" class="w-4 h-4 mr-2"></i>
                                Apply Filters
                            </button>
                            <?php if (!empty($searchTerm) || !empty($filterStatus)): ?>
                                <a href="members.php" class="btn-secondary inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 w-full sm:w-auto">
                                    Clear Filters
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
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
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
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= $row['id'] ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['full_name']) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['phone']) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['email'] ?? '-') ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['package_name']) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y', strtotime($row['join_date'])) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y', strtotime($row['expired_date'])) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    <?php
                                                    if ($row['status'] === 'active') echo 'status-active';
                                                    else if ($row['status'] === 'inactive') echo 'status-inactive';
                                                    else echo 'status-pending';
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

        const full_name_modal = document.getElementById('full_name_modal');
        const phone_modal = document.getElementById('phone_modal');
        const address_modal = document.getElementById('address_modal');
        const email_modal = document.getElementById('email_modal');
        const age_modal = document.getElementById('age_modal');
        const package_id_main_modal = document.getElementById('package_id_main_modal');
        const promo_id_main_modal = document.getElementById('promo_id_main_modal');

        const package_id_extend = document.getElementById('package_id_extend');
        const promo_id_extend = document.getElementById('promo_id_extend');

        const submitButton = document.getElementById('submitButton');
        const toastContainer = document.getElementById('toastContainer');

        function openModal(mode, memberData = null) {
            memberForm.reset();
            memberId.value = '';
            memberIdExtend.value = '';
            memberForm.querySelectorAll('[required]').forEach(el => el.removeAttribute('required'));

            addEditFields.classList.add('hidden');
            extendFields.classList.add('hidden');

            if (mode === 'add') {
                modalTitle.textContent = 'Add New Member';
                actionType.value = 'add_edit';
                submitButton.innerHTML = '<i data-lucide="plus-circle" class="w-4 h-4 mr-2"></i> Add Member';

                addEditFields.classList.remove('hidden');

                full_name_modal.setAttribute('required', '');
                phone_modal.setAttribute('required', '');
                address_modal.setAttribute('required', '');
                package_id_main_modal.setAttribute('required', '');

            } else if (mode === 'edit' && memberData) {
                modalTitle.textContent = 'Edit Member';
                actionType.value = 'add_edit';
                submitButton.innerHTML = '<i data-lucide="save" class="w-4 h-4 mr-2"></i> Update Member';
                memberId.value = memberData.id;

                addEditFields.classList.remove('hidden');

                full_name_modal.setAttribute('required', '');
                phone_modal.setAttribute('required', '');
                address_modal.setAttribute('required', '');
                package_id_main_modal.setAttribute('required', '');

                full_name_modal.value = memberData.full_name;
                phone_modal.value = memberData.phone;
                address_modal.value = memberData.address;
                email_modal.value = memberData.email;
                age_modal.value = memberData.age;
                package_id_main_modal.value = memberData.package_id;
                promo_id_main_modal.value = memberData.promo_id || '';

            } else if (mode === 'extend' && memberData) {
                modalTitle.textContent = 'Extend Membership for ' + memberData.full_name;
                actionType.value = 'extend';
                submitButton.innerHTML = '<i data-lucide="calendar-plus" class="w-4 h-4 mr-2"></i> Extend Membership';
                memberIdExtend.value = memberData.id;

                extendFields.classList.remove('hidden');

                package_id_extend.setAttribute('required', '');

                currentExpiredDateSpan.textContent = new Date(memberData.expired_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

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