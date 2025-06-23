<?php

require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/ProductStock.php';

class ProductController {
    private $productModel;
    private $transactionModel;
    private $productStockEntryModel;

    public function __construct($conn) {
        $this->productModel = new Product($conn);
        $this->transactionModel = new Transaction($conn);
        $this->productStockEntryModel = new ProductStock($conn);
    }

    public function handleProductSubmit($formData, $userId) {
        $id = $formData['id'] ?? '';
        $name = htmlspecialchars(trim($formData['name']));
        $description = htmlspecialchars(trim($formData['description']));
        $price = (float)$formData['price'];
        $stock = (int)$formData['stock'];
        $initial_buy_price = (float)($formData['initial_buy_price'] ?? 0);

        if (empty($name) || $price < 0 || $stock < 0) {
            return ['success' => false, 'message' => "Product name, price, and stock are required and cannot be negative."];
        }

        if ($id) {
            $result = $this->productModel->updateProduct($id, ['name' => $name, 'description' => $description, 'price' => $price, 'stock' => $stock]);
            if ($result) {
                return ['success' => true, 'message' => "Product updated successfully!"];
            } else {
                return ['success' => false, 'message' => "Error updating product. Please check logs for details."];
            }
        } else {
            $newProductId = $this->productModel->createProduct(['name' => $name, 'description' => $description, 'price' => $price, 'stock' => $stock]);

            if ($newProductId) {
                if ($stock > 0 && $initial_buy_price > 0) {
                    $stockAddResponse = $this->addStockInternal($newProductId, $stock, $initial_buy_price, $userId);
                    if (!$stockAddResponse['success']) {
                        error_log("ProductController: Failed to record initial stock transaction/entry for new product ID #{$newProductId}. Message: " . $stockAddResponse['message']);
                    }
                }
                return ['success' => true, 'message' => "Product added successfully!"];
            } else {
                return ['success' => false, 'message' => "Error adding product. Please check logs for details."];
            }
        }
    }

    private function addStockInternal($productId, $quantity, $buyPrice, $userId) {
        $product = $this->productModel->getById($productId);
        if (!$product) {
            return ['success' => false, 'message' => "Product not found for stock addition."];
        }

        $stockEntryAdded = $this->productStockEntryModel->addStockEntry($productId, $quantity, $buyPrice);
        if (!$stockEntryAdded) {
            error_log("ProductController (Internal): Failed to log stock entry history for product ID #{$productId}.");
            return ['success' => false, 'message' => "Failed to log stock entry history."];
        }

        $stockUpdated = $this->productModel->addStock($productId, $quantity);
        if (!$stockUpdated) {
            error_log("ProductController (Internal): Failed to update main product stock for ID #{$productId} after entry was logged.");
            return ['success' => false, 'message' => "Failed to update product's main stock count."];
        }

        if ($buyPrice > 0) {
            $transactionData = [
                'transaction_type' => 'stock_add',
                'label' => 'outcome',
                'description' => htmlspecialchars($product['name']) . " x{$quantity} (Total: Rp" . number_format($buyPrice, 0, ',', '.') . ")",
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
                error_log("ProductController (Internal): Failed to create transaction for stock addition product ID #{$productId}.");
                return ['success' => false, 'message' => "Stock added successfully, but failed to record transaction."];
            }
        }
        
        return ['success' => true, 'message' => "Stock for " . htmlspecialchars($product['name']) . " added successfully!"];
    }

    public function handleAddStock($formData, $userId) {
        $productId = (int)($formData['product_id'] ?? 0);
        $qty = (int)($formData['qty'] ?? 0);
        $buyPrice = (float)($formData['buy_price'] ?? 0);

        return $this->addStockInternal($productId, $qty, $buyPrice, $userId);
    }

