<?php
class Promotion {
    private $conn;
    public function __construct($db) {
        $this->conn = $db;
    }

    public function getActiveByPackage($package_id) {
        $today = date('Y-m-d');
        $stmt = $this->conn->prepare("SELECT * FROM promotions WHERE package_id = ? AND start_date <= ? AND end_date >= ?");
        $stmt->bind_param("iss", $package_id, $today, $today);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
