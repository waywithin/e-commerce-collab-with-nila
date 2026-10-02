<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Manager Global Orders & Bookings Ledger
 * Location: /manager/orders.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Global Orders & Bookings';
include __DIR__ . '/includes/header.php';

$orders = $db->query("
    SELECT o.*, p.title AS product_title, sp.business_name, sp.slug AS provider_slug,
           c.platform_commission_amount, c.provider_payable_amount,
           pay.payment_status, pay.payment_method, pay.gateway_payment_id
    FROM orders o
    JOIN products p ON o.product_id = p.id
    JOIN service_providers sp ON o.provider_id = sp.id
    LEFT JOIN commissions c ON o.id = c.order_id
    LEFT JOIN payments pay ON o.id = pay.order_id
    ORDER BY o.id DESC
")->fetchAll();
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">Global Orders &amp; Bookings</h1>
    <p style="font-size: 14px; color: #64748b;">Complete transaction ledger showing gross payments, commission retentions, and fulfillment status.</p>
</div>

<div class="data-table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Service Provider</th>
                <th>Offering</th>
                <th>Gross</th>
                <th>Platform 10%</th>
                <th>Provider Payout</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $ord): ?>
                <tr>
                    <td>
                        <strong><?php echo e($ord['order_number']); ?></strong>
                        <div style="font-size: 11px; color: #64748b;"><?php echo e($ord['gateway_payment_id'] ?? 'Pending'); ?></div>
                    </td>
                    <td><?php echo format_date($ord['created_at']); ?></td>
                    <td>
                        <div><?php echo e($ord['customer_name']); ?></div>
                        <small style="color: #64748b;"><?php echo e($ord['customer_phone']); ?></small>
                    </td>
                    <td>
                        <a href="<?php echo BASE_URL; ?>/provider-profile.php?slug=<?php echo urlencode($ord['provider_slug']); ?>" target="_blank" style="color: var(--primary); font-weight: 600;">
                            <?php echo e($ord['business_name']); ?>
                        </a>
                    </td>
                    <td>
                        <div><?php echo e($ord['product_title']); ?></div>
                        <?php if (!empty($ord['booking_date'])): ?>
                            <small style="color: #0284c7;"><i class="fa-solid fa-calendar-day"></i> <?php echo date('d M Y', strtotime($ord['booking_date'])); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo format_price($ord['total_amount']); ?></strong></td>
                    <td style="color: #732D1D; font-weight: 700;">+<?php echo format_price($ord['platform_commission_amount'] ?? ($ord['total_amount'] * 0.10)); ?></td>
                    <td style="color: #059669; font-weight: 700;"><?php echo format_price($ord['provider_payable_amount'] ?? ($ord['total_amount'] * 0.90)); ?></td>
                    <td>
                        <span class="badge" style="background: #d1fae5; color: #065f46; font-size: 11px;">
                            <?php echo strtoupper($ord['order_status']); ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

