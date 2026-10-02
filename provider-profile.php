<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Public Provider Profile Page
 * Location: /provider-profile.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$db = getDB();
$slug = sanitize_string($_GET['slug'] ?? '');

if (empty($slug)) {
    header("Location: " . BASE_URL . "/browse.php?tab=providers");
    exit;
}

// 1. Fetch provider details
$stmt = $db->prepare("
    SELECT sp.*, c.name AS category_name, u.full_name AS owner_name
    FROM service_providers sp
    LEFT JOIN categories c ON sp.category_id = c.id
    JOIN users u ON sp.user_id = u.id
    WHERE sp.slug = :slug AND sp.approval_status = 'approved'
    LIMIT 1
");
$stmt->execute(['slug' => $slug]);
$provider = $stmt->fetch();

if (!$provider) {
    http_response_code(404);
    $pageTitle = 'Provider Not Found | Magal Creator';
    include __DIR__ . '/includes/header.php';
    echo '<div class="container" style="padding: 80px 20px; text-align: center;">
            <h2>Business Profile Not Found</h2>
            <p>The requested woman entrepreneur business profile does not exist or is pending review.</p>
            <a href="' . BASE_URL . '/browse.php?tab=providers" class="btn btn-primary" style="margin-top: 15px;">Explore Other Entrepreneurs</a>
          </div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

// 2. Fetch all active offerings by this provider
$pStmt = $db->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.provider_id = :pid AND p.status = 'active'
    ORDER BY p.id DESC
");
$pStmt->execute(['pid' => $provider['id']]);
$products = $pStmt->fetchAll();

$pageTitle = e($provider['business_name']) . ' - Business Profile | Magal Creator';
$metaDescription = e(substr($provider['description'] ?? '', 0, 150));
include __DIR__ . '/includes/header.php';
?>

<div style="background: #ffffff; border-bottom: 1px solid var(--border-color); padding-bottom: 30px;">
    <!-- Cover Banner -->
    <div style="height: 220px; background: linear-gradient(135deg, #F1E0DB 0%, #F1DEC2 100%); position: relative; overflow: hidden;">
        <div class="container" style="height: 100%; display: flex; align-items: flex-end; padding-bottom: 20px;">
            <span style="font-size: 13px; font-weight: 700; color: #881337; background: rgba(255,255,255,0.85); backdrop-filter: blur(4px); padding: 4px 14px; border-radius: 20px;">
                <i class="fa-solid fa-crown" style="color: #8C3A27;"></i> Magal Creator Verified Entrepreneur
            </span>
        </div>
    </div>

    <!-- Header Details -->
    <div class="container">
        <div style="display: flex; gap: 24px; align-items: flex-start; margin-top: -50px; flex-wrap: wrap;">
            <img src="<?php echo e(get_image_url($provider['logo_image'], 'provider')); ?>" 
                 alt="<?php echo e($provider['business_name']); ?>" 
                 style="width: 120px; height: 120px; border-radius: 50%; border: 4px solid #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: #ffffff; object-fit: cover;">
            
            <div style="flex: 1; min-width: 280px; padding-top: 50px;">
                <h1 style="font-size: 30px; color: var(--text-heading); margin-bottom: 4px;"><?php echo e($provider['business_name']); ?></h1>
                <?php if (!empty($provider['tagline'])): ?>
                    <p style="font-size: 16px; color: var(--primary); font-weight: 600; margin-bottom: 10px;"><?php echo e($provider['tagline']); ?></p>
                <?php endif; ?>
                
                <div style="display: flex; gap: 20px; flex-wrap: wrap; font-size: 14px; color: #64748b;">
                    <span><i class="fa-solid fa-tag" style="color: var(--primary);"></i> <?php echo e($provider['category_name'] ?? 'Bespoke Services'); ?></span>
                    <span><i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> <?php echo e($provider['city'] ?? 'India'); ?></span>
                    <span><i class="fa-solid fa-user-check" style="color: var(--primary);"></i> Founder: <?php echo e($provider['owner_name']); ?></span>
                </div>
            </div>

            <!-- Contact Card -->
            <div style="background: #F7EEEB; border: 1px solid #E8D4CE; border-radius: var(--radius-md); padding: 18px 24px; margin-top: 20px;">
                <h4 style="font-size: 13px; text-transform: uppercase; color: #732D1D; margin-bottom: 8px;">Direct Contact</h4>
                <?php if (!empty($provider['contact_phone'])): ?>
                    <div style="font-size: 14px; color: #881337; margin-bottom: 6px;">
                        <i class="fa-solid fa-phone"></i> <?php echo e($provider['contact_phone']); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($provider['contact_email'])): ?>
                    <div style="font-size: 14px; color: #881337;">
                        <i class="fa-solid fa-envelope"></i> <?php echo e($provider['contact_email']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<main class="container" style="padding: 50px 20px 80px;">
    <!-- Story & Description -->
    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 30px; margin-bottom: 40px;">
        <h2 style="font-size: 22px; margin-bottom: 12px; color: var(--secondary);">About the Business</h2>
        <div style="color: #475569; line-height: 1.8; font-size: 15px;">
            <?php echo nl2br(e($provider['description'])); ?>
        </div>
        <?php if (!empty($provider['location'])): ?>
            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px dashed var(--border-color); font-size: 14px; color: #64748b;">
                <strong><i class="fa-solid fa-map-pin" style="color: var(--primary);"></i> Studio Location:</strong> <?php echo e($provider['location']); ?>, <?php echo e($provider['city']); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Offerings & Catalog by this Provider -->
    <div>
        <div class="section-header">
            <div>
                <h2 class="section-title">Available Services &amp; Products</h2>
                <p class="section-subtitle">Book directly through our secured marketplace checkout</p>
            </div>
            <span style="font-weight: 700; color: #64748b;"><?php echo count($products); ?> Offerings</span>
        </div>

        <?php if (empty($products)): ?>
            <div style="text-align: center; padding: 50px 20px; background: #ffffff; border-radius: 12px; border: 1px dashed #cbd5e1;">
                <p style="color: #64748b;">This entrepreneur is currently preparing new listings. Please check back soon!</p>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $prod): 
                    $offer = get_product_offer_details($prod);
                ?>
                    <div class="product-card">
                        <div class="product-image-wrap">
                            <img src="<?php echo e(get_image_url($prod['cover_image'], 'product')); ?>" alt="<?php echo e($prod['title']); ?>" loading="lazy">
                            <div class="product-badge-group">
                                <span class="badge badge-<?php echo ($prod['service_type'] === 'service') ? 'service' : 'product'; ?>">
                                    <?php echo strtoupper($prod['service_type']); ?>
                                </span>
                                <?php if ($offer['status'] === 'ACTIVE'): ?>
                                    <span class="badge badge-offer">
                                        <i class="fa-solid fa-fire"></i> <?php echo $offer['savings_percent']; ?>% OFF
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($offer['status'] === 'ACTIVE'): ?>
                            <div class="card-countdown-banner" 
                                 data-countdown-target="<?php echo e($offer['end_timestamp_iso']); ?>"
                                 data-countdown-type="end">
                                <span><i class="fa-solid fa-hourglass-half"></i> Offer ends:</span>
                                <span class="countdown-digits">...</span>
                            </div>
                        <?php endif; ?>

                        <div class="product-body">
                            <span class="product-category-tag"><?php echo e($prod['category_name']); ?></span>
                            <h3 class="product-title">
                                <a href="<?php echo BASE_URL; ?>/product-details.php?id=<?php echo (int)$prod['id']; ?>">
                                    <?php echo e($prod['title']); ?>
                                </a>
                            </h3>

                            <p style="font-size: 13.5px; color: #64748b; line-height: 1.5; margin-bottom: 15px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?php echo e($prod['description']); ?>
                            </p>

                            <div class="product-price-row">
                                <span class="price-current"><?php echo format_price($offer['effective_price']); ?></span>
                                <?php if ($offer['savings_amount'] > 0): ?>
                                    <span class="price-original"><?php echo format_price($prod['price']); ?></span>
                                    <span class="price-discount-tag">Save <?php echo format_price($offer['savings_amount']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div style="margin-top: 14px;">
                                <a href="<?php echo BASE_URL; ?>/product-details.php?id=<?php echo (int)$prod['id']; ?>" class="btn btn-primary btn-sm" style="width: 100%;">
                                    View Details &amp; Book
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

