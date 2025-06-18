<?php
class Member {
    private $conn;
    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        return $this->conn->query("SELECT m.*, p.name AS package_name FROM members m LEFT JOIN membership_packages p ON m.package_id = p.id");
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function create($data) {
        $stmt = $this->conn->prepare("INSERT INTO members (full_name, phone, address, email, age, join_date, expired_date, package_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssissi", $data['full_name'], $data['phone'], $data['address'], $data['email'], $data['age'], $data['join_date'], $data['expired_date'], $data['package_id']);
        return $stmt->execute();
    }

    public function update($id, $data) {
        $stmt = $this->conn->prepare("UPDATE members SET full_name=?, phone=?, address=?, email=?, age=?, join_date=?, expired_date=?, package_id=? WHERE id=?");
        $stmt->bind_param("ssssissii", $data['full_name'], $data['phone'], $data['address'], $data['email'], $data['age'], $data['join_date'], $data['expired_date'], $data['package_id'], $id);
        return $stmt->execute();
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM members WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
