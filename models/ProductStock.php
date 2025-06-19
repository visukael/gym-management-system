<?php

class ProductStock {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function addStockEntry($product_id, $quantity, $buy_price) {
        $stmt = $this->conn->prepare("INSERT INTO product_stock_entries (product_id, quantity, buy_price) VALUES (?, ?, ?)");
        $stmt->bind_param("iid", $product_id, $quantity, $buy_price);
        return $stmt->execute();
    }

    public function getAll() {
        return $this->conn->query("
            SELECT se.*, p.name AS product_name 
            FROM product_stock_entries se
            JOIN products p ON se.product_id = p.id
            ORDER BY se.created_at DESC
        ");
    }
}
