<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Women Entrepreneur / Service Provider Registration
 * Location: /register-provider.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    if (current_user_role() === 'provider') {
        header("Location: " . BASE_URL . "/provider/index.php");
        exit;
    }
}

$db = getDB();
$errorMessage = null;

// Fetch dynamic active categories
$categories = $db->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Security verification failed. Please try again.';
    } else {
        $fullName = sanitize_string($_POST['full_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_string($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $businessName = sanitize_string($_POST['business_name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $description = sanitize_textarea($_POST['description'] ?? '');
        $city = sanitize_string($_POST['city'] ?? '');
        $location = sanitize_string($_POST['location'] ?? '');

        if (empty($fullName) || empty($email) || empty($password) || empty($businessName) || $categoryId <= 0) {
            $errorMessage = 'Please complete all required fields marked with an asterisk (*).';
        } elseif (!$email) {
            $errorMessage = 'Please provide a valid email address.';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errorMessage = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
        } else {
            $res = register_provider(
                $fullName,
                $email,
                $phone,
                $password,
                $businessName,
                $categoryId,
                $description,
                $city,
                $location
            );

            if ($res['success']) {
                set_flash('success', 'Your provider registration is pending manager approval. You can sign in after it is approved.');
                header("Location: " . BASE_URL . "/login.php");
                exit;
            } else {
                $errorMessage = $res['message'];
            }
        }
    }
}

$pageTitle = 'Join as a Service Provider | Magal Creator Marketplace';
include __DIR__ . '/includes/header.php';
?>

<main class="container" style="padding: 60px 20px 100px;">
    <div style="max-width: 680px; margin: 0 auto; background: #ffffff; border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 40px; box-shadow: var(--shadow-md);">
        
        <div style="text-align: center; margin-bottom: 28px;">
            <div style="width: 56px; height: 56px; background: #F1E0DB; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 24px;">
                <i class="fa-solid fa-sparkles"></i>
            </div>
            <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 6px;">List Your Business on Magal Creator</h1>
            <p style="font-size: 14px; color: #64748b;">Reach thousands of customers, showcase your offerings, and grow your brand</p>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo e($errorMessage); ?></span>
            </div>
        <?php endif; ?>

        <form action="<?php echo BASE_URL; ?>/register-provider.php" method="POST">
            <?php echo csrf_field(); ?>

            <h3 style="font-size: 16px; color: var(--text-heading); border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-bottom: 18px;">
                1. Founder Information
            </h3>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="provFullName">Founder Full Name *</label>
                    <input type="text" id="provFullName" name="full_name" class="form-control" required placeholder="e.g. Ayesha Fatima" value="<?php echo e($_POST['full_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="provEmail">Login Email Address *</label>
                    <input type="email" id="provEmail" name="email" class="form-control" required placeholder="ayesha@studio.com" value="<?php echo e($_POST['email'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="provPhone">Contact Phone Number *</label>
                    <input type="text" id="provPhone" name="phone" class="form-control" required placeholder="+91 98765 00000" value="<?php echo e($_POST['phone'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="provPassword">Studio Password *</label>
                    <input type="password" id="provPassword" name="password" class="form-control" required placeholder="Minimum 6 characters">
                </div>
            </div>

            <h3 style="font-size: 16px; color: var(--text-heading); border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin: 25px 0 18px;">
                2. Business &amp; Studio Details
            </h3>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="businessName">Business / Brand Name *</label>
                    <input type="text" id="businessName" name="business_name" class="form-control" required placeholder="e.g. Ayesha Henna Artistry" value="<?php echo e($_POST['business_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="businessCategory">Primary Category *</label>
                    <select id="businessCategory" name="category_id" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>" <?php echo ((int)($_POST['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : ''; ?>>
                                <?php echo e($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="businessCity">City *</label>
                    <input type="text" id="businessCity" name="city" class="form-control" required placeholder="e.g. Bengaluru" value="<?php echo e($_POST['city'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="businessLocation">Studio Address / Location</label>
                    <input type="text" id="businessLocation" name="location" class="form-control" placeholder="e.g. Koramangala 4th Block" value="<?php echo e($_POST['location'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="businessDesc">Business Story &amp; Description</label>
                <textarea id="businessDesc" name="description" class="form-control" placeholder="Tell customers about your expertise, craft, experience, and what makes your services special..."><?php echo e($_POST['description'] ?? ''); ?></textarea>
                <div class="form-hint">You can upload your logo, banner, and add offerings after registration.</div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 15px;">
                <i class="fa-solid fa-crown"></i> Complete Registration &amp; Open Studio
            </button>
        </form>

        <div style="margin-top: 24px; text-align: center; font-size: 13.5px; color: #64748b; border-top: 1px solid var(--border-color); padding-top: 20px;">
            Already have a provider account? 
            <a href="<?php echo BASE_URL; ?>/login.php" style="font-weight: 700; color: var(--primary);">Sign In</a>
        </div>

    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

