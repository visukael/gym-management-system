<?php
require_once __DIR__ . '/../models/Transaction.php';

class TransactionController {
    private $transactionModel;

    public function __construct($conn) {
        $this->transactionModel = new Transaction($conn);
    }

    public function createManual($data) {
        return $this->transactionModel->create([
            'transaction_type' => 'manual',
            'label' => $data['label'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'discount' => 0,
            'final_amount' => $data['amount'],
            'member_id' => null,
            'product_id' => null,
            'user_id' => $data['user_id']
        ]);
    }

    public function getAll() {
        return $this->transactionModel->all();
    }
}
