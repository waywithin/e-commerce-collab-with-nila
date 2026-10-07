<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Manager Global Products & Services Moderation
 * Location: /manager/products.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Enforce manager authentication
require_manager();
$db = getDB();

$pageTitle = 'Marketplace Offerings & Moderation';

// Explicit default variable initialization
$action = null;
$prodId = 0;
$targetProduct = null;

// --------------------------------------------------------------------
// 1. SECURE POST ACTION HANDLER (Approve/Publish, Hide/Draft, Toggle)
// --------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validate CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
        header("Location: " . BASE_URL . "/manager/products.php");
        exit;
    }

    // 2. Read and sanitize request parameters
    $action = sanitize_string($_POST['action'] ?? '');
    $prodId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: (int)($_POST['id'] ?? 0);

    // 3. Whitelist allowed state-changing actions
    $allowedActions = ['activate', 'hide', 'toggle_status'];
    if (!in_array($action, $allowedActions, true)) {
        set_flash('danger', 'Invalid or unsupported moderation action requested.');
        header("Location: " . BASE_URL . "/manager/products.php");
        exit;
    }

    // 4. Validate Product / Service ID
    if ($prodId <= 0) {
        set_flash('danger', 'Invalid offering identifier provided.');
        header("Location: " . BASE_URL . "/manager/products.php");
        exit;
    }

    // 5. Verify Offering existence in Database
    $checkStmt = $db->prepare("
        SELECT p.id, p.title, p.status, p.service_type, sp.business_name, sp.id AS provider_id
        FROM products p
        JOIN service_providers sp ON p.provider_id = sp.id
        WHERE p.id = :id
        LIMIT 1
    ");
    $checkStmt->execute(['id' => $prodId]);
    $targetProduct = $checkStmt->fetch();

    if (!$targetProduct) {
        set_flash('danger', 'Offering record not found in marketplace database.');
        header("Location: " . BASE_URL . "/manager/products.php");
        exit;
    }

    $prodTitle = $targetProduct['title'];
    $bizName = $targetProduct['business_name'];
    $serviceType = ucfirst($targetProduct['service_type']);

    // 6. Execute state change safely
    if ($action === 'activate') {
        $db->prepare("UPDATE products SET status = 'active', updated_at = NOW() WHERE id = :id")->execute(['id' => $prodId]);
        log_activity(current_user_id(), 'APPROVE_PRODUCT', 'product', $prodId, 'Activated offering "' . $prodTitle . '" for ' . $bizName);
        set_flash('success', $serviceType . ' "' . e($prodTitle) . '" is now active and publicly visible.');
    } elseif ($action === 'hide') {
        $db->prepare("UPDATE products SET status = 'draft', updated_at = NOW() WHERE id = :id")->execute(['id' => $prodId]);
        log_activity(current_user_id(), 'HIDE_PRODUCT', 'product', $prodId, 'Hid offering "' . $prodTitle . '" for ' . $bizName);
        set_flash('warning', $serviceType . ' "' . e($prodTitle) . '" has been hidden from the public catalog (set to Draft).');
    } elseif ($action === 'toggle_status') {
        $newStatus = ($targetProduct['status'] === 'active') ? 'draft' : 'active';
        $db->prepare("UPDATE products SET status = :status, updated_at = NOW() WHERE id = :id")->execute([
            'status' => $newStatus,
            'id'     => $prodId
        ]);
        $statusMsg = ($newStatus === 'active') ? 'activated and publicly visible' : 'hidden from public catalog';
        log_activity(current_user_id(), 'TOGGLE_PRODUCT_STATUS', 'product', $prodId, 'Toggled offering "' . $prodTitle . '" status to ' . $newStatus);
        set_flash('success', $serviceType . ' "' . e($prodTitle) . '" is now ' . $statusMsg . '.');
    }

    // Preserve search/filter query params on redirect
    $redirectUrl = BASE_URL . "/manager/products.php";
    $queryParts = [];
    if (!empty($_GET['search'])) {
        $queryParts['search'] = sanitize_string($_GET['search']);
    }
    if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
        $queryParts['status'] = sanitize_string($_GET['status']);
    }
    if (!empty($_GET['category']) && (int)$_GET['category'] > 0) {
        $queryParts['category'] = (int)$_GET['category'];
    }
    if (!empty($queryParts)) {
        $redirectUrl .= '?' . http_build_query($queryParts);
    }

    header("Location: " . $redirectUrl);
    exit;
}

