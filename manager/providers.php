<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Manager Provider Governance & Approvals
 * Location: /manager/providers.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Enforce manager authentication
require_manager();
$db = getDB();

$pageTitle = 'Manage Business Owners';

// --------------------------------------------------------------------
// 1. SECURE POST ACTION HANDLER (Approve, Reject, Suspend, Toggle Feature)
// --------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security validation failed (invalid CSRF token). Please try again.');
        header("Location: " . BASE_URL . "/manager/providers.php");
        exit;
    }

    $action = sanitize_string($_POST['action'] ?? '');
    $provId = (int)($_POST['id'] ?? 0);

    // Verify Business Owner existence
    $checkStmt = $db->prepare("
        SELECT sp.id, sp.business_name, sp.user_id, sp.approval_status, sp.is_featured, u.status AS user_status
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.id = :id
        LIMIT 1
    ");
    $checkStmt->execute(['id' => $provId]);
    $targetProvider = $checkStmt->fetch();

    if (!$targetProvider) {
        set_flash('danger', 'Business Owner record not found or invalid identifier.');
        header("Location: " . BASE_URL . "/manager/providers.php");
        exit;
    }

    $bizName = $targetProvider['business_name'];
    $userId = (int)$targetProvider['user_id'];

    if ($action === 'approve') {
        $db->prepare("UPDATE service_providers SET approval_status = 'approved', updated_at = NOW() WHERE id = :id")->execute(['id' => $provId]);
        $db->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = :uid")->execute(['uid' => $userId]);
        log_activity(current_user_id(), 'APPROVE_PROVIDER', 'service_provider', $provId, 'Approved Business Owner: ' . $bizName);
        set_flash('success', 'Business Owner "' . e($bizName) . '" profile has been approved and is now live.');
    } elseif ($action === 'reject') {
        $db->prepare("UPDATE service_providers SET approval_status = 'rejected', updated_at = NOW() WHERE id = :id")->execute(['id' => $provId]);
        log_activity(current_user_id(), 'REJECT_PROVIDER', 'service_provider', $provId, 'Rejected Business Owner application: ' . $bizName);
        set_flash('warning', 'Business Owner "' . e($bizName) . '" application has been rejected.');
    } elseif ($action === 'suspend') {
        $db->prepare("UPDATE service_providers SET approval_status = 'rejected', updated_at = NOW() WHERE id = :id")->execute(['id' => $provId]);
        $db->prepare("UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = :uid")->execute(['uid' => $userId]);
        log_activity(current_user_id(), 'SUSPEND_PROVIDER', 'service_provider', $provId, 'Suspended Business Owner account: ' . $bizName);
        set_flash('danger', 'Business Owner "' . e($bizName) . '" has been suspended and storefront deactivated.');
    } elseif ($action === 'toggle_featured') {
        $newFeatured = $targetProvider['is_featured'] ? 0 : 1;
        $db->prepare("UPDATE service_providers SET is_featured = :feat, updated_at = NOW() WHERE id = :id")->execute([
            'feat' => $newFeatured,
            'id'   => $provId
        ]);
        $statusLabel = $newFeatured ? 'featured' : 'unfeatured';
        log_activity(current_user_id(), 'FEATURE_PROVIDER', 'service_provider', $provId, 'Toggled featured status (' . $statusLabel . ') for: ' . $bizName);
        set_flash('success', 'Business Owner "' . e($bizName) . '" is now ' . $statusLabel . '.');
    } else {
        set_flash('danger', 'Invalid action requested.');
    }

    // Preserve search/filter query params on redirect
    $redirectUrl = BASE_URL . "/manager/providers.php";
    $queryParts = [];
    if (!empty($_GET['search'])) {
        $queryParts['search'] = $_GET['search'];
    }
    if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
        $queryParts['status'] = $_GET['status'];
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
// 2. SEARCH & FILTER PARAMETERS
// --------------------------------------------------------------------
$search = sanitize_string($_GET['search'] ?? '');
$statusFilter = sanitize_string($_GET['status'] ?? 'all');
$categoryFilter = (int)($_GET['category'] ?? 0);

// Fetch categories for dropdown filter
$categoriesList = $db->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// --------------------------------------------------------------------
// 3. OVERALL SUMMARY METRICS
// --------------------------------------------------------------------
$summaryCounts = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN sp.approval_status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN sp.approval_status = 'approved' AND u.status = 'active' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN sp.approval_status = 'rejected' OR u.status = 'suspended' THEN 1 ELSE 0 END) AS rejected_count,
        SUM(CASE WHEN sp.is_featured = 1 THEN 1 ELSE 0 END) AS featured_count
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
")->fetch() ?: ['total' => 0, 'pending_count' => 0, 'approved_count' => 0, 'rejected_count' => 0, 'featured_count' => 0];

