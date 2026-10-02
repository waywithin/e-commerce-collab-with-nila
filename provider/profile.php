<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Service Provider Studio & Business Profile Editor
 * Location: /provider/profile.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/uploader.php';

$pageTitle = 'Edit Business Profile';
include __DIR__ . '/includes/header.php';

// Fetch dynamic active categories
$categories = $db->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Security verification failed. Please try again.';
    } else {
        $businessName = sanitize_string($_POST['business_name'] ?? '');
        $tagline = sanitize_string($_POST['tagline'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $description = sanitize_textarea($_POST['description'] ?? '');
        $city = sanitize_string($_POST['city'] ?? '');
        $location = sanitize_string($_POST['location'] ?? '');
        $contactPhone = sanitize_string($_POST['contact_phone'] ?? '');
        $contactEmail = sanitize_email($_POST['contact_email'] ?? '');
        $bankAccountName = sanitize_string($_POST['bank_account_name'] ?? '');
        $bankAccountNumber = sanitize_string($_POST['bank_account_number'] ?? '');
        $bankIfsc = sanitize_string($_POST['bank_ifsc'] ?? '');

        if (empty($businessName) || $categoryId <= 0) {
            $errorMessage = 'Business name and category are required.';
        } else {
            $logoPath = $currentProvider['logo_image'];

            // Check if new logo uploaded
            if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
                $uploadRes = ImageUploader::upload($_FILES['logo_image'], 'providers');
                if (!$uploadRes['success']) {
                    $errorMessage = $uploadRes['error'];
                } else {
                    ImageUploader::deleteOldImage($currentProvider['logo_image']);
                    $logoPath = $uploadRes['path'];
                }
            }

            if (!$errorMessage) {
                $upStmt = $db->prepare("
                    UPDATE service_providers SET
                        business_name = :bname,
                        tagline = :tagline,
                        category_id = :cat_id,
                        description = :desc,
                        city = :city,
                        location = :loc,
                        contact_phone = :phone,
                        contact_email = :email,
                        logo_image = :logo,
                        bank_account_name = :b_acc_name,
                        bank_account_number = :b_acc_num,
                        bank_ifsc = :b_ifsc,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $upStmt->execute([
                    'bname'      => $businessName,
                    'tagline'    => $tagline,
                    'cat_id'     => $categoryId,
                    'desc'       => $description,
                    'city'       => $city,
                    'loc'        => $location,
                    'phone'      => $contactPhone,
                    'email'      => $contactEmail,
                    'logo'       => $logoPath,
                    'b_acc_name' => $bankAccountName,
                    'b_acc_num'  => $bankAccountNumber,
                    'b_ifsc'     => $bankIfsc,
                    'id'         => $providerId
                ]);

                log_activity(current_user_id(), 'UPDATE_PROVIDER_PROFILE', 'service_providers', $providerId, 'Updated profile for ' . $businessName);

                set_flash('success', 'Your business profile has been updated successfully!');
                header("Location: " . BASE_URL . "/provider/profile.php");
                exit;
            }
        }
    }
}
?>

<div style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">My Business Profile</h1>
        <p style="font-size: 14px; color: #64748b;">Keep your brand information, public contact channels, and payout details up-to-date.</p>
    </div>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?php echo e($errorMessage); ?></span>
        </div>
    <?php endif; ?>

    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 32px; box-shadow: var(--shadow-sm);">
        <form action="<?php echo BASE_URL; ?>/provider/profile.php" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>

            <h3 style="font-size: 16px; color: var(--secondary); border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-bottom: 20px;">
                1. Brand Presentation &amp; Showcase
            </h3>

            <!-- Logo Upload & Preview -->
            <div class="form-group">
                <label class="form-label">Business Logo / Founder Portrait</label>
                <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 10px;">
                    <img src="<?php echo e(get_image_url($currentProvider['logo_image'], 'provider')); ?>" alt="Current Logo" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #E8D4CE;">
                    <div style="flex: 1;">
                        <input type="file" name="logo_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <div class="form-hint">JPG, PNG, or WEBP (3MB max). Uploading a new image will update your profile.</div>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="bName">Business Name *</label>
                    <input type="text" id="bName" name="business_name" class="form-control" required value="<?php echo e($currentProvider['business_name']); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="bCat">Category *</label>
                    <select id="bCat" name="category_id" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>" <?php echo ((int)$currentProvider['category_id'] === (int)$cat['id']) ? 'selected' : ''; ?>>
                                <?php echo e($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="bTagline">Tagline / Motto</label>
                <input type="text" id="bTagline" name="tagline" class="form-control" placeholder="e.g. Artisanal eggless cakes crafted with love" value="<?php echo e($currentProvider['tagline'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="bDesc">Business Story &amp; Description</label>
                <textarea id="bDesc" name="description" class="form-control" style="min-height: 120px;"><?php echo e($currentProvider['description'] ?? ''); ?></textarea>
            </div>

            <h3 style="font-size: 16px; color: var(--secondary); border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin: 30px 0 20px;">
                2. Public Location &amp; Contact Information
            </h3>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="bCity">City</label>
                    <input type="text" id="bCity" name="city" class="form-control" value="<?php echo e($currentProvider['city'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="bLoc">Studio / Physical Address</label>
                    <input type="text" id="bLoc" name="location" class="form-control" value="<?php echo e($currentProvider['location'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="bPhone">Public Phone Number</label>
                    <input type="text" id="bPhone" name="contact_phone" class="form-control" value="<?php echo e($currentProvider['contact_phone'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="bEmail">Public Email Address</label>
                    <input type="email" id="bEmail" name="contact_email" class="form-control" value="<?php echo e($currentProvider['contact_email'] ?? ''); ?>">
                </div>
            </div>

            <h3 style="font-size: 16px; color: var(--secondary); border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin: 30px 0 20px;">
                3. Direct Bank Settlement Information
            </h3>

            <div class="form-group">
                <label class="form-label" for="accName">Account Holder Name</label>
                <input type="text" id="accName" name="bank_account_name" class="form-control" value="<?php echo e($currentProvider['bank_account_name'] ?? ''); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="accNum">Bank Account Number</label>
                    <input type="text" id="accNum" name="bank_account_number" class="form-control" value="<?php echo e($currentProvider['bank_account_number'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="accIfsc">Bank IFSC Code</label>
                    <input type="text" id="accIfsc" name="bank_ifsc" class="form-control" value="<?php echo e($currentProvider['bank_ifsc'] ?? ''); ?>">
                </div>
            </div>

            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px; font-size: 12.5px; color: #1e40af; margin-bottom: 24px;">
                <i class="fa-solid fa-shield-halved"></i>
                <strong>Notice:</strong> Payments for all customer bookings are processed through the platform's official payment gateway. Personal UPI QR codes are strictly prohibited to ensure buyer protection and automated commission settlements.
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                <i class="fa-solid fa-floppy-disk"></i> Update Business Profile
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

