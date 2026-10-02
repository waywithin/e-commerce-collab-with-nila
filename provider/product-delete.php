<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Delete Product Endpoint with Strict Ownership Verification
 * Location: /provider/product-delete.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/uploader.php';

require_provider();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed");
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Security validation failed.');
    header("Location: " . BASE_URL . "/provider/products.php");
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);

// Strict Ownership Verification (fails with 403 if provider doesn't own product)
$product = verify_product_ownership($productId);

$db = getDB();

try {
    // Delete product cover photo
    ImageUploader::deleteOldImage($product['cover_image']);

    // Delete product record
    $stmt = $db->prepare("DELETE FROM products WHERE id = :id AND provider_id = :pid");
    $stmt->execute([
        'id'  => $productId,
        'pid' => current_provider_id()
    ]);

    log_activity(current_user_id(), 'DELETE_PRODUCT', 'products', $productId, 'Deleted product: ' . $product['title']);

    set_flash('success', 'Offering "' . htmlspecialchars($product['title']) . '" has been removed.');
} catch (Exception $e) {
    set_flash('danger', 'Failed to delete offering: ' . $e->getMessage());
}

header("Location: " . BASE_URL . "/provider/products.php");
exit;

