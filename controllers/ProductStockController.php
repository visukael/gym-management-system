<?php

require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/ProductStock.php';
require_once __DIR__ . '/../models/Transaction.php';

class ProductStockController {
    private $productModel;
    private $productStockEntryModel;
    private $transactionModel;

    public function __construct($conn) {
        $this->productModel = new Product($conn);
        $this->productStockEntryModel = new ProductStock($conn);
        $this->transactionModel = new Transaction($conn);
    }

    public function addStock($productId, $quantity, $buyPrice, $userId) {
        if ($productId <= 0 || $quantity <= 0 || $buyPrice < 0) {
            return ['success' => false, 'message' => "Invalid product ID, quantity, or buy price for adding stock."];
        }

        $product = $this->productModel->getById($productId);
        if (!$product) {
            return ['success' => false, 'message' => "Product not found."];
        }

        $stockEntryAdded = $this->productStockEntryModel->addStockEntry($productId, $quantity, $buyPrice);
        if (!$stockEntryAdded) {
            error_log("ProductStockController: Failed to log stock entry history for product ID #{$productId}.");
            return ['success' => false, 'message' => "Failed to log stock entry history."];
        }

        $stockUpdated = $this->productModel->addStock($productId, $quantity);
        if (!$stockUpdated) {
            error_log("ProductStockController: Failed to update main product stock for ID #{$productId} after entry was logged.");
            return ['success' => false, 'message' => "Failed to update product's main stock count after logging entry."];
        }

        if ($buyPrice > 0) {
            $transactionData = [
                'transaction_type' => 'stock_add',
                'label' => 'outcome',
                'description' => "Stock added: " . htmlspecialchars($product['name']) . " x{$quantity} (Total: Rp" . number_format($buyPrice, 0, ',', '.') . ")",
                'amount' => $buyPrice,
                'discount' => 0,
                'final_amount' => $buyPrice,
                'member_id' => null,
                'product_id' => $productId,
                'user_id' => $userId,
                'payment_method' => 'cash'
            ];
            $transactionCreated = $this->transactionModel->create($transactionData);
            if (!$transactionCreated) {
                error_log("ProductStockController: Failed to create transaction for stock addition product ID #{$productId}.");
                return ['success' => false, 'message' => "Stock added successfully, but failed to record transaction."];
            }
        }
        
        return ['success' => true, 'message' => "Stock for " . htmlspecialchars($product['name']) . " added successfully!"];
    }

    public function getStockHistory($limit = 10) {
        return $this->productModel->getRecentStockHistory($limit);
    }
}