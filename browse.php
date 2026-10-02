<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Browse & Search Catalog (Products, Services & Providers)
 * Location: /browse.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$db = getDB();

// 1. Capture and sanitize query parameters
$searchQuery = sanitize_string($_GET['q'] ?? '');
$categorySlug = sanitize_string($_GET['category'] ?? '');
$filterType = sanitize_string($_GET['type'] ?? ''); // 'service', 'product', ''
$specialFilter = sanitize_string($_GET['filter'] ?? ''); // 'offers'
$currentTab = sanitize_string($_GET['tab'] ?? 'products'); // 'products' or 'providers'

// 2. Fetch all active categories for sidebar filter
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// 3. Resolve active category ID if slug provided
$activeCategory = null;
if (!empty($categorySlug)) {
    $cStmt = $db->prepare("SELECT * FROM categories WHERE slug = :slug LIMIT 1");
    $cStmt->execute(['slug' => $categorySlug]);
    $activeCategory = $cStmt->fetch();
}

// 4. Query for Products & Services
$productWhere = ["p.status = 'active'", "sp.approval_status = 'approved'"];
$productParams = [];

if (!empty($searchQuery)) {
    $productWhere[] = "(p.title LIKE :q OR p.description LIKE :q OR sp.business_name LIKE :q)";
    $productParams['q'] = '%' . $searchQuery . '%';
}

if ($activeCategory) {
    $productWhere[] = "p.category_id = :cat_id";
    $productParams['cat_id'] = $activeCategory['id'];
}

if (!empty($filterType) && in_array($filterType, ['service', 'product', 'package'])) {
    $productWhere[] = "p.service_type = :stype";
    $productParams['stype'] = $filterType;
}

if ($specialFilter === 'offers') {
    $productWhere[] = "p.offer_enabled = 1 AND p.offer_end_at > NOW()";
}

$productSql = "
    SELECT p.*, c.name AS category_name, c.slug AS category_slug, sp.business_name, sp.slug AS provider_slug, sp.city, sp.logo_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN service_providers sp ON p.provider_id = sp.id
    WHERE " . implode(' AND ', $productWhere) . "
    ORDER BY p.id DESC
";
$pStmt = $db->prepare($productSql);
$pStmt->execute($productParams);
$productsList = $pStmt->fetchAll();

// 5. Query for Service Providers
$providerWhere = ["sp.approval_status = 'approved'"];
$providerParams = [];

if (!empty($searchQuery)) {
    $providerWhere[] = "(sp.business_name LIKE :pq OR sp.description LIKE :pq OR sp.city LIKE :pq)";
    $providerParams['pq'] = '%' . $searchQuery . '%';
}

if ($activeCategory) {
    $providerWhere[] = "sp.category_id = :pcat_id";
    $providerParams['pcat_id'] = $activeCategory['id'];
}

$providerSql = "
    SELECT sp.*, c.name AS category_name, COUNT(p.id) AS total_items
    FROM service_providers sp
    LEFT JOIN categories c ON sp.category_id = c.id
    LEFT JOIN products p ON sp.id = p.provider_id AND p.status = 'active'
    WHERE " . implode(' AND ', $providerWhere) . "
    GROUP BY sp.id
    ORDER BY sp.is_featured DESC, sp.business_name ASC
";
$prStmt = $db->prepare($providerSql);
$prStmt->execute($providerParams);
$providersList = $prStmt->fetchAll();

$pageTitle = 'Explore Marketplace Offerings | Magal Creator';
include __DIR__ . '/includes/header.php';
?>

