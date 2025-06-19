<?php
require_once __DIR__ . '/../models/Member.php';
require_once __DIR__ . '/../models/MembershipPackage.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Promotion.php';

class MemberController {
    private $memberModel;
    private $packageModel;
    private $transactionModel;
    private $promotionModel;

    public function __construct($conn) {
        $this->memberModel = new Member($conn);
        $this->packageModel = new MembershipPackage($conn);
        $this->transactionModel = new Transaction($conn);
        $this->promotionModel = new Promotion($conn);
    }

    public function addMember($data, $userId) {
        $package = $this->packageModel->getById($data['package_id']);
        $promo = $this->promotionModel->getActiveByPackage($data['package_id']);

        $amount = $package['price'];
        $discount = $promo ? (
            $promo['discount_type'] == 'percent' ? ($amount * $promo['discount_value'] / 100) : $promo['discount_value']
        ) : 0;

        $final = $amount - $discount;

        $this->memberModel->create($data);

        $transactionData = [
            'transaction_type' => 'member_new',
            'label' => 'income',
            'description' => 'Pendaftaran Member',
            'amount' => $amount,
            'discount' => $discount,
            'final_amount' => $final,
            'member_id' => null,
            'product_id' => null,
            'user_id' => $userId
        ];
        return $this->transactionModel->create($transactionData);
    }

    public function extendMember($memberId, $userId) {
        $member = $this->memberModel->getById($memberId);
        $package = $this->packageModel->getById($member['package_id']);
        $promo = $this->promotionModel->getActiveByPackage($member['package_id']);

        $old_expired = new DateTime($member['expired_date']);
        $new_expired = $old_expired->modify("+{$package['duration_months']} months")->format('Y-m-d');

        $this->memberModel->update($memberId, ['expired_date' => $new_expired] + $member);

        $amount = $package['price'];
        $discount = $promo ? (
            $promo['discount_type'] == 'percent' ? ($amount * $promo['discount_value'] / 100) : $promo['discount_value']
        ) : 0;

        $final = $amount - $discount;

        $transactionData = [
            'transaction_type' => 'member_extend',
            'label' => 'income',
            'description' => 'Perpanjangan Member',
            'amount' => $amount,
            'discount' => $discount,
            'final_amount' => $final,
            'member_id' => $memberId,
            'product_id' => null,
            'user_id' => $userId
        ];
        return $this->transactionModel->create($transactionData);
    }
}