// --------------------------------------------------------------------
// 4. PREPARED SEARCH & FILTER QUERY
// --------------------------------------------------------------------
$whereClauses = [];
$queryParams = [];

if (!empty($search)) {
    $whereClauses[] = "(sp.business_name LIKE :s1 OR u.full_name LIKE :s2 OR u.email LIKE :s3 OR sp.contact_email LIKE :s4 OR sp.city LIKE :s5)";
    $searchTerm = '%' . $search . '%';
    $queryParams['s1'] = $searchTerm;
    $queryParams['s2'] = $searchTerm;
    $queryParams['s3'] = $searchTerm;
    $queryParams['s4'] = $searchTerm;
    $queryParams['s5'] = $searchTerm;
}

if ($statusFilter === 'pending') {
    $whereClauses[] = "sp.approval_status = 'pending'";
} elseif ($statusFilter === 'approved') {
    $whereClauses[] = "sp.approval_status = 'approved' AND u.status = 'active'";
} elseif ($statusFilter === 'rejected') {
    $whereClauses[] = "sp.approval_status = 'rejected'";
} elseif ($statusFilter === 'suspended') {
    $whereClauses[] = "u.status = 'suspended'";
} elseif ($statusFilter === 'featured') {
    $whereClauses[] = "sp.is_featured = 1";
}

