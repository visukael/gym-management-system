<?php

class Transaction {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function create($data) {
        $type = $data['transaction_type'];
        $label = $data['label']; // income / outcome
        $desc = $data['description'];
        $amount = (float) $data['amount'];
        $discount = isset($data['discount']) ? (float) $data['discount'] : 0;
        $final = isset($data['final_amount']) ? (float) $data['final_amount'] : $amount;
        $member_id = $data['member_id'] ?? null;
        $product_id = $data['product_id'] ?? null;
        $user_id = $data['user_id'];

        $stmt = $this->conn->prepare("
            INSERT INTO transactions 
            (transaction_type, label, description, amount, discount, final_amount, member_id, product_id, user_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        // Bind NULL-safe values
        $member_id = isset($member_id) ? (int) $member_id : null;
        $product_id = isset($product_id) ? (int) $product_id : null;

        // Gunakan bind_param untuk nullable int
        $stmt->bind_param(
            "sssddiiii",
            $type,
            $label,
            $desc,
            $amount,
            $discount,
            $final,
            $member_id,
            $product_id,
            $user_id
        );

        if (!$stmt->execute()) {
            // Jika gagal, tampilkan error
            die("Gagal menyimpan transaksi: " . $stmt->error);
        }

        return true;
    }

    public function all() {
        return $this->conn->query("
            SELECT t.*, u.name AS user_name
            FROM transactions t
            LEFT JOIN users u ON t.user_id = u.id
            ORDER BY t.created_at DESC
        ");
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM transactions WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getTotalIncome() {
        $result = $this->conn->query("SELECT SUM(final_amount) as total FROM transactions WHERE label = 'income'");
        return $result->fetch_assoc()['total'] ?? 0;
    }

    public function getTotalOutcome() {
        $result = $this->conn->query("SELECT SUM(final_amount) as total FROM transactions WHERE label = 'outcome'");
        return $result->fetch_assoc()['total'] ?? 0;
    }
}
