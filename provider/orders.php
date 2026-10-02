<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Provider Orders & Bookings Management
 * Location: /provider/orders.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Customer Bookings & Orders';
include __DIR__ . '/includes/header.php';

// Handle Order Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = sanitize_string($_POST['order_status'] ?? '');

        if (in_array($newStatus, ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'])) {
            $up = $db->prepare("UPDATE orders SET order_status = :st, updated_at = NOW() WHERE id = :oid AND provider_id = :pid");
            $up->execute(['st' => $newStatus, 'oid' => $orderId, 'pid' => $providerId]);
            set_flash('success', 'Order status updated to ' . strtoupper($newStatus) . '.');
        }
    }
}

// Fetch all bookings for this provider
$stmt = $db->prepare("
    SELECT o.*, p.title AS product_title, p.cover_image, p.service_type,
           pay.payment_status, pay.payment_method, pay.gateway_payment_id,
           c.platform_commission_amount, c.provider_payable_amount
    FROM orders o
    JOIN products p ON o.product_id = p.id
    LEFT JOIN payments pay ON o.id = pay.order_id
    LEFT JOIN commissions c ON o.id = c.order_id
    WHERE o.provider_id = :pid
    ORDER BY o.id DESC
");
$stmt->execute(['pid' => $providerId]);
$orders = $stmt->fetchAll();
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">Customer Bookings &amp; Orders</h1>
    <p style="font-size: 14px; color: #64748b;">Review customer appointments, contact clients, and fulfill verified orders.</p>
</div>

<div class="data-table-card">
    <?php if (empty($orders)): ?>
        <div style="padding: 60px 20px; text-align: center;">
            <div style="width: 60px; height: 60px; background: #F1E0DB; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 24px;">
                <i class="fa-solid fa-calendar-xmark"></i>
            </div>
            <h3 style="color: var(--text-heading); margin-bottom: 6px;">No bookings received yet</h3>
            <p style="color: #64748b;">When customers book your services or purchase your creations, they will appear here instantly.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer Details</th>
                    <th>Offering</th>
                    <th>Booking Date</th>
                    <th>Gross</th>
                    <th>Platform 10%</th>
                    <th>Your Payout</th>
                    <th>Fulfillment Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $ord): ?>
                    <tr>
                        <td>
                            <strong><?php echo e($ord['order_number']); ?></strong>
                            <div style="font-size: 11px; color: #64748b;"><?php echo format_date($ord['created_at']); ?></div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: var(--text-heading);"><?php echo e($ord['customer_name']); ?></div>
                            <div style="font-size: 12px; color: var(--primary);">
                                <i class="fa-solid fa-phone"></i> <?php echo e($ord['customer_phone']); ?>
                            </div>
                            <?php if (!empty($ord['customer_address'])): ?>
                                <small style="color: #64748b; display: block; max-width: 180px;"><?php echo e($ord['customer_address']); ?></small>
                            <?php endif; ?>
                            <?php if (!empty($ord['notes'])): ?>
                                <div style="font-size: 11.5px; color: #d97706; margin-top: 4px;">
                                    <strong>Note:</strong> <?php echo e($ord['notes']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight: 600;"><?php echo e($ord['product_title']); ?></div>
                            <small style="color: #64748b;">Qty: <?php echo (int)$ord['quantity']; ?></small>
                        </td>
                        <td>
                            <?php if (!empty($ord['booking_date'])): ?>
                                <span style="font-weight: 700; color: #0284c7;">
                                    <i class="fa-solid fa-calendar-check"></i> <?php echo date('d M Y', strtotime($ord['booking_date'])); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">Standard order</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo format_price($ord['total_amount']); ?></strong>
                        </td>
                        <td style="color: #732D1D;">
                            -<?php echo format_price($ord['platform_commission_amount'] ?? ($ord['total_amount'] * 0.10)); ?>
                        </td>
                        <td style="color: #059669; font-weight: 800; font-size: 14.5px;">
                            <?php echo format_price($ord['provider_payable_amount'] ?? ($ord['total_amount'] * 0.90)); ?>
                        </td>
                        <td>
                            <form action="<?php echo BASE_URL; ?>/provider/orders.php" method="POST" style="display: flex; gap: 6px; align-items: center;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="order_id" value="<?php echo (int)$ord['id']; ?>">
                                <select name="order_status" class="form-control" style="padding: 4px 8px; font-size: 12px; width: 110px;" onchange="this.form.submit()">
                                    <option value="pending" <?php echo ($ord['order_status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo ($ord['order_status'] === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="in_progress" <?php echo ($ord['order_status'] === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo ($ord['order_status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo ($ord['order_status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

