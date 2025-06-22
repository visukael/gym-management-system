<?php

class Product {
    private $conn;
    private $table_name = "products";
    private $stock_entries_table = "product_stock_entries";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function createProduct($data) {
        $name = $data['name'];
        $description = $data['description'];
        $price = (float)$data['price'];
        $stock = (int)$data['stock'];

        $stmt = $this->conn->prepare("INSERT INTO " . $this->table_name . " (name, description, price, stock) VALUES (?, ?, ?, ?)");
        if ($stmt === false) {
            error_log("Product model prepare failed (createProduct): " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("ssdi", $name, $description, $price, $stock);
        if ($stmt->execute()) {
            $last_id = $stmt->insert_id;
            $stmt->close();
            return $last_id;
        } else {
            error_log("Product model execute failed (createProduct): " . $this->conn->error);
            $stmt->close();
            return false;
        }
    }

    public function updateProduct($id, $data) {
        $name = $data['name'];
        $description = $data['description'];
        $price = (float)$data['price'];
        $stock = (int)$data['stock'];

        $stmt = $this->conn->prepare("UPDATE " . $this->table_name . " SET name=?, description=?, price=?, stock=? WHERE id=?");
        if ($stmt === false) {
            error_log("Product model prepare failed (updateProduct): " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("ssdii", $name, $description, $price, $stock, $id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Product model execute failed (updateProduct): " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table_name . " WHERE id = ?");
        if ($stmt === false) {
            error_log("Product model prepare failed (getById): " . $this->conn->error);
            return null;
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function addStock($id, $qty) {
        $stmt = $this->conn->prepare("UPDATE " . $this->table_name . " SET stock = stock + ? WHERE id = ?");
        if ($stmt === false) {
            error_log("Product model prepare failed (addStock): " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("ii", $qty, $id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Product model execute failed (addStock): " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function removeStock($id, $qty) {
        $stmt = $this->conn->prepare("UPDATE " . $this->table_name . " SET stock = stock - ? WHERE id = ? AND stock >= ?");
        if ($stmt === false) {
            error_log("Product model prepare failed (removeStock): " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("iii", $qty, $id, $qty);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Product model execute failed (removeStock): " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function deleteProduct($id) {
        $stmt = $this->conn->prepare("DELETE FROM " . $this->table_name . " WHERE id = ?");
        if ($stmt === false) {
            error_log("Product model prepare failed (deleteProduct): " . $this->conn->error);
            return ['success' => false, 'message' => "Database error preparing delete operation."];
        }
        $stmt->bind_param("i", $id);
        
        try {
            if ($stmt->execute()) {
                $stmt->close();
                return ['success' => true, 'message' => "Product deleted successfully!"];
            } else {
                $errorMessage = $stmt->error;
                error_log("Product model execute failed (deleteProduct): " . $errorMessage);
                $stmt->close();
                if (strpos($errorMessage, 'foreign key constraint fails') !== false || $stmt->errno === 1451) {
                    return ['success' => false, 'message' => "Failed to delete product: It is currently linked to existing transactions or stock entries."];
                }
                return ['success' => false, 'message' => "Error deleting product: " . $errorMessage];
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Product model SQL Exception (deleteProduct): " . $e->getMessage());
            if ($e->getCode() === 1451 || $e->getCode() === 1452) {
                return ['success' => false, 'message' => "Failed to delete product: It is currently linked to existing transactions or stock entries."];
            }
            return ['success' => false, 'message' => "An unexpected database error occurred during deletion."];
        }
    }

    public function getFilteredProducts($searchTerm = '', $filterStock = '') {
        $sql = "SELECT p.*,
                    (SELECT AVG(buy_price / quantity)
                     FROM " . $this->stock_entries_table . "
                     WHERE product_id = p.id AND quantity > 0) as avg_buy_price
                FROM " . $this->table_name . " p
                WHERE 1=1";

        $params = [];
        $types = "";

        if (!empty($searchTerm)) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $params[] = '%' . $searchTerm . '%';
            $params[] = '%' . $searchTerm . '%';
            $types .= "ss";
        }

        if (!empty($filterStock)) {
            if ($filterStock === 'low') {
                $sql .= " AND p.stock <= 5 AND p.stock > 0";
            } elseif ($filterStock === 'in_stock') {
                $sql .= " AND p.stock > 0";
            } elseif ($filterStock === 'out_of_stock') {
                $sql .= " AND p.stock = 0";
            }
        }

        $sql .= " ORDER BY p.id DESC";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Product model prepare failed (getFilteredProducts): " . $this->conn->error);
            return [];
        }

        if (!empty($params) && !empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $result;
    }

    public function getRecentStockHistory($limit = 10) {
        $sql = "SELECT s.*, p.name AS product_name
                FROM " . $this->stock_entries_table . " s
                JOIN " . $this->table_name . " p ON s.product_id = p.id
                ORDER BY s.created_at DESC
                LIMIT ?";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Product model prepare failed (getRecentStockHistory): " . $this->conn->error);
            return [];
        }
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $result;
    }
}