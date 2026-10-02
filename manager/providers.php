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

$pageTitle = 'Manage Women Entrepreneurs';

// Make sure only managers can perform these actions
require_manager();

// Handle Actions (Approve, Reject, Toggle Feature)
if (isset($_GET['action']) && isset($_GET['id'])) {

    if ($action === 'approve') {
        $db->prepare("UPDATE service_providers SET approval_status = 'approved', updated_at = NOW() WHERE id = :id")->execute(['id' => $provId]);
        set_flash('success', 'Provider profile has been approved and is now live.');
        header("Location: " . BASE_URL . "/manager/providers.php");
        exit;
    } elseif ($action === 'reject') {
        $db->prepare("UPDATE service_providers SET approval_status = 'rejected', updated_at = NOW() WHERE id = :id")->execute(['id' => $provId]);
        set_flash('warning', 'Provider profile has been rejected.');
        header("Location: " . BASE_URL . "/manager/providers.php");
        exit;
    } elseif ($action === 'toggle_featured') {
        $db->prepare("UPDATE service_providers SET is_featured = (1 - is_featured), updated_at = NOW() WHERE id = :id")->execute(['id' => $provId]);
        set_flash('success', 'Featured status updated.');
        header("Location: " . BASE_URL . "/manager/providers.php");
        exit;
    }
}

// Fetch all providers
$stmt = $db->query("
    SELECT sp.*, u.full_name AS owner_name, u.email AS owner_email, c.name AS category_name,
           COUNT(p.id) AS total_products
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN categories c ON sp.category_id = c.id
    LEFT JOIN products p ON sp.id = p.provider_id
    GROUP BY sp.id
    ORDER BY sp.id DESC
");
$providers = $stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">Manage Service Providers</h1>
    <p style="font-size: 14px; color: #64748b;">Review registrations, approve profiles, and feature outstanding women entrepreneurs.</p>
</div>

<div class="data-table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 70px;">Logo</th>
                <th>Business Name &amp; Founder</th>
                <th>Category</th>
                <th>City</th>
                <th>Listings</th>
                <th>Featured</th>
                <th>Approval Status</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($providers as $p): ?>
                <tr>
                    <td>
                        <img src="<?php echo e(get_image_url($p['logo_image'], 'provider')); ?>" alt="<?php echo e($p['business_name']); ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0;">
                    </td>
                    <td>
                        <div style="font-weight: 700; color: var(--text-heading); font-size: 14.5px;">
                            <a href="<?php echo BASE_URL; ?>/provider-profile.php?slug=<?php echo urlencode($p['slug']); ?>" target="_blank" style="color: inherit;">
                                <?php echo e($p['business_name']); ?>
                            </a>
                        </div>
                        <small style="color: #64748b;">Founder: <?php echo e($p['owner_name']); ?> (<?php echo e($p['owner_email']); ?>)</small>
                    </td>
                    <td>
                        <span style="font-size: 13px; font-weight: 600; color: var(--primary);">
                            <?php echo e($p['category_name'] ?? 'General'); ?>
                        </span>
                    </td>
                    <td><?php echo e($p['city'] ?? 'India'); ?></td>
                    <td><strong><?php echo (int)$p['total_products']; ?></strong> offerings</td>
                    <td>
                        <a href="<?php echo BASE_URL; ?>/manager/providers.php?action=toggle_featured&id=<?php echo (int)$p['id']; ?>" class="badge" style="background: <?php echo $p['is_featured'] ? '#fef3c7; color: #b45309;' : '#f1f5f9; color: #94a3b8;'; ?> text-decoration: none;">
                            <i class="fa-solid fa-star"></i> <?php echo $p['is_featured'] ? 'FEATURED' : 'No'; ?>
                        </a>
                    </td>
                    <td>
                        <?php if ($p['approval_status'] === 'approved'): ?>
                            <span class="badge" style="background: #d1fae5; color: #065f46;">Approved</span>
                        <?php elseif ($p['approval_status'] === 'pending'): ?>
                            <span class="badge" style="background: #fef3c7; color: #92400e;">Pending Review</span>
                        <?php else: ?>
                            <span class="badge" style="background: #fee2e2; color: #991b1b;">Rejected</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: flex; gap: 6px; justify-content: flex-end;">
                            <?php if ($p['approval_status'] !== 'approved'): ?>
                                <a href="<?php echo BASE_URL; ?>/manager/providers.php?action=approve&id=<?php echo (int)$p['id']; ?>" class="btn btn-primary btn-sm" title="Approve">
                                    <i class="fa-solid fa-check"></i> Approve
                                </a>
                            <?php else: ?>
                                <a href="<?php echo BASE_URL; ?>/manager/providers.php?action=reject&id=<?php echo (int)$p['id']; ?>" class="btn btn-outline btn-sm" style="color: #ef4444;" title="Suspend" data-confirm="Are you sure you want to suspend this provider?">
                                    <i class="fa-solid fa-ban"></i> Suspend
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

