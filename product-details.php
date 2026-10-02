<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Product & Service Details with Live Dynamic Countdown
 * Location: /product-details.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$db = getDB();
$productId = (int)($_GET['id'] ?? 0);

if ($productId <= 0) {
    header("Location: " . BASE_URL . "/browse.php");
    exit;
}

// 1. Fetch product along with category & provider details
$stmt = $db->prepare("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug,
           sp.business_name, sp.slug AS provider_slug, sp.logo_image, sp.city, sp.location, sp.contact_phone, sp.contact_email, sp.description AS provider_description
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN service_providers sp ON p.provider_id = sp.id
    WHERE p.id = :id AND p.status = 'active' AND sp.approval_status = 'approved'
    LIMIT 1
");
$stmt->execute(['id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Offering Not Found | Magal Creator';
    include __DIR__ . '/includes/header.php';
    echo '<div class="container" style="padding: 80px 20px; text-align: center;">
            <h2>Offering Not Found</h2>
            <p>The product or service you are looking for is no longer active or available.</p>
            <a href="' . BASE_URL . '/browse.php" class="btn btn-primary" style="margin-top: 15px; background-color: var(--color-primary);">Browse Other Services</a>
          </div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

// 2. Authoritative Offer Calculation
$offer = get_product_offer_details($product);

$pageTitle = e($product['title']) . ' by ' . e($product['business_name']) . ' | Magal Creator';
$metaDescription = e(substr($product['description'], 0, 150));
include __DIR__ . '/includes/header.php';
?>

<main class="container product-details-container" style="padding: 40px 20px 80px;">
    <!-- Breadcrumb -->
    <div style="font-size: 13px; color: var(--color-text-muted, #6E6761); margin-bottom: 24px;">
        <a href="<?php echo BASE_URL; ?>/index.php" style="color: var(--color-text-muted, #6E6761);">Home</a> &gt;
        <a href="<?php echo BASE_URL; ?>/browse.php?category=<?php echo urlencode($product['category_slug']); ?>" style="color: var(--color-text-muted, #6E6761);"><?php echo e($product['category_name']); ?></a> &gt;
        <span style="color: var(--color-text, #2C2825); font-weight: 600;"><?php echo e($product['title']); ?></span>
    </div>

    <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 40px; align-items: flex-start;">
        <!-- Left: Image Gallery / Showcase & Entrepreneur Profile -->
        <div>
            <div style="border-radius: 12px; overflow: hidden; border: 1px solid var(--color-border, #E8E2DA); background: var(--color-card-bg, #ffffff); box-shadow: 0 2px 8px rgba(0,0,0,0.05); position: relative;">
                <img src="<?php echo e(get_image_url($product['cover_image'], 'product')); ?>"
                     alt="<?php echo e($product['title']); ?>"
                     style="width: 100%; height: 420px; object-fit: cover;">

                <?php if ($offer['status'] === 'ACTIVE'): ?>
                    <span class="badge badge-offer" style="position: absolute; top: 16px; left: 16px; font-size: 13px; padding: 6px 14px; background-color: var(--color-accent, #D9822B);">
                        <i class="fa-solid fa-fire"></i> <?php echo $offer['savings_percent']; ?>% LIMITED OFFER
                    </span>
                <?php endif; ?>
            </div>

            <!-- Provider Trust Box (Entrepreneur Storytelling) -->
            <div style="margin-top: 24px; padding: 20px; background: var(--color-card-bg, #ffffff); border-radius: 12px; border: 1px solid var(--color-border, #E8E2DA); display: flex; gap: 16px; align-items: center;">
                <img src="<?php echo e(get_image_url($product['logo_image'], 'provider')); ?>" alt="<?php echo e($product['business_name']); ?>" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--color-accent, #D9822B);">
                <div style="flex: 1;">
                    <div style="font-size: 12px; color: var(--color-support, #6B8E7B); font-weight: 700; text-transform: uppercase;">Meet the Entrepreneur</div>
                    <h4 style="font-size: 17px; margin-bottom: 2px;">
                        <a href="<?php echo BASE_URL; ?>/provider-profile.php?slug=<?php echo urlencode($product['provider_slug']); ?>" style="color: var(--color-primary, #8C3A27); text-decoration: none; font-weight: 700;">
                            <?php echo e($product['business_name']); ?>
                        </a>
                    </h4>
                    <span style="font-size: 13px; color: var(--color-text-muted, #6E6761);"><i class="fa-solid fa-location-dot" style="color: var(--color-primary, #8C3A27);"></i> <?php echo e($product['city']); ?></span>
                </div>
                <a href="<?php echo BASE_URL; ?>/provider-profile.php?slug=<?php echo urlencode($product['provider_slug']); ?>" class="btn btn-outline btn-sm" style="border-color: var(--color-primary, #8C3A27); color: var(--color-primary, #8C3A27);">
                    View Studio
                </a>
            </div>
        </div>

        <!-- Right: Offer Details, Price & Booking Action -->
        <div style="background: var(--color-card-bg, #ffffff); border: 1px solid var(--color-border, #E8E2DA); border-radius: 12px; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                <span class="badge badge-<?php echo ($product['service_type'] === 'service') ? 'service' : 'product'; ?>">
                    <?php echo strtoupper($product['service_type']); ?>
                </span>
                <span style="font-size: 12px; font-weight: 700; color: var(--color-text-muted, #6E6761); background: var(--color-bg, #FAF8F5); padding: 4px 10px; border-radius: 4px; border: 1px solid var(--color-border, #E8E2DA);">
                    <?php echo e($product['category_name']); ?>
                </span>
            </div>

            <h1 style="font-size: 26px; line-height: 1.3; margin-bottom: 16px; color: var(--color-text, #2C2825); font-weight: 700;"><?php echo e($product['title']); ?></h1>

            <!-- DYNAMIC COUNTDOWN & PRICING BOX -->
            <div style="background: var(--color-bg, #FAF8F5); border: 1.5px solid var(--color-border, #E8E2DA); border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                <?php if ($offer['status'] === 'ACTIVE'): ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed var(--color-border, #E8E2DA);">
                        <div style="font-size: 13px; font-weight: 700; color: var(--color-primary, #8C3A27); display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-clock-bolt" style="color: var(--color-accent, #D9822B);"></i> Limited-Time Special Offer
                        </div>
                        <div class="card-countdown-banner" 
                             style="border-radius: 20px; padding: 4px 12px;"
                             data-countdown-target="<?php echo e($offer['end_timestamp_iso']); ?>"
                             data-countdown-type="end"
                             data-normal-price="<?php echo e(format_price($product['price'])); ?>">
                            <span class="countdown-digits">Calculating...</span>
                        </div>
                    </div>
                <?php elseif ($offer['status'] === 'UPCOMING'): ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed var(--color-border, #E8E2DA);">
                        <div style="font-size: 13px; font-weight: 700; color: var(--color-text, #2C2825);">
                            <i class="fa-solid fa-bolt"></i> Upcoming Offer Teaser
                        </div>
                        <div class="card-countdown-banner" 
                             style="background: var(--color-text, #2C2825); border-radius: 20px; padding: 4px 12px;"
                             data-countdown-target="<?php echo e($offer['start_timestamp_iso']); ?>"
                             data-countdown-type="start">
                            <span>Starts in: </span><span class="countdown-digits">...</span>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="display: flex; align-items: baseline; gap: 14px;">
                    <span class="price-current" style="font-size: 34px; color: var(--color-primary, #8C3A27); font-weight: 700;"><?php echo format_price($offer['effective_price']); ?></span>
                    <?php if ($offer['savings_amount'] > 0): ?>
                        <span class="price-original" style="font-size: 18px; color: var(--color-text-muted, #6E6761); text-decoration: line-through;"><?php echo format_price($product['price']); ?></span>
                        <span class="price-discount-tag" style="font-size: 13px; color: var(--color-accent, #D9822B); font-weight: 600;">Save <?php echo format_price($offer['savings_amount']); ?> (<?php echo $offer['savings_percent']; ?>% OFF)</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Booking / Checkout Form -->
            <form action="<?php echo BASE_URL; ?>/checkout.php" method="GET" style="margin-bottom: 20px;">
                <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">

                <?php if ($product['service_type'] === 'service'): ?>
                    <div class="form-group mb-3">
                        <label class="form-label" style="font-weight: 600; color: var(--color-text, #2C2825);"><i class="fa-solid fa-calendar-day" style="color: var(--color-primary, #8C3A27);"></i> Preferred Appointment Date</label>
                        <input type="date" name="booking_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" style="border-color: var(--color-border, #E8E2DA);">
                    </div>
                <?php endif; ?>

                <div class="form-group mb-3">
                    <label class="form-label" style="font-weight: 600; color: var(--color-text, #2C2825);">Quantity / Persons</label>
                    <input type="number" name="quantity" class="form-control" min="1" max="10" value="1" required style="border-color: var(--color-border, #E8E2DA);">
                </div>

                <button type="submit" class="btn btn-lg text-white font-weight-bold" style="width: 100%; background-color: var(--color-primary, #8C3A27); border: none; padding: 12px; font-weight: 700; box-shadow: 0 4px 14px rgba(140, 58, 39, 0.3);">
                    <i class="fa-solid fa-shield-check"></i> Book Now &amp; Pay Securely
                </button>
            </form>

            <!-- Impact & Trust Messaging -->
            <div style="font-size: 13px; color: var(--color-text-muted, #6E6761); line-height: 1.6; border-top: 1px solid var(--color-border, #E8E2DA); padding-top: 16px;">
                <div class="mb-1"><i class="fa-solid fa-lock" style="color: var(--color-support, #6B8E7B);"></i> 100% Platform Verified Payment Protection</div>
                <div style="color: var(--color-support, #6B8E7B); font-weight: 600;"><i class="fa-solid fa-hand-holding-heart"></i> ✨ Your order powers independent craft</div>
            </div>

            <!-- Full Description -->
            <div style="margin-top: 30px;">
                <h3 style="font-size: 18px; margin-bottom: 12px; color: var(--color-primary, #8C3A27); font-weight: 700;">Description &amp; Deliverables</h3>
                <div style="color: var(--color-text, #2C2825); line-height: 1.8; font-size: 14.5px;">
                    <?php echo nl2br(e($product['description'])); ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>