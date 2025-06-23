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

    public function getAllTransactionsForView($getParams) {
        $recordsPerPage = 50;
        $currentPage = isset($getParams['page']) ? (int)$getParams['page'] : 1;
        if ($currentPage < 1) $currentPage = 1;
        $offset = ($currentPage - 1) * $recordsPerPage;

        $filters = [
            'search' => $getParams['search'] ?? '',
            'type' => $getParams['type'] ?? '',
            'label' => $getParams['label'] ?? '',
            'sortBy' => $getParams['sort_by'] ?? '',
            'payment_method' => $getParams['payment_method'] ?? ''
        ];

        $transactions = $this->transactionModel->getFilteredTransactions($filters, $recordsPerPage, $offset);
        $totalRecords = $this->transactionModel->countFilteredTransactions($filters);
        $totalPages = ceil($totalRecords / $recordsPerPage);

        return [
            'transactions' => $transactions,
            'totalRecords' => $totalRecords,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'searchTerm' => $filters['search'],
            'filterType' => $filters['type'],
            'filterLabel' => $filters['label'],
            'sortBy' => $filters['sortBy'],
            'filterPaymentMethod' => $filters['payment_method']
        ];
    }
}