<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Add New Product / Service & Limited-Time Offer Setup
 * Location: /provider/product-add.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/uploader.php';

$pageTitle = 'Add New Offering';
include __DIR__ . '/includes/header.php';

// Fetch dynamic active categories
$categories = $db->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Security token invalid. Please refresh the page.';
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
            $errorMessage = 'Please fill in all required fields (Title, Category, Price, and Description).';
        } elseif (!isset($_FILES['cover_image']) || $_FILES['cover_image']['error'] === UPLOAD_ERR_NO_FILE) {
            $errorMessage = 'Please upload a cover image for this offering.';
        } elseif ($offerEnabled) {
            if (!$offerPrice || $offerPrice <= 0) {
                $errorMessage = 'Please provide a valid limited-time offer price.';
            } elseif ($offerPrice >= $price) {
                $errorMessage = 'The limited-time offer price must be lower than the regular price (' . format_price($price) . ').';
            } elseif (empty($offerStartAt) || empty($offerEndAt)) {
                $errorMessage = 'Please provide both start and end date/time for the limited-time offer.';
            } elseif (strtotime($offerEndAt) <= strtotime($offerStartAt)) {
                $errorMessage = 'The offer end time must be later than the offer start time.';
            }
        }

        // If validation passed, upload image and insert into database
        if (!$errorMessage) {
            $uploadRes = ImageUploader::upload($_FILES['cover_image'], 'products');
            if (!$uploadRes['success']) {
                $errorMessage = $uploadRes['error'];
            } else {
                $coverImage = $uploadRes['path'];
                $slug = slugify($title) . '-' . rand(100, 999);

                $stmt = $db->prepare("
                    INSERT INTO products 
                    (provider_id, category_id, title, slug, description, service_type, price, discount_price, cover_image, status, offer_enabled, offer_price, offer_start_at, offer_end_at)
                    VALUES 
                    (:pid, :cat_id, :title, :slug, :desc, :stype, :price, :dprice, :img, :status, :off_en, :off_price, :off_start, :off_end)
                ");

                $stmt->execute([
                    'pid'       => $providerId,
                    'cat_id'    => $categoryId,
                    'title'     => $title,
                    'slug'      => $slug,
                    'desc'      => $description,
                    'stype'     => $serviceType,
                    'price'     => $price,
                    'dprice'    => $discountPrice,
                    'img'       => $coverImage,
                    'status'    => $status,
                    'off_en'    => $offerEnabled,
                    'off_price' => $offerPrice,
                    'off_start' => $offerStartAt ? date('Y-m-d H:i:s', strtotime($offerStartAt)) : null,
                    'off_end'   => $offerEndAt ? date('Y-m-d H:i:s', strtotime($offerEndAt)) : null
                ]);

                log_activity(current_user_id(), 'CREATE_PRODUCT', 'products', (int)$db->lastInsertId(), 'Added new listing: ' . $title);

                set_flash('success', 'Offering "' . htmlspecialchars($title) . '" has been published successfully!');
                header("Location: " . BASE_URL . "/provider/products.php");
                exit;
            }
        }
    }
}
?>

