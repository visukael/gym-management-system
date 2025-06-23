<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['owner', 'admin'])) {
    header('Location: ../login.php');
    exit;
}

$userRoleForAccess = $_SESSION['user_role'] ?? '';
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Unknown');
$userRoleDisplay = htmlspecialchars(ucfirst($userRoleForAccess));

$conn = $conn;

$incomeQuery = $conn->query("SELECT SUM(final_amount) AS total_income FROM transactions WHERE label = 'income'");
$totalIncome = $incomeQuery->fetch_assoc()['total_income'] ?? 0;

$outcomeQuery = $conn->query("SELECT SUM(final_amount) AS total_outcome FROM transactions WHERE label = 'outcome'");
$totalOutcome = $outcomeQuery->fetch_assoc()['total_outcome'] ?? 0;

$activeMembersQuery = $conn->query("SELECT COUNT(*) AS total_active FROM members WHERE status = 'active'");
$totalActiveMembers = $activeMembersQuery->fetch_assoc()['total_active'] ?? 0;

$productSalesQuery = $conn->query("SELECT COUNT(*) AS products_sold FROM transactions WHERE transaction_type = 'product_sale'");
$productsSold = $productSalesQuery->fetch_assoc()['products_sold'] ?? 0;

$today = date('Y-m-d');
$promoQuery = $conn->query("SELECT COUNT(*) AS active_promos FROM promotions WHERE start_date <= '$today' AND end_date >= '$today'");
$activePromotions = $promoQuery->fetch_assoc()['active_promos'] ?? 0;

$attendanceQuery = $conn->query("SELECT COUNT(*) AS today_attendance FROM attendance WHERE DATE(checkin_time) = '$today'");
$todayAttendance = $attendanceQuery->fetch_assoc()['today_attendance'] ?? 0;

$recentTransactionsQuery = $conn->query("SELECT t.*, m.full_name FROM transactions t LEFT JOIN members m ON t.member_id = m.id ORDER BY t.created_at DESC LIMIT 5");
$recentTransactions = $recentTransactionsQuery->fetch_all(MYSQLI_ASSOC);

$expiringSoonQuery = $conn->query("SELECT full_name, expired_date FROM members WHERE status = 'active' AND expired_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)");
$expiringSoon = $expiringSoonQuery->fetch_all(MYSQLI_ASSOC);

$lowStockQuery = $conn->query("SELECT name, stock FROM products WHERE stock <= 5 ORDER BY stock ASC LIMIT 3");
$lowStockProducts = $lowStockQuery->fetch_all(MYSQLI_ASSOC);

$todayAttendanceDetailQuery = $conn->query("SELECT m.full_name, a.checkin_time FROM attendance a JOIN members m ON a.member_id = m.id WHERE DATE(a.checkin_time) = CURDATE() ORDER BY a.checkin_time DESC LIMIT 5");
$todayAttendanceList = $todayAttendanceDetailQuery->fetch_all(MYSQLI_ASSOC);

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Unknown');
$userRole = htmlspecialchars(ucfirst($_SESSION['user_role'] ?? '-'));

$initialMonthlyIncomeData = [];
$initialMonthlyIncomeLabels = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthLabel = date('M Y', strtotime("-$i months"));

    $incomeMonthQuery = $conn->query("SELECT SUM(final_amount) AS monthly_income FROM transactions WHERE label = 'income' AND DATE_FORMAT(created_at, '%Y-%m') = '$month'");
    $monthlyIncome = $incomeMonthQuery->fetch_assoc()['monthly_income'] ?? 0;

    $initialMonthlyIncomeData[] = (int)$monthlyIncome;
    $initialMonthlyIncomeLabels[] = $monthLabel;
}

