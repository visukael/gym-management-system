<?php
class Transaction {
    private $conn;
    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        return $this->conn->query("SELECT * FROM transactions ORDER BY created_at DESC");
    }

    public function create($data) {
        $stmt = $this->conn->prepare("INSERT INTO transactions (transaction_type, label, description, amount, discount, final_amount, member_id, product_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssddsdii", $data['transaction_type'], $data['label'], $data['description'], $data['amount'], $data['discount'], $data['final_amount'], $data['member_id'], $data['product_id'], $data['user_id']);
        return $stmt->execute();
    }
}
