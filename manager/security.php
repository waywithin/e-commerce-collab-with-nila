<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Manager Password & Account Security Management
 * Location: /manager/security.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Enforce manager authorization
require_manager();
$db = getDB();

$managerId = (int)current_user_id();

// --------------------------------------------------------------------
// 1. SECURE POST HANDLER (Change Password)
// --------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validate CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security validation failed (invalid or expired CSRF token). Please try again.');
        header("Location: " . BASE_URL . "/manager/security.php");
        exit;
    }

    // 2. Read input fields
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    // 3. Validation: All fields required
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        set_flash('danger', 'All password fields are required.');
        header("Location: " . BASE_URL . "/manager/security.php");
        exit;
    }

    // 4. Retrieve current password hash strictly using session-authenticated Manager ID
    $authStmt = $db->prepare("SELECT id, password_hash, email, full_name FROM users WHERE id = :id AND role = 'manager' LIMIT 1");
    $authStmt->execute(['id' => $managerId]);
    $userAuth = $authStmt->fetch();

    if (!$userAuth || !password_verify($currentPassword, $userAuth['password_hash'])) {
        set_flash('danger', 'The current password you entered is incorrect.');
        header("Location: " . BASE_URL . "/manager/security.php");
        exit;
    }

    // 5. Password Policy: Length constraints (8 to 128 chars)
    if (strlen($newPassword) < 8) {
        set_flash('danger', 'The new password must be at least 8 characters long.');
        header("Location: " . BASE_URL . "/manager/security.php");
        exit;
    }

    if (strlen($newPassword) > 128) {
        set_flash('danger', 'The new password must not exceed 128 characters.');
        header("Location: " . BASE_URL . "/manager/security.php");
        exit;
    }

    // 6. Confirmation check
    if ($newPassword !== $confirmPassword) {
        set_flash('danger', 'The new password and confirmation password do not match.');
        header("Location: " . BASE_URL . "/manager/security.php");
        exit;
    }

    // 7. Prevent reusing the same password
    if ($newPassword === $currentPassword) {
        set_flash('danger', 'The new password must be different from your current password.');
        header("Location: " . BASE_URL . "/manager/security.php");
        exit;
    }

    // 8. Securely hash the new password using PASSWORD_BCRYPT
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);

    // 9. Update password hash strictly for the authenticated manager
    $updStmt = $db->prepare("UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id AND role = 'manager'");
    $updStmt->execute([
        'hash' => $newHash,
        'id'   => $managerId
    ]);

    // 10. Regenerate session to prevent session fixation
    session_regenerate_id(true);

    // 11. Record security activity log
    log_activity($managerId, 'MANAGER_PASSWORD_CHANGED', 'user', $managerId, 'Manager account password was changed successfully.');

    set_flash('success', 'Your Manager account password has been changed successfully.');
    header("Location: " . BASE_URL . "/manager/security.php");
    exit;
}