$incomeByTypeData = [];
$incomeByTypeLabels = [];
$incomeByTypeColors = [];
$transactionTypesQuery = $conn->query("SELECT transaction_type, SUM(final_amount) AS total_amount FROM transactions WHERE label = 'income' GROUP BY transaction_type");
while ($row = $transactionTypesQuery->fetch_assoc()) {
    $incomeByTypeLabels[] = htmlspecialchars(str_replace('_', ' ', ucfirst($row['transaction_type'])));
    $incomeByTypeData[] = (int)$row['total_amount'];
    $incomeByTypeColors[] = '#' . substr(md5($row['transaction_type']), 0, 6);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gym Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .card-item {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        .card-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .table-row-hover:hover {
            background-color: #f9fafb;
        }

        select:hover {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
        }

        .chart-placeholder {
            background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
            background-size: 400% 100%;
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
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
                        <a href="dashboard.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : '' ?>">
                            <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Dashboard
                        </a>
                        <a href="members/members.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'members.php') ? 'active' : '' ?>">
                            <i data-lucide="users" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Members
                        </a>
                        <a href="products/products.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'products.php') ? 'active' : '' ?>">
                            <i data-lucide="shopping-bag" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Products
                        </a>
                        <a href="transactions/transactions.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'transactions.php') ? 'active' : '' ?>">
                            <i data-lucide="credit-card" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Transactions
                        </a>
                        <a href="attendance/attendance.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'attendance.php') ? 'active' : '' ?>">
                            <i data-lucide="calendar-check" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Attendance
                        </a>
                        <a href="promotions/promotions.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'promotions.php') ? 'active' : '' ?>">
                            <i data-lucide="percent" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                            Promotions
                        </a>

                        <?php if ($userRoleForAccess === 'owner'): ?>
                            <a href="user.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item <?= (basename($_SERVER['PHP_SELF']) == 'user.php') ? 'active' : '' ?>">
                                <i data-lucide="settings" class="w-5 h-5 mr-3 sidebar-icon text-gray-500"></i>
                                Manage Users
                            </a>
                        <?php endif; ?>

                        <a href="logout.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg sidebar-item">
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

            <div class="flex-1 overflow-auto p-4 md:p-6">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold">Dashboard Overview</h1>
                    <p class="text-gray-500">Welcome back! Here's what's happening with your gym today.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="p-6 bg-white rounded-2xl shadow-sm border border-gray-100 card-item">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-gray-500 text-sm font-medium">Total Income</div>
                            <i data-lucide="credit-card" class="w-6 h-6 text-red-500"></i>
                        </div>
                        <div class="text-2xl font-semibold text-gray-800">Rp <?= number_format($totalIncome, 0, ',', '.') ?></div>
                    </div>

                    <div class="p-6 bg-white rounded-2xl shadow-sm border border-gray-100 card-item">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-gray-500 text-sm font-medium">Total Outcome</div>
                            <i data-lucide="wallet" class="w-6 h-6 text-red-500"></i>
                        </div>
                        <div class="text-2xl font-semibold text-gray-800">Rp <?= number_format($totalOutcome, 0, ',', '.') ?></div>
                    </div>

                    <div class="p-6 bg-white rounded-2xl shadow-sm border border-gray-100 card-item">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-gray-500 text-sm font-medium">Active Members</div>
                            <i data-lucide="users" class="w-6 h-6 text-red-500"></i>
                        </div>
                        <div class="text-2xl font-semibold text-gray-800"><?= $totalActiveMembers ?></div>
                    </div>

                    <div class="p-6 bg-white rounded-2xl shadow-sm border border-gray-100 card-item">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-gray-500 text-sm font-medium">Products Sold</div>
                            <i data-lucide="shopping-bag" class="w-6 h-6 text-red-500"></i>
                        </div>
                        <div class="text-2xl font-semibold text-gray-800"><?= $productsSold ?></div>
                    </div>

                    <div class="p-6 bg-white rounded-2xl shadow-sm border border-gray-100 card-item">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-gray-500 text-sm font-medium">Active Promotions</div>
                            <i data-lucide="percent" class="w-6 h-6 text-red-500"></i>
                        </div>
                        <div class="text-2xl font-semibold text-gray-800"><?= $activePromotions ?></div>
                    </div>

                    <div class="p-6 bg-white rounded-2xl shadow-sm border border-gray-100 card-item">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-gray-500 text-sm font-medium">Today's Attendance</div>
                            <i data-lucide="calendar-days" class="w-6 h-6 text-red-500"></i>
                        </div>
                        <div class="text-2xl font-semibold text-gray-800"><?= $todayAttendance ?></div>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-2">
                    <div class="p-6 bg-white rounded-2xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-medium">Income Overview</h2>
                            <div class="flex space-x-2">
                                <select id="chartTypeSelect" class="block w-auto py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm">
                                    <option value="bar">Bar Chart</option>
                                    <option value="line">Line Chart</option>
                                    <option value="pie">Pie Chart</option>
                                </select>
                                <select id="chartPeriodSelect" class="block w-auto py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm">
                                    <option value="month">Monthly</option>
                                    <option value="week">Weekly</option>
                                    <option value="day">Daily</option>
                                </select>
                            </div>
                        </div>
                        <canvas id="incomeChart" class="w-full h-64"></canvas>
                    </div>
                    <div class="p-6 bg-white rounded-2xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-medium">Recent Transactions</h2>
                            <a href="../views/transactions/transactions.php" class="text-sm text-red-600 hover:underline">View All</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Label</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($recentTransactions as $tx): ?>
                                        <tr class="table-row-hover">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars(str_replace('_', ' ', ucfirst($tx['transaction_type'] ?? '-'))) ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                Rp <?= number_format($tx['final_amount'], 0, ',', '.') ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <?php if ($tx['label'] === 'income'): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Income</span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Outcome</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('d M Y', strtotime($tx['created_at'])) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
                <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-2">
                    <div class="p-6 bg-white rounded-2xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-medium">Alerts</h2>
                            <i data-lucide="alert-circle" class="w-5 h-5 text-yellow-500"></i>
                        </div>
                        <div class="space-y-4">
                            <?php if (count($expiringSoon) > 0): ?>
                                <div class="flex items-start p-3 bg-red-50 rounded-lg border border-red-200 hover:shadow-sm transition-shadow">
                                    <i data-lucide="alert-triangle" class="flex-shrink-0 w-5 h-5 mt-0.5 text-red-500"></i>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-red-800">
                                            <?= count($expiringSoon) ?> memberships expiring in 3 days
                                        </p>
                                        <ul class="text-xs text-red-600 mt-1 list-disc ml-5">
                                            <?php foreach ($expiringSoon as $member): ?>
                                                <li><?= htmlspecialchars($member['full_name']) ?> (<?= date('d M', strtotime($member['expired_date'])) ?>)</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (count($lowStockProducts) > 0): ?>
                                <div class="flex items-start p-3 bg-yellow-50 rounded-lg border border-yellow-200 hover:shadow-sm transition-shadow">
                                    <i data-lucide="alert-circle" class="flex-shrink-0 w-5 h-5 mt-0.5 text-yellow-500"></i>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-yellow-800">Low stock warning</p>
                                        <ul class="text-xs text-yellow-600 mt-1 list-disc ml-5">
                                            <?php foreach ($lowStockProducts as $product): ?>
                                                <li><?= htmlspecialchars($product['name']) ?> (<?= $product['stock'] ?> left)</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (count($expiringSoon) === 0 && count($lowStockProducts) === 0): ?>
                                <div class="flex items-center p-3 bg-green-50 rounded-lg border border-green-200">
                                    <i data-lucide="check-circle" class="flex-shrink-0 w-5 h-5 mt-0.5 text-green-500"></i>
                                    <p class="ml-3 text-sm text-gray-500">No alerts at the moment. All clear!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="p-6 bg-white rounded-2xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-medium">Today's Attendance</h2>
                            <a href="../views/attendance/attendance.php" class="text-sm text-red-600 hover:underline">View All</a>
                        </div>
                        <div class="space-y-4">
                            <?php if (count($todayAttendanceList) > 0): ?>
                                <?php foreach ($todayAttendanceList as $att): ?>
                                    <div class="flex items-center p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-semibold">
                                                <?= strtoupper(substr($att['full_name'], 0, 1)) ?>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($att['full_name']) ?></p>
                                            <p class="text-xs text-gray-500">Checked in at <?= date('H:i', strtotime($att['checkin_time'])) ?></p>
                                        </div>
                                        <div class="ml-auto">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-sm text-gray-500">No members checked in yet today.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const initialMonthlyIncomeLabels = <?= json_encode($initialMonthlyIncomeLabels) ?>;
        const initialMonthlyIncomeData = <?= json_encode($initialMonthlyIncomeData) ?>;
        const incomeByTypeLabels = <?= json_encode($incomeByTypeLabels) ?>;
        const incomeByTypeData = <?= json_encode($incomeByTypeData) ?>;
        const incomeByTypeColors = <?= json_encode($incomeByTypeColors) ?>;

        let incomeChartInstance;

        async function fetchChartData(period) {
            try {
                const response = await fetch(`../api/get_chart_data.php?period=${period}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                return data;
            } catch (error) {
                console.error('Error fetching chart data:', error);
                return {
                    labels: [],
                    data: []
                };
            }
        }

        async function renderChart() {
            const chartType = document.getElementById('chartTypeSelect').value;
            const chartPeriod = document.getElementById('chartPeriodSelect').value;
            const ctx = document.getElementById('incomeChart').getContext('2d');

            if (incomeChartInstance) {
                incomeChartInstance.destroy();
            }

            let chartData, chartOptions;

            if (chartType === 'pie') {
                chartData = {
                    labels: incomeByTypeLabels,
                    datasets: [{
                        label: 'Income by Type (Rp)',
                        data: incomeByTypeData,
                        backgroundColor: incomeByTypeColors,
                        hoverOffset: 4
                    }]
                };
                chartOptions = {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += 'Rp ' + context.parsed.toLocaleString('id-ID');
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                };
                document.getElementById('chartPeriodSelect').style.display = 'none';

            } else {
                const fetchedData = await fetchChartData(chartPeriod);

                chartData = {
                    labels: fetchedData.labels,
                    datasets: [{
                        label: 'Income (Rp)',
                        data: fetchedData.data,
                        backgroundColor: chartType === 'bar' ? '#ef4444' : 'rgba(239, 68, 68, 0.1)',
                        borderColor: '#ef4444',
                        tension: chartType === 'line' ? 0.4 : 0,
                        fill: chartType === 'line',
                        borderWidth: 1
                    }]
                };
                chartOptions = {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => 'Rp ' + value.toLocaleString('id-ID')
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                };
                document.getElementById('chartPeriodSelect').style.display = 'block';
            }

            incomeChartInstance = new Chart(ctx, {
                type: chartType,
                data: chartData,
                options: chartOptions
            });
        }

        function toggleSidebar() {
            const sidebar = document.querySelector('.md\\:flex-shrink-0');
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('absolute');
            sidebar.classList.toggle('inset-y-0');
            sidebar.classList.toggle('left-0');
            sidebar.classList.toggle('z-40');
        }


        document.addEventListener("DOMContentLoaded", function() {
            renderChart();

            const chartTypeSelect = document.getElementById('chartTypeSelect');
            chartTypeSelect.addEventListener('change', renderChart);

            const chartPeriodSelect = document.getElementById('chartPeriodSelect');
            chartPeriodSelect.addEventListener('change', renderChart);
        });
    </script>
</body>

</html>