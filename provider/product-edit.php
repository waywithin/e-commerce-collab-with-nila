<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Edit Product / Service & Limited-Time Offer
 * Location: /provider/product-edit.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/uploader.php';

$productId = (int)($_REQUEST['id'] ?? 0);
if ($productId <= 0) {
    header("Location: " . BASE_URL . "/provider/products.php");
    exit;
}

// 1. STRICT RESOURCE OWNERSHIP CHECK
// Guarantees Provider A cannot edit Provider B's product
$product = verify_product_ownership($productId);

$pageTitle = 'Edit Offering: ' . e($product['title']);
include __DIR__ . '/includes/header.php';

// Fetch dynamic active categories
$categories = $db->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Security verification failed. Please try again.';
    } else {
        $title = sanitize_string($_POST['title'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $serviceType = sanitize_string($_POST['service_type'] ?? 'service');
        $description = sanitize_textarea($_POST['description'] ?? '');
        $price = sanitize_amount($_POST['price'] ?? 0);
        $discountPrice = !empty($_POST['discount_price']) ? sanitize_amount($_POST['discount_price']) : null;
        $status = sanitize_string($_POST['status'] ?? 'active');

        // Limited-Time Offer fields
        $offerEnabled = !empty($_POST['offer_enabled']) ? 1 : 0;
        $offerPrice = !empty($_POST['offer_price']) ? sanitize_amount($_POST['offer_price']) : null;
        $offerStartAt = !empty($_POST['offer_start_at']) ? sanitize_string($_POST['offer_start_at']) : null;
        $offerEndAt = !empty($_POST['offer_end_at']) ? sanitize_string($_POST['offer_end_at']) : null;

        // Validation
        if (empty($title) || $categoryId <= 0 || $price <= 0 || empty($description)) {
            $errorMessage = 'Please complete all required fields.';
        } elseif ($offerEnabled) {
            if (!$offerPrice || $offerPrice <= 0) {
                $errorMessage = 'Please provide a valid limited-time offer price.';
            } elseif ($offerPrice >= $price) {
                $errorMessage = 'Offer price must be lower than the regular price (' . format_price($price) . ').';
            } elseif (empty($offerStartAt) || empty($offerEndAt)) {
                $errorMessage = 'Please select both start and end date/time for the offer.';
            } elseif (strtotime($offerEndAt) <= strtotime($offerStartAt)) {
                $errorMessage = 'Offer end time must be later than the offer start time.';
            }
        }

        if (!$errorMessage) {
            $coverImagePath = $product['cover_image'];

            // Check if user uploaded a replacement image
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $uploadRes = ImageUploader::upload($_FILES['cover_image'], 'products');
                if (!$uploadRes['success']) {
                    $errorMessage = $uploadRes['error'];
                } else {
                    // Delete old image file
                    ImageUploader::deleteOldImage($product['cover_image']);
                    $coverImagePath = $uploadRes['path'];
                }
            }

            if (!$errorMessage) {
                $stmt = $db->prepare("
                    UPDATE products SET 
                        category_id = :cat_id,
                        title = :title,
                        description = :desc,
                        service_type = :stype,
                        price = :price,
                        discount_price = :dprice,
                        cover_image = :img,
                        status = :status,
                        offer_enabled = :off_en,
                        offer_price = :off_price,
                        offer_start_at = :off_start,
                        offer_end_at = :off_end,
                        updated_at = NOW()
                    WHERE id = :id AND provider_id = :pid
                ");

                $stmt->execute([
                    'cat_id'    => $categoryId,
                    'title'     => $title,
                    'desc'      => $description,
                    'stype'     => $serviceType,
                    'price'     => $price,
                    'dprice'    => $discountPrice,
                    'img'       => $coverImagePath,
                    'status'    => $status,
                    'off_en'    => $offerEnabled,
                    'off_price' => $offerPrice,
                    'off_start' => $offerStartAt ? date('Y-m-d H:i:s', strtotime($offerStartAt)) : null,
                    'off_end'   => $offerEndAt ? date('Y-m-d H:i:s', strtotime($offerEndAt)) : null,
                    'id'        => $productId,
                    'pid'       => $providerId
                ]);

                log_activity(current_user_id(), 'UPDATE_PRODUCT', 'products', $productId, 'Updated offering: ' . $title);

                set_flash('success', 'Offering "' . htmlspecialchars($title) . '" updated successfully!');
                header("Location: " . BASE_URL . "/provider/products.php");
                exit;
            }
        }
    }
}
?>

<div style="max-width: 840px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">Edit Offering</h1>
        <p style="font-size: 14px; color: #64748b;">Modify your service details, cover photo, or limited-time offer settings.</p>
    </div>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?php echo e($errorMessage); ?></span>
        </div>
    <?php endif; ?>

    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 32px; box-shadow: var(--shadow-sm);">
        <form action="<?php echo BASE_URL; ?>/provider/product-edit.php?id=<?php echo (int)$productId; ?>" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label class="form-label" for="prodTitle">Product / Service Title *</label>
                <input type="text" id="prodTitle" name="title" class="form-control" required value="<?php echo e($_POST['title'] ?? $product['title']); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="prodCategory">Category *</label>
                    <select id="prodCategory" name="category_id" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>" <?php echo ((int)($_POST['category_id'] ?? $product['category_id']) === (int)$cat['id']) ? 'selected' : ''; ?>>
                                <?php echo e($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="prodType">Type *</label>
                    <select id="prodType" name="service_type" class="form-control" required>
                        <option value="service" <?php echo (($_POST['service_type'] ?? $product['service_type']) === 'service') ? 'selected' : ''; ?>>Service / Appointment</option>
                        <option value="product" <?php echo (($_POST['service_type'] ?? $product['service_type']) === 'product') ? 'selected' : ''; ?>>Physical Handcrafted Product</option>
                        <option value="package" <?php echo (($_POST['service_type'] ?? $product['service_type']) === 'package') ? 'selected' : ''; ?>>Combined Package</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="prodPrice">Regular Price (₹) *</label>
                    <input type="number" step="0.01" min="1" id="prodPrice" name="price" class="form-control" required value="<?php echo e($_POST['price'] ?? $product['price']); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="prodDiscountPrice">Standard Discounted Price (₹)</label>
                    <input type="number" step="0.01" min="0" id="prodDiscountPrice" name="discount_price" class="form-control" value="<?php echo e($_POST['discount_price'] ?? $product['discount_price']); ?>">
                </div>
            </div>

            <!-- Current Cover Preview & Replacement -->
            <div class="form-group">
                <label class="form-label">Cover Image</label>
                <div style="display: flex; gap: 16px; align-items: center; margin-bottom: 10px;">
                    <img src="<?php echo e(get_image_url($product['cover_image'], 'product')); ?>" alt="Current Cover" style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover; border: 1px solid #cbd5e1;">
                    <div style="font-size: 13px; color: #64748b;">
                        Current Cover Photo.<br>To replace, select a new file below.
                    </div>
                </div>
                <input type="file" name="cover_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                <div class="form-hint">Leave blank to keep existing image.</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="prodDesc">Full Description *</label>
                <textarea id="prodDesc" name="description" class="form-control" style="min-height: 120px;" required><?php echo e($_POST['description'] ?? $product['description']); ?></textarea>
            </div>

            <!-- LIMITED-TIME OFFER SETTINGS -->
            <?php 
                $isOfferOn = !empty($_POST['offer_enabled']) || (!isset($_POST['title']) && !empty($product['offer_enabled']));
                $currentStart = !empty($product['offer_start_at']) ? date('Y-m-d\TH:i', strtotime($product['offer_start_at'])) : date('Y-m-d\TH:i');
                $currentEnd = !empty($product['offer_end_at']) ? date('Y-m-d\TH:i', strtotime($product['offer_end_at'])) : date('Y-m-d\TH:i', strtotime('+24 hours'));
            ?>
            <div style="background: #F7EEEB; border: 1.5px solid #E8D4CE; border-radius: var(--radius-md); padding: 22px; margin: 30px 0;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-fire" style="color: #8C3A27; font-size: 18px;"></i>
                        <h3 style="font-size: 16px; margin: 0; color: #881337;">Limited-Time Offer Settings</h3>
                    </div>
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #732D1D; cursor: pointer;">
                        <input type="checkbox" id="toggleOffer" name="offer_enabled" value="1" <?php echo $isOfferOn ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: #8C3A27;">
                        Enable Limited-Time Offer
                    </label>
                </div>

                <div id="offerFields" style="display: <?php echo $isOfferOn ? 'block' : 'none'; ?>;">
                    <div class="form-group">
                        <label class="form-label" for="offerPrice">Offer Price (₹) *</label>
                        <input type="number" step="0.01" min="1" id="offerPrice" name="offer_price" class="form-control" value="<?php echo e($_POST['offer_price'] ?? $product['offer_price']); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="offerStart">Offer Starts *</label>
                            <input type="datetime-local" id="offerStart" name="offer_start_at" class="form-control" value="<?php echo e($_POST['offer_start_at'] ?? $currentStart); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="offerEnd">Offer Ends *</label>
                            <input type="datetime-local" id="offerEnd" name="offer_end_at" class="form-control" value="<?php echo e($_POST['offer_end_at'] ?? $currentEnd); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="prodStatus">Listing Status</label>
                <select id="prodStatus" name="status" class="form-control">
                    <option value="active" <?php echo (($_POST['status'] ?? $product['status']) === 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="draft" <?php echo (($_POST['status'] ?? $product['status']) === 'draft') ? 'selected' : ''; ?>>Draft</option>
                </select>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button type="submit" class="btn btn-primary btn-lg" style="flex: 1;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
                <a href="<?php echo BASE_URL; ?>/provider/products.php" class="btn btn-outline" style="padding: 14px 24px;">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('toggleOffer').addEventListener('change', function() {
    document.getElementById('offerFields').style.display = this.checked ? 'block' : 'none';
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

