<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Integrated Payment Gateway Simulator (For Awards & Demonstrations)
 * Location: /checkout-simulate.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/security.php';

$orderId = sanitize_string($_GET['order_id'] ?? '');
$gatewayOrderId = sanitize_string($_GET['gateway_order'] ?? '');
$amount = (float)($_GET['amount'] ?? 0);
$customerName = sanitize_string($_GET['customer_name'] ?? 'Customer');
$returnUrl = sanitize_string($_GET['return_url'] ?? (BASE_URL . '/payment-success.php'));

if (empty($gatewayOrderId) || $amount <= 0) {
    die("Invalid payment request parameters.");
}

$simulatedPaymentId = 'MOCK_PAY_' . rand(10000000, 99999999);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Payment Gateway | Magal Creator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .gateway-card { background: #ffffff; width: 100%; max-width: 440px; border-radius: 16px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .gateway-header { background: #1e293b; color: white; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; }
        .gateway-brand { font-size: 16px; font-weight: 700; color: #E8D4CE; display: flex; align-items: center; gap: 8px; }
        .gateway-amount { text-align: right; }
        .gateway-amount .label { font-size: 11px; color: #94a3b8; text-transform: uppercase; }
        .gateway-amount .value { font-size: 20px; font-weight: 800; color: #38bdf8; }
        .gateway-body { padding: 24px; }
        .demo-notice { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 10px 14px; border-radius: 8px; font-size: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .payment-methods { display: flex; flex-direction: column; gap: 10px; margin-bottom: 24px; }
        .method-item { border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 12px 16px; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: all 150ms ease; }
        .method-item:hover, .method-item.active { border-color: #8C3A27; background: #F7EEEB; }
        .method-icon { width: 36px; height: 36px; border-radius: 8px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 16px; color: #8C3A27; }
        .method-text strong { display: block; font-size: 14px; color: #1e293b; }
        .method-text span { font-size: 12px; color: #64748b; }
        .btn-pay { width: 100%; padding: 14px; background: #10b981; color: white; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; transition: background 150ms ease; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-pay:hover { background: #059669; }
        .btn-cancel { width: 100%; padding: 10px; background: transparent; color: #64748b; border: none; font-size: 13px; font-weight: 600; cursor: pointer; margin-top: 10px; }
        .btn-cancel:hover { color: #ef4444; }
        .gateway-footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 24px; font-size: 11.5px; color: #64748b; display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>

<div class="gateway-card">
    <div class="gateway-header">
        <div class="gateway-brand">
            <i class="fa-solid fa-crown"></i> Magal Creator Pay
        </div>
        <div class="gateway-amount">
            <div class="label">Amount Payable</div>
            <div class="value"><?php echo format_price($amount); ?></div>
        </div>
    </div>

    <div class="gateway-body">
        <div class="demo-notice">
            <i class="fa-solid fa-circle-info"></i>
            <span><strong>Event Demonstration Mode:</strong> Simulating real Cashfree PG response.</span>
        </div>

        <div style="font-size: 13px; color: #334155; margin-bottom: 12px; font-weight: 600;">
            Select Payment Method:
        </div>

        <div class="payment-methods">
            <label class="method-item active">
                <input type="radio" name="pay_mode" value="UPI / GooglePay" checked style="accent-color: #8C3A27;">
                <div class="method-icon"><i class="fa-brands fa-google-pay"></i></div>
                <div class="method-text">
                    <strong>UPI &amp; Instant Apps</strong>
                    <span>Google Pay, PhonePe, Paytm, BHIM</span>
                </div>
            </label>

            <label class="method-item">
                <input type="radio" name="pay_mode" value="Credit/Debit Card" style="accent-color: #8C3A27;">
                <div class="method-icon"><i class="fa-solid fa-credit-card"></i></div>
                <div class="method-text">
                    <strong>Cards (Visa / MasterCard / RuPay)</strong>
                    <span>Domestic &amp; Corporate Cards</span>
                </div>
            </label>

            <label class="method-item">
                <input type="radio" name="pay_mode" value="NetBanking" style="accent-color: #8C3A27;">
                <div class="method-icon"><i class="fa-solid fa-building-columns"></i></div>
                <div class="method-text">
                    <strong>Net Banking</strong>
                    <span>All major Indian retail banks</span>
                </div>
            </label>
        </div>

        <!-- Simulation Submission Form -->
        <form action="<?php echo e($returnUrl); ?>" method="POST" id="simForm">
            <input type="hidden" name="gateway_order_id" value="<?php echo e($gatewayOrderId); ?>">
            <input type="hidden" name="order_number" value="<?php echo e($orderId); ?>">
            <input type="hidden" name="payment_id" value="<?php echo e($simulatedPaymentId); ?>">
            <input type="hidden" name="payment_method" id="selectedMethod" value="UPI / GooglePay">
            <input type="hidden" name="amount" value="<?php echo $amount; ?>">
            <input type="hidden" name="status" value="SUCCESS">
            <input type="hidden" name="signature" value="<?php echo hash_hmac('sha256', $gatewayOrderId . $amount, 'MOCK_SECRET_KEY'); ?>">

            <button type="submit" class="btn-pay" id="payBtn">
                <i class="fa-solid fa-shield-check"></i> Complete Payment (<?php echo format_price($amount); ?>)
            </button>
        </form>

        <form action="<?php echo BASE_URL; ?>/payment-cancel.php" method="GET">
            <input type="hidden" name="order_id" value="<?php echo e($orderId); ?>">
            <button type="submit" class="btn-cancel">
                <i class="fa-solid fa-xmark"></i> Cancel Transaction
            </button>
        </form>
    </div>

    <div class="gateway-footer">
        <span><i class="fa-solid fa-lock"></i> 256-bit TLS Encrypted</span>
        <span>Magal Creator PG v3.2</span>
    </div>
</div>

<script>
document.querySelectorAll('input[name="pay_mode"]').forEach(radio => {
    radio.addEventListener('change', (e) => {
        document.querySelectorAll('.method-item').forEach(m => m.classList.remove('active'));
        e.target.closest('.method-item').classList.add('active');
        document.getElementById('selectedMethod').value = e.target.value;
    });
});

document.getElementById('simForm').addEventListener('submit', function() {
    const btn = document.getElementById('payBtn');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying with Gateway...';
    btn.style.opacity = '0.8';
});
</script>

</body>
</html>

