<?php
session_start();
require_once '../../config/database.php';
require_once '../../controllers/AttendanceController.php';

// Redirect user yang tidak berwenang
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['owner', 'admin', 'staff'])) { // Sesuaikan role yang diizinkan
    header('Location: ../../login.php');
    exit;
}

$attendanceController = new AttendanceController($conn);
$user_id = $_SESSION['user_id'] ?? 1; // Default ke 1 jika tidak ada user_id di session (untuk testing, pastikan di produksi ini selalu ada)
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Unknown');
$userRole = htmlspecialchars(ucfirst($_SESSION['user_role'] ?? '-'));

// Ambil pesan sukses/error dari session untuk ditampilkan sebagai toast
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Proses presensi masuk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'])) {
    $member_id = (int)$_POST['member_id'];
    if ($attendanceController->record($member_id, $user_id)) {
        $_SESSION['success_message'] = "Attendance recorded successfully for member ID: " . $member_id;
    } else {
        $_SESSION['error_message'] = "Error recording attendance or member already checked in today.";
    }
    header("Location: attendance.php");
    exit;
}

// Ambil semua member aktif untuk dropdown
$members_query = $conn->query("SELECT id, full_name FROM members WHERE status = 'active' ORDER BY full_name ASC");
$members = $members_query->fetch_all(MYSQLI_ASSOC);

// Filter berdasarkan tanggal (opsional)
$selected_date = $_GET['date'] ?? date('Y-m-d');
// Validasi format tanggal sederhana
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $selected_date)) {
    $selected_date = date('Y-m-d'); // Default to today if invalid
}

$attendances = $attendanceController->byDate($selected_date);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendance - Gym Dashboard</title>
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
                        <a href="attendance.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item active">
                            <i data-lucide="calendar-check" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Attendance
                        </a>
                        <a href="../promotions/promotions.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item">
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
                        <h1 class="text-2xl font-bold text-gray-900">Manage Attendance</h1>
                        <p class="text-gray-500">Record member check-ins and view attendance history.</p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6">
                    <h2 class="text-lg font-medium text-gray-800 mb-4">Record Member Check-in</h2>
                    <form method="post" action="attendance.php" class="space-y-4">
                        <div>
                            <label for="member_id" class="block text-sm font-medium text-gray-700 mb-1">Select Member to Check-in Today:</label>
                            <select name="member_id" id="member_id" required
                                class="form-select-field mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
                                <option value="">-- Select Member --</option>
                                <?php foreach ($members as $m) : ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <i data-lucide="user-check" class="w-4 h-4 mr-2"></i> Record Attendance
                        </button>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-4">
                        <h2 class="text-lg font-medium text-gray-800">Attendance Data for <?= date('d M Y', strtotime($selected_date)) ?></h2>
                        <form method="get" action="attendance.php" class="flex items-center gap-2">
                            <label for="attendance_date" class="block text-sm font-medium text-gray-700 sr-only">View Attendance Date:</label>
                            <input type="date" name="date" id="attendance_date" value="<?= $selected_date ?>"
                                class="form-input-field block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                            <button type="submit" class="btn-secondary inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <i data-lucide="calendar" class="w-4 h-4 mr-2"></i> View
                            </button>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-in Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($attendances->num_rows > 0): ?>
                                    <?php while ($row = $attendances->fetch_assoc()): ?>
                                        <tr class="table-row-hover">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($row['full_name']) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= date('H:i:s, d M Y', strtotime($row['checkin_time'])) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['admin_name'] ?? '-') ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-center">No attendance recorded for this date.</td>
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