<div style="background: var(--color-bg, #FAF8F5); border-bottom: 1px solid var(--color-border, #E8E2DA); padding: 25px 0;">
    <div class="container">
        <!-- Search & Filter Controls -->
        <form action="<?php echo BASE_URL; ?>/browse.php" method="GET" style="display: flex; gap: 12px; flex-wrap: wrap;">
            <input type="hidden" name="tab" value="<?php echo e($currentTab); ?>">
            
            <div style="flex: 2; min-width: 250px; position: relative; display: flex; align-items: center;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 14px; color: var(--color-text-muted, #6E6761);"></i>
                <input type="text" name="q" class="form-control" style="padding-left: 42px; border-color: var(--color-border, #E8E2DA);" 
                       placeholder="Search services, products, or providers..." 
                       value="<?php echo e($searchQuery); ?>">
            </div>

            <div style="flex: 1; min-width: 180px;">
                <select name="category" class="form-control" style="border-color: var(--color-border, #E8E2DA);">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo e($cat['slug']); ?>" <?php echo ($categorySlug === $cat['slug']) ? 'selected' : ''; ?>>
                            <?php echo e($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex: 1; min-width: 150px;">
                <select name="type" class="form-control" style="border-color: var(--color-border, #E8E2DA);">
                    <option value="">All Types</option>
                    <option value="service" <?php echo ($filterType === 'service') ? 'selected' : ''; ?>>Services</option>
                    <option value="product" <?php echo ($filterType === 'product') ? 'selected' : ''; ?>>Physical Products</option>
                </select>
            </div>

            <button type="submit" class="btn text-white" style="background-color: var(--color-primary, #8C3A27); padding: 10px 24px; font-weight: 600;">
                <i class="fa-solid fa-filter"></i> Filter
            </button>

            <?php if (!empty($searchQuery) || !empty($categorySlug) || !empty($filterType) || !empty($specialFilter)): ?>
                <a href="<?php echo BASE_URL; ?>/browse.php?tab=<?php echo e($currentTab); ?>" class="btn btn-outline" style="padding: 10px 18px; border-color: var(--color-border, #E8E2DA); color: var(--color-text-muted, #6E6761);">
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<main class="container" style="padding: 40px 20px 80px;">
    <!-- Discovery Tabs: Offerings vs Providers -->
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--color-border, #E8E2DA); margin-bottom: 30px; padding-bottom: 10px;">
        <div style="display: flex; gap: 20px;">
            <a href="<?php echo BASE_URL; ?>/browse.php?tab=products<?php echo (!empty($searchQuery) ? '&q='.urlencode($searchQuery) : ''); ?><?php echo (!empty($categorySlug) ? '&category='.urlencode($categorySlug) : ''); ?>"
               style="font-size: 16px; font-weight: 700; color: <?php echo ($currentTab !== 'providers') ? 'var(--color-primary, #8C3A27)' : 'var(--color-text-muted, #6E6761)'; ?>; display: flex; align-items: center; gap: 8px; text-decoration: none;">
                <i class="fa-solid fa-box-open"></i> Services &amp; Products (<?php echo count($productsList); ?>)
            </a>
            <a href="<?php echo BASE_URL; ?>/browse.php?tab=providers<?php echo (!empty($searchQuery) ? '&q='.urlencode($searchQuery) : ''); ?><?php echo (!empty($categorySlug) ? '&category='.urlencode($categorySlug) : ''); ?>"
               style="font-size: 16px; font-weight: 700; color: <?php echo ($currentTab === 'providers') ? 'var(--color-primary, #8C3A27)' : 'var(--color-text-muted, #6E6761)'; ?>; display: flex; align-items: center; gap: 8px; text-decoration: none;">
                <i class="fa-solid fa-users-viewfinder"></i> Women Entrepreneurs (<?php echo count($providersList); ?>)
            </a>
        </div>

        <?php if ($activeCategory): ?>
            <span style="font-size: 13px; font-weight: 600; color: var(--color-primary, #8C3A27); background: var(--color-support-light, #EBF2EE); padding: 4px 12px; border-radius: 20px;">
                Category: <?php echo e($activeCategory['name']); ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- TAB 1: PRODUCTS & SERVICES -->
    <?php if ($currentTab !== 'providers'): ?>
        <?php if (empty($productsList)): ?>
            <div style="text-align: center; padding: 60px 20px; background: var(--color-card-bg, #ffffff); border-radius: 16px; border: 1px dashed var(--color-border, #E8E2DA);">
                <i class="fa-solid fa-magnifying-glass" style="font-size: 40px; color: var(--color-text-muted, #6E6761); margin-bottom: 16px;"></i>
                <h3 style="color: var(--color-text, #2C2825); margin-bottom: 8px;">No listings matched your criteria</h3>
                <p style="color: var(--color-text-muted, #6E6761); margin-bottom: 20px;">Try adjusting your keyword or clearing the category filter.</p>
                <a href="<?php echo BASE_URL; ?>/browse.php" class="btn btn-outline btn-sm" style="border-color: var(--color-primary, #8C3A27); color: var(--color-primary, #8C3A27);">Clear All Filters</a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($productsList as $prod): 
                    $offer = get_product_offer_details($prod);
                ?>
                    <div class="product-card" style="background-color: var(--color-card-bg, #FFFFFF); border-radius: 12px; overflow: hidden; border: 1px solid var(--color-border, #E8E2DA); display: flex; flex-direction: column;">
                        <div class="product-image-wrap">
                            <img src="<?php echo e(get_image_url($prod['cover_image'], 'product')); ?>" alt="<?php echo e($prod['title']); ?>" loading="lazy">
                            <div class="product-badge-group">
                                <span class="badge badge-<?php echo ($prod['service_type'] === 'service') ? 'service' : 'product'; ?>">
                                    <?php echo strtoupper($prod['service_type']); ?>
                                </span>
                                <?php if ($offer['status'] === 'ACTIVE'): ?>
                                    <span class="badge badge-offer" style="background-color: var(--color-accent, #D9822B);">
                                        <i class="fa-solid fa-fire"></i> <?php echo $offer['savings_percent']; ?>% OFF
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($offer['status'] === 'ACTIVE'): ?>
                            <div class="card-countdown-banner" 
                                 data-countdown-target="<?php echo e($offer['end_timestamp_iso']); ?>"
                                 data-countdown-type="end">
                                <span><i class="fa-solid fa-hourglass-half"></i> Offer ends in:</span>
                                <span class="countdown-digits">...</span>
                            </div>
                        <?php endif; ?>

                        <div class="product-body" style="padding: 16px; flex-grow: 1; display: flex; flex-direction: column;">
                            <span class="product-category-tag"><?php echo e($prod['category_name']); ?></span>
                            <h3 class="product-title">
                                <a href="<?php echo BASE_URL; ?>/product-details.php?id=<?php echo (int)$prod['id']; ?>">
                                    <?php echo e($prod['title']); ?>
                                </a>
                            </h3>

                            <!-- Woman Entrepreneur Storytelling Section -->
                            <div class="product-provider-meta d-flex align-items-center gap-2 my-2 pt-2 border-top" style="border-top: 1px solid var(--color-border, #E8E2DA);">
                                <img src="<?php echo e(get_image_url($prod['logo_image'] ?? '', 'provider')); ?>" alt="<?php echo e($prod['business_name']); ?>" class="rounded-circle" width="28" height="28" style="object-fit: cover; border: 1px solid var(--color-border, #E8E2DA);">
                                <span class="small text-muted">
                                    by <a href="<?php echo BASE_URL; ?>/provider-profile.php?slug=<?php echo urlencode($prod['provider_slug']); ?>" style="color: var(--color-primary, #8C3A27); font-weight: 700; text-decoration: none;">
                                        <?php echo e($prod['business_name']); ?>
                                    </a>
                                    <?php if (!empty($prod['city'])): ?>
                                        <span>&bull; <?php echo e($prod['city']); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="product-price-row mt-auto pt-2">
                                <span class="price-current" style="color: var(--color-primary, #8C3A27); font-weight: 700;"><?php echo format_price($offer['effective_price']); ?></span>
                                <?php if ($offer['savings_amount'] > 0): ?>
                                    <span class="price-original"><?php echo format_price($prod['price']); ?></span>
                                    <span class="price-discount-tag">Save <?php echo format_price($offer['savings_amount']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div style="margin-top: 14px;">
                                <a href="<?php echo BASE_URL; ?>/product-details.php?id=<?php echo (int)$prod['id']; ?>" class="btn btn-outline-primary btn-sm" style="width: 100%; border-color: var(--color-primary, #8C3A27); color: var(--color-primary, #8C3A27);">
                                    View Details &amp; Book
                                </a>
                            </div>

                            <!-- Subtle Impact Badge -->
                            <div class="mt-2 text-center">
                                <small style="color: var(--color-support, #6B8E7B); font-size: 0.75rem; font-weight: 600;">✨ Your order powers independent craft</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <!-- TAB 2: SERVICE PROVIDERS -->
    <?php else: ?>
        <?php if (empty($providersList)): ?>
            <div style="text-align: center; padding: 60px 20px; background: var(--color-card-bg, #ffffff); border-radius: 16px; border: 1px dashed var(--color-border, #E8E2DA);">
                <i class="fa-solid fa-users" style="font-size: 40px; color: var(--color-text-muted, #6E6761); margin-bottom: 16px;"></i>
                <h3 style="color: var(--color-text, #2C2825); margin-bottom: 8px;">No women entrepreneurs found</h3>
                <p style="color: var(--color-text-muted, #6E6761); margin-bottom: 20px;">Try searching for a different specialty or location.</p>
                <a href="<?php echo BASE_URL; ?>/browse.php?tab=providers" class="btn btn-outline btn-sm" style="border-color: var(--color-primary, #8C3A27); color: var(--color-primary, #8C3A27);">Reset Search</a>
            </div>
        <?php else: ?>
            <div class="providers-grid">
                <?php foreach ($providersList as $prov): ?>
                    <div class="provider-card" style="background-color: var(--color-card-bg, #ffffff); border-radius: 12px; border: 1px solid var(--color-border, #E8E2DA); padding: 20px;">
                        <div class="provider-header">
                            <img src="<?php echo e(get_image_url($prov['logo_image'], 'provider')); ?>" alt="<?php echo e($prov['business_name']); ?>" class="provider-avatar" style="border: 2px solid var(--color-accent, #D9822B);">
                            <div>
                                <h3 class="provider-name" style="color: var(--color-text, #2C2825);"><?php echo e($prov['business_name']); ?></h3>
                                <span class="provider-specialty">
                                    <i class="fa-solid fa-tag" style="color: var(--color-primary, #8C3A27);"></i> <?php echo e($prov['category_name'] ?? 'Entrepreneur'); ?>
                                </span>
                            </div>
                        </div>

                        <p class="provider-bio" style="color: var(--color-text-muted, #6E6761);"><?php echo e($prov['description']); ?></p>

                        <div class="provider-meta-footer">
                            <span><i class="fa-solid fa-location-dot" style="color: var(--color-primary, #8C3A27);"></i> <?php echo e($prov['city'] ?? 'India'); ?></span>
                            <span><i class="fa-solid fa-box-open"></i> <?php echo (int)$prov['total_items']; ?> Offerings</span>
                        </div>

                        <div style="margin-top: 18px;">
                            <a href="<?php echo BASE_URL; ?>/provider-profile.php?slug=<?php echo urlencode($prov['slug']); ?>" class="btn btn-outline btn-sm" style="width: 100%; border-color: var(--color-primary, #8C3A27); color: var(--color-primary, #8C3A27);">
                                Visit Studio Profile <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>