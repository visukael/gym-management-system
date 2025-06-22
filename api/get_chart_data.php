<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$conn = $conn;

$period = $_GET['period'] ?? 'month'; 

$data = [];
$labels = [];

switch ($period) {
    case 'day':
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dayLabel = date('D, d M', strtotime("-$i days"));

            $query = $conn->prepare("SELECT SUM(final_amount) AS total_income FROM transactions WHERE label = 'income' AND DATE(created_at) = ?");
            $query->bind_param("s", $date);
            $query->execute();
            $result = $query->get_result();
            $dailyIncome = $result->fetch_assoc()['total_income'] ?? 0;

            $data[] = (int)$dailyIncome;
            $labels[] = $dayLabel;
        }
        break;

    case 'week':
        $today = new DateTime();
        $startOfWeek = clone $today;
        $startOfWeek->modify('Monday this week');

        for ($i = 5; $i >= 0; $i--) {
            $currentWeekStart = clone $startOfWeek;
            $currentWeekStart->modify("-$i weeks");
            $currentWeekEnd = clone $currentWeekStart;
            $currentWeekEnd->modify('+6 days');

            $weekLabel = $currentWeekStart->format('d M') . ' - ' . $currentWeekEnd->format('d M');
            
            $startDate = $currentWeekStart->format('Y-m-d');
            $endDate = $currentWeekEnd->format('Y-m-d');

            $query = $conn->prepare("SELECT SUM(final_amount) AS total_income FROM transactions WHERE label = 'income' AND created_at BETWEEN ? AND ?");
            $query->bind_param("ss", $startDate, $endDate);
            $query->execute();
            $result = $query->get_result();
            $weeklyIncome = $result->fetch_assoc()['total_income'] ?? 0;

            $data[] = (int)$weeklyIncome;
            $labels[] = $weekLabel;
        }
        break;

    case 'month':
    default:
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $monthLabel = date('M Y', strtotime("-$i months"));

            $query = $conn->prepare("SELECT SUM(final_amount) AS total_income FROM transactions WHERE label = 'income' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
            $query->bind_param("s", $month);
            $query->execute();
            $result = $query->get_result();
            $monthlyIncome = $result->fetch_assoc()['total_income'] ?? 0;

            $data[] = (int)$monthlyIncome;
            $labels[] = $monthLabel;
        }
        break;
}

echo json_encode(['labels' => $labels, 'data' => $data]);