<?php

require_once __DIR__ . '/../models/Promotion.php';
require_once __DIR__ . '/../models/MembershipPackage.php';

class PromotionController {
    private $promotionModel;
    private $packageModel;

    public function __construct($conn) {
        $this->promotionModel = new Promotion($conn);
        $this->packageModel = new MembershipPackage($conn);
    }

    public function handlePromotionSubmit($formData) {
        $id = $formData['id'] ?? '';
        $name = htmlspecialchars(trim($formData['name']));
        $type = htmlspecialchars(trim($formData['discount_type']));
        $value = (float)($formData['discount_value'] ?? 0);
        $start_date = htmlspecialchars(trim($formData['start_date']));
        $end_date = htmlspecialchars(trim($formData['end_date']));
        $package_id = !empty($formData['package_id']) ? (int)$formData['package_id'] : null;

        if (empty($name) || empty($start_date) || empty($end_date) || $value <= 0) {
            return ['success' => false, 'message' => "Please fill all required fields correctly."];
        }
        if (!in_array($type, ['flat', 'percent'])) {
            return ['success' => false, 'message' => "Invalid discount type. Must be 'flat' or 'percent'."];
        }
        if (strtotime($start_date) > strtotime($end_date)) {
            return ['success' => false, 'message' => "Start date cannot be after end date."];
        }

        $data = [
            'name' => $name,
            'discount_type' => $type,
            'discount_value' => $value,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'package_id' => $package_id
        ];

        if ($id) {
            $result = $this->promotionModel->updatePromotion((int)$id, $data);
            if ($result) {
                return ['success' => true, 'message' => "Promotion updated successfully!"];
            } else {
                return ['success' => false, 'message' => "Error updating promotion. Please check logs for details."];
            }
        } else {
            $result = $this->promotionModel->createPromotion($data);
            if ($result) {
                return ['success' => true, 'message' => "Promotion added successfully!"];
            } else {
                return ['success' => false, 'message' => "Error adding promotion. Please check logs for details."];
            }
        }
    }

    public function handleDeletePromotion($id) {
        if ($this->promotionModel->deletePromotion((int)$id)) {
            return ['success' => true, 'message' => "Promotion deleted successfully!"];
        } else {
            return ['success' => false, 'message' => "Error deleting promotion. It might be linked to other data. Please check logs for details."];
        }
    }

    public function getPromotionViewData($getParams) {
        $allPromotions = $this->promotionModel->getAllPromotions();
        $allPackages = $this->packageModel->getAll();

        $editData = null;
        if (isset($getParams['edit'])) {
            $editData = $this->promotionModel->getById((int)$getParams['edit']);
        }

        return [
            'allPromotions' => $allPromotions,
            'allPackages' => $allPackages,
            'editData' => $editData
        ];
    }
}