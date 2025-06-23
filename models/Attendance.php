<?php

class Attendance {
    private $conn;
    private $table_name = "attendance";
    private $members_table = "members";
    private $users_table = "users";

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function create($member_id, $user_id) {
        $today = date('Y-m-d');
        $checkStmt = $this->conn->prepare("SELECT id FROM " . $this->table_name . " WHERE member_id = ? AND DATE(checkin_time) = ?");
        if ($checkStmt === false) {
            error_log("Attendance model prepare failed (create check): " . $this->conn->error);
            return false;
        }
        $checkStmt->bind_param("is", $member_id, $today);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $checkStmt->close();
            return false;
        }
        $checkStmt->close();

        $stmt = $this->conn->prepare("INSERT INTO " . $this->table_name . " (member_id, checkin_time, created_by) VALUES (?, NOW(), ?)");
        if ($stmt === false) {
            error_log("Attendance model prepare failed (create): " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("ii", $member_id, $user_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Attendance model execute failed (create): " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function getByDateWithPagination($date, $limit, $offset) {
        $sql = "
            SELECT a.*, m.full_name, m.member_code, u.name AS admin_name
            FROM " . $this->table_name . " a
            JOIN " . $this->members_table . " m ON a.member_id = m.id
            LEFT JOIN " . $this->users_table . " u ON a.created_by = u.id
            WHERE DATE(a.checkin_time) = ?
            ORDER BY a.checkin_time DESC
            LIMIT ?, ?
        ";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Attendance model prepare failed (getByDateWithPagination): " . $this->conn->error);
            return [];
        }
        $stmt->bind_param("sii", $date, $offset, $limit);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function getTotalAttendanceByDate($date) {
        $sql = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE DATE(checkin_time) = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Attendance model prepare failed (getTotalAttendanceByDate): " . $this->conn->error);
            return 0;
        }
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['total'] ?? 0;
    }

    public function searchActiveMembers($searchTerm) {
        $members = [];
        $sql = "SELECT id, full_name, member_code FROM " . $this->members_table . " WHERE status = 'active' AND (full_name LIKE ? OR member_code LIKE ?) ORDER BY full_name ASC";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Attendance model prepare failed (searchActiveMembers): " . $this->conn->error);
            return [];
        }
        $likeTerm = '%' . $searchTerm . '%';
        $stmt->bind_param("ss", $likeTerm, $likeTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        $stmt->close();
        return $members;
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM " . $this->table_name . " WHERE id = ?");
        if ($stmt === false) {
            error_log("Attendance model prepare failed (delete): " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Attendance model execute failed (delete): " . $stmt->error);
            $stmt->close();
            return false;
        }
    }
}