<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Service Provider Dashboard Overview
 * Location: /provider/index.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Studio Overview';
include __DIR__ . '/includes/header.php';

// Calculate provider statistics
// 1. Total & Active Listings
$pStmt = $db->prepare("
    SELECT 
        COUNT(*) AS total_listings,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_listings,
        SUM(CASE WHEN offer_enabled = 1 AND offer_end_at > NOW() THEN 1 ELSE 0 END) AS active_offers
    FROM products 
    WHERE provider_id = :pid
");
$pStmt->execute(['pid' => $providerId]);
$listingStats = $pStmt->fetch();

// 2. Financials from Commissions table (gross, platform commission, net payable)
$cStmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT order_id) AS total_orders,
        COALESCE(SUM(gross_amount), 0) AS total_gross,
        COALESCE(SUM(platform_commission_amount), 0) AS total_commission_paid,
        COALESCE(SUM(provider_payable_amount), 0) AS total_net_earnings,
        COALESCE(SUM(CASE WHEN settlement_status = 'pending' THEN provider_payable_amount ELSE 0 END), 0) AS pending_payouts
    FROM commissions 
    WHERE provider_id = :pid
");
$cStmt->execute(['pid' => $providerId]);
$financeStats = $cStmt->fetch();

// 3. Recent Bookings
$rStmt = $db->prepare("
    SELECT o.*, p.title AS product_title, pay.payment_status, c.provider_payable_amount
    FROM orders o
    JOIN products p ON o.product_id = p.id
    LEFT JOIN payments pay ON o.id = pay.order_id
    LEFT JOIN commissions c ON o.id = c.order_id
    WHERE o.provider_id = :pid
    ORDER BY o.id DESC
    LIMIT 5
");
$rStmt->execute(['pid' => $providerId]);
$recentOrders = $rStmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">Welcome back, <?php echo e($currentProvider['business_name']); ?>!</h1>
        <p style="font-size: 14px; color: #64748b;">Manage your offerings, view customer bookings, and track your settlements.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="<?php echo BASE_URL; ?>/provider/product-add.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus"></i> Add New Offering
        </a>
    </div>
</div>

<!-- Key Performance Metrics Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div>
            <div class="stat-value"><?php echo (int)($listingStats['total_listings'] ?? 0); ?></div>
            <div class="stat-label">Total Offerings</div>
            <div style="font-size: 11px; color: #059669; margin-top: 2px;">
                <?php echo (int)($listingStats['active_listings'] ?? 0); ?> currently active
            </div>
        </div>
        <div class="stat-icon primary">
            <i class="fa-solid fa-box-open"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value"><?php echo (int)($listingStats['active_offers'] ?? 0); ?></div>
            <div class="stat-label">Active Countdown Deals</div>
            <div style="font-size: 11px; color: #8C3A27; margin-top: 2px;">
                <i class="fa-solid fa-fire"></i> Live countdown promotions
            </div>
        </div>
        <div class="stat-icon warning">
            <i class="fa-solid fa-bolt"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value"><?php echo (int)($financeStats['total_orders'] ?? 0); ?></div>
            <div class="stat-label">Client Bookings</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                Gross: <?php echo format_price($financeStats['total_gross']); ?>
            </div>
        </div>
        <div class="stat-icon info">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value" style="color: #059669;"><?php echo format_price($financeStats['total_net_earnings']); ?></div>
            <div class="stat-label">Net Entrepreneur Earnings</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                After 10% platform fee: <?php echo format_price($financeStats['total_commission_paid']); ?>
            </div>
        </div>
        <div class="stat-icon success">
            <i class="fa-solid fa-hand-holding-dollar"></i>
        </div>
    </div>
</div>

<!-- Recent Orders Table -->
<div class="data-table-card">
    <div class="table-header-bar">
        <h3 style="font-size: 16px; margin: 0; color: var(--text-heading);">Recent Bookings &amp; Orders</h3>
        <a href="<?php echo BASE_URL; ?>/provider/orders.php" class="btn btn-outline btn-sm">View All Bookings</a>
    </div>

    <?php if (empty($recentOrders)): ?>
        <div style="padding: 40px; text-align: center; color: #64748b;">
            <p>No customer bookings yet. Your listings are live on the marketplace for customers to discover!</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Offering</th>
                    <th>Booking Date</th>
                    <th>Gross</th>
                    <th>Net Payout</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOrders as $ro): ?>
                    <tr>
                        <td><strong><?php echo e($ro['order_number']); ?></strong></td>
                        <td>
                            <div><?php echo e($ro['customer_name']); ?></div>
                            <small style="color: #64748b;"><?php echo e($ro['customer_phone']); ?></small>
                        </td>
                        <td><?php echo e($ro['product_title']); ?></td>
                        <td>
                            <?php if (!empty($ro['booking_date'])): ?>
                                <?php echo date('d M Y', strtotime($ro['booking_date'])); ?>
                            <?php else: ?>
                                <span style="color: #94a3b8;">Standard order</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo format_price($ro['total_amount']); ?></td>
                        <td style="color: #059669; font-weight: 700;">
                            <?php echo format_price($ro['provider_payable_amount'] ?? ($ro['total_amount'] * 0.9)); ?>
                        </td>
                        <td>
                            <span class="badge" style="background: #d1fae5; color: #065f46; font-size: 11px;">
                                <?php echo strtoupper($ro['order_status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

