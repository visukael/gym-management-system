<?php

class ProductStock {
    private $conn;
    private $table_name = "product_stock_entries";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function addStockEntry($product_id, $quantity, $buy_price) {
        $sql = "INSERT INTO " . $this->table_name . " (product_id, quantity, buy_price, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("ProductStock model prepare failed (addStockEntry): " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("iid", $product_id, $quantity, $buy_price);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("ProductStock model execute failed (addStockEntry): " . $stmt->error);
            $stmt->close();
            return false;
        }
    }
}