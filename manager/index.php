<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Manager Command Center Dashboard
 * Location: /manager/index.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Manager Dashboard';
include __DIR__ . '/includes/header.php';

// 1. Providers Counts
$provStats = $db->query("
    SELECT 
        COUNT(*) AS total_providers,
        SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) AS approved_providers,
        SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) AS pending_providers
    FROM service_providers
")->fetch();

// 2. Customers Count
$custStats = $db->query("SELECT COUNT(*) AS total_customers FROM users WHERE role = 'customer'")->fetch();

// 3. Products Count
$prodStats = $db->query("SELECT COUNT(*) AS total_products, SUM(CASE WHEN offer_enabled = 1 AND offer_end_at > NOW() THEN 1 ELSE 0 END) AS active_offers FROM products")->fetch();

// 4. Financials from Commissions table
$financeStats = $db->query("
    SELECT 
        COUNT(DISTINCT order_id) AS total_orders,
        COALESCE(SUM(gross_amount), 0) AS total_volume,
        COALESCE(SUM(platform_commission_amount), 0) AS total_commission_earned,
        COALESCE(SUM(provider_payable_amount), 0) AS total_provider_payouts,
        COALESCE(SUM(CASE WHEN settlement_status = 'pending' THEN provider_payable_amount ELSE 0 END), 0) AS pending_settlements
    FROM commissions
")->fetch();

// 5. Recent Platform Activity
$recentOrders = $db->query("
    SELECT o.*, p.title AS product_title, sp.business_name, c.platform_commission_amount, pay.payment_method
    FROM orders o
    JOIN products p ON o.product_id = p.id
    JOIN service_providers sp ON o.provider_id = sp.id
    LEFT JOIN commissions c ON o.id = c.order_id
    LEFT JOIN payments pay ON o.id = pay.order_id
    ORDER BY o.id DESC
    LIMIT 6
")->fetchAll();

// 6. Pending Providers Needing Review
$pendingList = $db->query("
    SELECT sp.*, u.full_name, u.email
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.approval_status = 'pending'
    ORDER BY sp.id ASC
")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">Platform Command Center</h1>
        <p style="font-size: 14px; color: #64748b;">Comprehensive overview of women entrepreneurs, marketplace transactions, and commission yields.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="<?php echo BASE_URL; ?>/manager/providers.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-users"></i> Manage Providers
        </a>
        <a href="<?php echo BASE_URL; ?>/manager/categories.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-tags"></i> Add Category
        </a>
    </div>
</div>

<!-- Metrics Cards Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div>
            <div class="stat-value"><?php echo (int)$provStats['total_providers']; ?></div>
            <div class="stat-label">Women Entrepreneurs</div>
            <div style="font-size: 11px; color: #059669; margin-top: 2px;">
                <?php echo (int)$provStats['approved_providers']; ?> approved &bull; <?php echo (int)$provStats['pending_providers']; ?> pending
            </div>
        </div>
        <div class="stat-icon primary">
            <i class="fa-solid fa-store"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value"><?php echo (int)$custStats['total_customers']; ?></div>
            <div class="stat-label">Registered Customers</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                Direct buyers &amp; clients
            </div>
        </div>
        <div class="stat-icon info">
            <i class="fa-solid fa-users"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value"><?php echo (int)$prodStats['total_products']; ?></div>
            <div class="stat-label">Total Offerings</div>
            <div style="font-size: 11px; color: #8C3A27; margin-top: 2px;">
                <i class="fa-solid fa-bolt"></i> <?php echo (int)$prodStats['active_offers']; ?> active countdown deals
            </div>
        </div>
        <div class="stat-icon warning">
            <i class="fa-solid fa-box-open"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value"><?php echo format_price($financeStats['total_volume']); ?></div>
            <div class="stat-label">Gross Platform Volume</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                Across <?php echo (int)$financeStats['total_orders']; ?> completed orders
            </div>
        </div>
        <div class="stat-icon success">
            <i class="fa-solid fa-money-bill-trend-up"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value" style="color: #732D1D;"><?php echo format_price($financeStats['total_commission_earned']); ?></div>
            <div class="stat-label">Platform Commission (10%)</div>
            <div style="font-size: 11px; color: #059669; margin-top: 2px;">
                Net marketplace retained revenue
            </div>
        </div>
        <div class="stat-icon primary">
            <i class="fa-solid fa-percent"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value" style="color: #d97706;"><?php echo format_price($financeStats['pending_settlements']); ?></div>
            <div class="stat-label">Pending Payouts to Providers</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                Ready for bank disbursement
            </div>
        </div>
        <div class="stat-icon warning">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
    </div>
</div>

<!-- Pending Provider Review Alert (If any) -->
<?php if (!empty($pendingList)): ?>
<div class="data-table-card" style="border-left: 4px solid #f59e0b; margin-bottom: 30px;">
    <div class="table-header-bar" style="background: #fffbeb;">
        <h3 style="font-size: 16px; margin: 0; color: #92400e;">
            <i class="fa-solid fa-triangle-exclamation"></i> Pending Entrepreneur Applications (<?php echo count($pendingList); ?>)
        </h3>
        <a href="<?php echo BASE_URL; ?>/manager/providers.php" class="btn btn-outline btn-sm">Review All</a>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Business Name</th>
                <th>Founder</th>
                <th>Location</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendingList as $pend): ?>
                <tr>
                    <td><strong><?php echo e($pend['business_name']); ?></strong></td>
                    <td><?php echo e($pend['full_name']); ?> (<?php echo e($pend['email']); ?>)</td>
                    <td><?php echo e($pend['city'] ?? 'India'); ?></td>
                    <td>
                        <a href="<?php echo BASE_URL; ?>/manager/providers.php?action=approve&id=<?php echo (int)$pend['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-check"></i> Approve
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Recent Transactions Table -->
<div class="data-table-card">
    <div class="table-header-bar">
        <h3 style="font-size: 16px; margin: 0; color: var(--text-heading);">Recent Marketplace Transactions</h3>
        <a href="<?php echo BASE_URL; ?>/manager/orders.php" class="btn btn-outline btn-sm">All Orders</a>
    </div>

    <?php if (empty($recentOrders)): ?>
        <div style="padding: 40px; text-align: center; color: #64748b;">
            <p>No transactions recorded yet.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Provider</th>
                    <th>Gross</th>
                    <th>Platform 10% Fee</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOrders as $ro): ?>
                    <tr>
                        <td><strong><?php echo e($ro['order_number']); ?></strong></td>
                        <td><?php echo e($ro['customer_name']); ?></td>
                        <td><strong><?php echo e($ro['business_name']); ?></strong></td>
                        <td><?php echo format_price($ro['total_amount']); ?></td>
                        <td style="color: #732D1D; font-weight: 700;">+<?php echo format_price($ro['platform_commission_amount'] ?? ($ro['total_amount'] * 0.10)); ?></td>
                        <td><span style="font-size: 12px; color: #64748b;"><?php echo e($ro['payment_method'] ?? 'Online'); ?></span></td>
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

