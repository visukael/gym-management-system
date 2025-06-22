<?php

class Promotion {
    private $conn;
    private $table_name = "promotions";
    private $packages_table = "membership_packages";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function createPromotion($data) {
        $name = $data['name'];
        $discount_type = $data['discount_type'];
        $discount_value = (float)$data['discount_value'];
        $start_date = $data['start_date'];
        $end_date = $data['end_date'];
        $package_id = $data['package_id'] ?? null;

        $stmt = $this->conn->prepare("INSERT INTO " . $this->table_name . " (name, discount_type, discount_value, start_date, end_date, package_id) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            error_log("Promotion model prepare failed (createPromotion): " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("ssdsis", $name, $discount_type, $discount_value, $start_date, $end_date, $package_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Promotion model execute failed (createPromotion): " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function updatePromotion($id, $data) {
        $name = $data['name'];
        $discount_type = $data['discount_type'];
        $discount_value = (float)$data['discount_value'];
        $start_date = $data['start_date'];
        $end_date = $data['end_date'];
        $package_id = $data['package_id'] ?? null;

        $stmt = $this->conn->prepare("UPDATE " . $this->table_name . " SET name=?, discount_type=?, discount_value=?, start_date=?, end_date=?, package_id=? WHERE id=?");
        if ($stmt === false) {
            error_log("Promotion model prepare failed (updatePromotion): " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("ssdsisi", $name, $discount_type, $discount_value, $start_date, $end_date, $package_id, $id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Promotion model execute failed (updatePromotion): " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table_name . " WHERE id = ?");
        if ($stmt === false) {
            error_log("Promotion model prepare failed (getById): " . $this->conn->error);
            return null;
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function deletePromotion($id) {
        $stmt = $this->conn->prepare("DELETE FROM " . $this->table_name . " WHERE id = ?");
        if ($stmt === false) {
            error_log("Promotion model prepare failed (deletePromotion): " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Promotion model execute failed (deletePromotion): " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function getAllPromotions() {
        $promos = [];
        $sql = "
            SELECT p.*, mp.name AS package_name
            FROM " . $this->table_name . " p
            LEFT JOIN " . $this->packages_table . " mp ON p.package_id = mp.id
            ORDER BY p.id DESC
        ";
        $result = $this->conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $promos[] = $row;
            }
        } else {
            error_log("Promotion model query failed (getAllPromotions): " . $this->conn->error);
        }
        return $promos;
    }

    public function getActiveByPackage($package_id) {
        $today = date('Y-m-d');
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table_name . " WHERE (package_id = ? OR package_id IS NULL) AND start_date <= ? AND end_date >= ?");
        if ($stmt === false) {
            error_log("Promotion model prepare failed (getActiveByPackage): " . $this->conn->error);
            return null;
        }
        if (is_null($package_id) || $package_id === 0) {
            $stmt->bind_param("sss", $package_id, $today, $today);
        } else {
            $stmt->bind_param("iss", $package_id, $today, $today);
        }

        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function getActivePromotions() {
        $promos = [];
        $sql = "
            SELECT p.*, mp.name AS package_name
            FROM " . $this->table_name . " p
            LEFT JOIN " . $this->packages_table . " mp ON p.package_id = mp.id
            WHERE p.start_date <= CURDATE() AND p.end_date >= CURDATE()
            ORDER BY p.name ASC
        ";
        $result = $this->conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $promos[] = $row;
            }
        } else {
            error_log("Promotion model query failed (getActivePromotions): " . $this->conn->error);
        }
        return $promos;
    }
}