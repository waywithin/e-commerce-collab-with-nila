<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Provider Product / Service Management (List View)
 * Location: /provider/products.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'My Offerings & Services';
include __DIR__ . '/includes/header.php';

// Strict Provider Ownership Filter: only fetch products belonging to this provider
$stmt = $db->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.provider_id = :pid
    ORDER BY p.id DESC
");
$stmt->execute(['pid' => $providerId]);
$myProducts = $stmt->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">My Offerings &amp; Services</h1>
        <p style="font-size: 14px; color: #64748b;">Manage your service packages, prices, cover photos, and limited-time offers.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>/provider/product-add.php" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus-circle"></i> Add New Offering
    </a>
</div>

<div class="data-table-card">
    <?php if (empty($myProducts)): ?>
        <div style="padding: 60px 20px; text-align: center;">
            <div style="width: 60px; height: 60px; background: #F1E0DB; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 24px;">
                <i class="fa-solid fa-box-open"></i>
            </div>
            <h3 style="color: var(--text-heading); margin-bottom: 6px;">No offerings created yet</h3>
            <p style="color: #64748b; margin-bottom: 20px;">Start building your digital presence by listing your first service or product.</p>
            <a href="<?php echo BASE_URL; ?>/provider/product-add.php" class="btn btn-primary">Create Your First Listing</a>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Cover</th>
                    <th>Title &amp; Category</th>
                    <th>Type</th>
                    <th>Regular Price</th>
                    <th>Limited-Time Offer Status</th>
                    <th>Listing Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($myProducts as $prod): 
                    $offer = get_product_offer_details($prod);
                ?>
                    <tr>
                        <td>
                            <img src="<?php echo e(get_image_url($prod['cover_image'], 'product')); ?>" 
                                 alt="<?php echo e($prod['title']); ?>" 
                                 style="width: 60px; height: 60px; border-radius: 8px; object-fit: cover;">
                        </td>
                        <td>
                            <div style="font-weight: 700; color: var(--text-heading); font-size: 14.5px;">
                                <a href="<?php echo BASE_URL; ?>/product-details.php?id=<?php echo (int)$prod['id']; ?>" target="_blank" style="color: inherit;">
                                    <?php echo e($prod['title']); ?>
                                </a>
                            </div>
                            <small style="color: var(--primary); font-weight: 600;"><?php echo e($prod['category_name']); ?></small>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo ($prod['service_type'] === 'service') ? 'service' : 'product'; ?>">
                                <?php echo strtoupper($prod['service_type']); ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-weight: 700; color: var(--secondary); font-size: 15px;">
                                <?php echo format_price($prod['price']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($offer['status'] === 'ACTIVE'): ?>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <span class="badge badge-offer" style="width: fit-content;">
                                        <i class="fa-solid fa-fire"></i> ACTIVE (<?php echo format_price($offer['effective_price']); ?>)
                                    </span>
                                    <span class="countdown-digits" 
                                          style="font-size: 11px; color: #8C3A27;"
                                          data-countdown-target="<?php echo e($offer['end_timestamp_iso']); ?>"
                                          data-countdown-type="end">
                                        Calculating...
                                    </span>
                                </div>
                            <?php elseif ($offer['status'] === 'UPCOMING'): ?>
                                <span class="badge" style="background: #fef3c7; color: #92400e;">
                                    <i class="fa-solid fa-clock"></i> UPCOMING
                                </span>
                            <?php elseif ($offer['status'] === 'EXPIRED'): ?>
                                <span class="badge" style="background: #f1f5f9; color: #64748b;">
                                    EXPIRED
                                </span>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-size: 12px;">Standard Price</span>
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
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <a href="<?php echo BASE_URL; ?>/provider/product-edit.php?id=<?php echo (int)$prod['id']; ?>" class="btn btn-outline btn-sm" title="Edit Listing">
                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                </a>
                                <form action="<?php echo BASE_URL; ?>/provider/product-delete.php" method="POST" style="display: inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="product_id" value="<?php echo (int)$prod['id']; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: #ef4444; border-color: #E8D4CE;" 
                                            data-confirm="Are you sure you want to delete this listing? This action cannot be undone.">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