<div style="max-width: 840px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">Add New Offering</h1>
        <p style="font-size: 14px; color: #64748b;">Create a new service package or bespoke physical product for the marketplace.</p>
    </div>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?php echo e($errorMessage); ?></span>
        </div>
    <?php endif; ?>

    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 32px; box-shadow: var(--shadow-sm);">
        <form action="<?php echo BASE_URL; ?>/provider/product-add.php" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>

            <!-- Basic Details -->
            <div class="form-group">
                <label class="form-label" for="prodTitle">Product / Service Title *</label>
                <input type="text" id="prodTitle" name="title" class="form-control" required placeholder="e.g. Bridal Mehendi Full Hand Package" value="<?php echo e($_POST['title'] ?? ''); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="prodCategory">Category *</label>
                    <select id="prodCategory" name="category_id" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>" <?php echo ((int)($_POST['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : ''; ?>>
                                <?php echo e($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="prodType">Type *</label>
                    <select id="prodType" name="service_type" class="form-control" required>
                        <option value="service" <?php echo (($_POST['service_type'] ?? '') === 'service') ? 'selected' : ''; ?>>Service / Appointment</option>
                        <option value="product" <?php echo (($_POST['service_type'] ?? '') === 'product') ? 'selected' : ''; ?>>Physical Handcrafted Product</option>
                        <option value="package" <?php echo (($_POST['service_type'] ?? '') === 'package') ? 'selected' : ''; ?>>Combined Package</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="prodPrice">Regular Price (₹) *</label>
                    <input type="number" step="0.01" min="1" id="prodPrice" name="price" class="form-control" required placeholder="2500.00" value="<?php echo e($_POST['price'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="prodDiscountPrice">Standard Discounted Price (₹) (Optional)</label>
                    <input type="number" step="0.01" min="0" id="prodDiscountPrice" name="discount_price" class="form-control" placeholder="Optional standard discount" value="<?php echo e($_POST['discount_price'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="prodCover">Cover Image *</label>
                <input type="file" id="prodCover" name="cover_image" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                <div class="form-hint">Accepted formats: JPG, PNG, WEBP. Maximum file size: 3MB.</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="prodDesc">Full Description &amp; Deliverables *</label>
                <textarea id="prodDesc" name="description" class="form-control" style="min-height: 120px;" required placeholder="Describe what is included, time required, materials used, deliverables, etc..."><?php echo e($_POST['description'] ?? ''); ?></textarea>
            </div>

            <!-- ============================================================== -->
            <!-- LIMITED-TIME OFFER & REAL-TIME COUNTDOWN SECTION -->
            <!-- ============================================================== -->
            <div style="background: #F7EEEB; border: 1.5px solid #E8D4CE; border-radius: var(--radius-md); padding: 22px; margin: 30px 0;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-fire" style="color: #8C3A27; font-size: 18px;"></i>
                        <h3 style="font-size: 16px; margin: 0; color: #881337;">Limited-Time Offer &amp; Countdown (Optional)</h3>
                    </div>
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #732D1D; cursor: pointer;">
                        <input type="checkbox" id="toggleOffer" name="offer_enabled" value="1" <?php echo (!empty($_POST['offer_enabled'])) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: #8C3A27;">
                        Enable Limited-Time Offer
                    </label>
                </div>

                <p style="font-size: 13px; color: #64748b; margin-bottom: 16px;">
                    When enabled, the customer-facing card will show an active deal banner with an automatic real-time countdown clock.
                </p>

                <div id="offerFields" style="display: <?php echo (!empty($_POST['offer_enabled'])) ? 'block' : 'none'; ?>;">
                    <div class="form-group">
                        <label class="form-label" for="offerPrice">Offer Price (₹) *</label>
                        <input type="number" step="0.01" min="1" id="offerPrice" name="offer_price" class="form-control" placeholder="e.g. 1999.00" value="<?php echo e($_POST['offer_price'] ?? ''); ?>">
                        <div class="form-hint">Must be less than regular price.</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="offerStart">Offer Starts (Date &amp; Time) *</label>
                            <input type="datetime-local" id="offerStart" name="offer_start_at" class="form-control" value="<?php echo e($_POST['offer_start_at'] ?? date('Y-m-d\TH:i')); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="offerEnd">Offer Ends (Date &amp; Time) *</label>
                            <input type="datetime-local" id="offerEnd" name="offer_end_at" class="form-control" value="<?php echo e($_POST['offer_end_at'] ?? date('Y-m-d\TH:i', strtotime('+24 hours'))); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="prodStatus">Listing Status</label>
                <select id="prodStatus" name="status" class="form-control">
                    <option value="active" <?php echo (($_POST['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active (Visible on marketplace)</option>
                    <option value="draft" <?php echo (($_POST['status'] ?? '') === 'draft') ? 'selected' : ''; ?>>Draft (Saved privately)</option>
                </select>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button type="submit" class="btn btn-primary btn-lg" style="flex: 1;">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Publish Offering
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

