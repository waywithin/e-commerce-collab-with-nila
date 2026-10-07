<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Manager Platform Analytics & Ecosystem Intelligence
 * Location: /manager/analytics.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Enforce manager authorization
require_manager();
$db = getDB();

$pageTitle = 'Platform Analytics';

// --------------------------------------------------------------------
// 1. TIME-PERIOD FILTER HANDLING (Strict Whitelist & Parameterized Bounds)
// --------------------------------------------------------------------
$period = sanitize_string($_GET['period'] ?? 'all');
$validPeriods = ['all', '30d', '7d', 'today'];
if (!in_array($period, $validPeriods, true)) {
    $period = 'all';
}

$periodLabels = [
    'all'   => 'All Time',
    '30d'   => 'Last 30 Days',
    '7d'    => 'Last 7 Days',
    'today' => 'Today'
];

$selectedPeriodLabel = $periodLabels[$period] ?? 'All Time';

// SQL Date Conditions for time-bounded datasets
$commDateWhere = "";
$orderDateWhere = "";
$payDateWhere = "";
$logsDateWhere = "";

if ($period === 'today') {
    $commDateWhere  = "WHERE c.created_at >= CURDATE()";
    $orderDateWhere = "WHERE o.created_at >= CURDATE()";
    $payDateWhere   = "WHERE pay.created_at >= CURDATE()";
    $logsDateWhere  = "WHERE a.created_at >= CURDATE()";
} elseif ($period === '7d') {
    $commDateWhere  = "WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $orderDateWhere = "WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $payDateWhere   = "WHERE pay.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $logsDateWhere  = "WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($period === '30d') {
    $commDateWhere  = "WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $orderDateWhere = "WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $payDateWhere   = "WHERE pay.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $logsDateWhere  = "WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

// --------------------------------------------------------------------
// 2. REAL FINANCIAL & TRANSACTION AGGREGATIONS (commissions, orders, payments)
// --------------------------------------------------------------------
$financeStats = $db->query("
    SELECT 
        COUNT(DISTINCT c.order_id) AS total_orders,
        COALESCE(SUM(c.gross_amount), 0) AS total_gmv,
        COALESCE(SUM(c.platform_commission_amount), 0) AS total_commission,
        COALESCE(SUM(c.provider_payable_amount), 0) AS total_payable,
        COALESCE(SUM(CASE WHEN c.settlement_status = 'settled' THEN c.provider_payable_amount ELSE 0 END), 0) AS settled_payable,
        COALESCE(SUM(CASE WHEN c.settlement_status = 'pending' THEN c.provider_payable_amount ELSE 0 END), 0) AS pending_payable,
        COALESCE(AVG(c.gross_amount), 0) AS aov
    FROM commissions c
    {$commDateWhere}
")->fetch() ?: [
    'total_orders'     => 0,
    'total_gmv'        => 0,
    'total_commission' => 0,
    'total_payable'    => 0,
    'settled_payable'  => 0,
    'pending_payable'  => 0,
    'aov'              => 0
];

// Order counts by lifecycle status
$orderStatusStats = $db->query("
    SELECT 
        o.order_status,
        COUNT(*) AS status_count,
        COALESCE(SUM(o.total_amount), 0) AS status_volume
    FROM orders o
    {$orderDateWhere}
    GROUP BY o.order_status
")->fetchAll();

$statusOrderMap = [
    'completed'   => ['count' => 0, 'volume' => 0.0],
    'in_progress' => ['count' => 0, 'volume' => 0.0],
    'confirmed'   => ['count' => 0, 'volume' => 0.0],
    'pending'     => ['count' => 0, 'volume' => 0.0],
    'cancelled'   => ['count' => 0, 'volume' => 0.0],
];
$totalOrdersInPeriod = 0;
foreach ($orderStatusStats as $row) {
    $st = $row['order_status'];
    if (isset($statusOrderMap[$st])) {
        $statusOrderMap[$st]['count'] = (int)$row['status_count'];
        $statusOrderMap[$st]['volume'] = (float)$row['status_volume'];
    }
    $totalOrdersInPeriod += (int)$row['status_count'];
}

// Payment method & gateway breakdown
$paymentBreakdown = $db->query("
    SELECT 
        COALESCE(NULLIF(TRIM(pay.gateway_name), ''), 'mock') AS gateway_name,
        COALESCE(NULLIF(TRIM(pay.payment_method), ''), 'Unknown') AS payment_method,
        pay.payment_status,
        COUNT(*) AS transaction_count,
        COALESCE(SUM(pay.amount), 0) AS total_amount
    FROM payments pay
    {$payDateWhere}
    GROUP BY gateway_name, payment_method, pay.payment_status
    ORDER BY total_amount DESC
")->fetchAll();

// --------------------------------------------------------------------
// 3. SERVICE PROVIDERS / BUSINESS OWNERS HEALTH & DISTRIBUTION
// --------------------------------------------------------------------
$providerStats = $db->query("
    SELECT 
        COUNT(*) AS total_providers,
        SUM(CASE WHEN sp.approval_status = 'approved' THEN 1 ELSE 0 END) AS approved_providers,
        SUM(CASE WHEN sp.approval_status = 'pending' THEN 1 ELSE 0 END) AS pending_providers,
        SUM(CASE WHEN sp.approval_status = 'rejected' THEN 1 ELSE 0 END) AS rejected_providers,
        SUM(CASE WHEN sp.is_featured = 1 THEN 1 ELSE 0 END) AS featured_providers,
        SUM(CASE WHEN sp.approval_status = 'pending' AND sp.created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS overdue_reviews
    FROM service_providers sp
")->fetch() ?: [
    'total_providers'    => 0,
    'approved_providers' => 0,
    'pending_providers'  => 0,
    'rejected_providers' => 0,
    'featured_providers' => 0,
    'overdue_reviews'    => 0
];

// Top providers ranked by real orders & gross sales (using clean aggregate subqueries to eliminate Cartesian joins)
$topProviders = $db->query("
    SELECT 
        sp.id,
        sp.business_name,
        sp.slug,
        sp.city,
        sp.approval_status,
        sp.is_featured,
        c.name AS category_name,
        COALESCE(ord_agg.order_count, 0) AS order_count,
        COALESCE(ord_agg.gross_revenue, 0) AS gross_revenue,
        COALESCE(comm_agg.platform_commission, 0) AS platform_commission
    FROM service_providers sp
    LEFT JOIN categories c ON sp.category_id = c.id
    LEFT JOIN (
        SELECT provider_id, COUNT(id) AS order_count, SUM(total_amount) AS gross_revenue
        FROM orders
        GROUP BY provider_id
    ) ord_agg ON sp.id = ord_agg.provider_id
    LEFT JOIN (
        SELECT provider_id, SUM(platform_commission_amount) AS platform_commission
        FROM commissions
        GROUP BY provider_id
    ) comm_agg ON sp.id = comm_agg.provider_id
    ORDER BY gross_revenue DESC, order_count DESC
    LIMIT 5
")->fetchAll();

// Provider geographic footprint (City distribution)
$cityDistribution = $db->query("
    SELECT 
        COALESCE(NULLIF(TRIM(city), ''), 'Unspecified') AS city_name,
        COUNT(*) AS provider_count
    FROM service_providers
    GROUP BY city_name
    ORDER BY provider_count DESC
    LIMIT 6
")->fetchAll();

// --------------------------------------------------------------------
// 4. CATALOG OFFERINGS & SERVICE TYPES
// --------------------------------------------------------------------
$catalogStats = $db->query("
    SELECT 
        COUNT(*) AS total_offerings,
        SUM(CASE WHEN p.status = 'active' THEN 1 ELSE 0 END) AS active_offerings,
        SUM(CASE WHEN p.status = 'draft' THEN 1 ELSE 0 END) AS draft_offerings,
        SUM(CASE WHEN p.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_offerings,
        SUM(CASE WHEN p.service_type = 'service' THEN 1 ELSE 0 END) AS type_service,
        SUM(CASE WHEN p.service_type = 'product' THEN 1 ELSE 0 END) AS type_product,
        SUM(CASE WHEN p.service_type = 'package' THEN 1 ELSE 0 END) AS type_package,
        SUM(CASE WHEN p.offer_enabled = 1 AND p.offer_end_at > NOW() THEN 1 ELSE 0 END) AS active_promotions,
        COALESCE(AVG(CASE WHEN p.status = 'active' THEN p.price ELSE NULL END), 0) AS avg_price,
        COALESCE(MIN(CASE WHEN p.status = 'active' THEN p.price ELSE NULL END), 0) AS min_price,
        COALESCE(MAX(CASE WHEN p.status = 'active' THEN p.price ELSE NULL END), 0) AS max_price
    FROM products p
")->fetch() ?: [
    'total_offerings'   => 0,
    'active_offerings'  => 0,
    'draft_offerings'   => 0,
    'inactive_offerings'=> 0,
    'type_service'      => 0,
    'type_product'      => 0,
    'type_package'      => 0,
    'active_promotions' => 0,
    'avg_price'         => 0,
    'min_price'         => 0,
    'max_price'         => 0
];

// Top offerings ranked by real bookings / orders (clean aggregate subquery)
$topOfferings = $db->query("
    SELECT 
        p.id,
        p.title,
        p.service_type,
        p.price,
        p.status,
        sp.business_name,
        cat.name AS category_name,
        COALESCE(ord_agg.order_count, 0) AS order_count,
        COALESCE(ord_agg.total_sales, 0) AS total_sales
    FROM products p
    JOIN service_providers sp ON p.provider_id = sp.id
    LEFT JOIN categories cat ON p.category_id = cat.id
    LEFT JOIN (
        SELECT product_id, COUNT(id) AS order_count, SUM(total_amount) AS total_sales
        FROM orders
        GROUP BY product_id
    ) ord_agg ON p.id = ord_agg.product_id
    ORDER BY order_count DESC, total_sales DESC
    LIMIT 5
")->fetchAll();

// --------------------------------------------------------------------
// 5. CATEGORY ECOSYSTEM BREAKDOWN (Using isolated subqueries to prevent Cartesian multiplication)
// --------------------------------------------------------------------
$categoryStats = $db->query("
    SELECT 
        c.id,
        c.name,
        c.slug,
        c.icon_class,
        COALESCE(prov_agg.provider_count, 0) AS provider_count,
        COALESCE(prod_agg.offering_count, 0) AS offering_count,
        COALESCE(prod_agg.active_offering_count, 0) AS active_offering_count,
        COALESCE(ord_agg.order_count, 0) AS order_count,
        COALESCE(ord_agg.category_volume, 0) AS category_volume
    FROM categories c
    LEFT JOIN (
        SELECT category_id, COUNT(*) AS provider_count
        FROM service_providers
        GROUP BY category_id
    ) prov_agg ON c.id = prov_agg.category_id
    LEFT JOIN (
        SELECT 
            category_id, 
            COUNT(*) AS offering_count,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_offering_count
        FROM products
        GROUP BY category_id
    ) prod_agg ON c.id = prod_agg.category_id
    LEFT JOIN (
        SELECT 
            p.category_id, 
            COUNT(o.id) AS order_count, 
            SUM(o.total_amount) AS category_volume
        FROM orders o
        JOIN products p ON o.product_id = p.id
        GROUP BY p.category_id
    ) ord_agg ON c.id = ord_agg.category_id
    WHERE c.status = 'active'
    ORDER BY category_volume DESC, offering_count DESC, provider_count DESC
")->fetchAll();

// --------------------------------------------------------------------
// 6. USERS & COMMUNITY ENGAGEMENT
// --------------------------------------------------------------------
$userStats = $db->query("
    SELECT 
        COUNT(*) AS total_users,
        SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) AS total_customers,
        SUM(CASE WHEN role = 'provider' THEN 1 ELSE 0 END) AS total_provider_users,
        SUM(CASE WHEN role = 'manager' THEN 1 ELSE 0 END) AS total_manager_users,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_users,
        SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) AS suspended_users
    FROM users
")->fetch() ?: [
    'total_users'          => 0,
    'total_customers'      => 0,
    'total_provider_users' => 0,
    'total_manager_users'  => 0,
    'active_users'         => 0,
    'suspended_users'      => 0
];

// Magal Circle Posts (Safe query with fallback)
$circleStats = ['total_circle_posts' => 0, 'active_contributors' => 0];
try {
    $cQuery = $db->query("
        SELECT 
            COUNT(*) AS total_circle_posts,
            COUNT(DISTINCT provider_id) AS active_contributors
        FROM magal_circle_posts
    ")->fetch();
    if ($cQuery) {
        $circleStats = $cQuery;
    }
} catch (Exception $e) {
    $circleStats = ['total_circle_posts' => 0, 'active_contributors' => 0];
}

// --------------------------------------------------------------------
// 7. SYSTEM AUDIT & ACTIVITY INTELLIGENCE
// --------------------------------------------------------------------
$logStats = $db->query("
    SELECT 
        COUNT(*) AS total_audit_events,
        COUNT(DISTINCT user_id) AS distinct_actors
    FROM activity_logs a
    {$logsDateWhere}
")->fetch() ?: ['total_audit_events' => 0, 'distinct_actors' => 0];

$topActions = $db->query("
    SELECT 
        a.action,
        COUNT(*) AS action_count
    FROM activity_logs a
    {$logsDateWhere}
    GROUP BY a.action
    ORDER BY action_count DESC
    LIMIT 6
")->fetchAll();

$recentAuditStream = $db->query("
    SELECT 
        a.*, 
        u.full_name AS actor_name, 
        u.role AS actor_role
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    {$logsDateWhere}
    ORDER BY a.id DESC
    LIMIT 6
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Header Title & Filter Controls -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-chart-line" style="color: #8C3A27;"></i>
            Platform Analytics &amp; Intelligence
        </h1>
        <p style="font-size: 14px; color: #64748b;">
            Accurate, real-time metrics generated exclusively from verified database transactions, catalog items, and audit logs.
        </p>
    </div>

    <!-- Time Period Selector -->
    <div style="display: flex; align-items: center; gap: 6px; background: #ffffff; padding: 4px; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <span style="font-size: 12px; font-weight: 700; color: #64748b; padding: 0 10px; text-transform: uppercase; letter-spacing: 0.5px;">
            <i class="fa-regular fa-calendar"></i> Period:
        </span>
        <a href="?period=all" class="btn btn-sm <?php echo ($period === 'all') ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 12px; padding: 4px 10px; border-radius: 4px;">All Time</a>
        <a href="?period=30d" class="btn btn-sm <?php echo ($period === '30d') ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 12px; padding: 4px 10px; border-radius: 4px;">Last 30 Days</a>
        <a href="?period=7d" class="btn btn-sm <?php echo ($period === '7d') ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 12px; padding: 4px 10px; border-radius: 4px;">Last 7 Days</a>
        <a href="?period=today" class="btn btn-sm <?php echo ($period === 'today') ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 12px; padding: 4px 10px; border-radius: 4px;">Today</a>
    </div>
</div>

<!-- ==========================================================================
     TOP-LEVEL REAL KPI CARDS
     ========================================================================== -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-bottom: 28px;">
    <!-- 1. Processed GMV -->
    <div class="stat-card">
        <div>
            <div class="stat-value" style="color: #0f172a;"><?php echo format_price((float)$financeStats['total_gmv']); ?></div>
            <div class="stat-label">Gross Merchandise Value</div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">
                <i class="fa-solid fa-clock-rotate-left"></i> <?php echo e($selectedPeriodLabel); ?>
            </div>
        </div>
        <div class="stat-icon primary">
            <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
    </div>

    <!-- 2. Platform Commission -->
    <div class="stat-card">
        <div>
            <div class="stat-value" style="color: #059669;"><?php echo format_price((float)$financeStats['total_commission']); ?></div>
            <div class="stat-label">Platform Commission</div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">
                Earned marketplace revenue (recorded splits)
            </div>
        </div>
        <div class="stat-icon success">
            <i class="fa-solid fa-hand-holding-dollar"></i>
        </div>
    </div>

    <!-- 3. Provider Payable -->
    <div class="stat-card">
        <div>
            <div class="stat-value" style="color: #d97706;"><?php echo format_price((float)$financeStats['total_payable']); ?></div>
            <div class="stat-label">Provider Payable Volume</div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">
                Pending: <strong><?php echo format_price((float)$financeStats['pending_payable']); ?></strong>
            </div>
        </div>
        <div class="stat-icon warning">
            <i class="fa-solid fa-building-columns"></i>
        </div>
    </div>

    <!-- 4. Total Orders & AOV -->
    <div class="stat-card">
        <div>
            <div class="stat-value" style="color: #0284c7;"><?php echo (int)$financeStats['total_orders']; ?></div>
            <div class="stat-label">Total Completed/Placed Orders</div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">
                Avg Order Value: <strong><?php echo format_price((float)$financeStats['aov']); ?></strong>
            </div>
        </div>
        <div class="stat-icon info">
            <i class="fa-solid fa-bag-shopping"></i>
        </div>
    </div>
</div>

<!-- Secondary Ecosystem KPI Summary -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 32px;">
    <!-- Active Providers -->
    <div class="stat-card" style="padding: 16px 20px;">
        <div>
            <div style="font-size: 20px; font-weight: 800; color: #8C3A27;">
                <?php echo (int)$providerStats['approved_providers']; ?> <span style="font-size: 12px; font-weight: 600; color: #64748b;">/ <?php echo (int)$providerStats['total_providers']; ?> Total</span>
            </div>
            <div class="stat-label" style="font-size: 11.5px;">Approved Business Owners</div>
            <div style="font-size: 11px; color: #d97706; margin-top: 2px;">
                <?php echo (int)$providerStats['pending_providers']; ?> awaiting review
            </div>
        </div>
        <div style="width: 38px; height: 38px; border-radius: 8px; background: #F1E0DB; color: #8C3A27; display: flex; align-items: center; justify-content: center; font-size: 16px;">
            <i class="fa-solid fa-users-viewfinder"></i>
        </div>
    </div>

    <!-- Live Offerings -->
    <div class="stat-card" style="padding: 16px 20px;">
        <div>
            <div style="font-size: 20px; font-weight: 800; color: #0284c7;">
                <?php echo (int)$catalogStats['active_offerings']; ?> <span style="font-size: 12px; font-weight: 600; color: #64748b;">/ <?php echo (int)$catalogStats['total_offerings']; ?> Total</span>
            </div>
            <div class="stat-label" style="font-size: 11.5px;">Live Marketplace Offerings</div>
            <div style="font-size: 11px; color: #059669; margin-top: 2px;">
                <?php echo (int)$catalogStats['active_promotions']; ?> active flash deals
            </div>
        </div>
        <div style="width: 38px; height: 38px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 16px;">
            <i class="fa-solid fa-boxes-stacked"></i>
        </div>
    </div>

    <!-- Registered Customers -->
    <div class="stat-card" style="padding: 16px 20px;">
        <div>
            <div style="font-size: 20px; font-weight: 800; color: #7e22ce;">
                <?php echo (int)$userStats['total_customers']; ?>
            </div>
            <div class="stat-label" style="font-size: 11.5px;">Registered Customers</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                <?php echo (int)$userStats['total_users']; ?> platform accounts
            </div>
        </div>
        <div style="width: 38px; height: 38px; border-radius: 8px; background: #f3e8ff; color: #7e22ce; display: flex; align-items: center; justify-content: center; font-size: 16px;">
            <i class="fa-solid fa-user-group"></i>
        </div>
    </div>

    <!-- Community Engagement -->
    <div class="stat-card" style="padding: 16px 20px;">
        <div>
            <div style="font-size: 20px; font-weight: 800; color: #059669;">
                <?php echo (int)$circleStats['total_circle_posts']; ?>
            </div>
            <div class="stat-label" style="font-size: 11.5px;">Circle Posts &amp; Discussions</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                <?php echo (int)$circleStats['active_contributors']; ?> active contributors
            </div>
        </div>
        <div style="width: 38px; height: 38px; border-radius: 8px; background: #d1fae5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 16px;">
            <i class="fa-solid fa-comments"></i>
        </div>
    </div>
</div>

<!-- ==========================================================================
     SECTION 1: ORDER LIFECYCLE & PAYMENT METHOD PERFORMANCE
     ========================================================================== -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 32px;">
    <!-- Order Lifecycle Distribution -->
    <div class="data-table-card" style="margin-bottom: 0;">
        <div class="table-header-bar">
            <div>
                <h3 style="font-size: 16px; color: var(--secondary); margin-bottom: 2px;">Order Status Lifecycle</h3>
                <p style="font-size: 12px; color: #64748b;">Distribution of orders in the selected period (<?php echo e($selectedPeriodLabel); ?>)</p>
            </div>
            <span class="badge" style="background: #f1f5f9; color: #475569; font-weight: 700;">
                <?php echo $totalOrdersInPeriod; ?> Total Orders
            </span>
        </div>
        
        <div style="padding: 24px;">
            <?php if ($totalOrdersInPeriod > 0): ?>
                <!-- Visual Multi-segment Distribution Bar -->
                <div style="display: flex; height: 16px; border-radius: 8px; overflow: hidden; margin-bottom: 20px; background: #f1f5f9;">
                    <?php 
                    $colors = [
                        'completed'   => '#059669',
                        'in_progress' => '#0284c7',
                        'confirmed'   => '#6366f1',
                        'pending'     => '#f59e0b',
                        'cancelled'   => '#ef4444'
                    ];
                    foreach ($statusOrderMap as $stKey => $stData): 
                        $pct = round(($stData['count'] / $totalOrdersInPeriod) * 100, 1);
                        if ($pct > 0):
                    ?>
                        <div style="width: <?php echo $pct; ?>%; background: <?php echo $colors[$stKey]; ?>;" title="<?php echo ucfirst(str_replace('_', ' ', $stKey)) . ': ' . $stData['count'] . ' (' . $pct . '%)'; ?>"></div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>

                <!-- Status Details Table -->
                <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 11.5px; text-transform: uppercase;">
                            <th style="padding: 8px 0; text-align: left;">Lifecycle Status</th>
                            <th style="padding: 8px 0; text-align: center;">Orders</th>
                            <th style="padding: 8px 0; text-align: right;">Share</th>
                            <th style="padding: 8px 0; text-align: right;">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $badgeClasses = [
                            'completed'   => 'badge-success',
                            'in_progress' => 'badge-info',
                            'confirmed'   => 'badge-primary',
                            'pending'     => 'badge-warning',
                            'cancelled'   => 'badge-danger'
                        ];
                        foreach ($statusOrderMap as $stKey => $stData): 
                            $pct = round(($stData['count'] / $totalOrdersInPeriod) * 100, 1);
                        ?>
                            <tr style="border-bottom: 1px solid #f8fafc;">
                                <td style="padding: 10px 0;">
                                    <span class="badge <?php echo $badgeClasses[$stKey] ?? ''; ?>">
                                        <i class="fa-solid fa-circle" style="font-size: 8px; margin-right: 4px; color: <?php echo $colors[$stKey]; ?>;"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $stKey)); ?>
                                    </span>
                                </td>
                                <td style="padding: 10px 0; text-align: center; font-weight: 700; color: #1e293b;">
                                    <?php echo $stData['count']; ?>
                                </td>
                                <td style="padding: 10px 0; text-align: right; color: #64748b;">
                                    <?php echo $pct; ?>%
                                </td>
                                <td style="padding: 10px 0; text-align: right; font-weight: 600; color: #1e293b;">
                                    <?php echo format_price($stData['volume']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 32px 16px; color: #94a3b8;">
                    <i class="fa-regular fa-folder-open" style="font-size: 32px; margin-bottom: 8px; color: #cbd5e1;"></i>
                    <p style="font-size: 14px; margin-bottom: 0;">No order transactions recorded for <strong><?php echo e($selectedPeriodLabel); ?></strong>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Gateways & Methods Breakdown -->
    <div class="data-table-card" style="margin-bottom: 0;">
        <div class="table-header-bar">
            <div>
                <h3 style="font-size: 16px; color: var(--secondary); margin-bottom: 2px;">Payment Transactions</h3>
                <p style="font-size: 12px; color: #64748b;">Gateway &amp; payment method performance</p>
            </div>
            <span class="badge badge-success">
                <i class="fa-solid fa-shield-check"></i> Verified Records
            </span>
        </div>
        
        <?php if (!empty($paymentBreakdown)): ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Gateway</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th style="text-align: center;">Transactions</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentBreakdown as $pay): ?>
                            <tr>
                                <td>
                                    <strong style="text-transform: uppercase; font-size: 12px; color: #334155;">
                                        <?php echo e($pay['gateway_name']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span style="font-size: 13px; color: #475569;">
                                        <i class="fa-regular fa-credit-card" style="margin-right: 4px; color: #8C3A27;"></i>
                                        <?php echo e($pay['payment_method']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($pay['payment_status'] === 'completed'): ?>
                                        <span class="badge badge-success">Completed</span>
                                    <?php elseif ($pay['payment_status'] === 'failed'): ?>
                                        <span class="badge badge-danger">Failed</span>
                                    <?php elseif ($pay['payment_status'] === 'processing'): ?>
                                        <span class="badge badge-info">Processing</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning"><?php echo e(ucfirst($pay['payment_status'])); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center; font-weight: 700;"><?php echo (int)$pay['transaction_count']; ?></td>
                                <td style="text-align: right; font-weight: 700; color: #059669;">
                                    <?php echo format_price((float)$pay['total_amount']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 16px; color: #94a3b8;">
                <i class="fa-regular fa-credit-card" style="font-size: 32px; margin-bottom: 8px; color: #cbd5e1;"></i>
                <p style="font-size: 14px; margin-bottom: 0;">No payment gateway transactions recorded for <strong><?php echo e($selectedPeriodLabel); ?></strong>.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==========================================================================
     SECTION 2: SERVICE PROVIDER & BUSINESS OWNER HEALTH
     ========================================================================== -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 32px;">
    <!-- Provider Verification & Moderation Health -->
    <div class="data-table-card" style="margin-bottom: 0;">
        <div class="table-header-bar">
            <div>
                <h3 style="font-size: 16px; color: var(--secondary); margin-bottom: 2px;">Business Owner Verification</h3>
                <p style="font-size: 12px; color: #64748b;">Approval status breakdown across all registered entrepreneurs</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/manager/providers.php" class="btn btn-outline btn-sm" style="font-size: 12px;">
                Manage Profiles
            </a>
        </div>
        
        <div style="padding: 20px 24px;">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-bottom: 20px;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px;">
                    <div style="font-size: 22px; font-weight: 800; color: #059669;"><?php echo (int)$providerStats['approved_providers']; ?></div>
                    <div style="font-size: 12px; font-weight: 600; color: #475569;">Approved &amp; Active</div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Publicly live on marketplace</div>
                </div>

                <div style="background: #fefce8; border: 1px solid #fef08a; border-radius: 8px; padding: 14px;">
                    <div style="font-size: 22px; font-weight: 800; color: #d97706;"><?php echo (int)$providerStats['pending_providers']; ?></div>
                    <div style="font-size: 12px; font-weight: 600; color: #92400e;">Pending Review</div>
                    <div style="font-size: 11px; color: #b45309; margin-top: 2px;">
                        <?php if ((int)$providerStats['overdue_reviews'] > 0): ?>
                            <strong style="color: #dc2626;">&#9888; <?php echo (int)$providerStats['overdue_reviews']; ?> overdue (&gt;24h)</strong>
                        <?php else: ?>
                            Within 24h SLA
                        <?php endif; ?>
                    </div>
                </div>

                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 14px;">
                    <div style="font-size: 22px; font-weight: 800; color: #dc2626;"><?php echo (int)$providerStats['rejected_providers']; ?></div>
                    <div style="font-size: 12px; font-weight: 600; color: #991b1b;">Rejected / Ineligible</div>
                    <div style="font-size: 11px; color: #7f1d1d; margin-top: 2px;">Access restricted</div>
                </div>

                <div style="background: #faf5ff; border: 1px solid #f3e8ff; border-radius: 8px; padding: 14px;">
                    <div style="font-size: 22px; font-weight: 800; color: #7e22ce;"><?php echo (int)$providerStats['featured_providers']; ?></div>
                    <div style="font-size: 12px; font-weight: 600; color: #6b21a8;">Featured Placements</div>
                    <div style="font-size: 11px; color: #581c87; margin-top: 2px;">Homepage spotlight</div>
                </div>
            </div>

            <!-- Geographic Footprint -->
            <div style="border-top: 1px solid #f1f5f9; padding-top: 16px;">
                <div style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-location-dot" style="color: #8C3A27;"></i> Geographic Distribution (Top Cities)
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php foreach ($cityDistribution as $city): ?>
                        <span style="background: #f1f5f9; color: #334155; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 14px; border: 1px solid #e2e8f0;">
                            <?php echo e($city['city_name']); ?>: <strong><?php echo (int)$city['provider_count']; ?></strong>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Business Owners by Orders & Revenue -->
    <div class="data-table-card" style="margin-bottom: 0;">
        <div class="table-header-bar">
            <div>
                <h3 style="font-size: 16px; color: var(--secondary); margin-bottom: 2px;">Top Business Owners</h3>
                <p style="font-size: 12px; color: #64748b;">Ranked by verified order volume &amp; gross sales</p>
            </div>
            <span class="badge" style="background: #f1f5f9; color: #475569;">All-Time Performance</span>
        </div>
        
        <?php if (!empty($topProviders)): ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Business Name</th>
                            <th>Category</th>
                            <th style="text-align: center;">Orders</th>
                            <th style="text-align: right;">Gross Sales</th>
                            <th style="text-align: right;">Platform Commission</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProviders as $idx => $prov): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                                        <span style="color: #94a3b8; font-size: 11px;">#<?php echo $idx + 1; ?></span>
                                        <?php echo e($prov['business_name']); ?>
                                        <?php if ((int)$prov['is_featured'] === 1): ?>
                                            <i class="fa-solid fa-star" style="color: #eab308; font-size: 11px;" title="Featured Business Owner"></i>
                                        <?php endif; ?>
                                    </div>
                                    <small style="color: #64748b; font-size: 11px;"><?php echo e($prov['city'] ?? 'Location unlisted'); ?></small>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: #475569;"><?php echo e($prov['category_name'] ?? 'General'); ?></span>
                                </td>
                                <td style="text-align: center; font-weight: 700; color: #1e293b;">
                                    <?php echo (int)$prov['order_count']; ?>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: #0f172a;">
                                    <?php echo format_price((float)$prov['gross_revenue']); ?>
                                </td>
                                <td style="text-align: right; font-weight: 600; color: #059669;">
                                    <?php echo format_price((float)$prov['platform_commission']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 16px; color: #94a3b8;">
                <i class="fa-regular fa-user" style="font-size: 32px; margin-bottom: 8px; color: #cbd5e1;"></i>
                <p style="font-size: 14px; margin-bottom: 0;">No provider order activity recorded yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==========================================================================
     SECTION 3: MARKETPLACE CATALOG & OFFERING HEALTH
     ========================================================================= -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 32px;">
    <!-- Catalog Offering Types & Pricing Health -->
    <div class="data-table-card" style="margin-bottom: 0;">
        <div class="table-header-bar">
            <div>
                <h3 style="font-size: 16px; color: var(--secondary); margin-bottom: 2px;">Marketplace Catalog Health</h3>
                <p style="font-size: 12px; color: #64748b;">Offering types, moderation states, and pricing distribution</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/manager/products.php" class="btn btn-outline btn-sm" style="font-size: 12px;">
                Moderate Catalog
            </a>
        </div>
        
        <div style="padding: 20px 24px;">
            <!-- Offering Types Split -->
            <div style="margin-bottom: 20px;">
                <div style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 10px;">Offering Types Split</div>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center;">
                        <div style="font-size: 18px; font-weight: 800; color: #8C3A27;"><?php echo (int)$catalogStats['type_service']; ?></div>
                        <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">Services</div>
                    </div>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center;">
                        <div style="font-size: 18px; font-weight: 800; color: #0284c7;"><?php echo (int)$catalogStats['type_product']; ?></div>
                        <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">Products</div>
                    </div>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center;">
                        <div style="font-size: 18px; font-weight: 800; color: #7e22ce;"><?php echo (int)$catalogStats['type_package']; ?></div>
                        <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">Packages</div>
                    </div>
                </div>
            </div>

            <!-- Moderation States -->
            <div style="margin-bottom: 20px;">
                <div style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 10px;">Moderation &amp; Visibility States</div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <span class="badge badge-success" style="padding: 6px 12px; font-size: 12px;">
                        <i class="fa-solid fa-circle-check"></i> <?php echo (int)$catalogStats['active_offerings']; ?> Active &amp; Live
                    </span>
                    <span class="badge badge-warning" style="padding: 6px 12px; font-size: 12px;">
                        <i class="fa-solid fa-file-pen"></i> <?php echo (int)$catalogStats['draft_offerings']; ?> Draft / Hidden
                    </span>
                    <span class="badge badge-danger" style="padding: 6px 12px; font-size: 12px;">
                        <i class="fa-solid fa-eye-slash"></i> <?php echo (int)$catalogStats['inactive_offerings']; ?> Inactive
                    </span>
                </div>
            </div>

            <!-- Price Statistics -->
            <div style="border-top: 1px solid #f1f5f9; padding-top: 16px;">
                <div style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 10px;">Active Catalog Price Range</div>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                    <div>
                        <div style="font-size: 11px; color: #64748b;">Lowest Price</div>
                        <div style="font-size: 15px; font-weight: 700; color: #1e293b;"><?php echo format_price((float)$catalogStats['min_price']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 11px; color: #64748b;">Average Price</div>
                        <div style="font-size: 15px; font-weight: 700; color: #8C3A27;"><?php echo format_price((float)$catalogStats['avg_price']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 11px; color: #64748b;">Highest Price</div>
                        <div style="font-size: 15px; font-weight: 700; color: #1e293b;"><?php echo format_price((float)$catalogStats['max_price']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performing Offerings by Orders -->
    <div class="data-table-card" style="margin-bottom: 0;">
        <div class="table-header-bar">
            <div>
                <h3 style="font-size: 16px; color: var(--secondary); margin-bottom: 2px;">Top Offerings by Bookings</h3>
                <p style="font-size: 12px; color: #64748b;">Most ordered products &amp; services across all categories</p>
            </div>
            <span class="badge" style="background: #f1f5f9; color: #475569;">Verified Orders</span>
        </div>
        
        <?php if (!empty($topOfferings)): ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Offering</th>
                            <th>Provider</th>
                            <th>Type</th>
                            <th style="text-align: center;">Bookings</th>
                            <th style="text-align: right;">Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topOfferings as $idx => $prod): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                                        <span style="color: #94a3b8; font-size: 11px;">#<?php echo $idx + 1; ?></span>
                                        <?php echo e($prod['title']); ?>
                                    </div>
                                    <small style="color: #64748b; font-size: 11px;"><?php echo e($prod['category_name'] ?? 'General'); ?> &bull; <?php echo format_price((float)$prod['price']); ?></small>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: #475569;"><?php echo e($prod['business_name']); ?></span>
                                </td>
                                <td>
                                    <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 11px;">
                                        <?php echo ucfirst($prod['service_type']); ?>
                                    </span>
                                </td>
                                <td style="text-align: center; font-weight: 700; color: #1e293b;">
                                    <?php echo (int)$prod['order_count']; ?>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: #059669;">
                                    <?php echo format_price((float)$prod['total_sales']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 16px; color: #94a3b8;">
                <i class="fa-solid fa-box-open" style="font-size: 32px; margin-bottom: 8px; color: #cbd5e1;"></i>
                <p style="font-size: 14px; margin-bottom: 0;">No offerings order activity recorded yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==========================================================================
     SECTION 4: CATEGORY PERFORMANCE MATRIX
     ========================================================================== -->
<div class="data-table-card" style="margin-bottom: 32px;">
    <div class="table-header-bar">
        <div>
            <h3 style="font-size: 16px; color: var(--secondary); margin-bottom: 2px;">Category Performance Matrix</h3>
            <p style="font-size: 12px; color: #64748b;">Comprehensive distribution of entrepreneurs, catalog items, and gross order volume across active categories</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/manager/categories.php" class="btn btn-primary btn-sm" style="font-size: 12px;">
            <i class="fa-solid fa-tags"></i> Manage Categories
        </a>
    </div>

    <?php if (!empty($categoryStats)): ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th style="text-align: center;">Business Owners</th>
                        <th style="text-align: center;">Total Offerings</th>
                        <th style="text-align: center;">Live Offerings</th>
                        <th style="text-align: center;">Orders Placed</th>
                        <th style="text-align: right;">Gross Volume (GMV)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoryStats as $cat): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 32px; height: 32px; border-radius: 6px; background: #F1E0DB; color: #8C3A27; display: flex; align-items: center; justify-content: center; font-size: 13px;">
                                        <i class="fa-solid <?php echo e($cat['icon_class'] ?: 'fa-tag'); ?>"></i>
                                    </div>
                                    <div>
                                        <strong style="color: #1e293b;"><?php echo e($cat['name']); ?></strong>
                                        <div style="font-size: 11px; color: #94a3b8;"><?php echo e($cat['slug']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align: center; font-weight: 600; color: #334155;">
                                <?php echo (int)$cat['provider_count']; ?>
                            </td>
                            <td style="text-align: center; font-weight: 600; color: #334155;">
                                <?php echo (int)$cat['offering_count']; ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-success" style="font-size: 11px;">
                                    <?php echo (int)$cat['active_offering_count']; ?> Live
                                </span>
                            </td>
                            <td style="text-align: center; font-weight: 700; color: #1e293b;">
                                <?php echo (int)$cat['order_count']; ?>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: #059669;">
                                <?php echo format_price((float)$cat['category_volume']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px 16px; color: #94a3b8;">
            <i class="fa-solid fa-tags" style="font-size: 32px; margin-bottom: 8px; color: #cbd5e1;"></i>
            <p style="font-size: 14px; margin-bottom: 0;">No active marketplace categories found.</p>
        </div>
    <?php endif; ?>
</div>

<!-- ==========================================================================
     SECTION 5: SYSTEM AUDIT & ACTIVITY INTELLIGENCE
     ========================================================================== -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 32px;">
    <!-- Top Action Distribution -->
    <div class="data-table-card" style="margin-bottom: 0;">
        <div class="table-header-bar">
            <div>
                <h3 style="font-size: 16px; color: var(--secondary); margin-bottom: 2px;">Audit Events by Action Type</h3>
                <p style="font-size: 12px; color: #64748b;">Logged operational actions in the selected period (<?php echo e($selectedPeriodLabel); ?>)</p>
            </div>
            <span class="badge" style="background: #f1f5f9; color: #475569;">
                <?php echo (int)$logStats['total_audit_events']; ?> Total Logs
            </span>
        </div>

        <div style="padding: 20px 24px;">
            <?php if (!empty($topActions)): ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($topActions as $act): 
                        $totalLogs = (int)$logStats['total_audit_events'] ?: 1;
                        $pct = round(((int)$act['action_count'] / $totalLogs) * 100, 1);
                    ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 4px;">
                                <span style="color: #334155;">
                                    <code><?php echo e($act['action']); ?></code>
                                </span>
                                <span style="color: #64748b;">
                                    <?php echo (int)$act['action_count']; ?> (<?php echo $pct; ?>%)
                                </span>
                            </div>
                            <div style="height: 6px; background: #f1f5f9; border-radius: 3px; overflow: hidden;">
                                <div style="height: 100%; width: <?php echo $pct; ?>%; background: #8C3A27; border-radius: 3px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 32px 16px; color: #94a3b8;">
                    <i class="fa-solid fa-list-check" style="font-size: 32px; margin-bottom: 8px; color: #cbd5e1;"></i>
                    <p style="font-size: 14px; margin-bottom: 0;">No audit events recorded for <strong><?php echo e($selectedPeriodLabel); ?></strong>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Audit Stream -->
    <div class="data-table-card" style="margin-bottom: 0;">
        <div class="table-header-bar">
            <div>
                <h3 style="font-size: 16px; color: var(--secondary); margin-bottom: 2px;">Recent Audit Stream</h3>
                <p style="font-size: 12px; color: #64748b;">Latest administrative and moderation operations</p>
            </div>
            <span class="badge badge-info">Live Stream</span>
        </div>

        <?php if (!empty($recentAuditStream)): ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Actor</th>
                            <th>Details</th>
                            <th style="text-align: right;">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAuditStream as $log): ?>
                            <tr>
                                <td>
                                    <span style="font-family: monospace; font-size: 11.5px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #0f172a; font-weight: 600;">
                                        <?php echo e($log['action']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 12.5px; font-weight: 600; color: #334155;">
                                        <?php echo e($log['actor_name'] ?? 'System / Guest'); ?>
                                    </div>
                                    <small style="color: #94a3b8; font-size: 10.5px; text-transform: uppercase;">
                                        <?php echo e($log['actor_role'] ?? 'Anonymous'); ?>
                                    </small>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: #475569;">
                                        <?php echo e($log['details'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td style="text-align: right; font-size: 11.5px; color: #64748b; white-space: nowrap;">
                                    <?php echo format_date($log['created_at']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 16px; color: #94a3b8;">
                <i class="fa-solid fa-clock-rotate-left" style="font-size: 32px; margin-bottom: 8px; color: #cbd5e1;"></i>
                <p style="font-size: 14px; margin-bottom: 0;">No audit events recorded for <strong><?php echo e($selectedPeriodLabel); ?></strong>.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==========================================================================
     SECTION 6: DATA INTEGRITY & TRACKING TRANSPARENCY NOTICE
     ========================================================================== -->
<div style="background: #ffffff; border: 1px solid var(--border-color); border-left: 4px solid #8C3A27; border-radius: var(--radius-lg); padding: 20px 24px; box-shadow: var(--shadow-sm); margin-bottom: 24px;">
    <div style="display: flex; align-items: flex-start; gap: 14px;">
        <div style="width: 36px; height: 36px; border-radius: 50%; background: #F1E0DB; color: #8C3A27; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; margin-top: 2px;">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div style="flex: 1;">
            <h4 style="font-size: 15px; font-weight: 700; color: var(--secondary); margin-bottom: 4px;">
                Data Integrity &amp; Tracking Transparency
            </h4>
            <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin-bottom: 12px;">
                This platform analytics dashboard strictly visualizes verified transactional records, catalog moderation states, user registrations, and audit logs stored in the relational database. It never uses fabricated, simulated, or estimated figures.
            </p>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <span class="badge" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; font-size: 11.5px; padding: 4px 10px;">
                    <i class="fa-solid fa-eye-slash" style="color: #94a3b8;"></i> Storefront Views: <em>Not tracked yet</em>
                </span>
                <span class="badge" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; font-size: 11.5px; padding: 4px 10px;">
                    <i class="fa-brands fa-whatsapp" style="color: #94a3b8;"></i> WhatsApp Clicks: <em>Not tracked yet</em>
                </span>
                <span class="badge" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; font-size: 11.5px; padding: 4px 10px;">
                    <i class="fa-solid fa-magnifying-glass" style="color: #94a3b8;"></i> Search Query History: <em>Not tracked yet</em>
                </span>
                <span class="badge" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; font-size: 11.5px; padding: 4px 10px;">
                    <i class="fa-solid fa-receipt" style="color: #94a3b8;"></i> Subscriptions: <em>Module not active</em>
                </span>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/includes/footer.php';
