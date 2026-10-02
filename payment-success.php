<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Payment Verification & Order Confirmation Receipt
 * Location: /payment-success.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/payment/PaymentService.php';

require_login();

$db = getDB();
$gatewayOrderId = sanitize_string($_REQUEST['gateway_order_id'] ?? $_REQUEST['order_id'] ?? '');

if (empty($gatewayOrderId)) {
    header("Location: " . BASE_URL . "/browse.php");
    exit;
}

// 1. Process payment verification and calculate commission
$paymentService = new PaymentService();
$verifyRes = $paymentService->processVerifiedPayment($gatewayOrderId, $_POST, $_POST['signature'] ?? '');

if (!$verifyRes['success']) {
    set_flash('danger', 'Payment verification failed: ' . $verifyRes['error']);
    header("Location: " . BASE_URL . "/payment-cancel.php?error=" . urlencode($verifyRes['error']));
    exit;
}

// 2. Fetch completed order details for receipt display
$stmt = $db->prepare("
    SELECT o.*, p.title AS product_title, p.cover_image, p.service_type,
           sp.business_name, sp.slug AS provider_slug, sp.contact_phone AS provider_phone, sp.city,
           pay.gateway_payment_id, pay.payment_method, pay.paid_at,
           c.platform_commission_amount, c.provider_payable_amount
    FROM orders o
    JOIN products p ON o.product_id = p.id
    JOIN service_providers sp ON o.provider_id = sp.id
    LEFT JOIN payments pay ON o.id = pay.order_id
    LEFT JOIN commissions c ON o.id = c.order_id
    WHERE o.id = :oid AND o.customer_id = :cid
    LIMIT 1
");
$stmt->execute([
    'oid' => $verifyRes['order_id'],
    'cid' => current_user_id()
]);
$order = $stmt->fetch();

if (!$order) {
    die("Receipt not accessible.");
}

$pageTitle = 'Booking Confirmed - Order #' . e($order['order_number']) . ' | Magal Creator';
include __DIR__ . '/includes/header.php';
?>

<main class="container" style="padding: 50px 20px 100px;">
    <div style="max-width: 680px; margin: 0 auto; background: #ffffff; border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 40px; box-shadow: var(--shadow-md);">
        
        <!-- Confirmation Icon & Header -->
        <div style="text-align: center; margin-bottom: 30px;">
            <div style="width: 72px; height: 72px; background: #ecfdf5; color: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 36px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.2);">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <span style="font-size: 13px; font-weight: 700; color: #059669; text-transform: uppercase; letter-spacing: 0.5px;">Payment Verified &amp; Confirmed</span>
            <h1 style="font-size: 26px; color: var(--text-heading); margin: 6px 0;">Thank You for Your Order!</h1>
            <p style="font-size: 14px; color: #64748b;">A confirmation has been sent to the entrepreneur. Your booking is secured.</p>
        </div>

        <!-- Receipt Card -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radius-md); padding: 24px; margin-bottom: 28px;">
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-bottom: 14px; font-size: 13.5px;">
                <div>
                    <span style="color: #64748b;">Order Number:</span>
                    <strong style="color: var(--text-heading); margin-left: 6px;"><?php echo e($order['order_number']); ?></strong>
                </div>
                <div>
                    <span style="color: #64748b;">Date:</span>
                    <strong style="color: var(--text-heading); margin-left: 6px;"><?php echo format_date($order['paid_at'] ?? $order['created_at'], true); ?></strong>
                </div>
            </div>

            <!-- Item Row -->
            <div style="display: flex; gap: 16px; align-items: center; margin-bottom: 18px;">
                <img src="<?php echo e(get_image_url($order['cover_image'], 'product')); ?>" alt="<?php echo e($order['product_title']); ?>" style="width: 64px; height: 64px; border-radius: 8px; object-fit: cover;">
                <div style="flex: 1;">
                    <h3 style="font-size: 16px; color: var(--text-heading); margin-bottom: 4px;"><?php echo e($order['product_title']); ?></h3>
                    <div style="font-size: 13px; color: #64748b;">
                        Entrepreneur: <strong><?php echo e($order['business_name']); ?></strong> (<?php echo e($order['city']); ?>)
                    </div>
                    <?php if (!empty($order['booking_date'])): ?>
                        <div style="font-size: 12.5px; color: var(--primary); font-weight: 600; margin-top: 2px;">
                            <i class="fa-solid fa-calendar-check"></i> Booked for: <?php echo date('D, d M Y', strtotime($order['booking_date'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 18px; font-weight: 800; color: var(--secondary);"><?php echo format_price($order['total_amount']); ?></div>
                    <div style="font-size: 12px; color: #64748b;">Qty: <?php echo (int)$order['quantity']; ?></div>
                </div>
            </div>

            <!-- Payment Details Breakdown -->
            <div style="border-top: 1px dashed #cbd5e1; padding-top: 14px; font-size: 13px; color: #475569; display: flex; flex-direction: column; gap: 6px;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Payment Method</span>
                    <strong><?php echo e($order['payment_method'] ?? 'Online Payment'); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Transaction Ref ID</span>
                    <code><?php echo e($order['gateway_payment_id'] ?? 'N/A'); ?></code>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Payment Status</span>
                    <span style="color: #059669; font-weight: 700;"><i class="fa-solid fa-check"></i> COMPLETED</span>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;">
            <a href="<?php echo BASE_URL; ?>/user/my-orders.php" class="btn btn-primary">
                <i class="fa-solid fa-bag-shopping"></i> View in My Bookings
            </a>
            <a href="<?php echo BASE_URL; ?>/browse.php" class="btn btn-outline">
                <i class="fa-solid fa-magnifying-glass"></i> Explore More Services
            </a>
            <button onclick="window.print()" class="btn btn-outline">
                <i class="fa-solid fa-print"></i> Print Receipt
            </button>
        </div>

    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

