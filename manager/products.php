<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Manager Global Products & Services Monitor
 * Location: /manager/products.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'All Offerings & Services';
include __DIR__ . '/includes/header.php';

// Handle Toggle Status
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $pId = (int)$_GET['id'];
    $db->prepare("UPDATE products SET status = IF(status='active', 'draft', 'active'), updated_at = NOW() WHERE id = :id")->execute(['id' => $pId]);
    set_flash('success', 'Offering visibility updated.');
    header("Location: " . BASE_URL . "/manager/products.php");
    exit;
}

// Fetch all products across all providers
$products = $db->query("
    SELECT p.*, c.name AS category_name, sp.business_name, sp.slug AS provider_slug
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN service_providers sp ON p.provider_id = sp.id
    ORDER BY p.id DESC
")->fetchAll();
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">Marketplace Offerings Catalog</h1>
    <p style="font-size: 14px; color: #64748b;">Monitor all service packages, pricing, and active countdown deals across all entrepreneurs.</p>
</div>

<div class="data-table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 70px;">Cover</th>
                <th>Title &amp; Type</th>
                <th>Entrepreneur</th>
                <th>Category</th>
                <th>Regular Price</th>
                <th>Limited-Time Offer</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $prod): 
                $offer = get_product_offer_details($prod);
            ?>
                <tr>
                    <td>
                        <img src="<?php echo e(get_image_url($prod['cover_image'], 'product')); ?>" alt="<?php echo e($prod['title']); ?>" style="width: 50px; height: 50px; border-radius: 6px; object-fit: cover;">
                    </td>
                    <td>
                        <div style="font-weight: 700; color: var(--text-heading);">
                            <a href="<?php echo BASE_URL; ?>/product-details.php?id=<?php echo (int)$prod['id']; ?>" target="_blank" style="color: inherit;">
                                <?php echo e($prod['title']); ?>
                            </a>
                        </div>
                        <span class="badge badge-<?php echo ($prod['service_type'] === 'service') ? 'service' : 'product'; ?>" style="font-size: 10px;">
                            <?php echo strtoupper($prod['service_type']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?php echo BASE_URL; ?>/provider-profile.php?slug=<?php echo urlencode($prod['provider_slug']); ?>" target="_blank" style="color: var(--primary); font-weight: 600;">
                            <?php echo e($prod['business_name']); ?>
                        </a>
                    </td>
                    <td><?php echo e($prod['category_name']); ?></td>
                    <td><strong><?php echo format_price($prod['price']); ?></strong></td>
                    <td>
                        <?php if ($offer['status'] === 'ACTIVE'): ?>
                            <span class="badge badge-offer">
                                <i class="fa-solid fa-fire"></i> <?php echo format_price($offer['effective_price']); ?> (Ends in: <span class="countdown-digits" data-countdown-target="<?php echo e($offer['end_timestamp_iso']); ?>">...</span>)
                            </span>
                        <?php elseif ($offer['status'] === 'UPCOMING'): ?>
                            <span class="badge" style="background: #fef3c7; color: #92400e;">Upcoming</span>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 12px;">None</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($prod['status'] === 'active'): ?>
                            <span class="badge" style="background: #d1fae5; color: #065f46;">Active</span>
                        <?php else: ?>
                            <span class="badge" style="background: #f1f5f9; color: #64748b;">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <a href="<?php echo BASE_URL; ?>/manager/products.php?toggle_status=1&id=<?php echo (int)$prod['id']; ?>" class="btn btn-outline btn-sm">
                            <?php echo ($prod['status'] === 'active') ? 'Hide' : 'Activate'; ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

