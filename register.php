<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Customer Registration Page
 * Location: /register.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Security token invalid. Please refresh the page.';
    } else {
        $name = sanitize_string($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_string($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            $errorMessage = 'Please complete all required fields.';
        } elseif (!$email) {
            $errorMessage = 'Please enter a valid email address.';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errorMessage = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        } elseif ($password !== $confirmPassword) {
            $errorMessage = 'Passwords do not match.';
        } else {
            $res = register_customer($name, $email, $phone, $password);
            if ($res['success']) {
                // Auto-login newly registered customer
                attempt_login($email, $password);
                set_flash('success', 'Your account has been created successfully! Welcome to Magal Creator.');
                header("Location: " . BASE_URL . "/index.php");
                exit;
            } else {
                $errorMessage = $res['message'];
            }
        }
    }
}

$pageTitle = 'Create Customer Account | Magal Creator';
include __DIR__ . '/includes/header.php';
?>

<main class="container" style="padding: 60px 20px 100px;">
    <div style="max-width: 500px; margin: 0 auto; background: #ffffff; border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 40px; box-shadow: var(--shadow-md);">
        
        <div style="text-align: center; margin-bottom: 28px;">
            <div style="width: 50px; height: 50px; background: #F1E0DB; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 22px;">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h1 style="font-size: 24px; color: var(--text-heading); margin-bottom: 6px;">Join Magal Creator</h1>
            <p style="font-size: 13.5px; color: #64748b;">Discover, book, and support inspiring women entrepreneurs</p>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo e($errorMessage); ?></span>
            </div>
        <?php endif; ?>

        <form action="<?php echo BASE_URL; ?>/register.php" method="POST">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label class="form-label" for="regName">Full Name *</label>
                <input type="text" id="regName" name="name" class="form-control" required placeholder="e.g. Priya Nair" value="<?php echo e($_POST['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="regEmail">Email Address *</label>
                <input type="email" id="regEmail" name="email" class="form-control" required placeholder="name@example.com" value="<?php echo e($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="regPhone">Phone Number</label>
                <input type="text" id="regPhone" name="phone" class="form-control" placeholder="+91 98765 43210" value="<?php echo e($_POST['phone'] ?? ''); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="regPass">Password *</label>
                    <input type="password" id="regPass" name="password" class="form-control" required placeholder="Min 6 characters">
                </div>
                <div class="form-group">
                    <label class="form-label" for="regConfirmPass">Confirm Password *</label>
                    <input type="password" id="regConfirmPass" name="confirm_password" class="form-control" required placeholder="Confirm password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px; padding: 12px;">
                <i class="fa-solid fa-sparkles"></i> Create Customer Account
            </button>
        </form>

        <div style="margin-top: 24px; text-align: center; font-size: 13.5px; color: #64748b; border-top: 1px solid var(--border-color); padding-top: 20px;">
            Already have an account? 
            <a href="<?php echo BASE_URL; ?>/login.php" style="font-weight: 700; color: var(--primary);">Sign In</a>
            <div style="margin-top: 8px;">
                Are you a woman business owner? 
                <a href="<?php echo BASE_URL; ?>/register-provider.php" style="font-weight: 700; color: var(--secondary);">Join as Service Provider</a>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