// --------------------------------------------------------------------
// 2. SEARCH & FILTER PARAMETERS (GET Sanitization & Whitelisting)
// --------------------------------------------------------------------
$search = mb_substr(sanitize_string($_GET['search'] ?? ''), 0, 100);
$rawStatus = sanitize_string($_GET['status'] ?? 'all');
$allowedStatuses = ['all', 'active', 'draft', 'inactive', 'offers', 'service', 'product', 'package'];
$statusFilter = in_array($rawStatus, $allowedStatuses, true) ? $rawStatus : 'all';
$categoryFilter = max(0, (int)($_GET['category'] ?? 0));

// Fetch categories for dropdown filter
$categoriesList = $db->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// --------------------------------------------------------------------
// 3. OVERALL SUMMARY METRICS
// --------------------------------------------------------------------
$summaryCounts = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN status = 'draft' OR status = 'inactive' THEN 1 ELSE 0 END) AS draft_count,
        SUM(CASE WHEN offer_enabled = 1 AND offer_end_at > NOW() THEN 1 ELSE 0 END) AS offers_count,
        SUM(CASE WHEN service_type = 'service' THEN 1 ELSE 0 END) AS services_count,
        SUM(CASE WHEN service_type = 'product' THEN 1 ELSE 0 END) AS products_count,
        SUM(CASE WHEN service_type = 'package' THEN 1 ELSE 0 END) AS packages_count
    FROM products
")->fetch() ?: [
    'total' => 0,
    'active_count' => 0,
    'draft_count' => 0,
    'offers_count' => 0,
    'services_count' => 0,
    'products_count' => 0,
    'packages_count' => 0
];

// --------------------------------------------------------------------
// 4. PREPARED SEARCH & FILTER QUERY
// --------------------------------------------------------------------
$whereClauses = [];
$queryParams = [];

if (!empty($search)) {
    $whereClauses[] = "(p.title LIKE :s1 OR p.description LIKE :s2 OR sp.business_name LIKE :s3 OR u.email LIKE :s4 OR c.name LIKE :s5)";
    $searchTerm = '%' . $search . '%';
    $queryParams['s1'] = $searchTerm;
    $queryParams['s2'] = $searchTerm;
    $queryParams['s3'] = $searchTerm;
    $queryParams['s4'] = $searchTerm;
    $queryParams['s5'] = $searchTerm;
}

if ($statusFilter === 'active') {
    $whereClauses[] = "p.status = 'active'";
} elseif ($statusFilter === 'draft') {
    $whereClauses[] = "(p.status = 'draft' OR p.status = 'inactive')";
} elseif ($statusFilter === 'inactive') {
    $whereClauses[] = "p.status = 'inactive'";
} elseif ($statusFilter === 'offers') {
    $whereClauses[] = "p.offer_enabled = 1 AND p.offer_end_at > NOW()";
} elseif ($statusFilter === 'service') {
    $whereClauses[] = "p.service_type = 'service'";
} elseif ($statusFilter === 'product') {
    $whereClauses[] = "p.service_type = 'product'";
} elseif ($statusFilter === 'package') {
    $whereClauses[] = "p.service_type = 'package'";
}

