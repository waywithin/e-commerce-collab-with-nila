<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Customer Bookings & Orders History
 * Location: /user/my-orders.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_customer();

$db = getDB();
$userId = current_user_id();

// Fetch all orders placed by this customer
$stmt = $db->prepare("
    SELECT o.*, p.title AS product_title, p.cover_image, p.service_type,
           sp.business_name, sp.slug AS provider_slug, sp.contact_phone, sp.city,
           pay.payment_status, pay.payment_method, pay.gateway_payment_id
    FROM orders o
    JOIN products p ON o.product_id = p.id
    JOIN service_providers sp ON o.provider_id = sp.id
    LEFT JOIN payments pay ON o.id = pay.order_id
    WHERE o.customer_id = :cid
    ORDER BY o.id DESC
");
$stmt->execute(['cid' => $userId]);
$orders = $stmt->fetchAll();

$pageTitle = 'My Bookings & Orders | Magal Creator';
include __DIR__ . '/../includes/header.php';
?>

<main class="container" style="padding: 40px 20px 80px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px;">
        <div>
            <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">My Bookings &amp; Orders</h1>
            <p style="font-size: 14px; color: #64748b;">Track your bookings, appointments, and verified payment receipts</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/browse.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus"></i> Book Another Service
        </a>
    </div>

    <?php if (empty($orders)): ?>
        <div style="text-align: center; padding: 70px 20px; background: #ffffff; border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
            <div style="width: 64px; height: 64px; background: #F1E0DB; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 26px;">
                <i class="fa-solid fa-bag-shopping"></i>
            </div>
            <h3 style="color: var(--text-heading); margin-bottom: 6px;">You haven't placed any bookings yet</h3>
            <p style="color: #64748b; margin-bottom: 24px;">Explore inspiring services and products created by women entrepreneurs.</p>
            <a href="<?php echo BASE_URL; ?>/browse.php" class="btn btn-primary">Discover Services Now</a>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php foreach ($orders as $order): ?>
                <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-sm);">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 14px; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                        <div style="font-size: 13.5px;">
                            <span style="color: #64748b;">Order #:</span>
                            <strong style="color: var(--text-heading);"><?php echo e($order['order_number']); ?></strong>
                            <span style="color: #cbd5e1; margin: 0 8px;">|</span>
                            <span style="color: #64748b;">Placed on:</span>
                            <span style="color: #334155; font-weight: 500;"><?php echo format_date($order['created_at'], true); ?></span>
                        </div>

                        <div>
                            <?php if ($order['order_status'] === 'confirmed'): ?>
                                <span class="badge" style="background: #d1fae5; color: #065f46; font-size: 12px; padding: 4px 10px;">
                                    <i class="fa-solid fa-circle-check"></i> Confirmed &amp; Paid
                                </span>
                            <?php elseif ($order['order_status'] === 'pending'): ?>
                                <span class="badge" style="background: #fef3c7; color: #92400e; font-size: 12px; padding: 4px 10px;">
                                    <i class="fa-solid fa-clock"></i> Payment Pending
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 12px; padding: 4px 10px;">
                                    <?php echo strtoupper($order['order_status']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="display: flex; gap: 18px; align-items: center; flex-wrap: wrap;">
                        <img src="<?php echo e(get_image_url($order['cover_image'], 'product')); ?>" alt="<?php echo e($order['product_title']); ?>" style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover;">
                        
                        <div style="flex: 1; min-width: 250px;">
                            <h3 style="font-size: 17px; margin-bottom: 4px;">
                                <a href="<?php echo BASE_URL; ?>/product-details.php?id=<?php echo (int)$order['product_id']; ?>" style="color: var(--text-heading);">
                                    <?php echo e($order['product_title']); ?>
                                </a>
                            </h3>
                            <div style="font-size: 13.5px; color: #64748b;">
                                Entrepreneur: <strong><a href="<?php echo BASE_URL; ?>/provider-profile.php?slug=<?php echo urlencode($order['provider_slug']); ?>" style="color: var(--primary);"><?php echo e($order['business_name']); ?></a></strong> (<?php echo e($order['city']); ?>)
                            </div>
                            <?php if (!empty($order['booking_date'])): ?>
                                <div style="font-size: 13px; color: #0284c7; font-weight: 600; margin-top: 4px;">
                                    <i class="fa-solid fa-calendar-day"></i> Appointment Date: <?php echo date('D, d M Y', strtotime($order['booking_date'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="text-align: right; min-width: 140px;">
                            <div style="font-size: 20px; font-weight: 800; color: var(--secondary);">
                                <?php echo format_price($order['total_amount']); ?>
                            </div>
                            <div style="font-size: 12px; color: #64748b;">
                                Qty: <?php echo (int)$order['quantity']; ?> &bull; Ref: <?php echo e($order['gateway_payment_id'] ?? 'Pending'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