if ($categoryFilter > 0) {
    $whereClauses[] = "sp.category_id = :cat_id";
    $queryParams['cat_id'] = $categoryFilter;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$stmt = $db->prepare("
    SELECT sp.*,
           u.full_name AS owner_name,
           u.email AS owner_email,
           u.phone AS owner_phone,
           u.status AS user_status,
           c.name AS category_name,
           COUNT(p.id) AS total_products
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN categories c ON sp.category_id = c.id
    LEFT JOIN products p ON sp.id = p.provider_id
    $whereSql
    GROUP BY sp.id
    ORDER BY sp.id DESC
");
$stmt->execute($queryParams);
$providers = $stmt->fetchAll();

$isFiltered = (!empty($search) || ($statusFilter !== 'all') || ($categoryFilter > 0));

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">Manage Business Owners</h1>
        <p style="font-size: 14px; color: #64748b;">Review onboarding applications, search directory, inspect business profiles, and manage active status.</p>
    </div>
    <div>
        <a href="<?php echo BASE_URL; ?>/manager/categories.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-tags"></i> Manage Categories
        </a>
    </div>
</div>

<!-- Summary Chips Navigation -->
<div class="summary-chips-row">
    <a href="<?php echo BASE_URL; ?>/manager/providers.php" class="summary-chip <?php echo ($statusFilter === 'all' && empty($search)) ? 'active' : ''; ?>">
        <span>All Business Owners</span>
        <span class="summary-chip-count"><?php echo (int)($summaryCounts['total'] ?? 0); ?></span>
    </a>
    <a href="<?php echo BASE_URL; ?>/manager/providers.php?status=pending" class="summary-chip <?php echo ($statusFilter === 'pending') ? 'active' : ''; ?>">
        <i class="fa-solid fa-clock" style="color: #d97706;"></i>
        <span>Pending Review</span>
        <span class="summary-chip-count" style="color: #d97706;"><?php echo (int)($summaryCounts['pending_count'] ?? 0); ?></span>
    </a>
    <a href="<?php echo BASE_URL; ?>/manager/providers.php?status=approved" class="summary-chip <?php echo ($statusFilter === 'approved') ? 'active' : ''; ?>">
        <i class="fa-solid fa-circle-check" style="color: #059669;"></i>
        <span>Approved &amp; Active</span>
        <span class="summary-chip-count" style="color: #059669;"><?php echo (int)($summaryCounts['approved_count'] ?? 0); ?></span>
    </a>
    <a href="<?php echo BASE_URL; ?>/manager/providers.php?status=rejected" class="summary-chip <?php echo ($statusFilter === 'rejected') ? 'active' : ''; ?>">
        <i class="fa-solid fa-circle-xmark" style="color: #dc2626;"></i>
        <span>Rejected / Inactive</span>
        <span class="summary-chip-count" style="color: #dc2626;"><?php echo (int)($summaryCounts['rejected_count'] ?? 0); ?></span>
    </a>
    <a href="<?php echo BASE_URL; ?>/manager/providers.php?status=featured" class="summary-chip <?php echo ($statusFilter === 'featured') ? 'active' : ''; ?>">
        <i class="fa-solid fa-star" style="color: #b45309;"></i>
        <span>Featured</span>
        <span class="summary-chip-count" style="color: #b45309;"><?php echo (int)($summaryCounts['featured_count'] ?? 0); ?></span>
    </a>
</div>

<!-- Search & Filter Controls -->
<div class="filter-card">
    <form method="GET" action="<?php echo BASE_URL; ?>/manager/providers.php" class="filter-row">
        <div class="filter-input-group">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" class="filter-input" placeholder="Search by business name, owner name, email, city..." value="<?php echo e($search); ?>">
        </div>

        <div class="filter-select-group">
            <select name="status" class="filter-select">
                <option value="all" <?php echo ($statusFilter === 'all') ? 'selected' : ''; ?>>All Statuses</option>
                <option value="pending" <?php echo ($statusFilter === 'pending') ? 'selected' : ''; ?>>Pending Review</option>
                <option value="approved" <?php echo ($statusFilter === 'approved') ? 'selected' : ''; ?>>Approved &amp; Active</option>
                <option value="rejected" <?php echo ($statusFilter === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                <option value="suspended" <?php echo ($statusFilter === 'suspended') ? 'selected' : ''; ?>>Suspended Accounts</option>
                <option value="featured" <?php echo ($statusFilter === 'featured') ? 'selected' : ''; ?>>Featured Only</option>
            </select>
        </div>

        <div class="filter-select-group">
            <select name="category" class="filter-select">
                <option value="0">All Categories</option>
                <?php foreach ($categoriesList as $cat): ?>
                    <option value="<?php echo (int)$cat['id']; ?>" <?php echo ($categoryFilter === (int)$cat['id']) ? 'selected' : ''; ?>>
                        <?php echo e($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary btn-sm" style="height: 40px; padding: 0 16px;">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <?php if ($isFiltered): ?>
                <a href="<?php echo BASE_URL; ?>/manager/providers.php" class="btn btn-outline btn-sm" style="height: 40px; display: inline-flex; align-items: center; padding: 0 14px;" title="Reset filters">
                    <i class="fa-solid fa-xmark"></i> Clear
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Business Owners Data Table -->
<div class="data-table-card">
    <div class="table-header-bar">
        <h3 style="font-size: 16px; margin: 0; color: var(--text-heading); display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-users-viewfinder" style="color: #8C3A27;"></i>
            Business Owner Directory (<?php echo count($providers); ?> <?php echo count($providers) === 1 ? 'profile' : 'profiles'; ?>)
        </h3>
        <?php if ($isFiltered): ?>
            <span style="font-size: 12.5px; color: #64748b;">Filtered Results</span>
        <?php endif; ?>
    </div>

    <?php if (empty($providers)): ?>
        <div class="empty-state-box">
            <div class="empty-state-icon">
                <i class="fa-solid fa-store-slash"></i>
            </div>
            <div class="empty-state-title">No Business Owners Found</div>
            <div class="empty-state-desc">
                <?php if ($isFiltered): ?>
                    No records match your active search and filter criteria. Try adjusting your search query or reset the filters.
                    <div style="margin-top: 16px;">
                        <a href="<?php echo BASE_URL; ?>/manager/providers.php" class="btn btn-outline btn-sm">Clear All Filters</a>
                    </div>
                <?php else: ?>
                    There are currently no registered Business Owners in the database. When entrepreneurs register, they will appear here for review.
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">Logo</th>
                        <th>Business Name &amp; Founder</th>
                        <th>Category</th>
                        <th>City</th>
                        <th>Offerings</th>
                        <th>Featured</th>
                        <th>Approval Status</th>
                        <th style="text-align: right; min-width: 220px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($providers as $p):
                        $logoUrl = get_image_url($p['logo_image'], 'provider');
                        $isPending = ($p['approval_status'] === 'pending');
                        $isApproved = ($p['approval_status'] === 'approved' && ($p['user_status'] ?? 'active') === 'active');
                        $isSuspended = (($p['user_status'] ?? '') === 'suspended');
                        $isRejected = ($p['approval_status'] === 'rejected');

                        $providerModalData = [
                            'id' => (int)$p['id'],
                            'business_name' => $p['business_name'],
                            'slug' => $p['slug'],
                            'owner_name' => $p['owner_name'],
                            'owner_email' => $p['owner_email'],
                            'owner_phone' => $p['owner_phone'] ?? '',
                            'contact_email' => $p['contact_email'] ?? '',
                            'contact_phone' => $p['contact_phone'] ?? '',
                            'category_name' => $p['category_name'] ?? 'General',
                            'city' => $p['city'] ?? 'India',
                            'location' => $p['location'] ?? '',
                            'description' => $p['description'] ?? '',
                            'tagline' => $p['tagline'] ?? '',
                            'approval_status' => $p['approval_status'],
                            'user_status' => $p['user_status'] ?? 'active',
                            'is_featured' => (int)$p['is_featured'],
                            'total_products' => (int)$p['total_products'],
                            'created_at' => format_date($p['created_at'], true),
                            'updated_at' => format_date($p['updated_at'], true),
                            'logo_url' => $logoUrl
                        ];
                    ?>
                        <tr>
                            <td>
                                <img src="<?php echo e($logoUrl); ?>" alt="<?php echo e($p['business_name']); ?>" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0;">
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--text-heading); font-size: 14px;">
                                    <a href="<?php echo BASE_URL; ?>/provider-profile.php?slug=<?php echo urlencode($p['slug']); ?>" target="_blank" style="color: inherit; text-decoration: none;" title="View public storefront">
                                        <?php echo e($p['business_name']); ?> <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 11px; color: #94a3b8;"></i>
                                    </a>
                                </div>
                                <div style="font-size: 12px; color: #64748b;">
                                    Founder: <strong><?php echo e($p['owner_name']); ?></strong> (<?php echo e($p['owner_email']); ?>)
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background: #F1E0DB; color: #8C3A27; font-size: 11px;">
                                    <?php echo e($p['category_name'] ?? 'General'); ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-size: 13px; color: #334155;"><?php echo e($p['city'] ?? 'India'); ?></div>
                                <?php if (!empty($p['location'])): ?>
                                    <small style="color: #94a3b8;"><?php echo e($p['location']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: #1e293b;"><?php echo (int)$p['total_products']; ?></strong>
                                <span style="font-size: 12px; color: #64748b;">items</span>
                            </td>
                            <td>
                                <form method="POST" action="<?php echo BASE_URL; ?>/manager/providers.php" style="display: inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_featured">
                                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                    <button type="submit" class="badge" style="background: <?php echo $p['is_featured'] ? '#fef3c7; color: #b45309;' : '#f1f5f9; color: #94a3b8;'; ?> border: none; cursor: pointer; text-decoration: none;" title="Click to toggle featured placement">
                                        <i class="fa-solid fa-star"></i> <?php echo $p['is_featured'] ? 'Featured' : 'No'; ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <?php if ($isSuspended): ?>
                                    <span class="badge" style="background: #f1f5f9; color: #475569;">
                                        <i class="fa-solid fa-user-slash"></i> Suspended
                                    </span>
                                <?php elseif ($isPending): ?>
                                    <span class="badge" style="background: #fef3c7; color: #92400e;">
                                        <i class="fa-solid fa-clock"></i> Pending Review
                                    </span>
                                <?php elseif ($isApproved): ?>
                                    <span class="badge" style="background: #d1fae5; color: #065f46;">
                                        <i class="fa-solid fa-check-circle"></i> Approved
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: #fee2e2; color: #991b1b;">
                                        <i class="fa-solid fa-circle-xmark"></i> Rejected
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                                    <!-- Inspect Details Button -->
                                    <button type="button" class="btn btn-outline btn-sm" onclick="openProviderModal(<?php echo htmlspecialchars(json_encode($providerModalData), ENT_QUOTES, 'UTF-8'); ?>)" title="Inspect complete details">
                                        <i class="fa-solid fa-eye"></i> Details
                                    </button>

                                    <!-- Approve Form (POST) -->
                                    <?php if (!$isApproved): ?>
                                        <form method="POST" action="<?php echo BASE_URL; ?>/manager/providers.php" style="display: inline;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                            <button type="submit" class="btn btn-primary btn-sm" title="Approve profile and make active">
                                                <i class="fa-solid fa-check"></i> Approve
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Reject Form (POST) for Pending Applications -->
                                    <?php if ($isPending): ?>
                                        <form method="POST" action="<?php echo BASE_URL; ?>/manager/providers.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to reject this Business Owner application?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color: #dc2626; border-color: #fca5a5;" title="Reject application">
                                                <i class="fa-solid fa-xmark"></i> Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Suspend Form (POST) for Approved Profiles -->
                                    <?php if ($isApproved): ?>
                                        <form method="POST" action="<?php echo BASE_URL; ?>/manager/providers.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to suspend this Business Owner? This will deactivate their storefront and user login.');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="suspend">
                                            <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color: #dc2626; border-color: #fca5a5;" title="Suspend Business Owner">
                                                <i class="fa-solid fa-ban"></i> Suspend
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

<!-- Business Owner Details Inspection Modal -->
<div id="providerDetailsModal" class="modal-backdrop" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modalProviderTitle">
        <div class="modal-header">
            <h3 id="modalProviderTitle" class="modal-title">Business Owner Details</h3>
            <button type="button" class="modal-close-btn" onclick="closeProviderModal()" aria-label="Close modal">&times;</button>
        </div>

        <div class="modal-body">
            <!-- Header Row with Logo & Quick Identifiers -->
            <div style="display: flex; gap: 16px; align-items: center; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9;">
                <img id="modalLogo" src="" alt="" style="width: 58px; height: 58px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0;">
                <div>
                    <h4 id="modalBizName" style="font-size: 18px; margin: 0 0 4px 0; color: #1e293b;"></h4>
                    <div id="modalTagline" style="font-size: 13px; color: #64748b; margin-bottom: 6px;"></div>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <span id="modalCategoryBadge" class="badge" style="background: #F1E0DB; color: #8C3A27; font-size: 11px;"></span>
                        <span id="modalStatusBadge"></span>
                        <a id="modalProfileLink" href="#" target="_blank" class="btn btn-outline btn-sm" style="padding: 2px 8px; font-size: 11.5px;">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Storefront
                        </a>
                    </div>
                </div>
            </div>

            <!-- Two-Column Information Grid -->
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Founder / Owner Name</div>
                    <div id="modalOwnerName" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Account Email</div>
                    <div id="modalOwnerEmail" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Contact Phone</div>
                    <div id="modalContactPhone" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Contact Email</div>
                    <div id="modalContactEmail" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">City &amp; Region</div>
                    <div id="modalCity" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Physical Location / Address</div>
                    <div id="modalLocation" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Catalog Offerings</div>
                    <div id="modalTotalProducts" class="detail-value"></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Registration Date</div>
                    <div id="modalCreatedAt" class="detail-value"></div>
                </div>
            </div>

            <!-- Description / Biography -->
            <div>
                <div class="detail-label">Business Description &amp; Bio</div>
                <div id="modalDescription" style="font-size: 13.5px; color: #475569; line-height: 1.6; background: #f8fafc; padding: 12px 14px; border-radius: 6px; border: 1px solid #f1f5f9; min-height: 50px;"></div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline btn-sm" onclick="closeProviderModal()">Close</button>

            <!-- Modal Action Forms (POST) -->
            <form id="modalApproveForm" method="POST" action="<?php echo BASE_URL; ?>/manager/providers.php" style="display: inline;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="approve">
                <input type="hidden" id="modalApproveId" name="id" value="">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-check"></i> Approve Profile
                </button>
            </form>

            <form id="modalRejectForm" method="POST" action="<?php echo BASE_URL; ?>/manager/providers.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to reject this application?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" id="modalRejectId" name="id" value="">
                <button type="submit" class="btn btn-outline btn-sm" style="color: #dc2626; border-color: #fca5a5;">
                    <i class="fa-solid fa-xmark"></i> Reject
                </button>
            </form>

            <form id="modalSuspendForm" method="POST" action="<?php echo BASE_URL; ?>/manager/providers.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to suspend this Business Owner?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="suspend">
                <input type="hidden" id="modalSuspendId" name="id" value="">
                <button type="submit" class="btn btn-outline btn-sm" style="color: #dc2626; border-color: #fca5a5;">
                    <i class="fa-solid fa-ban"></i> Suspend Account
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openProviderModal(data) {
    if (!data) return;

    document.getElementById('modalLogo').src = data.logo_url || '';
    document.getElementById('modalLogo').alt = data.business_name || '';
    document.getElementById('modalBizName').textContent = data.business_name || 'Business Profile';
    document.getElementById('modalTagline').textContent = data.tagline || 'No tagline provided';
    document.getElementById('modalCategoryBadge').textContent = data.category_name || 'General';

    // Status Badge
    const statusContainer = document.getElementById('modalStatusBadge');
    if (data.user_status === 'suspended') {
        statusContainer.innerHTML = '<span class="badge" style="background: #f1f5f9; color: #475569;">Suspended</span>';
    } else if (data.approval_status === 'pending') {
        statusContainer.innerHTML = '<span class="badge" style="background: #fef3c7; color: #92400e;">Pending Review</span>';
    } else if (data.approval_status === 'approved') {
        statusContainer.innerHTML = '<span class="badge" style="background: #d1fae5; color: #065f46;">Approved</span>';
    } else {
        statusContainer.innerHTML = '<span class="badge" style="background: #fee2e2; color: #991b1b;">Rejected</span>';
    }

    // Public storefront link
    document.getElementById('modalProfileLink').href = '<?php echo BASE_URL; ?>/provider-profile.php?slug=' + encodeURIComponent(data.slug);

    // Detail values
    document.getElementById('modalOwnerName').textContent = data.owner_name || 'N/A';
    document.getElementById('modalOwnerEmail').textContent = data.owner_email || 'N/A';
    document.getElementById('modalContactPhone').textContent = data.contact_phone || (data.owner_phone || 'N/A');
    document.getElementById('modalContactEmail').textContent = data.contact_email || (data.owner_email || 'N/A');
    document.getElementById('modalCity').textContent = data.city || 'India';
    document.getElementById('modalLocation').textContent = data.location || 'Not specified';
    document.getElementById('modalTotalProducts').textContent = (data.total_products || 0) + ' active listings';
    document.getElementById('modalCreatedAt').textContent = data.created_at || 'N/A';
    document.getElementById('modalDescription').textContent = data.description || 'No business description has been entered yet.';

    // Action form IDs
    document.getElementById('modalApproveId').value = data.id;
    document.getElementById('modalRejectId').value = data.id;
    document.getElementById('modalSuspendId').value = data.id;

    // Toggle button visibility based on status
    const isApproved = (data.approval_status === 'approved' && data.user_status === 'active');
    const isPending = (data.approval_status === 'pending');

    document.getElementById('modalApproveForm').style.display = isApproved ? 'none' : 'inline';
    document.getElementById('modalRejectForm').style.display = isPending ? 'inline' : 'none';
    document.getElementById('modalSuspendForm').style.display = isApproved ? 'inline' : 'none';

    // Show modal
    const modal = document.getElementById('providerDetailsModal');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
}

function closeProviderModal() {
    const modal = document.getElementById('providerDetailsModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

// Close on backdrop click or ESC key
document.getElementById('providerDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeProviderModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeProviderModal();
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