if ($categoryFilter > 0) {
    $whereClauses[] = "p.category_id = :cat_id";
    $queryParams['cat_id'] = $categoryFilter;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$stmt = $db->prepare("
    SELECT p.*,
           c.name AS category_name,
           c.slug AS category_slug,
           sp.business_name,
           sp.slug AS provider_slug,
           sp.approval_status AS provider_approval,
           sp.contact_phone,
           sp.contact_email,
           sp.city,
           sp.location,
           u.full_name AS owner_name,
           u.email AS owner_email
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN service_providers sp ON p.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    $whereSql
    ORDER BY p.id DESC
");
$stmt->execute($queryParams);
$products = $stmt->fetchAll();

$isFiltered = (!empty($search) || ($statusFilter !== 'all') || ($categoryFilter > 0));

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">Marketplace Offerings &amp; Moderation</h1>
        <p style="font-size: 14px; color: #64748b;">Review, moderate, and inspect services, physical items, and active promotional offers across all entrepreneurs.</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="<?php echo BASE_URL; ?>/manager/categories.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-tags"></i> Manage Categories
        </a>
        <a href="<?php echo BASE_URL; ?>/manager/providers.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-users"></i> Business Owners
        </a>
    </div>
</div>

<!-- Summary Chips Navigation -->
<div class="summary-chips-row">
    <a href="<?php echo BASE_URL; ?>/manager/products.php" class="summary-chip <?php echo ($statusFilter === 'all' && empty($search) && $categoryFilter === 0) ? 'active' : ''; ?>">
        <span>All Offerings</span>
        <span class="summary-chip-count"><?php echo (int)($summaryCounts['total'] ?? 0); ?></span>
    </a>
    <a href="<?php echo BASE_URL; ?>/manager/products.php?status=active" class="summary-chip <?php echo ($statusFilter === 'active') ? 'active' : ''; ?>">
        <i class="fa-solid fa-circle-check" style="color: #059669;"></i>
        <span>Active / Visible</span>
        <span class="summary-chip-count" style="color: #059669;"><?php echo (int)($summaryCounts['active_count'] ?? 0); ?></span>
    </a>
    <a href="<?php echo BASE_URL; ?>/manager/products.php?status=draft" class="summary-chip <?php echo ($statusFilter === 'draft') ? 'active' : ''; ?>">
        <i class="fa-solid fa-eye-slash" style="color: #64748b;"></i>
        <span>Draft / Hidden</span>
        <span class="summary-chip-count" style="color: #64748b;"><?php echo (int)($summaryCounts['draft_count'] ?? 0); ?></span>
    </a>
    <a href="<?php echo BASE_URL; ?>/manager/products.php?status=offers" class="summary-chip <?php echo ($statusFilter === 'offers') ? 'active' : ''; ?>">
        <i class="fa-solid fa-fire" style="color: #8C3A27;"></i>
        <span>Active Deals</span>
        <span class="summary-chip-count" style="color: #8C3A27;"><?php echo (int)($summaryCounts['offers_count'] ?? 0); ?></span>
    </a>
    <a href="<?php echo BASE_URL; ?>/manager/products.php?status=service" class="summary-chip <?php echo ($statusFilter === 'service') ? 'active' : ''; ?>">
        <i class="fa-solid fa-hand-holding-heart" style="color: #732D1D;"></i>
        <span>Services</span>
        <span class="summary-chip-count"><?php echo (int)($summaryCounts['services_count'] ?? 0); ?></span>
    </a>
    <a href="<?php echo BASE_URL; ?>/manager/products.php?status=product" class="summary-chip <?php echo ($statusFilter === 'product') ? 'active' : ''; ?>">
        <i class="fa-solid fa-box-open" style="color: #0284c7;"></i>
        <span>Products</span>
        <span class="summary-chip-count"><?php echo (int)($summaryCounts['products_count'] ?? 0); ?></span>
    </a>
    <?php if ((int)($summaryCounts['packages_count'] ?? 0) > 0): ?>
        <a href="<?php echo BASE_URL; ?>/manager/products.php?status=package" class="summary-chip <?php echo ($statusFilter === 'package') ? 'active' : ''; ?>">
            <i class="fa-solid fa-cubes" style="color: #881337;"></i>
            <span>Packages</span>
            <span class="summary-chip-count"><?php echo (int)($summaryCounts['packages_count'] ?? 0); ?></span>
        </a>
    <?php endif; ?>
</div>

<!-- Search & Filter Card -->
<div class="filter-card">
    <form method="GET" action="<?php echo BASE_URL; ?>/manager/products.php" class="filter-grid">
        <!-- Search Input -->
        <div class="filter-group" style="flex: 2; min-width: 240px;">
            <label class="filter-label" for="searchQuery">Search Offerings</label>
            <input type="text" id="searchQuery" name="search" class="filter-input"
                   placeholder="Search by title, description, entrepreneur, email..."
                   value="<?php echo e($search); ?>">
        </div>

        <!-- Status / Type Dropdown -->
        <div class="filter-group">
            <label class="filter-label" for="statusFilter">Status / Type</label>
            <select id="statusFilter" name="status" class="filter-select">
                <option value="all" <?php echo ($statusFilter === 'all') ? 'selected' : ''; ?>>All Offerings</option>
                <option value="active" <?php echo ($statusFilter === 'active') ? 'selected' : ''; ?>>Active (Visible)</option>
                <option value="draft" <?php echo ($statusFilter === 'draft') ? 'selected' : ''; ?>>Draft / Hidden</option>
                <option value="offers" <?php echo ($statusFilter === 'offers') ? 'selected' : ''; ?>>Active Deals / Offers</option>
                <option value="service" <?php echo ($statusFilter === 'service') ? 'selected' : ''; ?>>Services Only</option>
                <option value="product" <?php echo ($statusFilter === 'product') ? 'selected' : ''; ?>>Products Only</option>
                <option value="package" <?php echo ($statusFilter === 'package') ? 'selected' : ''; ?>>Packages Only</option>
            </select>
        </div>

        <!-- Category Dropdown -->
        <div class="filter-group">
            <label class="filter-label" for="categoryFilter">Category</label>
            <select id="categoryFilter" name="category" class="filter-select">
                <option value="0">All Categories</option>
                <?php foreach ($categoriesList as $cat): ?>
                    <option value="<?php echo (int)$cat['id']; ?>" <?php echo ($categoryFilter === (int)$cat['id']) ? 'selected' : ''; ?>>
                        <?php echo e($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Submit & Reset Buttons -->
        <div class="filter-group" style="flex: 0 0 auto; display: flex; gap: 8px; align-items: flex-end;">
            <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 18px;">
                <i class="fa-solid fa-magnifying-glass"></i> Filter
            </button>
            <?php if ($isFiltered): ?>
                <a href="<?php echo BASE_URL; ?>/manager/products.php" class="btn btn-outline" style="height: 40px; padding: 0 14px; display: inline-flex; align-items: center;" title="Clear all filters">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Moderation Catalog Table -->
<div class="data-table-card">
    <?php if (empty($products)): ?>
        <div style="padding: 60px 20px; text-align: center;">
            <div style="width: 60px; height: 60px; background: #F1E0DB; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 24px;">
                <i class="fa-solid fa-box-open"></i>
            </div>
            <h3 style="color: var(--text-heading); margin-bottom: 6px;">No offerings match your criteria</h3>
            <p style="color: #64748b; margin-bottom: 20px;">
                <?php if ($isFiltered): ?>
                    Try adjusting your search terms or filters to find what you are looking for.
                <?php else: ?>
                    There are currently no products or services listed in the marketplace.
                <?php endif; ?>
            </p>
            <?php if ($isFiltered): ?>
                <a href="<?php echo BASE_URL; ?>/manager/products.php" class="btn btn-outline btn-sm">Clear Filters</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">Cover</th>
                        <th>Title &amp; Type</th>
                        <th>Entrepreneur</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Promotion / Offer</th>
                        <th>Listing Status</th>
                        <th>Created</th>
                        <th style="text-align: right; min-width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $prod):
                        $offer = get_product_offer_details($prod);
                        $isActive = ($prod['status'] === 'active');
                        $isProviderApproved = ($prod['provider_approval'] === 'approved');

                        // Clean JSON payload for inspection modal
                        $modalData = [
                            'id' => (int)$prod['id'],
                            'title' => $prod['title'],
                            'slug' => $prod['slug'],
                            'service_type' => $prod['service_type'],
                            'category_name' => $prod['category_name'],
                            'price' => (float)$prod['price'],
                            'price_formatted' => format_price($prod['price']),
                            'discount_price' => !empty($prod['discount_price']) ? (float)$prod['discount_price'] : null,
                            'discount_price_formatted' => !empty($prod['discount_price']) ? format_price($prod['discount_price']) : null,
                            'cover_url' => get_image_url($prod['cover_image'], 'product'),
                            'status' => $prod['status'],
                            'description' => $prod['description'],
                            'business_name' => $prod['business_name'],
                            'provider_slug' => $prod['provider_slug'],
                            'provider_approval' => $prod['provider_approval'],
                            'owner_name' => $prod['owner_name'] ?? 'N/A',
                            'owner_email' => $prod['owner_email'] ?? 'N/A',
                            'contact_phone' => $prod['contact_phone'] ?? 'N/A',
                            'contact_email' => $prod['contact_email'] ?? 'N/A',
                            'city' => $prod['city'] ?? 'India',
                            'location' => $prod['location'] ?? 'Not specified',
                            'created_at' => format_date($prod['created_at'], true),
                            'updated_at' => format_date($prod['updated_at'], true),
                            'has_offer' => $offer['has_offer'],
                            'offer_status' => $offer['status'],
                            'offer_effective_price' => format_price($offer['effective_price']),
                            'offer_savings' => format_price($offer['savings_amount']),
                            'offer_percent' => $offer['savings_percent'],
                            'offer_start' => !empty($prod['offer_start_at']) ? format_date($prod['offer_start_at'], true) : 'N/A',
                            'offer_end' => !empty($prod['offer_end_at']) ? format_date($prod['offer_end_at'], true) : 'N/A',
                        ];
                        $jsonPayload = htmlspecialchars(json_encode($modalData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr>
                            <!-- Cover Thumbnail -->
                            <td>
                                <img src="<?php echo e(get_image_url($prod['cover_image'], 'product')); ?>"
                                     alt="<?php echo e($prod['title']); ?>"
                                     style="width: 52px; height: 52px; border-radius: 6px; object-fit: cover; border: 1px solid #E8E2DA;">
                            </td>

                            <!-- Title & Type -->
                            <td>
                                <div style="font-weight: 700; color: var(--text-heading); font-size: 14px; margin-bottom: 3px;">
                                    <a href="<?php echo BASE_URL; ?>/product-details.php?id=<?php echo (int)$prod['id']; ?>" target="_blank" style="color: inherit; text-decoration: none;" title="Open public product page">
                                        <?php echo e($prod['title']); ?>
                                        <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 11px; color: #94a3b8; margin-left: 3px;"></i>
                                    </a>
                                </div>
                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <span class="badge badge-<?php echo ($prod['service_type'] === 'service') ? 'service' : (($prod['service_type'] === 'package') ? 'package' : 'product'); ?>" style="font-size: 10px;">
                                        <?php echo strtoupper($prod['service_type']); ?>
                                    </span>
                                    <?php if (!empty($prod['discount_price'])): ?>
                                        <span class="badge" style="background: #e0f2fe; color: #0369a1; font-size: 10px;">Discounted</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Entrepreneur -->
                            <td>
                                <div>
                                    <a href="<?php echo BASE_URL; ?>/provider-profile.php?slug=<?php echo urlencode($prod['provider_slug']); ?>" target="_blank" style="color: var(--primary); font-weight: 600; font-size: 13.5px;">
                                        <?php echo e($prod['business_name']); ?>
                                    </a>
                                </div>
                                <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
                                    <?php if ($isProviderApproved): ?>
                                        <span style="color: #059669;"><i class="fa-solid fa-circle-check"></i> Approved</span>
                                    <?php else: ?>
                                        <span style="color: #d97706;"><i class="fa-solid fa-clock"></i> Owner Pending</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Category -->
                            <td>
                                <span style="font-size: 13px; color: #334155; font-weight: 500;">
                                    <?php echo e($prod['category_name']); ?>
                                </span>
                            </td>

                            <!-- Price -->
                            <td>
                                <div style="font-weight: 700; color: var(--secondary); font-size: 14px;">
                                    <?php echo format_price($prod['price']); ?>
                                </div>
                                <?php if (!empty($prod['discount_price'])): ?>
                                    <small style="color: #64748b; text-decoration: line-through; font-size: 11.5px;">
                                        <?php echo format_price($prod['price']); ?>
                                    </small>
                                    <small style="color: #059669; font-weight: 700; font-size: 11.5px; margin-left: 2px;">
                                        <?php echo format_price($prod['discount_price']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <!-- Limited-Time Offer Status -->
                            <td>
                                <?php if ($offer['status'] === 'ACTIVE'): ?>
                                    <span class="badge badge-offer" style="display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-fire"></i> <?php echo format_price($offer['effective_price']); ?>
                                    </span>
                                    <div class="countdown-digits" data-countdown-target="<?php echo e($offer['end_timestamp_iso']); ?>" style="font-size: 11px; color: #8C3A27; margin-top: 2px;">
                                        Ends soon
                                    </div>
                                <?php elseif ($offer['status'] === 'UPCOMING'): ?>
                                    <span class="badge" style="background: #fef3c7; color: #92400e;">
                                        <i class="fa-solid fa-clock"></i> Upcoming Deal
                                    </span>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 12px;">Standard Price</span>
                                <?php endif; ?>
                            </td>

                            <!-- Moderation / Visibility Status -->
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="badge" style="background: #d1fae5; color: #065f46; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-circle-check"></i> Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: #f1f5f9; color: #64748b; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-eye-slash"></i> Draft
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Created Date -->
                            <td style="font-size: 12px; color: #64748b; white-space: nowrap;">
                                <?php echo format_date($prod['created_at']); ?>
                            </td>

                            <!-- Actions -->
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                                    <!-- Inspect Modal Button -->
                                    <button type="button" class="btn btn-outline btn-sm" onclick='openProductModal(<?php echo $jsonPayload; ?>)' title="Inspect offering details">
                                        <i class="fa-solid fa-magnifying-glass"></i> Review
                                    </button>

                                    <!-- Quick State-Changing Form (POST) -->
                                    <?php if ($isActive): ?>
                                        <!-- Hide / Set Draft Form -->
                                        <form method="POST" action="<?php echo BASE_URL; ?>/manager/products.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to hide this offering from the public marketplace?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="hide">
                                            <input type="hidden" name="id" value="<?php echo (int)$prod['id']; ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color: #64748b; border-color: #cbd5e1;" title="Hide from public catalog">
                                                <i class="fa-solid fa-eye-slash"></i> Hide
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Activate / Approve Form -->
                                        <form method="POST" action="<?php echo BASE_URL; ?>/manager/products.php" style="display: inline;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="id" value="<?php echo (int)$prod['id']; ?>">
                                            <button type="submit" class="btn btn-primary btn-sm" title="Publish offering live">
                                                <i class="fa-solid fa-check"></i> Publish
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Offering Details Inspection Modal -->
<div id="productDetailsModal" class="modal-backdrop" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modalProductTitle">
        <div class="modal-header">
            <h3 id="modalProductTitle" class="modal-title">Offering Moderation Details</h3>
            <button type="button" class="modal-close-btn" onclick="closeProductModal()" aria-label="Close modal">&times;</button>
        </div>

        <div class="modal-body">
            <!-- Header Row with Cover Photo & Quick Identifiers -->
            <div style="display: flex; gap: 16px; align-items: center; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap;">
                <img id="modalCover" src="" alt="" style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0;">
                <div style="flex: 1; min-width: 220px;">
                    <h4 id="modalHeadingTitle" style="font-size: 17px; margin: 0 0 6px 0; color: #1e293b;"></h4>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <span id="modalTypeBadge" class="badge" style="font-size: 11px;"></span>
                        <span id="modalCategoryBadge" class="badge" style="background: #F1E0DB; color: #8C3A27; font-size: 11px;"></span>
                        <span id="modalStatusBadge"></span>
                    </div>
                </div>
            </div>

            <!-- Two-Column Information Grid -->
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Business Owner</div>
                    <div id="modalBusinessName" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Founder / Owner Name</div>
                    <div id="modalOwnerName" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Contact Email</div>
                    <div id="modalContactEmail" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Contact Phone</div>
                    <div id="modalContactPhone" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">City &amp; Location</div>
                    <div id="modalLocation" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Regular Price</div>
                    <div id="modalPrice" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Discount Price</div>
                    <div id="modalDiscountPrice" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Limited-Time Offer Deal</div>
                    <div id="modalOfferSummary" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Created At</div>
                    <div id="modalCreatedAt" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Last Updated</div>
                    <div id="modalUpdatedAt" class="detail-value"></div>
                </div>
            </div>

            <!-- Description -->
            <div>
                <div class="detail-label">Full Description &amp; Details</div>
                <div id="modalDescription" style="font-size: 13.5px; color: #475569; line-height: 1.6; background: #f8fafc; padding: 12px 14px; border-radius: 6px; border: 1px solid #f1f5f9; min-height: 50px; white-space: pre-wrap;"></div>
            </div>
        </div>

        <div class="modal-footer">
            <a id="modalPublicLink" href="#" target="_blank" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> View Live Page
            </a>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeProductModal()">Close</button>

            <!-- Modal Action Forms (POST) -->
            <form id="modalActivateForm" method="POST" action="<?php echo BASE_URL; ?>/manager/products.php" style="display: inline;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="activate">
                <input type="hidden" id="modalActivateId" name="id" value="">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-check"></i> Publish / Make Active
                </button>
            </form>

            <form id="modalHideForm" method="POST" action="<?php echo BASE_URL; ?>/manager/products.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to hide this offering from public view?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="hide">
                <input type="hidden" id="modalHideId" name="id" value="">
                <button type="submit" class="btn btn-outline btn-sm" style="color: #64748b; border-color: #cbd5e1;">
                    <i class="fa-solid fa-eye-slash"></i> Hide / Make Draft
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openProductModal(data) {
    if (!data) return;

    document.getElementById('modalCover').src = data.cover_url || '';
    document.getElementById('modalCover').alt = data.title || '';
    document.getElementById('modalHeadingTitle').textContent = data.title || 'Offering Details';

    // Type Badge
    const typeBadge = document.getElementById('modalTypeBadge');
    typeBadge.textContent = (data.service_type || 'service').toUpperCase();
    typeBadge.className = 'badge badge-' + (data.service_type === 'service' ? 'service' : (data.service_type === 'package' ? 'package' : 'product'));

    // Category Badge
    document.getElementById('modalCategoryBadge').textContent = data.category_name || 'General';

    // Status Badge
    const statusContainer = document.getElementById('modalStatusBadge');
    if (data.status === 'active') {
        statusContainer.innerHTML = '<span class="badge" style="background: #d1fae5; color: #065f46;"><i class="fa-solid fa-circle-check"></i> Active</span>';
    } else {
        statusContainer.innerHTML = '<span class="badge" style="background: #f1f5f9; color: #64748b;"><i class="fa-solid fa-eye-slash"></i> Draft / Hidden</span>';
    }

    // Detail values
    document.getElementById('modalBusinessName').textContent = data.business_name || 'N/A';
    document.getElementById('modalOwnerName').textContent = data.owner_name || 'N/A';
    document.getElementById('modalContactEmail').textContent = data.contact_email || (data.owner_email || 'N/A');
    document.getElementById('modalContactPhone').textContent = data.contact_phone || 'N/A';
    document.getElementById('modalLocation').textContent = (data.city || 'India') + (data.location ? ' (' + data.location + ')' : '');
    document.getElementById('modalPrice').textContent = data.price_formatted || 'N/A';
    document.getElementById('modalDiscountPrice').textContent = data.discount_price_formatted || 'None';

    // Offer summary
    const offerSummaryEl = document.getElementById('modalOfferSummary');
    if (data.has_offer && data.offer_status === 'ACTIVE') {
        offerSummaryEl.innerHTML = '<span style="color: #8C3A27; font-weight: 700;"><i class="fa-solid fa-fire"></i> ' + data.offer_effective_price + ' (' + data.offer_percent + '% off) — Ends ' + data.offer_end + '</span>';
    } else if (data.offer_status === 'UPCOMING') {
        offerSummaryEl.innerHTML = '<span style="color: #d97706; font-weight: 600;"><i class="fa-solid fa-clock"></i> Upcoming (' + data.offer_effective_price + ') starts ' + data.offer_start + '</span>';
    } else {
        offerSummaryEl.textContent = 'No active deal';
    }

    document.getElementById('modalCreatedAt').textContent = data.created_at || 'N/A';
    document.getElementById('modalUpdatedAt').textContent = data.updated_at || 'N/A';
    document.getElementById('modalDescription').textContent = data.description || 'No description provided.';

    // Links & Action Form IDs
    document.getElementById('modalPublicLink').href = '<?php echo BASE_URL; ?>/product-details.php?id=' + data.id;
    document.getElementById('modalActivateId').value = data.id;
    document.getElementById('modalHideId').value = data.id;

    // Toggle button visibility based on active status
    const isActive = (data.status === 'active');
    document.getElementById('modalActivateForm').style.display = isActive ? 'none' : 'inline';
    document.getElementById('modalHideForm').style.display = isActive ? 'inline' : 'none';

    // Show modal
    const modal = document.getElementById('productDetailsModal');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
}

function closeProductModal() {
    const modal = document.getElementById('productDetailsModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

// Close on backdrop click or ESC key
document.getElementById('productDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeProductModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeProductModal();
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
