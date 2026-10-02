<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Manager Commission Ledger & Provider Settlements
 * Location: /manager/commissions.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Commission & Settlements';
include __DIR__ . '/includes/header.php';

// Handle Action to Mark Settlement as Settled
if (isset($_GET['mark_settled']) && isset($_GET['id'])) {
    $cId = (int)$_GET['id'];
    $db->prepare("UPDATE commissions SET settlement_status = 'settled', settled_at = NOW() WHERE id = :id")->execute(['id' => $cId]);
    set_flash('success', 'Commission record #' . $cId . ' marked as settled.');
    header("Location: " . BASE_URL . "/manager/commissions.php");
    exit;
}

// Fetch all commissions
$commissions = $db->query("
    SELECT c.*, o.order_number, sp.business_name, sp.bank_account_name, sp.bank_account_number, sp.bank_ifsc
    FROM commissions c
    JOIN orders o ON c.order_id = o.id
    JOIN service_providers sp ON c.provider_id = sp.id
    ORDER BY c.id DESC
")->fetchAll();

$totGross = 0;
$totCommission = 0;
$totPayable = 0;
$pendingPayable = 0;

foreach ($commissions as $cm) {
    $totGross += (float)$cm['gross_amount'];
    $totCommission += (float)$cm['platform_commission_amount'];
    $totPayable += (float)$cm['provider_payable_amount'];
    if ($cm['settlement_status'] === 'pending') {
        $pendingPayable += (float)$cm['provider_payable_amount'];
    }
}
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">Platform Commission &amp; Provider Settlements</h1>
    <p style="font-size: 14px; color: #64748b;">Financial breakdown of platform revenues, split payouts, and entrepreneur bank settlements.</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div>
            <div class="stat-value"><?php echo format_price($totGross); ?></div>
            <div class="stat-label">Total Processed GMV</div>
        </div>
        <div class="stat-icon info">
            <i class="fa-solid fa-receipt"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value" style="color: #732D1D;"><?php echo format_price($totCommission); ?></div>
            <div class="stat-label">Platform Net Commission (10%)</div>
        </div>
        <div class="stat-icon primary">
            <i class="fa-solid fa-percent"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value" style="color: #059669;"><?php echo format_price($totPayable); ?></div>
            <div class="stat-label">Total Entrepreneur Payouts</div>
        </div>
        <div class="stat-icon success">
            <i class="fa-solid fa-hand-holding-dollar"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value" style="color: #d97706;"><?php echo format_price($pendingPayable); ?></div>
            <div class="stat-label">Pending Bank Disbursements</div>
        </div>
        <div class="stat-icon warning">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
    </div>
</div>

<div class="data-table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Provider &amp; Bank Account</th>
                <th>Gross Paid</th>
                <th>Rate</th>
                <th>Platform Commission</th>
                <th>Net Provider Share</th>
                <th>Settlement Status</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($commissions as $c): ?>
                <tr>
                    <td><strong><?php echo e($c['order_number']); ?></strong></td>
                    <td>
                        <div style="font-weight: 700; color: var(--text-heading);"><?php echo e($c['business_name']); ?></div>
                        <small style="color: #64748b;">
                            Bank: <?php echo e($c['bank_account_name'] ?? 'Not set'); ?> 
                            <?php if (!empty($c['bank_account_number'])): ?>
                                (•••<?php echo substr($c['bank_account_number'], -4); ?> / <?php echo e($c['bank_ifsc']); ?>)
                            <?php endif; ?>
                        </small>
                    </td>
                    <td><?php echo format_price($c['gross_amount']); ?></td>
                    <td><?php echo $c['commission_rate_percent']; ?>%</td>
                    <td style="color: #732D1D; font-weight: 700;">+<?php echo format_price($c['platform_commission_amount']); ?></td>
                    <td style="color: #059669; font-weight: 800;"><?php echo format_price($c['provider_payable_amount']); ?></td>
                    <td>
                        <?php if ($c['settlement_status'] === 'settled'): ?>
                            <span class="badge" style="background: #d1fae5; color: #065f46;">
                                <i class="fa-solid fa-check"></i> SETTLED
                            </span>
                        <?php else: ?>
                            <span class="badge" style="background: #fef3c7; color: #92400e;">
                                <i class="fa-solid fa-clock"></i> PENDING
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <?php if ($c['settlement_status'] === 'pending'): ?>
                            <a href="<?php echo BASE_URL; ?>/manager/commissions.php?mark_settled=1&id=<?php echo (int)$c['id']; ?>" class="btn btn-outline btn-sm" style="color: #059669; border-color: #a7f3d0;" title="Disburse Bank Payout">
                                <i class="fa-solid fa-money-bill-transfer"></i> Mark Settled
                            </a>
                        <?php else: ?>
                            <span style="font-size: 11px; color: #94a3b8;">Completed</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

