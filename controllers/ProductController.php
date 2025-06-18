<?php
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Transaction.php';

class ProductController {
    private $productModel;
    private $transactionModel;

    public function __construct($conn) {
        $this->productModel = new Product($conn);
        $this->transactionModel = new Transaction($conn);
    }

    public function sellProduct($productId, $qty, $userId) {
        $product = $this->productModel->getById($productId);
        $total = $product['price'] * $qty;

        $this->productModel->updateStock($productId, $qty);

        $transactionData = [
            'transaction_type' => 'product',
            'label' => 'income',
            'description' => "Penjualan Produk: {$product['name']} x{$qty}",
            'amount' => $total,
            'discount' => 0,
            'final_amount' => $total,
            'member_id' => null,
            'product_id' => $productId,
            'user_id' => $userId
        ];

        return $this->transactionModel->create($transactionData);
    }
}
