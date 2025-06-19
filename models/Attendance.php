<?php

class Attendance {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function create($member_id, $user_id) {
        $stmt = $this->conn->prepare("INSERT INTO attendance (member_id, checkin_time, created_by) VALUES (?, NOW(), ?)");
        $stmt->bind_param("ii", $member_id, $user_id);
        return $stmt->execute();
    }

    public function getAll() {
        return $this->conn->query("
            SELECT a.*, m.full_name, u.name AS admin_name 
            FROM attendance a
            JOIN members m ON a.member_id = m.id
            LEFT JOIN users u ON a.created_by = u.id
            ORDER BY a.checkin_time DESC
        ");
    }

    public function getByDate($date) {
        $stmt = $this->conn->prepare("
            SELECT a.*, m.full_name, u.name AS admin_name 
            FROM attendance a
            JOIN members m ON a.member_id = m.id
            LEFT JOIN users u ON a.created_by = u.id
            WHERE DATE(a.checkin_time) = ?
            ORDER BY a.checkin_time DESC
        ");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        return $stmt->get_result();
    }
}
