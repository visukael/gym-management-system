<?php
class DashboardController {
    private $conn;
    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getStats() {
        $stats = [];

        $stats['total_income'] = $this->conn->query("SELECT SUM(final_amount) FROM transactions WHERE label='income'")->fetch_row()[0] ?? 0;
        $stats['total_outcome'] = $this->conn->query("SELECT SUM(final_amount) FROM transactions WHERE label='outcome'")->fetch_row()[0] ?? 0;
        $stats['total_members'] = $this->conn->query("SELECT COUNT(*) FROM members WHERE status='active'")->fetch_row()[0] ?? 0;
        $stats['products_sold'] = $this->conn->query("SELECT COUNT(*) FROM transactions WHERE transaction_type='product'")->fetch_row()[0] ?? 0;

        return $stats;
    }
}
