<?php

class Member {
    private $conn;
    private $table_name = "members";
    private $packages_table = "membership_packages";
    private $promotions_table = "promotions";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function activateExpiredMemberships() {
        $today = date('Y-m-d');
        $sql = "UPDATE " . $this->table_name . " SET status = 'inactive' WHERE expired_date < ? AND status = 'active'";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Member model prepare failed (activateExpiredMemberships): " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("s", $today);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table_name . " WHERE id = ?");
        if ($stmt === false) {
            error_log("Member model prepare failed (getById): " . $this->conn->error);
            return null;
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function getMembershipPackages() {
        $packages = [];
        $result = $this->conn->query("SELECT * FROM " . $this->packages_table . " ORDER BY name ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $packages[] = $row;
            }
        } else {
            error_log("Member model query failed (getMembershipPackages): " . $this->conn->error);
        }
        return $packages;
    }

    public function getActivePromotions() {
        $promos = [];
        $sql = "
            SELECT p.*, mp.name AS package_name
            FROM " . $this->promotions_table . " p
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
            error_log("Member model query failed (getActivePromotions): " . $this->conn->error);
        }
        return $promos;
    }

    private function calculateDiscount($basePrice, $promoData) {
        $discount = 0;
        if ($promoData) {
            $type = strtolower(trim($promoData['discount_type']));
            $value = (float) preg_replace('/[^0-9.]/', '', $promoData['discount_value']);

            if (in_array($type, ['flat', 'rp', 'nominal', 'uang'])) {
                $discount = $value;
            } elseif (in_array($type, ['percent', 'persen', '%'])) {
                $discount = ($basePrice * $value) / 100;
            }
        }
        return $discount;
    }

