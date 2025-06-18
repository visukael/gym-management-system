<?php
class MembershipPackage {
    private $conn;
    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        return $this->conn->query("SELECT * FROM membership_packages");
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM membership_packages WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
