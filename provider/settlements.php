<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Provider Payouts & Commission Settlement Information
 * Location: /provider/settlements.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Payouts & Settlements';
include __DIR__ . '/includes/header.php';

// Fetch settlements from commissions table
$stmt = $db->prepare("
    SELECT c.*, o.order_number, o.created_at AS order_date, pay.payment_method
    FROM commissions c
    JOIN orders o ON c.order_id = o.id
    LEFT JOIN payments pay ON c.payment_id = pay.id
    WHERE c.provider_id = :pid
    ORDER BY c.id DESC
");
$stmt->execute(['pid' => $providerId]);
$settlements = $stmt->fetchAll();

// Calculate totals
$totGross = 0;
$totCommission = 0;
$totPayable = 0;
$pendingPayable = 0;

foreach ($settlements as $s) {
    $totGross += (float)$s['gross_amount'];
    $totCommission += (float)$s['platform_commission_amount'];
    $totPayable += (float)$s['provider_payable_amount'];
    if ($s['settlement_status'] === 'pending') {
        $pendingPayable += (float)$s['provider_payable_amount'];
    }
}
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">Payouts &amp; Settlements</h1>
    <p style="font-size: 14px; color: #64748b;">Transparent financial records of your earnings, platform commission (10%), and bank settlements.</p>
</div>

<!-- Financial Summary Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div>
            <div class="stat-value"><?php echo format_price($totGross); ?></div>
            <div class="stat-label">Total Gross Sales</div>
        </div>
        <div class="stat-icon info">
            <i class="fa-solid fa-receipt"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value" style="color: #732D1D;"><?php echo format_price($totCommission); ?></div>
            <div class="stat-label">Platform Commission (10%)</div>
        </div>
        <div class="stat-icon primary">
            <i class="fa-solid fa-percent"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value" style="color: #059669;"><?php echo format_price($totPayable); ?></div>
            <div class="stat-label">Total Net Earnings</div>
        </div>
        <div class="stat-icon success">
            <i class="fa-solid fa-hand-holding-dollar"></i>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value" style="color: #d97706;"><?php echo format_price($pendingPayable); ?></div>
            <div class="stat-label">Pending Bank Payout</div>
        </div>
        <div class="stat-icon warning">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
    </div>
</div>

<!-- Bank Account Box -->
<div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h3 style="font-size: 16px; margin: 0; color: var(--secondary);">
            <i class="fa-solid fa-building-columns"></i> Registered Bank Account for Direct Settlements
        </h3>
        <a href="<?php echo BASE_URL; ?>/provider/profile.php" class="btn btn-outline btn-sm">Update Bank Details</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; font-size: 13.5px; color: #475569;">
        <div>
            <span style="color: #94a3b8; display: block; font-size: 12px;">Account Name:</span>
            <strong><?php echo e($currentProvider['bank_account_name'] ?? 'Not set'); ?></strong>
        </div>
        <div>
            <span style="color: #94a3b8; display: block; font-size: 12px;">Account Number:</span>
            <strong><?php echo !empty($currentProvider['bank_account_number']) ? '•••• •••• ' . substr($currentProvider['bank_account_number'], -4) : 'Not set'; ?></strong>
        </div>
        <div>
            <span style="color: #94a3b8; display: block; font-size: 12px;">Bank IFSC Code:</span>
            <strong><?php echo e($currentProvider['bank_ifsc'] ?? 'Not set'); ?></strong>
        </div>
        <div>
            <span style="color: #94a3b8; display: block; font-size: 12px;">Settlement Cycle:</span>
            <strong style="color: #059669;">Daily / T+2 Days Direct Bank Transfer</strong>
        </div>
    </div>
</div>

<!-- Settlements Breakdown Table -->
<div class="data-table-card">
    <div class="table-header-bar">
        <h3 style="font-size: 16px; margin: 0; color: var(--text-heading);">Transaction Settlement Breakdown</h3>
    </div>

    <?php if (empty($settlements)): ?>
        <div style="padding: 50px 20px; text-align: center; color: #64748b;">
            <p>No settled transactions recorded yet.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Gross Amount</th>
                    <th>Commission Rate</th>
                    <th>Platform Fee</th>
                    <th>Provider Share</th>
                    <th>Settlement Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($settlements as $set): ?>
                    <tr>
                        <td><strong><?php echo e($set['order_number']); ?></strong></td>
                        <td><?php echo format_date($set['created_at']); ?></td>
                        <td><?php echo format_price($set['gross_amount']); ?></td>
                        <td><?php echo $set['commission_rate_percent']; ?>%</td>
                        <td style="color: #732D1D;">-<?php echo format_price($set['platform_commission_amount']); ?></td>
                        <td style="color: #059669; font-weight: 800;"><?php echo format_price($set['provider_payable_amount']); ?></td>
                        <td>
                            <?php if ($set['settlement_status'] === 'settled'): ?>
                                <span class="badge" style="background: #d1fae5; color: #065f46;">
                                    <i class="fa-solid fa-check"></i> SETTLED
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background: #fef3c7; color: #92400e;">
                                    <i class="fa-solid fa-clock"></i> PENDING
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

