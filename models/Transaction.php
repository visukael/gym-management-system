<?php

class Transaction {
    private $conn;
    private $table_name = "transactions";

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function create($data) {
        $type = $data['transaction_type'] ?? null;
        $label = $data['label'] ?? null;
        $description = $data['description'] ?? null;
        $amount = (float)($data['amount'] ?? 0);
        $discount = (float)($data['discount'] ?? 0);
        $final_amount = (float)($data['final_amount'] ?? $amount);
        $member_id = $data['member_id'] ?? null;
        $product_id = $data['product_id'] ?? null;
        $user_id = $data['user_id'] ?? null;
        $payment_method = $data['payment_method'] ?? 'cash';

        $query = "
            INSERT INTO " . $this->table_name . " 
            (transaction_type, label, description, amount, discount, final_amount, member_id, product_id, user_id, payment_method, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $this->conn->prepare($query);

        if ($stmt === false) {
            error_log("Transaction model prepare failed (create): " . $this->conn->error);
            return false;
        }
        $stmt->bind_param(
            "sssdddiiss",
            $type,
            $label,
            $description,
            $amount,
            $discount,
            $final_amount,
            $member_id,
            $product_id,
            $user_id,
            $payment_method
        );

        if ($stmt->execute()) {
            return true;
        } else {
            error_log("Transaction model execute failed (create): " . $stmt->error);
            return false;
        }
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM " . $this->table_name . " WHERE id = ?");
        if ($stmt === false) {
            error_log("Transaction model prepare failed (delete): " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            return true;
        } else {
            error_log("Transaction model execute failed (delete): " . $stmt->error);
            return false;
        }
    }

    public function getFilteredTransactions($filters, $limit, $offset) {
        $sql = "SELECT t.*
                FROM " . $this->table_name . " t
                WHERE 1=1";
        
        $params = [];
        $types = "";

        if (!empty($filters['search'])) {
            $sql .= " AND t.description LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
            $types .= "s";
        }
        if (!empty($filters['type'])) {
            $sql .= " AND t.transaction_type = ?";
            $params[] = $filters['type'];
            $types .= "s";
        }
        if (!empty($filters['label'])) {
            $sql .= " AND t.label = ?";
            $params[] = $filters['label'];
            $types .= "s";
        }
        if (!empty($filters['payment_method'])) {
            $sql .= " AND t.payment_method = ?";
            $params[] = $filters['payment_method'];
            $types .= "s";
        }

        $periodStart = null;
        if ($filters['sortBy'] === 'today') {
            $periodStart = date('Y-m-d 00:00:00');
            $sql .= " AND t.created_at >= ?";
            $params[] = $periodStart;
            $types .= "s";
        } elseif ($filters['sortBy'] === 'this_week') {
            $periodStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
            $sql .= " AND t.created_at >= ?";
            $params[] = $periodStart;
            $types .= "s";
        } elseif ($filters['sortBy'] === 'this_month') {
            $periodStart = date('Y-m-01 00:00:00');
            $sql .= " AND t.created_at >= ?";
            $params[] = $periodStart;
            $types .= "s";
        }

        $sql .= " ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
        $types .= "ii";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Transaction model prepare failed (getFilteredTransactions): " . $this->conn->error);
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

    public function countFilteredTransactions($filters) {
        $countSql = "SELECT COUNT(*) AS total FROM " . $this->table_name . " t WHERE 1=1";
        
        $params = [];
        $types = "";

        if (!empty($filters['search'])) {
            $countSql .= " AND t.description LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
            $types .= "s";
        }
        if (!empty($filters['type'])) {
            $countSql .= " AND t.transaction_type = ?";
            $params[] = $filters['type'];
            $types .= "s";
        }
        if (!empty($filters['label'])) {
            $countSql .= " AND t.label = ?";
            $params[] = $filters['label'];
            $types .= "s";
        }
        if (!empty($filters['payment_method'])) {
            $countSql .= " AND t.payment_method = ?";
            $params[] = $filters['payment_method'];
            $types .= "s";
        }

        $periodStart = null;
        if ($filters['sortBy'] === 'today') {
            $periodStart = date('Y-m-d 00:00:00');
            $countSql .= " AND t.created_at >= ?";
            $params[] = $periodStart;
            $types .= "s";
        } elseif ($filters['sortBy'] === 'this_week') {
            $periodStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
            $countSql .= " AND t.created_at >= ?";
            $params[] = $periodStart;
            $types .= "s";
        } elseif ($filters['sortBy'] === 'this_month') {
            $periodStart = date('Y-m-01 00:00:00');
            $countSql .= " AND t.created_at >= ?";
            $params[] = $periodStart;
            $types .= "s";
        }

        $stmt = $this->conn->prepare($countSql);
        if ($stmt === false) {
            error_log("Transaction model prepare failed (countFilteredTransactions): " . $this->conn->error);
            return 0;
        }

        if (!empty($params) && !empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();
        
        return $total;
    }

    public function getTotalIncome() {
        $result = $this->conn->query("SELECT SUM(final_amount) as total FROM " . $this->table_name . " WHERE label = 'income'");
        return $result->fetch_assoc()['total'] ?? 0;
    }

    public function getTotalOutcome() {
        $result = $this->conn->query("SELECT SUM(final_amount) as total FROM " . $this->table_name . " WHERE label = 'outcome'");
        return $result->fetch_assoc()['total'] ?? 0;
    }
}