    public function createMember($data, $allPackages, $activePromotions) {
        $full_name = $data['full_name'];
        $phone = $data['phone'];
        $address = $data['address'];
        $email = $data['email'] ?? null;
        $age = $data['age'] ?? null;
        $package_id = (int)$data['package_id_main'];
        $promo_id = !empty($data['promo_id_main']) ? (int)$data['promo_id_main'] : null;

        $join_date = date('Y-m-d');
        $status = 'active';

        $pkg_data = null;
        foreach ($allPackages as $p) {
            if ($p['id'] == $package_id) {
                $pkg_data = $p;
                break;
            }
        }
        if (!$pkg_data) {
            error_log("Member model: Invalid package ID ($package_id) provided for new member.");
            return false;
        }

        $duration = $pkg_data['duration_months'];
        $expired_date = date('Y-m-d', strtotime("+$duration months", strtotime($join_date)));

        $base_price = (float) $pkg_data['price'];
        $discount = 0;

        $promo_data = null;
        if (!empty($promo_id)) {
            foreach ($activePromotions as $pr) {
                if ($pr['id'] == $promo_id) {
                    $promo_data = $pr;
                    break;
                }
            }
            $discount = $this->calculateDiscount($base_price, $promo_data);
        }
        $final_price = max(0, $base_price - $discount);

        $stmt = $this->conn->prepare("INSERT INTO " . $this->table_name . " (full_name, phone, address, email, age, join_date, expired_date, package_id, promo_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            error_log("Member model prepare failed (createMember): " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("ssssisssis", $full_name, $phone, $address, $email, $age, $join_date, $expired_date, $package_id, $promo_id, $status);

        if ($stmt->execute()) {
            $member_id = $stmt->insert_id;
            $stmt->close();
            return ['member_id' => $member_id, 'base_price' => $base_price, 'discount' => $discount, 'final_price' => $final_price, 'member_name' => $full_name, 'package_name' => $pkg_data['name']];
        } else {
            error_log("Member model execute failed (createMember): " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function updateMember($id, $data) {
        $full_name = $data['full_name'];
        $phone = $data['phone'];
        $address = $data['address'];
        $email = $data['email'] ?? null;
        $age = $data['age'] ?? null;
        $package_id = (int)$data['package_id_main']; 
        $promo_id = !empty($data['promo_id_main']) ? (int)$data['promo_id_main'] : null;

        $stmt = $this->conn->prepare("UPDATE " . $this->table_name . " SET full_name=?, phone=?, address=?, email=?, age=?, package_id=?, promo_id=? WHERE id=?");
        if ($stmt === false) {
            error_log("Member model prepare failed (updateMember): " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param("ssssiiii", $full_name, $phone, $address, $email, $age, $package_id, $promo_id, $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Member model execute failed (updateMember): " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function extendMemberMembership($memberId, $packageId, $promoId, $allPackages, $activePromotions) {
        $current_member = $this->getById($memberId);
        if (!$current_member) {
            error_log("Member not found for extension (ID: $memberId).");
            return false;
        }
        $member_name = $current_member['full_name'];

        $pkg = null;
        foreach ($allPackages as $p) {
            if ($p['id'] == $packageId) {
                $pkg = $p;
                break;
            }
        }
        if (!$pkg) {
            error_log("Invalid package ID ($packageId) selected for extension.");
            return false;
        }
        $duration = $pkg['duration_months'];
        $base_price = (float) $pkg['price'];
        $package_name = $pkg['name'];

        $discount = 0;
        $promo_data = null;
        if (!empty($promoId)) {
            foreach ($activePromotions as $pr) {
                if ($pr['id'] == $promoId) {
                    $promo_data = $pr;
                    break;
                }
            }
            $discount = $this->calculateDiscount($base_price, $promo_data);
        }
        $final_price = max(0, $base_price - $discount);

        $today = new DateTime(date('Y-m-d'));
        $current_expired_date_obj = new DateTime($current_member['expired_date']);
        
        $start_date_for_extension_obj = ($current_expired_date_obj < $today) ? $today : $current_expired_date_obj;
        
        $start_date_for_extension_obj->modify("+$duration months");
        $new_expired_date = $start_date_for_extension_obj->format('Y-m-d');

        $updateMemberStmt = $this->conn->prepare("UPDATE " . $this->table_name . " SET expired_date = ?, package_id = ?, promo_id = ?, status = 'active' WHERE id = ?");
        if ($updateMemberStmt === false) {
            error_log("Member model prepare failed (extendMemberMembership update): " . $this->conn->error);
            return false;
        }
        $updateMemberStmt->bind_param("siii", $new_expired_date, $packageId, $promoId, $memberId);

        if ($updateMemberStmt->execute()) {
            $updateMemberStmt->close();
            return [
                'member_name' => $member_name,
                'package_name' => $package_name,
                'base_price' => $base_price,
                'discount' => $discount,
                'final_price' => $final_price,
                'member_id' => $memberId
            ];
        } else {
            error_log("Member model execute failed (extendMemberMembership update): " . $updateMemberStmt->error);
            $updateMemberStmt->close();
            return false;
        }
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM " . $this->table_name . " WHERE id = ?");
        if ($stmt === false) {
            error_log("Member model prepare failed (delete): " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Member model execute failed (delete): " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function getFilteredMembers($searchTerm = '', $filterStatus = '') {
        $sql = "SELECT m.*, p.name AS package_name
                FROM " . $this->table_name . " m
                JOIN " . $this->packages_table . " p ON m.package_id = p.id
                WHERE 1=1";

        $params = [];
        $types = "";

        if (!empty($searchTerm)) {
            $sql .= " AND (m.full_name LIKE ? OR m.phone LIKE ? OR m.email LIKE ?)";
            $params[] = '%' . $searchTerm . '%';
            $params[] = '%' . $searchTerm . '%';
            $params[] = '%' . $searchTerm . '%';
            $types .= "sss";
        }

        if (!empty($filterStatus)) {
            $sql .= " AND m.status = ?";
            $params[] = $filterStatus;
            $types .= "s";
        }

        $sql .= " ORDER BY m.id DESC";

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Member model prepare failed (getFilteredMembers): " . $this->conn->error);
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
}