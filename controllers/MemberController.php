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
        if (empty($formData['member_code']) || empty($formData['full_name']) || empty($formData['phone']) || empty($formData['address']) || empty($formData['package_id_main'])) {
            return ['success' => false, 'message' => "Please fill all required fields for adding a member."];
        }

        if ($this->memberModel->isMemberCodeExists($formData['member_code'])) {
            return ['success' => false, 'message' => 'Member Code already exists. Please choose a different one.'];
        }

        $allPackages = $this->memberModel->getMembershipPackages();
        $activePromotions = $this->memberModel->getActivePromotions();

        $memberCreationResult = $this->memberModel->createMember($formData, $allPackages, $activePromotions);

        if ($memberCreationResult) {
            $this->transactionModel->create([
                'transaction_type' => 'member_new',
                'label' => 'income',
                'description' => $memberCreationResult['member_code'] . " - " . $memberCreationResult['member_name'] . " (Package: " . $memberCreationResult['package_name'] . ")",
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
        if (empty($formData['member_code']) || empty($formData['full_name']) || empty($formData['phone']) || empty($formData['address']) || empty($formData['package_id_main'])) {
            return ['success' => false, 'message' => "Please fill all required fields for updating a member."];
        }

        if ($this->memberModel->isMemberCodeExistsForOtherMember($formData['member_code'], $memberId)) {
            return ['success' => false, 'message' => 'Member Code already exists for another member. Please choose a different one.'];
        }

        $userRole = $_SESSION['user_role'] ?? null;

        $updateData = [
            'member_code' => $formData['member_code'],
            'full_name' => $formData['full_name'],
            'phone' => $formData['phone'],
            'address' => $formData['address'],
            'email' => $formData['email'] ?? null,
            'age' => $formData['age'] ?? null,
            'package_id_main' => (int)$formData['package_id_main'],
            'promo_id_main' => !empty($formData['promo_id_main']) ? (int)$formData['promo_id_main'] : null,
        ];

        if ($userRole === 'owner') {
            if (isset($formData['join_date_modal'])) {
                $updateData['join_date'] = $formData['join_date_modal'];
            }
            if (isset($formData['expired_date_modal'])) {
                $updateData['expired_date'] = $formData['expired_date_modal'];
            }
        } else {
        }

        $result = $this->memberModel->updateMember($memberId, $updateData);

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
                'description' => $extensionResult['member_code'] . " - " . $extensionResult['member_name'] . " (Package: " . $extensionResult['package_name'] . ")",
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
        $orderBy = $getParams['order_by'] ?? 'member_code_asc';
        $limit = 50;
        $page = isset($getParams['page']) ? (int)$getParams['page'] : 1;
        $offset = ($page - 1) * $limit;

        $members = $this->memberModel->getFilteredMembers($searchTerm, $filterStatus, $orderBy, $limit, $offset);
        $totalMembers = $this->memberModel->getTotalFilteredMembers($searchTerm, $filterStatus);
        $totalPages = ceil($totalMembers / $limit);


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
            'filterStatus' => $filterStatus,
            'orderBy' => $orderBy,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalMembers' => $totalMembers
        ];
    }

    public function getSmallestAvailableMemberCode() {
        try {
            $existingCodes = $this->memberModel->getAllMemberCodes();
            $i = 1;
            while (true) {
                if (!in_array((string)$i, $existingCodes)) {
                    return (string)$i;
                }
                $i++;
            }
        } catch (Exception $e) {
            error_log("Error getting smallest available member code: " . $e->getMessage());
            return null;
        }
    }
}