<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Homepage (Public Front)
 * Location: /index.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle = 'Magal Creator | One Platform. Many Women Entrepreneurs.';
$metaDescription = 'Connect with inspiring women entrepreneurs offering fashion, bridal henna, artisanal baking, organic wellness, and creative services.';

$db = getDB();

// 1. Fetch Dynamic Active Categories with product count
$categories = [];
try {
    $cStmt = $db->query("
        SELECT c.*, COUNT(p.id) AS total_products 
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
        WHERE c.status = 'active'
        GROUP BY c.id
        ORDER BY c.name ASC
    ");
    $categories = $cStmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// 2. Fetch Limited-Time Offers (Where offer_enabled = 1 and offer_end_at > NOW())
$activeOffers = [];
try {
    $oStmt = $db->query("
        SELECT p.*, c.name AS category_name, sp.business_name, sp.slug AS provider_slug, sp.city
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN service_providers sp ON p.provider_id = sp.id
        WHERE p.status = 'active' 
          AND p.offer_enabled = 1 
          AND p.offer_end_at > NOW()
        ORDER BY p.offer_end_at ASC
        LIMIT 6
    ");
    $activeOffers = $oStmt->fetchAll();
} catch (Exception $e) {
    $activeOffers = [];
}

// 3. Fetch Featured Women Entrepreneurs
$featuredProviders = [];
try {
    $pStmt = $db->query("
        SELECT sp.*, c.name AS category_name, COUNT(p.id) AS active_products_count
        FROM service_providers sp
        LEFT JOIN categories c ON sp.category_id = c.id
        LEFT JOIN products p ON sp.id = p.provider_id AND p.status = 'active'
        WHERE sp.approval_status = 'approved'
        GROUP BY sp.id
        ORDER BY sp.is_featured DESC, sp.created_at DESC
        LIMIT 4
    ");
    $featuredProviders = $pStmt->fetchAll();
} catch (Exception $e) {
    $featuredProviders = [];
}

// 4. Fetch Popular / Recently Added Offerings
$recentProducts = [];
try {
    $rStmt = $db->query("
        SELECT p.*, c.name AS category_name, sp.business_name, sp.slug AS provider_slug
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN service_providers sp ON p.provider_id = sp.id
        WHERE p.status = 'active'
        ORDER BY p.id DESC
        LIMIT 8
    ");
    $recentProducts = $rStmt->fetchAll();
} catch (Exception $e) {
    $recentProducts = [];
}

include __DIR__ . '/includes/header.php';
?>

<!-- 1. HERO SECTION & MARKETPLACE EXPLANATION -->
<section class="hero-section">
    <div class="container">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fa-solid fa-award"></i>  Women In Business
            </div>
            <h1 class="hero-title">
                One Platform. <span>Many Women Entrepreneurs.</span>
            </h1>
            <p class="hero-description">
                A digital marketplace that brings women entrepreneurs and customers together in one place. Discover boutique fashion, bridal henna, artisanal baking, organic wellness, and creative services from verified women-owned businesses.
            </p>

            <!-- Search & Quick Discovery Bar -->
            <form action="<?php echo BASE_URL; ?>/browse.php" method="GET" class="search-filter-box">
                <div class="search-input-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="Search services, products, or women entrepreneurs..." value="">
                </div>
                <div class="search-select-wrap">
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo e($cat['slug']); ?>"><?php echo e($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-arrow-right"></i> Explore
                </button>
            </form>
        </div>

        <!-- DUAL AUDIENCE VALUE PROPOSITION (CUSTOMERS VS ENTREPRENEURS) -->
        <div class="audience-grid">
            <!-- For Customers -->
            <div class="audience-card customer">
                <div class="audience-icon">
                    <i class="fa-solid fa-heart"></i>
                </div>
                <h3>For Customers &amp; Clients</h3>
                <p>
                    Looking for exceptional products and specialized services? Support women-owned businesses and book trusted services with total confidence.
                </p>
                <ul class="audience-features">
                    <li><i class="fa-solid fa-circle-check"></i> Browse diverse women entrepreneurs in one marketplace</li>
                    <li><i class="fa-solid fa-circle-check"></i> Transparent pricing, portfolios &amp; client reviews</li>
                    <li><i class="fa-solid fa-circle-check"></i> Secure platform payments &amp; guaranteed booking confirmations</li>
                </ul>
                <a href="<?php echo BASE_URL; ?>/browse.php" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-magnifying-glass"></i> Explore Services
                </a>
            </div>

            <!-- For Service Providers / Women Entrepreneurs -->
            <div class="audience-card provider">
                <div class="audience-icon">
                    <i class="fa-solid fa-store"></i>
                </div>
                <h3>For Women Entrepreneurs</h3>
                <p>
                    Are you a woman business owner or independent service provider? Create your digital storefront, reach clients, and scale your brand.
                </p>
                <ul class="audience-features">
                    <li><i class="fa-solid fa-circle-check"></i> Create your branded business showcase in minutes</li>
                    <li><i class="fa-solid fa-circle-check"></i> Add products, packages, &amp; limited-time countdown offers</li>
                    <li><i class="fa-solid fa-circle-check"></i> Automated commission calculations &amp; transparent settlements</li>
                </ul>
                <a href="<?php echo BASE_URL; ?>/register-provider.php" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-sparkles"></i> List Your Business
                </a>
            </div>
        </div>
    </div>
</section>

<!-- 2. BROWSE BY CATEGORY -->
<section style="padding: 70px 0 30px;">
    <div class="container">
        <div class="section-header">
            <div>
                <h2 class="section-title">Explore Categories</h2>
                <p class="section-subtitle">Discover curated offerings across dynamic service sectors</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/browse.php" class="btn btn-outline btn-sm">
                View All Categories <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="category-grid">
            <?php foreach ($categories as $cat): ?>
                <a href="<?php echo BASE_URL; ?>/browse.php?category=<?php echo urlencode($cat['slug']); ?>" class="category-card">
                    <div class="category-icon">
                        <i class="fa-solid <?php echo e($cat['icon_class'] ?: 'fa-tag'); ?>"></i>
                    </div>
                    <span class="category-name"><?php echo e($cat['name']); ?></span>
                    <span class="category-count"><?php echo (int)$cat['total_products']; ?> offerings</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 3. LIMITED-TIME OFFERS & LIVE COUNTDOWNS -->
<?php if (!empty($activeOffers)): ?>
<section style="padding: 40px 0 70px; background: #fff5f7; border-top: 1px solid #fed7aa; border-bottom: 1px solid #fed7aa;">
    <div class="container">
        <div class="section-header">
            <div>
                <div style="display: inline-flex; align-items: center; gap: 6px; color: #be123c; font-weight: 800; font-size: 13px; text-transform: uppercase; margin-bottom: 4px;">
                    <i class="fa-solid fa-fire"></i> Limited-Time Offers
                </div>
                <h2 class="section-title">Deals Ending Soon</h2>
                <p class="section-subtitle">Exclusive limited-time promotions with live countdown clocks</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/browse.php?filter=offers" class="btn btn-primary btn-sm">
                All Offers <i class="fa-solid fa-bolt"></i>
            </a>
        </div>

        <div class="products-grid">
            <?php foreach ($activeOffers as $prod): 
                $offer = get_product_offer_details($prod);
            ?>
                <div class="product-card">
                    <!-- Image & Badges -->
                    <div class="product-image-wrap">
                        <img src="<?php echo e(get_image_url($prod['cover_image'], 'product')); ?>" alt="<?php echo e($prod['title']); ?>" loading="lazy">
                        <div class="product-badge-group">
                            <span class="badge badge-offer">
                                <i class="fa-solid fa-fire"></i> <?php echo $offer['savings_percent']; ?>% OFF
                            </span>
                        </div>
                    </div>

                    <!-- Authoritative Real-Time Countdown Banner -->
                    <?php if ($offer['status'] === 'ACTIVE'): ?>
                        <div class="card-countdown-banner" 
                             data-countdown-target="<?php echo e($offer['end_timestamp_iso']); ?>"
                             data-countdown-type="end"
                             data-normal-price="<?php echo e(format_price($prod['price'])); ?>">
                            <span><i class="fa-solid fa-hourglass-half"></i> Offer ends in:</span>
                            <span class="countdown-digits">Calculating...</span>
                        </div>
                    <?php elseif ($offer['status'] === 'UPCOMING'): ?>
                        <div class="card-countdown-banner" 
                             style="background: #1e293b;"
                             data-countdown-target="<?php echo e($offer['start_timestamp_iso']); ?>"
                             data-countdown-type="start">
                            <span><i class="fa-solid fa-calendar-clock"></i> Starts in:</span>
                            <span class="countdown-digits">Upcoming</span>
                        </div>
                    <?php endif; ?>

                    <div class="product-body">
                        <span class="product-category-tag"><?php echo e($prod['category_name']); ?></span>
                        <h3 class="product-title">
                            <a href="<?php echo BASE_URL; ?>/product-details.php?id=<?php echo (int)$prod['id']; ?>">
                                <?php echo e($prod['title']); ?>
                            </a>
                        </h3>

                        <div class="product-provider-meta">
                            <i class="fa-solid fa-store"></i>
                            <a href="<?php echo BASE_URL; ?>/provider-profile.php?slug=<?php echo urlencode($prod['provider_slug']); ?>" style="color: inherit; font-weight: 600;">
                                <?php echo e($prod['business_name']); ?>
                            </a>
                            <?php if (!empty($prod['city'])): ?>
                                <span>&bull; <?php echo e($prod['city']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="product-price-row">
                            <span class="price-current"><?php echo format_price($offer['effective_price']); ?></span>
                            <?php if ($offer['savings_amount'] > 0): ?>
                                <span class="price-original"><?php echo format_price($prod['price']); ?></span>
                                <span class="price-discount-tag">Save <?php echo format_price($offer['savings_amount']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div style="margin-top: 16px;">
                            <a href="<?php echo BASE_URL; ?>/product-details.php?id=<?php echo (int)$prod['id']; ?>" class="btn btn-outline-primary btn-sm" style="width: 100%;">
                                View Details &amp; Book
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 4. FEATURED WOMEN ENTREPRENEURS -->
<section style="padding: 70px 0;">
    <div class="container">
        <div class="section-header">
            <div>
                <h2 class="section-title">Featured Women Entrepreneurs</h2>
                <p class="section-subtitle">Meet the visionary founders building inspiring businesses</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/browse.php?tab=providers" class="btn btn-outline btn-sm">
                Meet All Entrepreneurs <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="providers-grid">
            <?php foreach ($featuredProviders as $prov): ?>
                <div class="provider-card">
                    <div class="provider-header">
                        <img src="<?php echo e(get_image_url($prov['logo_image'], 'provider')); ?>" alt="<?php echo e($prov['business_name']); ?>" class="provider-avatar">
                        <div>
                            <h3 class="provider-name"><?php echo e($prov['business_name']); ?></h3>
                            <span class="provider-specialty">
                                <i class="fa-solid fa-tag"></i> <?php echo e($prov['category_name'] ?? 'Entrepreneur'); ?>
                            </span>
                        </div>
                    </div>

                    <p class="provider-bio">
                        <?php echo e($prov['description']); ?>
                    </p>

                    <div class="provider-meta-footer">
                        <span><i class="fa-solid fa-location-dot" style="color: #e11d48;"></i> <?php echo e($prov['city'] ?? 'India'); ?></span>
                        <span><i class="fa-solid fa-box-open"></i> <?php echo (int)$prov['active_products_count']; ?> Listings</span>
                    </div>

                    <div style="margin-top: 18px;">
                        <a href="<?php echo BASE_URL; ?>/provider-profile.php?slug=<?php echo urlencode($prov['slug']); ?>" class="btn btn-outline btn-sm" style="width: 100%;">
                            Visit Business Profile <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 5. POPULAR & RECENT OFFERINGS -->
<section style="padding: 60px 0 80px; background: #ffffff; border-top: 1px solid var(--border-color);">
    <div class="container">
        <div class="section-header">
            <div>
                <h2 class="section-title">Explore Services &amp; Products</h2>
                <p class="section-subtitle">Handpicked bespoke creations and services ready to book</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/browse.php" class="btn btn-outline btn-sm">
                View Full Catalog <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="products-grid">
            <?php foreach ($recentProducts as $item): 
                $itemOffer = get_product_offer_details($item);
            ?>
                <div class="product-card">
                    <div class="product-image-wrap">
                        <img src="<?php echo e(get_image_url($item['cover_image'], 'product')); ?>" alt="<?php echo e($item['title']); ?>" loading="lazy">
                        <div class="product-badge-group">
                            <span class="badge badge-<?php echo ($item['service_type'] === 'service') ? 'service' : 'product'; ?>">
                                <?php echo strtoupper($item['service_type']); ?>
                            </span>
                            <?php if ($itemOffer['status'] === 'ACTIVE'): ?>
                                <span class="badge badge-offer">
                                    <i class="fa-solid fa-fire"></i> <?php echo $itemOffer['savings_percent']; ?>% OFF
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($itemOffer['status'] === 'ACTIVE'): ?>
                        <div class="card-countdown-banner" 
                             data-countdown-target="<?php echo e($itemOffer['end_timestamp_iso']); ?>"
                             data-countdown-type="end">
                            <span><i class="fa-solid fa-hourglass-half"></i> Offer ends:</span>
                            <span class="countdown-digits">...</span>
                        </div>
                    <?php endif; ?>

                    <div class="product-body">
                        <span class="product-category-tag"><?php echo e($item['category_name']); ?></span>
                        <h3 class="product-title">
                            <a href="<?php echo BASE_URL; ?>/product-details.php?id=<?php echo (int)$item['id']; ?>">
                                <?php echo e($item['title']); ?>
                            </a>
                        </h3>

                        <div class="product-provider-meta">
                            <i class="fa-solid fa-store"></i>
                            <a href="<?php echo BASE_URL; ?>/provider-profile.php?slug=<?php echo urlencode($item['provider_slug']); ?>" style="color: inherit; font-weight: 600;">
                                <?php echo e($item['business_name']); ?>
                            </a>
                        </div>

                        <div class="product-price-row">
                            <span class="price-current"><?php echo format_price($itemOffer['effective_price']); ?></span>
                            <?php if ($itemOffer['savings_amount'] > 0): ?>
                                <span class="price-original"><?php echo format_price($item['price']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div style="margin-top: 14px;">
                            <a href="<?php echo BASE_URL; ?>/product-details.php?id=<?php echo (int)$item['id']; ?>" class="btn btn-outline-primary btn-sm" style="width: 100%;">
                                View Details &amp; Book
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 6. HOW IT WORKS (DUAL AUDIENCE WORKFLOW) -->
<section id="how-it-works" class="container">
    <div class="steps-container">
        <div style="text-align: center; max-width: 650px; margin: 0 auto 30px;">
            <h2 style="font-size: 32px; margin-bottom: 10px;">How Magal Creator Works</h2>
            <p style="color: #64748b;">
                Whether you're shopping for specialized services or launching your business, our multi-vendor platform makes it effortless.
            </p>
        </div>

        <div class="steps-tabs">
            <button class="step-tab-btn active" data-target="steps-customer">For Customers</button>
            <button class="step-tab-btn" data-target="steps-provider">For Women Entrepreneurs</button>
        </div>

        <!-- Customer Flow -->
        <div id="steps-customer" class="step-grid step-grid-content">
            <div class="step-card">
                <div class="step-number">1</div>
                <h4>Discover</h4>
                <p>Browse diverse categories and compare women-owned businesses in one place.</p>
            </div>
            <div class="step-card">
                <div class="step-number">2</div>
                <h4>Select &amp; Customise</h4>
                <p>View packages, portfolios, genuine pricing, and active limited-time offers.</p>
            </div>
            <div class="step-card">
                <div class="step-number">3</div>
                <h4>Secure Checkout</h4>
                <p>Book with integrated payment gateway processing. Instant order confirmation.</p>
            </div>
            <div class="step-card">
                <div class="step-number">4</div>
                <h4>Enjoy the Service</h4>
                <p>Connect directly with the entrepreneur and experience premium personal service.</p>
            </div>
        </div>

        <!-- Provider Flow -->
        <div id="steps-provider" class="step-grid step-grid-content" style="display: none;">
            <div class="step-card">
                <div class="step-number">1</div>
                <h4>Join Platform</h4>
                <p>Register your business with your story, contact info, and portfolio banner.</p>
            </div>
            <div class="step-card">
                <div class="step-number">2</div>
                <h4>List Offerings</h4>
                <p>Add products, services, bespoke packages, and configure countdown offers.</p>
            </div>
            <div class="step-card">
                <div class="step-number">3</div>
                <h4>Receive Bookings</h4>
                <p>Manage customer orders and appointment schedules through your studio dashboard.</p>
            </div>
            <div class="step-card">
                <div class="step-number">4</div>
                <h4>Direct Payouts</h4>
                <p>Platform automatically deducts 10% commission and settles your payout safely.</p>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

