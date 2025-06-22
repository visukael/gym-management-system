<?php

require_once __DIR__ . '/../models/Member.php';
require_once __DIR__ . '/../models/Transaction.php';

class MemberController {
    private $memberModel;
    private $transactionModel;

    public function __construct($conn) {
        $this->memberModel = new Member($conn);
        $this->transactionModel = new Transaction($conn);
    }

    public function handleAddMember($formData, $userId) {
        if (empty($formData['full_name']) || empty($formData['phone']) || empty($formData['address']) || empty($formData['package_id_main'])) {
            return ['success' => false, 'message' => "Please fill all required fields for adding a member."];
        }

        $allPackages = $this->memberModel->getMembershipPackages();
        $activePromotions = $this->memberModel->getActivePromotions();

        $memberCreationResult = $this->memberModel->createMember($formData, $allPackages, $activePromotions);

        if ($memberCreationResult) {
            $this->transactionModel->create([
                'transaction_type' => 'member_new',
                'label' => 'income',
                'description' => "New member registration: " . $memberCreationResult['member_name'] . " (Package: " . $memberCreationResult['package_name'] . ")",
                'amount' => $memberCreationResult['base_price'],
                'discount' => $memberCreationResult['discount'],
                'final_amount' => $memberCreationResult['final_price'],
                'member_id' => $memberCreationResult['member_id'],
                'product_id' => null,
                'user_id' => $userId,
                'payment_method' => $formData['payment_method_new_member'] ?? 'cash'
            ]);
            return ['success' => true, 'message' => "Member added successfully!"];
        } else {
            return ['success' => false, 'message' => "Error adding member. Please check logs for details."];
        }
    }

    public function handleUpdateMember($memberId, $formData) {
        if (empty($formData['full_name']) || empty($formData['phone']) || empty($formData['address']) || empty($formData['package_id_main'])) {
            return ['success' => false, 'message' => "Please fill all required fields for updating a member."];
        }

        $result = $this->memberModel->updateMember($memberId, $formData);

        if ($result) {
            return ['success' => true, 'message' => "Member updated successfully!"];
        } else {
            return ['success' => false, 'message' => "Error updating member. Please check logs for details."];
        }
    }

    public function handleExtendMember($formData, $userId) {
        $memberIdToExtend = (int)($formData['member_id_extend'] ?? 0);
        $packageIdExtend = (int)($formData['package_id_extend'] ?? 0);
        $promoIdExtend = !empty($formData['promo_id_extend']) ? (int)$formData['promo_id_extend'] : null;

        if (empty($memberIdToExtend) || empty($packageIdExtend)) {
            return ['success' => false, 'message' => "Missing required fields for extension. Please select a member and a package."];
        }

        $allPackages = $this->memberModel->getMembershipPackages();
        $activePromotions = $this->memberModel->getActivePromotions();

        $extensionResult = $this->memberModel->extendMemberMembership($memberIdToExtend, $packageIdExtend, $promoIdExtend, $allPackages, $activePromotions);

        if ($extensionResult) {
            $this->transactionModel->create([
                'transaction_type' => 'member_extend',
                'label' => 'income',
                'description' => "Membership extension for: " . $extensionResult['member_name'] . " (Package: " . $extensionResult['package_name'] . ")",
                'amount' => $extensionResult['base_price'],
                'discount' => $extensionResult['discount'],
                'final_amount' => $extensionResult['final_price'],
                'member_id' => $extensionResult['member_id'],
                'product_id' => null,
                'user_id' => $userId,
                'payment_method' => $formData['payment_method_extend'] ?? 'cash'
            ]);
            return ['success' => true, 'message' => "Member membership extended successfully!"];
        } else {
            return ['success' => false, 'message' => "Error extending member membership. Please check logs for details."];
        }
    }

    public function handleDeleteMember($memberId) {
        $result = $this->memberModel->delete($memberId);
        if ($result) {
            return ['success' => true, 'message' => "Member deleted successfully!"];
        } else {
            return ['success' => false, 'message' => "Error deleting member. It might be referenced in other data."];
        }
    }

    public function getMemberViewData($getParams) {
        $this->memberModel->activateExpiredMemberships();

        $packages = $this->memberModel->getMembershipPackages();
        $promos = $this->memberModel->getActivePromotions();

        $searchTerm = $getParams['search'] ?? '';
        $filterStatus = $getParams['status'] ?? '';
        $members = $this->memberModel->getFilteredMembers($searchTerm, $filterStatus);

        $editData = null;
        if (isset($getParams['edit'])) {
            $editData = $this->memberModel->getById((int)$getParams['edit']);
        }

        return [
            'members' => $members,
            'packages' => $packages,
            'promos' => $promos,
            'editData' => $editData,
            'searchTerm' => $searchTerm,
            'filterStatus' => $filterStatus
        ];
    }
}