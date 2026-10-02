<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Checkout & Order Placement
 * Location: /checkout.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/payment/PaymentService.php';

// Checkout requires a logged-in user
require_login();

$db = getDB();
$productId = (int)($_REQUEST['product_id'] ?? 0);
$quantity = max(1, (int)($_REQUEST['quantity'] ?? 1));
$bookingDate = sanitize_string($_REQUEST['booking_date'] ?? date('Y-m-d', strtotime('+1 day')));

if ($productId <= 0) {
    header("Location: " . BASE_URL . "/browse.php");
    exit;
}

// 1. Authoritative Database Fetch of Product & Provider
$stmt = $db->prepare("
    SELECT p.*, c.name AS category_name, sp.id AS provider_id, sp.business_name, sp.slug AS provider_slug, sp.city
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN service_providers sp ON p.provider_id = sp.id
    WHERE p.id = :id AND p.status = 'active'
    LIMIT 1
");
$stmt->execute(['id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    die("Product or service unavailable.");
}

// 2. Authoritative Price Calculation (Guarantees customer cannot manipulate price)
$offer = get_product_offer_details($product);
$unitPrice = $offer['effective_price'];
$totalAmount = round($unitPrice * $quantity, 2);

$errorMessage = null;

// 3. Process Checkout Order Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Security token expired. Please try again.';
    } else {
        $customerName = sanitize_string($_POST['customer_name'] ?? '');
        $customerPhone = sanitize_string($_POST['customer_phone'] ?? '');
        $customerAddress = sanitize_textarea($_POST['customer_address'] ?? '');
        $bookingNotes = sanitize_textarea($_POST['notes'] ?? '');

        if (empty($customerName) || empty($customerPhone)) {
            $errorMessage = 'Please provide your full contact name and phone number.';
        } else {
            try {
                // Generate unique order number (e.g. ORD-2026-XXXXX)
                $orderNumber = 'ORD-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));

                $oStmt = $db->prepare("
                    INSERT INTO orders 
                    (order_number, customer_id, provider_id, product_id, unit_price, quantity, total_amount, order_status, booking_date, customer_name, customer_phone, customer_address, notes)
                    VALUES 
                    (:ord_num, :cid, :pid, :prod_id, :uprice, :qty, :tot, 'pending', :bdate, :cname, :cphone, :caddr, :notes)
                ");
                $oStmt->execute([
                    'ord_num' => $orderNumber,
                    'cid'     => current_user_id(),
                    'pid'     => $product['provider_id'],
                    'prod_id' => $product['id'],
                    'uprice'  => $unitPrice,
                    'qty'     => $quantity,
                    'tot'     => $totalAmount,
                    'bdate'   => ($product['service_type'] === 'service') ? $bookingDate : null,
                    'cname'   => $customerName,
                    'cphone'  => $customerPhone,
                    'caddr'   => $customerAddress,
                    'notes'   => $bookingNotes
                ]);
                $orderId = (int)$db->lastInsertId();

                // Call Payment Service
                $paymentService = new PaymentService();
                $initRes = $paymentService->initiatePayment($orderId);

                if ($initRes['success'] && !empty($initRes['checkout_url'])) {
                    // Redirect to payment gateway / simulator
                    header("Location: " . $initRes['checkout_url']);
                    exit;
                } else {
                    $errorMessage = 'Failed to initiate payment with gateway: ' . ($initRes['error'] ?? 'Unknown error');
                }
            } catch (Exception $e) {
                $errorMessage = 'Order placement failed: ' . $e->getMessage();
            }
        }
    }
}

$currentUser = current_user();
$pageTitle = 'Secure Marketplace Checkout | Magal Creator';
include __DIR__ . '/includes/header.php';
?>

<main class="container" style="padding: 40px 20px 80px;">
    <div style="max-width: 900px; margin: 0 auto;">
        
        <div style="margin-bottom: 24px;">
            <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 6px;">Secure Marketplace Checkout</h1>
            <p style="font-size: 14px; color: #64748b;">Complete your booking and pay securely through the platform gateway</p>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo e($errorMessage); ?></span>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 32px; align-items: flex-start;">
            <!-- Customer Information Form -->
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 30px; box-shadow: var(--shadow-sm);">
                <h3 style="font-size: 17px; margin-bottom: 20px; color: var(--text-heading); border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                    <i class="fa-solid fa-user-check" style="color: var(--primary);"></i> Client &amp; Appointment Details
                </h3>

                <form action="<?php echo BASE_URL; ?>/checkout.php" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                    <input type="hidden" name="quantity" value="<?php echo (int)$quantity; ?>">
                    <input type="hidden" name="booking_date" value="<?php echo e($bookingDate); ?>">

                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="customer_name" class="form-control" required value="<?php echo e($currentUser['name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Phone Number *</label>
                        <input type="text" name="customer_phone" class="form-control" required placeholder="+91 98765 43210" value="">
                    </div>

                    <?php if ($product['service_type'] === 'service'): ?>
                        <div class="form-group">
                            <label class="form-label">Appointment / Service Date</label>
                            <input type="text" readonly class="form-control" style="background: #f8fafc;" value="<?php echo date('D, d M Y', strtotime($bookingDate)); ?>">
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">Delivery Address / Location</label>
                        <textarea name="customer_address" class="form-control" placeholder="House/Flat number, Street, Landmark..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Special Instructions / Customization Notes</label>
                        <textarea name="notes" class="form-control" placeholder="Any specific requirements for the entrepreneur..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 15px;">
                        <i class="fa-solid fa-lock"></i> Proceed to Payment Gateway &rarr;
                    </button>
                </form>
            </div>

            <!-- Order Summary Sidebar -->
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 26px; box-shadow: var(--shadow-sm);">
                <h3 style="font-size: 17px; margin-bottom: 16px; color: var(--text-heading); border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                    Order Summary
                </h3>

                <div style="display: flex; gap: 14px; margin-bottom: 20px;">
                    <img src="<?php echo e(get_image_url($product['cover_image'], 'product')); ?>" alt="<?php echo e($product['title']); ?>" style="width: 70px; height: 70px; border-radius: 8px; object-fit: cover;">
                    <div>
                        <h4 style="font-size: 14px; margin-bottom: 4px;"><?php echo e($product['title']); ?></h4>
                        <div style="font-size: 12px; color: #64748b;">By: <?php echo e($product['business_name']); ?></div>
                        <div style="font-size: 12px; color: var(--primary); font-weight: 600;">Qty: <?php echo $quantity; ?></div>
                    </div>
                </div>

                <?php if ($offer['status'] === 'ACTIVE'): ?>
                    <div style="background: #F7EEEB; border: 1px solid #E8D4CE; border-radius: 6px; padding: 10px; font-size: 12px; color: #732D1D; margin-bottom: 16px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-fire" style="color: #8C3A27;"></i>
                        <span>Limited-time offer pricing applied: <strong><?php echo $offer['savings_percent']; ?>% OFF</strong></span>
                    </div>
                <?php endif; ?>

                <div style="display: flex; flex-direction: column; gap: 10px; font-size: 14px; border-top: 1px dashed var(--border-color); padding-top: 14px; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; color: #64748b;">
                        <span>Unit Price</span>
                        <span><?php echo format_price($unitPrice); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; color: #64748b;">
                        <span>Quantity</span>
                        <span>&times; <?php echo $quantity; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; color: #64748b;">
                        <span>Marketplace Processing Fee</span>
                        <span style="color: #059669; font-weight: 600;">FREE (₹0.00)</span>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: baseline; font-size: 18px; font-weight: 800; color: var(--secondary); border-top: 2px solid var(--border-color); padding-top: 14px;">
                    <span>Total Amount Payable</span>
                    <span style="font-size: 24px; color: var(--primary);"><?php echo format_price($totalAmount); ?></span>
                </div>

                <div style="margin-top: 20px; font-size: 12px; color: #64748b; line-height: 1.6; background: #f8fafc; padding: 12px; border-radius: 6px;">
                    <i class="fa-solid fa-shield-halved" style="color: #0284c7;"></i>
                    <strong>Platform Payment Guarantee:</strong> Your payment is held securely by the platform gateway and disbursed to the entrepreneur upon booking verification.
                </div>
            </div>
        </div>

    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

