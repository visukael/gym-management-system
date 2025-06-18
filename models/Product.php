<?php
class Product {
    private $conn;
    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        return $this->conn->query("SELECT * FROM products");
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function updateStock($id, $qty) {
        $stmt = $this->conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmt->bind_param("ii", $qty, $id);
        return $stmt->execute();
    }
public function addStock($id, $qty) {
    $stmt = $this->conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
    $stmt->bind_param("ii", $qty, $id);
    return $stmt->execute();
}

public function removeStock($id, $qty) {
    $stmt = $this->conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
    $stmt->bind_param("iii", $qty, $id, $qty);
    return $stmt->execute();
}



}
