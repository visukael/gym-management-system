<?php
class Attendance {
    private $conn;
    public function __construct($db) {
        $this->conn = $db;
    }

    public function log($member_id) {
        $date = date('Y-m-d');
        $time = date('H:i:s');
        $stmt = $this->conn->prepare("INSERT INTO attendance (member_id, date, time) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $member_id, $date, $time);
        return $stmt->execute();
    }

    public function getToday() {
        $date = date('Y-m-d');
        $stmt = $this->conn->prepare("SELECT a.*, m.full_name FROM attendance a LEFT JOIN members m ON a.member_id = m.id WHERE a.date = ?");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        return $stmt->get_result();
    }
}