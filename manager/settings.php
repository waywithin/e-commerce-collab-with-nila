<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Manager Platform Settings & Financial Configuration
 * Location: /manager/settings.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Platform Settings';
include __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $commissionPercent = sanitize_amount($_POST['platform_commission_percent'] ?? 10.0);
        $platformName = sanitize_string($_POST['platform_name'] ?? 'Magal Creator Marketplace');
        $supportEmail = sanitize_email($_POST['support_email'] ?? 'support@magalcreator.local');
        $activeGateway = sanitize_string($_POST['active_gateway'] ?? ACTIVE_PAYMENT_GATEWAY);
        $allowedGateways = APP_ENV === 'development' ? ['mock', 'cashfree'] : ['cashfree'];

        if (!in_array($activeGateway, $allowedGateways, true)) {
            set_flash('danger', 'Mock payments are available only in development.');
            header("Location: " . BASE_URL . "/manager/settings.php");
            exit;
        }

        // Update settings in database
        $settingsToSave = [
            'platform_commission_percent' => number_format($commissionPercent, 2, '.', ''),
            'platform_name'               => $platformName,
            'support_email'               => $supportEmail ?: 'support@magalcreator.local',
            'active_gateway'              => $activeGateway
        ];

        $upStmt = $db->prepare("
            INSERT INTO platform_settings (setting_key, setting_value) 
            VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value = :v2, updated_at = NOW()
        ");

        foreach ($settingsToSave as $key => $val) {
            $upStmt->execute(['k' => $key, 'v' => $val, 'v2' => $val]);
        }

        log_activity(current_user_id(), 'UPDATE_SETTINGS', 'platform_settings', 1, 'Updated platform configuration.');

        set_flash('success', 'Platform settings saved successfully.');
        header("Location: " . BASE_URL . "/manager/settings.php");
        exit;
    }
}

// Fetch current settings
$currentCommission = get_platform_setting('platform_commission_percent', '10.00');
$currentName = get_platform_setting('platform_name', 'Magal Creator Marketplace');
$currentEmail = get_platform_setting('support_email', 'support@magalcreator.local');
$currentGateway = get_platform_setting('active_gateway', ACTIVE_PAYMENT_GATEWAY);
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">Platform Settings &amp; Financial Governance</h1>
    <p style="font-size: 14px; color: #64748b;">Configure platform commission splits, marketplace metadata, and active payment gateway adapters.</p>
</div>

<div style="max-width: 720px; background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 32px; box-shadow: var(--shadow-sm);">
    <form action="<?php echo BASE_URL; ?>/manager/settings.php" method="POST">
        <?php echo csrf_field(); ?>

        <h3 style="font-size: 16px; color: var(--secondary); border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-bottom: 20px;">
            1. Marketplace Commission Model
        </h3>

        <div class="form-group">
            <label class="form-label" for="commPct">Platform Commission Rate (%) *</label>
            <div style="position: relative; max-width: 250px;">
                <input type="number" step="0.1" min="0" max="50" id="commPct" name="platform_commission_percent" class="form-control" required value="<?php echo e($currentCommission); ?>">
                <span style="position: absolute; right: 14px; top: 12px; font-weight: 700; color: #94a3b8;">%</span>
            </div>
            <div class="form-hint">
                Standard percentage automatically deducted upon verified checkout payment. (e.g. 10.00% means ₹100 platform fee on ₹1,000 transaction, ₹900 provider share).
            </div>
        </div>

        <h3 style="font-size: 16px; color: var(--secondary); border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin: 30px 0 20px;">
            2. Payment Gateway Adapter Configuration
        </h3>

        <div class="form-group">
            <label class="form-label">Active Gateway Mode</label>
            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 8px;">
                <label style="border: 1.5px solid <?php echo ($currentGateway === 'mock') ? 'var(--primary)' : '#e2e8f0'; ?>; border-radius: 8px; padding: 12px 16px; display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                    <input type="radio" name="active_gateway" value="mock" <?php echo ($currentGateway === 'mock') ? 'checked' : ''; ?> style="margin-top: 4px; accent-color: var(--primary);">
                    <div>
                        <strong>Event Demonstration &amp; Sandbox Simulator (Recommended for Live Award Presentation)</strong>
                        <div style="font-size: 12px; color: #64748b;">
                            Simulates authentic Indian UPI, Google Pay, and NetBanking gateways without requiring active KYC merchant accounts or live internet.
                        </div>
                    </div>
                </label>

                <label style="border: 1.5px solid <?php echo ($currentGateway === 'cashfree') ? 'var(--primary)' : '#e2e8f0'; ?>; border-radius: 8px; padding: 12px 16px; display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                    <input type="radio" name="active_gateway" value="cashfree" <?php echo ($currentGateway === 'cashfree') ? 'checked' : ''; ?> style="margin-top: 4px; accent-color: var(--primary);">
                    <div>
                        <strong>Cashfree Payment Gateway (Live / Sandbox PG API)</strong>
                        <div style="font-size: 12px; color: #64748b;">
                            Direct integration with Cashfree v3 REST PG endpoints using credentials in <code>config/config.php</code>.
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <h3 style="font-size: 16px; color: var(--secondary); border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin: 30px 0 20px;">
            3. Platform Metadata
        </h3>

        <div class="form-group">
            <label class="form-label" for="pName">Platform Title</label>
            <input type="text" id="pName" name="platform_name" class="form-control" value="<?php echo e($currentName); ?>">
        </div>

        <div class="form-group">
            <label class="form-label" for="sEmail">Support &amp; Compliance Email</label>
            <input type="email" id="sEmail" name="support_email" class="form-control" value="<?php echo e($currentEmail); ?>">
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 15px;">
            <i class="fa-solid fa-floppy-disk"></i> Save Platform Settings
        </button>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

