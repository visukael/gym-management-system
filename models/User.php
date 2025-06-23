<?php
class User {
    private $conn;
    public function __construct($db) {
        $this->conn = $db;
    }

    public function findByUsername($username) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getByUsername($username) {
        $stmt = $this->conn->prepare("SELECT id, name, username, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function create($name, $username, $password, $role) {
        $stmt = $this->conn->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $username, $password, $role);
        return $stmt->execute();
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getAll() {
        $result = $this->conn->query("SELECT id, name, username, role FROM users ORDER BY id DESC");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT id, name, username, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function update($id, $name, $role) {
        $stmt = $this->conn->prepare("UPDATE users SET name = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $role, $id);
        return $stmt->execute();
    }

    public function updatePassword($id, $hashedPassword) {
        $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $id);
        return $stmt->execute();
    }
}