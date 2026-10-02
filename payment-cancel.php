<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Payment Cancellation & Recovery Page
 * Location: /payment-cancel.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/security.php';

$error = sanitize_string($_GET['error'] ?? 'The payment process was cancelled or timed out.');
$pageTitle = 'Payment Cancelled | Magal Creator';
include __DIR__ . '/includes/header.php';
?>

<main class="container" style="padding: 70px 20px 100px; text-align: center;">
    <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 40px; box-shadow: var(--shadow-sm);">
        <div style="width: 70px; height: 70px; background: #F7EEEB; color: #8C3A27; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; font-size: 32px;">
            <i class="fa-solid fa-circle-exclamation"></i>
        </div>
        <h1 style="font-size: 24px; color: var(--text-heading); margin-bottom: 8px;">Payment Not Completed</h1>
        <p style="color: #64748b; font-size: 14px; line-height: 1.6; margin-bottom: 25px;">
            <?php echo e($error); ?> No amount has been deducted from your account. You can retry your order anytime.
        </p>

        <div style="display: flex; gap: 12px; justify-content: center;">
            <a href="<?php echo BASE_URL; ?>/browse.php" class="btn btn-primary">
                Return to Marketplace
            </a>
            <a href="<?php echo BASE_URL; ?>/user/my-orders.php" class="btn btn-outline">
                View My Orders
            </a>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