    public function handleProcessSale($formData, $userId) {
        $selected_products_ids = $formData['selected_products'] ?? [];
        $quantities_from_form = $formData['qty'] ?? [];
        $payment_method_sale = htmlspecialchars(trim($formData['payment_method_sale'] ?? 'cash'));

        $total_sale_amount = 0;
        $products_sold_details = [];

        foreach ($selected_products_ids as $product_id_str) {
            $product_id = (int)$product_id_str;
            $qty_sold = isset($quantities_from_form[$product_id]) ? (int)$quantities_from_form[$product_id] : 0;

            if ($product_id <= 0 || $qty_sold <= 0) {
                return ['success' => false, 'message' => "Invalid product or quantity for sale in selected items."];
            }

            $product = $this->productModel->getById($product_id);

            if ($product && $product['stock'] >= $qty_sold) {
                $subTotal = $product['price'] * $qty_sold;
                $total_sale_amount += $subTotal;
                $stockRemoved = $this->productModel->removeStock($product_id, $qty_sold);
                if (!$stockRemoved) {
                    error_log("ProductController: Failed to update stock for product ID #{$product_id} during multi-sale.");
                    return ['success' => false, 'message' => "Failed to update stock for " . htmlspecialchars($product['name']) . "."];
                }
                $products_sold_details[] = htmlspecialchars($product['name']) . " x" . $qty_sold;
            } else {
                return ['success' => false, 'message' => "Not enough stock for " . htmlspecialchars($product['name']) . " or product not found. Available: " . ($product['stock'] ?? 0)];
            }
        }

        if ($total_sale_amount > 0) {
            $desc = "Sale: " . implode(', ', $products_sold_details);
            $transactionCreated = $this->transactionModel->create([
                'transaction_type' => 'product_sale',
                'label' => 'income',
                'description' => $desc,
                'amount' => $total_sale_amount,
                'discount' => 0,
                'final_amount' => $total_sale_amount,
                'member_id' => null,
                'product_id' => null,
                'user_id' => $userId,
                'payment_method' => $payment_method_sale
            ]);
            if ($transactionCreated) {
                return ['success' => true, 'message' => "Product sale successfully processed!"];
            } else {
                error_log("ProductController: Failed to create transaction for multi-product sale.");
                return ['success' => false, 'message' => "Product sale processed, but failed to record transaction."];
            }
        } else {
            return ['success' => false, 'message' => "No products selected or quantities are invalid for sale."];
        }
    }

    public function handleMissingProduct($productId, $userId) {
        $product = $this->productModel->getById($productId);
        if ($product && $product['stock'] > 0) {
            if ($this->productModel->removeStock($productId, 1)) {
                $this->transactionModel->create([
                    'transaction_type' => 'missing_item',
                    'label' => 'outcome',
                    'description' => "Missing product: {$product['name']} (1 unit)",
                    'amount' => $product['price'],
                    'discount' => 0,
                    'final_amount' => $product['price'],
                    'member_id' => null,
                    'product_id' => $productId,
                    'user_id' => $userId,
                    'payment_method' => 'cash'
                ]);
                return ['success' => true, 'message' => htmlspecialchars($product['name']) . " marked as missing."];
            } else {
                return ['success' => false, 'message' => "Failed to mark product as missing (stock update failed)."];
            }
        } else {
            return ['success' => false, 'message' => "Product not found or no stock to mark as missing."];
        }
    }

    public function handleDeleteProduct($productId) {
        $deleteResult = $this->productModel->deleteProduct($productId);
        return $deleteResult;
    }

    public function getProductViewData($getParams) {
        $searchTerm = $getParams['search'] ?? '';
        $filterStock = $getParams['stock_status'] ?? '';

        $products = $this->productModel->getFilteredProducts($searchTerm, $filterStock);
        $stockLog = $this->productModel->getRecentStockHistory(10);

        $editData = null;
        if (isset($getParams['edit'])) {
            $editData = $this->productModel->getById((int)$getParams['edit']);
        }
        
        $addStockData = null;
        if (isset($getParams['add_stock'])) {
            $addStockData = $this->productModel->getById((int)$getParams['add_stock']);
        }

        return [
            'products' => $products,
            'stockLog' => $stockLog,
            'searchTerm' => $searchTerm,
            'filterStock' => $filterStock,
            'editData' => $editData,
            'addStockData' => $addStockData
        ];
    }
}