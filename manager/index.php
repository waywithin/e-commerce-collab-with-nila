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
        SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) AS pending_providers,
        SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) AS featured_providers
    FROM service_providers
")->fetch() ?: ['total_providers' => 0, 'approved_providers' => 0, 'pending_providers' => 0, 'featured_providers' => 0];

// 2. Customers Count
$custStats = $db->query("SELECT COUNT(*) AS total_customers FROM users WHERE role = 'customer'")->fetch() ?: ['total_customers' => 0];

// 3. Products Count
$prodStats = $db->query("
    SELECT
        COUNT(*) AS total_products,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_products,
        SUM(CASE WHEN offer_enabled = 1 AND offer_end_at > NOW() THEN 1 ELSE 0 END) AS active_offers
    FROM products
")->fetch() ?: ['total_products' => 0, 'active_products' => 0, 'active_offers' => 0];

// 4. Financials from Commissions table
$financeStats = $db->query("
    SELECT 
        COUNT(DISTINCT order_id) AS total_orders,
        COALESCE(SUM(gross_amount), 0) AS total_volume,
        COALESCE(SUM(platform_commission_amount), 0) AS total_commission_earned,
        COALESCE(SUM(provider_payable_amount), 0) AS total_provider_payouts,
        COALESCE(SUM(CASE WHEN settlement_status = 'pending' THEN provider_payable_amount ELSE 0 END), 0) AS pending_settlements
    FROM commissions
")->fetch() ?: ['total_orders' => 0, 'total_volume' => 0, 'total_commission_earned' => 0, 'total_provider_payouts' => 0, 'pending_settlements' => 0];

// 5. Recent Platform Activity (Orders)
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
    SELECT sp.*, u.full_name, u.email, c.name AS category_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN categories c ON sp.category_id = c.id
    WHERE sp.approval_status = 'pending'
    ORDER BY sp.id ASC
")->fetchAll();

// 7. System Audit & Recent Activity Logs
$recentLogs = $db->query("
    SELECT a.*, u.full_name AS actor_name, u.email AS actor_email, u.role AS actor_role
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.id DESC
    LIMIT 8
")->fetchAll();

$hasAnalyticsPage = file_exists(__DIR__ . '/analytics.php');
$totalPendingProviders = (int)($provStats['pending_providers'] ?? 0);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">Platform Command Center</h1>
        <p style="font-size: 14px; color: #64748b;">Comprehensive overview of women entrepreneurs, marketplace transactions, moderation, and system activity.</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="<?php echo BASE_URL; ?>/manager/providers.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-users"></i> Manage Providers
            <?php if ($totalPendingProviders > 0): ?>
                <span class="badge" style="background: #f59e0b; color: #ffffff; padding: 2px 6px; font-size: 10px; border-radius: 10px;"><?php echo $totalPendingProviders; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo BASE_URL; ?>/manager/categories.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-tags"></i> Add Category
        </a>
    </div>
</div>

<!-- Quick-Action Navigation Cards -->
<div class="quick-actions-grid">
    <a href="<?php echo BASE_URL; ?>/manager/providers.php" class="action-card">
        <div class="action-card-icon primary">
            <i class="fa-solid fa-users-viewfinder"></i>
        </div>
        <div>
            <div class="action-card-title">Review Business Owners</div>
            <div class="action-card-desc">
                <?php if ($totalPendingProviders > 0): ?>
                    <strong style="color: #d97706;"><?php echo $totalPendingProviders; ?> awaiting review</strong>
                <?php else: ?>
                    All profiles up to date
                <?php endif; ?>
            </div>
        </div>
        <?php if ($totalPendingProviders > 0): ?>
            <span class="action-card-badge" style="background: #fef3c7; color: #92400e;"><?php echo $totalPendingProviders; ?> Pending</span>
        <?php endif; ?>
    </a>

    <a href="<?php echo BASE_URL; ?>/manager/products.php" class="action-card">
        <div class="action-card-icon info">
            <i class="fa-solid fa-box-open"></i>
        </div>
        <div>
            <div class="action-card-title">Moderate Offerings</div>
            <div class="action-card-desc">
                <?php echo (int)($prodStats['active_products'] ?? 0); ?> active / <?php echo (int)($prodStats['total_products'] ?? 0); ?> total catalog
            </div>
        </div>
    </a>

    <?php if ($hasAnalyticsPage): ?>
        <a href="<?php echo BASE_URL; ?>/manager/analytics.php" class="action-card">
            <div class="action-card-icon purple">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <div>
                <div class="action-card-title">Platform Analytics</div>
                <div class="action-card-desc">Ecosystem insights &amp; category trends</div>
            </div>
        </a>
    <?php else: ?>
        <div class="action-card" style="opacity: 0.85; cursor: default;">
            <div class="action-card-icon purple">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <div>
                <div class="action-card-title">Platform Analytics</div>
                <div class="action-card-desc">Ecosystem insights &amp; breakdown</div>
            </div>
            <span class="action-card-badge" style="background: #f3e8ff; color: #7e22ce;">Phase 1 Suite</span>
        </div>
    <?php endif; ?>

    <a href="<?php echo BASE_URL; ?>/manager/settings.php" class="action-card">
        <div class="action-card-icon warning">
            <i class="fa-solid fa-sliders"></i>
        </div>
        <div>
            <div class="action-card-title">System Settings</div>
            <div class="action-card-desc">Financial models &amp; gateway adapters</div>
        </div>
    </a>
</div>

<!-- Metrics Cards Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div>
            <div class="stat-value"><?php echo (int)($provStats['total_providers'] ?? 0); ?></div>
            <div class="stat-label">Women Entrepreneurs</div>
            <div style="font-size: 11.5px; color: #059669; margin-top: 3px;">
                <strong><?php echo (int)($provStats['approved_providers'] ?? 0); ?></strong> approved &bull;
                <span style="color: <?php echo ($totalPendingProviders > 0) ? '#d97706' : '#64748b'; ?>; font-weight: <?php echo ($totalPendingProviders > 0) ? '700' : 'normal'; ?>;">
                    <?php echo $totalPendingProviders; ?> pending
                </span>
            </div>
        </div>
        <div class="stat-icon primary">
            <i class="fa-solid fa-store"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value"><?php echo (int)($custStats['total_customers'] ?? 0); ?></div>
            <div class="stat-label">Registered Customers</div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 3px;">
                Direct buyers &amp; clients
            </div>
        </div>
        <div class="stat-icon info">
            <i class="fa-solid fa-users"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value"><?php echo (int)($prodStats['total_products'] ?? 0); ?></div>
            <div class="stat-label">Total Offerings</div>
            <div style="font-size: 11.5px; color: #8C3A27; margin-top: 3px;">
                <i class="fa-solid fa-bolt"></i> <?php echo (int)($prodStats['active_offers'] ?? 0); ?> active countdown deals
            </div>
        </div>
        <div class="stat-icon warning">
            <i class="fa-solid fa-box-open"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value"><?php echo format_price($financeStats['total_volume'] ?? 0); ?></div>
            <div class="stat-label">Gross Platform Volume</div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 3px;">
                Across <?php echo (int)($financeStats['total_orders'] ?? 0); ?> completed orders
            </div>
        </div>
        <div class="stat-icon success">
            <i class="fa-solid fa-money-bill-trend-up"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value" style="color: #732D1D;"><?php echo format_price($financeStats['total_commission_earned'] ?? 0); ?></div>
            <div class="stat-label">Platform Commission (10%)</div>
            <div style="font-size: 11.5px; color: #059669; margin-top: 3px;">
                Net marketplace retained revenue
            </div>
        </div>
        <div class="stat-icon primary">
            <i class="fa-solid fa-percent"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value" style="color: #d97706;"><?php echo format_price($financeStats['pending_settlements'] ?? 0); ?></div>
            <div class="stat-label">Pending Payouts to Providers</div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 3px;">
                Ready for bank disbursement
            </div>
        </div>
        <div class="stat-icon warning">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
    </div>
</div>

<!-- Pending Provider Review Section -->
<?php if (!empty($pendingList)): ?>
<div class="data-table-card" style="border-left: 4px solid #f59e0b; margin-bottom: 30px;">
    <div class="table-header-bar" style="background: #fffbeb;">
        <h3 style="font-size: 16px; margin: 0; color: #92400e; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-triangle-exclamation"></i> Pending Business Owner Applications (<?php echo count($pendingList); ?>)
        </h3>
        <a href="<?php echo BASE_URL; ?>/manager/providers.php" class="btn btn-outline btn-sm">Review All Applications</a>
    </div>
    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Business Name</th>
                    <th>Founder &amp; Contact</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>Registration Date</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingList as $pend): ?>
                    <tr>
                        <td>
                            <strong><?php echo e($pend['business_name']); ?></strong>
                            <?php if (!empty($pend['tagline'])): ?>
                                <div style="font-size: 11.5px; color: #64748b;"><?php echo e($pend['tagline']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?php echo e($pend['full_name']); ?></div>
                            <small style="color: #64748b;"><?php echo e($pend['email']); ?></small>
                        </td>
                        <td>
                            <span class="badge" style="background: #F1E0DB; color: #8C3A27; font-size: 11px;">
                                <?php echo e($pend['category_name'] ?? 'General'); ?>
                            </span>
                        </td>
                        <td><?php echo e($pend['city'] ?? 'India'); ?></td>
                        <td>
                            <span style="font-size: 12px; color: #64748b;">
                                <?php echo format_date($pend['created_at'], true); ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <a href="<?php echo BASE_URL; ?>/manager/providers.php?action=approve&id=<?php echo (int)$pend['id']; ?>" class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-check"></i> Approve
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Two-Column Layout: Recent Transactions & System Audit Trail -->
<div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; margin-bottom: 30px; align-items: flex-start;">

    <!-- Recent Transactions Table -->
    <div class="data-table-card" style="margin-bottom: 0;">
        <div class="table-header-bar">
            <h3 style="font-size: 16px; margin: 0; color: var(--text-heading);">Recent Marketplace Transactions</h3>
            <a href="<?php echo BASE_URL; ?>/manager/orders.php" class="btn btn-outline btn-sm">All Orders</a>
        </div>

        <?php if (empty($recentOrders)): ?>
            <div class="empty-state-box">
                <div class="empty-state-icon">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div class="empty-state-title">No Transactions Recorded</div>
                <div class="empty-state-desc">When customers purchase services and products, transactions and commission splits will appear here.</div>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Business Owner</th>
                            <th>Gross</th>
                            <th>Platform 10%</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $ro): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($ro['order_number']); ?></strong>
                                    <div style="font-size: 11px; color: #64748b;"><?php echo format_date($ro['created_at']); ?></div>
                                </td>
                                <td><?php echo e($ro['customer_name']); ?></td>
                                <td><strong><?php echo e($ro['business_name']); ?></strong></td>
                                <td><?php echo format_price($ro['total_amount']); ?></td>
                                <td style="color: #732D1D; font-weight: 700;">+<?php echo format_price($ro['platform_commission_amount'] ?? ($ro['total_amount'] * 0.10)); ?></td>
                                <td>
                                    <span class="badge" style="background: #d1fae5; color: #065f46; font-size: 11px;">
                                        <?php echo strtoupper(e($ro['order_status'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- System Audit & Recent Activity Section -->
    <div class="data-table-card" style="margin-bottom: 0;">
        <div class="table-header-bar">
            <h3 style="font-size: 16px; margin: 0; color: var(--text-heading); display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-shield-halved" style="color: #8C3A27;"></i> System Audit &amp; Activity
            </h3>
            <span style="font-size: 12px; color: #64748b;">Live Security Feed</span>
        </div>

        <?php if (empty($recentLogs)): ?>
            <div class="empty-state-box">
                <div class="empty-state-icon">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div class="empty-state-title">No Activity Logs Yet</div>
                <div class="empty-state-desc">System events, user logins, registrations, and administrative updates are automatically recorded here.</div>
            </div>
        <?php else: ?>
            <ul class="activity-list">
                <?php foreach ($recentLogs as $log):
                    $action = $log['action'] ?? '';
                    $iconClass = 'fa-circle-dot';
                    $theme = 'neutral';

                    if (str_contains($action, 'LOGIN')) {
                        $iconClass = 'fa-right-to-bracket';
                        $theme = 'info';
                    } elseif (str_contains($action, 'LOGOUT')) {
                        $iconClass = 'fa-right-from-bracket';
                        $theme = 'neutral';
                    } elseif (str_contains($action, 'REGISTER_PROVIDER')) {
                        $iconClass = 'fa-store';
                        $theme = 'primary';
                    } elseif (str_contains($action, 'REGISTER_CUSTOMER') || str_contains($action, 'REGISTER')) {
                        $iconClass = 'fa-user-plus';
                        $theme = 'success';
                    } elseif (str_contains($action, 'PRODUCT')) {
                        $iconClass = 'fa-box-open';
                        $theme = 'warning';
                    } elseif (str_contains($action, 'SETTINGS')) {
                        $iconClass = 'fa-sliders';
                        $theme = 'warning';
                    }
                ?>
                    <li class="activity-item">
                        <div class="activity-icon-wrap <?php echo $theme; ?>">
                            <i class="fa-solid <?php echo $iconClass; ?>"></i>
                        </div>
                        <div class="activity-body">
                            <div class="activity-title">
                                <?php echo e($log['details'] ?: $action); ?>
                            </div>
                            <div class="activity-meta">
                                <span>
                                    <i class="fa-solid fa-user"></i>
                                    <?php echo e($log['actor_name'] ?: ($log['actor_email'] ?: 'System / Guest')); ?>
                                    <?php if (!empty($log['actor_role'])): ?>
                                        <small>(<?php echo e(strtoupper($log['actor_role'])); ?>)</small>
                                    <?php endif; ?>
                                </span>
                                <?php if (!empty($log['ip_address'])): ?>
                                    <span><i class="fa-solid fa-network-wired"></i> <?php echo e($log['ip_address']); ?></span>
                                <?php endif; ?>
                                <span><i class="fa-solid fa-clock"></i> <?php echo format_date($log['created_at'], true); ?></span>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
