<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Unified Login Page (Manager, Service Provider, Customer)
 * Location: /login.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// If already logged in, redirect to respective dashboard
if (is_logged_in()) {
    $role = current_user_role();
    if ($role === 'manager') header("Location: " . BASE_URL . "/manager/index.php");
    elseif ($role === 'provider') header("Location: " . BASE_URL . "/provider/index.php");
    else header("Location: " . BASE_URL . "/index.php");
    exit;
}

$errorMessage = null;
$redirectUrl = sanitize_string($_GET['redirect'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($submittedToken)) {
        $errorMessage = 'Security token invalid. Please refresh the page and try again.';
    } else {
        $email = sanitize_string($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $errorMessage = 'Please enter both your email address and password.';
        } else {
            $loginRes = attempt_login($email, $password);
            if ($loginRes['success']) {
                set_flash('success', 'Welcome back, ' . htmlspecialchars($_SESSION['user_name']) . '!');

                if (!empty($redirectUrl) && !str_contains($redirectUrl, 'login.php')) {
                    header("Location: " . $redirectUrl);
                } else {
                    // Route to role-specific dashboard
                    if ($loginRes['role'] === 'manager') {
                        header("Location: " . BASE_URL . "/manager/index.php");
                    } elseif ($loginRes['role'] === 'provider') {
                        header("Location: " . BASE_URL . "/provider/index.php");
                    } else {
                        header("Location: " . BASE_URL . "/user/my-orders.php");
                    }
                }
                exit;
            } else {
                $errorMessage = $loginRes['message'];
            }
        }
    }
}

$pageTitle = 'Sign In to Your Account | Magal Creator';
include __DIR__ . '/includes/header.php';
?>

<main class="container" style="padding: 60px 20px 100px;">
    <div style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 40px; box-shadow: var(--shadow-md);">
        
        <div style="text-align: center; margin-bottom: 28px;">
            <div style="width: 50px; height: 50px; background: #F1E0DB; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 22px;">
                <i class="fa-solid fa-lock"></i>
            </div>
            <h1 style="font-size: 24px; color: var(--text-heading); margin-bottom: 6px;">Sign In to Magal Creator</h1>
            <p style="font-size: 13.5px; color: #64748b;">Access your customer orders, entrepreneur studio, or manager portal</p>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo e($errorMessage); ?></span>
            </div>
        <?php endif; ?>

        <form action="<?php echo BASE_URL; ?>/login.php<?php echo (!empty($redirectUrl) ? '?redirect='.urlencode($redirectUrl) : ''); ?>" method="POST">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label class="form-label" for="loginEmail">Email Address</label>
                <input type="email" id="loginEmail" name="email" class="form-control" required placeholder="name@example.com" value="<?php echo e($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <label class="form-label" for="loginPass">Password</label>
                </div>
                <input type="password" id="loginPass" name="password" class="form-control" required placeholder="Enter password">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px; padding: 12px;">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In
            </button>
        </form>

        <div style="margin-top: 24px; text-align: center; font-size: 13.5px; color: #64748b; border-top: 1px solid var(--border-color); padding-top: 20px;">
            New to Magal Creator? 
            <a href="<?php echo BASE_URL; ?>/register.php" style="font-weight: 700; color: var(--primary);">Create Customer Account</a>
            <div style="margin-top: 8px;">
                Are you an entrepreneur? 
                <a href="<?php echo BASE_URL; ?>/register-provider.php" style="font-weight: 700; color: var(--secondary);">Join as Service Provider</a>
            </div>
        </div>

    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

