<?php

class MembershipPackage {
    private $conn;
    private $table_name = "membership_packages";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $packages = [];
        $result = $this->conn->query("SELECT * FROM " . $this->table_name . " ORDER BY name ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $packages[] = $row;
            }
        } else {
            error_log("MembershipPackage model query failed (getAll): " . $this->conn->error);
        }
        return $packages;
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table_name . " WHERE id = ?");
        if ($stmt === false) {
            error_log("MembershipPackage model prepare failed (getById): " . $this->conn->error);
            return null;
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }
}