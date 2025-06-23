<?php
session_start();
require_once '../../config/database.php';
require_once '../../controllers/AttendanceController.php';
require_once '../../models/Member.php';

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['owner', 'admin', 'staff'])) {
    header('Location: ../../login.php');
    exit;
}

$attendanceController = new AttendanceController($conn);
$user_id = $_SESSION['user_id'] ?? 1;

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

if (isset($_GET['action']) && $_GET['action'] === 'search_members' && isset($_GET['term'])) {
    header('Content-Type: application/json');
    $searchTerm = $_GET['term'];
    $membersFound = $attendanceController->searchMembersAjax($searchTerm);
    echo json_encode($membersFound);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id_input'])) {
    $member_id = (int)$_POST['member_id_input'];
    $response = $attendanceController->recordAttendance($member_id, $user_id);

    if ($response['success']) {
        $_SESSION['success_message'] = $response['message'];
    } else {
        $_SESSION['error_message'] = $response['message'];
    }
    header("Location: attendance.php");
    exit;
}

if (isset($_GET['delete'])) {
    $attendance_id_to_delete = (int)$_GET['delete'];
    $response = $attendanceController->handleDeleteAttendance($attendance_id_to_delete);

    if ($response['success']) {
        $_SESSION['success_message'] = $response['message'];
    } else {
        $_SESSION['error_message'] = $response['message'];
    }
    header("Location: attendance.php?date=" . htmlspecialchars($selected_date ?? date('Y-m-d')));
    exit;
}

$viewData = $attendanceController->getAttendanceForView($_GET);

$userRoleForAccess = $_SESSION['user_role'] ?? '';
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Unknown');
$userRoleDisplay = htmlspecialchars(ucfirst($userRoleForAccess));

$attendances = $viewData['attendances'];
$selected_date = $viewData['selectedDate'];
$currentPage = $viewData['currentPage'];
$totalPages = $viewData['totalPages'];
$totalRecords = $viewData['totalRecords'];

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Unknown');
$userRole = htmlspecialchars(ucfirst($_SESSION['user_role'] ?? '-'));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendance - Gym Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
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
                        <h1 class="text-2xl font-bold text-gray-900">Manage Attendance</h1>
                        <p class="text-gray-500">Record member check-ins and view attendance history.</p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6">
                    <h2 class="text-lg font-medium text-gray-800 mb-4">Record Member Check-in</h2>
                    <form method="post" action="attendance.php" class="space-y-4">
                        <div>
                            <label for="member_search" class="block text-sm font-medium text-gray-700 mb-1">Search Member by Name or Code:</label>
                            <input type="text" id="member_search" placeholder="Type member name or code..."
                                class="form-input-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                            <input type="hidden" name="member_id_input" id="member_id_input" required>
                        </div>
                        <button type="submit" id="recordAttendanceButton" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" disabled>
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
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member Code</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-in Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($attendances) > 0): ?>
                                    <?php foreach ($attendances as $row): ?>
                                        <tr class="table-row-hover">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($row['member_code']) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($row['full_name']) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= date('H:i:s, d M Y', strtotime($row['checkin_time'])) ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['admin_name'] ?? '-') ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-medium">
                                                <a href="attendance.php?delete=<?= $row['id'] ?>&date=<?= htmlspecialchars($selected_date) ?>"
                                                    onclick="return confirm('Are you sure you want to delete this attendance record?');"
                                                    class="action-link text-red-600 hover:text-red-900">
                                                    <i data-lucide="trash-2" class="w-4 h-4 inline-block align-text-bottom"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-center">No attendance recorded for this date.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-between items-center mt-6">
                        <span class="text-sm text-gray-700">
                            Showing <?= count($attendances) ?> of <?= $totalRecords ?> attendances
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

        $(function() {
            $("#member_search").autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: "attendance.php",
                        dataType: "json",
                        data: {
                            action: "search_members",
                            term: request.term
                        },
                        success: function(data) {
                            response($.map(data, function(item) {
                                return {
                                    label: item.member_code + " - " + item.full_name,
                                    value: item.full_name,
                                    id: item.id
                                };
                            }));
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    $("#member_id_input").val(ui.item.id);
                    $("#recordAttendanceButton").prop('disabled', false);
                    console.log("Selected member ID: " + ui.item.id);
                },
                change: function(event, ui) {
                    if (ui.item == null || $("#member_search").val().trim() === "") {
                        $("#member_id_input").val('');
                        $("#recordAttendanceButton").prop('disabled', true);
                    }
                }
            });

            if ($("#member_id_input").val() === '') {
                $("#recordAttendanceButton").prop('disabled', true);
            }

            $("#member_search").on("input", function() {
                if ($(this).val().trim() === "") {
                    $("#member_id_input").val('');
                    $("#recordAttendanceButton").prop('disabled', true);
                }
            });
        });
    </script>
</body>

</html>