// --------------------------------------------------------------------
// 2. FETCH MANAGER ACCOUNT IDENTITY & AUDIT RECORDS
// --------------------------------------------------------------------
$mgrStmt = $db->prepare("
    SELECT id, full_name, email, phone, role, status, created_at, updated_at 
    FROM users 
    WHERE id = :id AND role = 'manager' 
    LIMIT 1
");
$mgrStmt->execute(['id' => $managerId]);
$manager = $mgrStmt->fetch();

if (!$manager) {
    set_flash('danger', 'Authenticated manager profile not found.');
    header("Location: " . BASE_URL . "/logout.php");
    exit;
}

// Recent security activity logs for this manager
$secLogsStmt = $db->prepare("
    SELECT id, action, entity_type, ip_address, details, created_at 
    FROM activity_logs 
    WHERE user_id = :uid 
      AND action IN ('LOGIN', 'LOGOUT', 'MANAGER_PASSWORD_CHANGED', 'APPROVE_PROVIDER', 'REJECT_PROVIDER', 'APPROVE_PRODUCT', 'HIDE_PRODUCT')
    ORDER BY id DESC 
    LIMIT 5
");
$secLogsStmt->execute(['uid' => $managerId]);
$recentSecurityLogs = $secLogsStmt->fetchAll();

$pageTitle = 'Account Security';
include __DIR__ . '/includes/header.php';
?>

<!-- Header Title -->
<div style="margin-bottom: 24px;">
    <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px; display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-shield-halved" style="color: #8C3A27;"></i>
        Manager Account Security
    </h1>
    <p style="font-size: 14px; color: #64748b;">
        Manage your administrator account credentials, review security status, and inspect recent authentication events.
    </p>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start;">
    <!-- ==========================================================================
         COLUMN 1: CHANGE PASSWORD FORM CARD
         ========================================================================== -->
    <div class="data-table-card" style="margin-bottom: 0;">
        <div class="table-header-bar">
            <div>
                <h3 style="font-size: 16px; color: var(--secondary); margin-bottom: 2px;">Change Password</h3>
                <p style="font-size: 12px; color: #64748b;">Update your administrator login password securely</p>
            </div>
            <span class="badge badge-success">
                <i class="fa-solid fa-lock"></i> Encrypted
            </span>
        </div>

        <div style="padding: 24px;">
            <form method="POST" action="<?php echo BASE_URL; ?>/manager/security.php" autocomplete="off">
                <?php echo csrf_field(); ?>

                <!-- Current Password -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Current Password <span style="color: #dc2626;">*</span>
                    </label>
                    <div style="position: relative;">
                        <input type="password" name="current_password" id="current_password" required 
                               class="form-control" placeholder="Enter your current password"
                               style="padding-right: 40px; font-size: 14px; height: 42px;">
                        <button type="button" onclick="togglePasswordVisibility('current_password', 'toggle_current_icon')" 
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px;"
                                title="Toggle visibility">
                            <i class="fa-regular fa-eye" id="toggle_current_icon"></i>
                        </button>
                    </div>
                </div>

                <!-- New Password -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        New Password <span style="color: #dc2626;">*</span>
                    </label>
                    <div style="position: relative;">
                        <input type="password" name="new_password" id="new_password" required minlength="8" maxlength="128"
                               class="form-control" placeholder="Enter at least 8 characters"
                               style="padding-right: 40px; font-size: 14px; height: 42px;">
                        <button type="button" onclick="togglePasswordVisibility('new_password', 'toggle_new_icon')" 
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px;"
                                title="Toggle visibility">
                            <i class="fa-regular fa-eye" id="toggle_new_icon"></i>
                        </button>
                    </div>
                    <small style="color: #64748b; font-size: 11.5px; display: block; margin-top: 4px;">
                        Must be at least 8 characters and differ from your current password.
                    </small>
                </div>

                <!-- Confirm New Password -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Confirm New Password <span style="color: #dc2626;">*</span>
                    </label>
                    <div style="position: relative;">
                        <input type="password" name="confirm_password" id="confirm_password" required minlength="8" maxlength="128"
                               class="form-control" placeholder="Re-enter your new password"
                               style="padding-right: 40px; font-size: 14px; height: 42px;">
                        <button type="button" onclick="togglePasswordVisibility('confirm_password', 'toggle_confirm_icon')" 
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px;"
                                title="Toggle visibility">
                            <i class="fa-regular fa-eye" id="toggle_confirm_icon"></i>
                        </button>
                    </div>
                </div>

                <!-- Password Policy Notice -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; margin-bottom: 24px; font-size: 12px; color: #475569;">
                    <strong style="color: #1e293b; display: block; margin-bottom: 4px;">
                        <i class="fa-solid fa-shield-check" style="color: #8C3A27;"></i> Password Security Requirements:
                    </strong>
                    <ul style="margin: 0; padding-left: 18px; line-height: 1.6;">
                        <li>Minimum of 8 characters in length</li>
                        <li>Must differ from your current password</li>
                        <li>Confirmation entry must match exactly</li>
                    </ul>
                </div>

                <!-- Action Button -->
                <button type="submit" class="btn btn-primary" style="width: 100%; height: 42px; font-size: 14px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fa-solid fa-key"></i> Update Password
                </button>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         COLUMN 2: ACCOUNT IDENTITY & RECENT SECURITY ACTIVITY
         ========================================================================== -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Account Identity Card -->
        <div class="data-table-card" style="margin-bottom: 0;">
            <div class="table-header-bar">
                <div>
                    <h3 style="font-size: 16px; color: var(--secondary); margin-bottom: 2px;">Account Identity</h3>
                    <p style="font-size: 12px; color: #64748b;">Authenticated administrator account metadata</p>
                </div>
                <span class="badge" style="background: #F1E0DB; color: #8C3A27; font-weight: 700;">
                    <i class="fa-solid fa-user-shield"></i> Manager
                </span>
            </div>

            <div style="padding: 20px 24px;">
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                        <span style="font-size: 13px; color: #64748b; font-weight: 600;">Full Name</span>
                        <strong style="font-size: 13.5px; color: #1e293b;"><?php echo e($manager['full_name']); ?></strong>
                    </div>

                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                        <span style="font-size: 13px; color: #64748b; font-weight: 600;">Email Address</span>
                        <span style="font-size: 13px; color: #334155;"><?php echo e($manager['email']); ?></span>
                    </div>

                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                        <span style="font-size: 13px; color: #64748b; font-weight: 600;">Contact Phone</span>
                        <span style="font-size: 13px; color: #334155;"><?php echo e($manager['phone'] ?? 'Unspecified'); ?></span>
                    </div>

                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                        <span style="font-size: 13px; color: #64748b; font-weight: 600;">Account Status</span>
                        <?php if ($manager['status'] === 'active'): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><?php echo ucfirst($manager['status']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                        <span style="font-size: 13px; color: #64748b; font-weight: 600;">Member Since</span>
                        <span style="font-size: 12.5px; color: #475569;"><?php echo format_date($manager['created_at']); ?></span>
                    </div>

                    <div style="display: flex; justify-content: space-between;">
                        <span style="font-size: 13px; color: #64748b; font-weight: 600;">Last Profile Update</span>
                        <span style="font-size: 12.5px; color: #475569;"><?php echo format_date($manager['updated_at'], true); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Security Activity Logs -->
        <div class="data-table-card" style="margin-bottom: 0;">
            <div class="table-header-bar">
                <div>
                    <h3 style="font-size: 16px; color: var(--secondary); margin-bottom: 2px;">Recent Security Events</h3>
                    <p style="font-size: 12px; color: #64748b;">Audit trail of your recent administrative sessions</p>
                </div>
                <span class="badge badge-info">Audit Log</span>
            </div>

            <?php if (!empty($recentSecurityLogs)): ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>IP Address</th>
                                <th style="text-align: right;">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSecurityLogs as $log): ?>
                                <tr>
                                    <td>
                                        <div style="font-size: 12px; font-weight: 700; color: #1e293b;">
                                            <code><?php echo e($log['action']); ?></code>
                                        </div>
                                        <small style="font-size: 11px; color: #64748b;"><?php echo e($log['details'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <span style="font-size: 12px; font-family: monospace; color: #475569;">
                                            <?php echo e($log['ip_address'] ?? '127.0.0.1'); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right; font-size: 11.5px; color: #64748b; white-space: nowrap;">
                                        <?php echo format_date($log['created_at'], true); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 32px 16px; color: #94a3b8;">
                    <i class="fa-solid fa-list-check" style="font-size: 28px; margin-bottom: 8px; color: #cbd5e1;"></i>
                    <p style="font-size: 13px; margin-bottom: 0;">No recent security audit events recorded.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility(fieldId, iconId) {
    const input = document.getElementById(fieldId);
    const icon = document.getElementById(iconId);
    if (!input || !icon) return;

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php
include __DIR__ . '/includes/footer.php';
