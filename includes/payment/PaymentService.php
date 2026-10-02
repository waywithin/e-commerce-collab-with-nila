<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Payment Orchestrator & Commission Engine
 * Location: /includes/payment/PaymentService.php
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/PaymentGatewayInterface.php';
require_once __DIR__ . '/MockGateway.php';
require_once __DIR__ . '/CashfreeGateway.php';

class PaymentService {
    private PaymentGatewayInterface $gateway;
    private PDO $db;

    public function __construct() {
        $this->db = getDB();

        // Retrieve preferred gateway from platform_settings table or fallback to config constant
        $configuredGateway = get_platform_setting('active_gateway', ACTIVE_PAYMENT_GATEWAY);

        if ($configuredGateway === 'mock') {
            if (APP_ENV !== 'development') {
                throw new RuntimeException('Mock payments are disabled outside development.');
            }
            $this->gateway = new MockGateway();
        } elseif (
            $configuredGateway === 'cashfree' &&
            CASHFREE_APP_ID !== '' &&
            CASHFREE_SECRET_KEY !== ''
        ) {
            $this->gateway = new CashfreeGateway();
        } else {
            throw new RuntimeException('No configured payment gateway is available.');
        }
    }

    /**
     * Get the active gateway instance
     */
    public function getGateway(): PaymentGatewayInterface {
        return $this->gateway;
    }

    /**
     * Initialize payment session for an order
     *
     * @param int $orderId
     * @return array
     */
    public function initiatePayment(int $orderId): array {
        // Fetch order details
        $stmt = $this->db->prepare("
            SELECT o.*, u.full_name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
            FROM orders o
            JOIN users u ON o.customer_id = u.id
            WHERE o.id = :oid LIMIT 1
        ");
        $stmt->execute(['oid' => $orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        $orderPayload = [
            'order_id'       => $order['order_number'],
            'order_amount'   => (float)$order['total_amount'],
            'order_currency' => 'INR',
            'customer_id'    => (string)$order['customer_id'],
            'customer_name'  => $order['customer_name'],
            'customer_email' => $order['customer_email'],
            'customer_phone' => $order['customer_phone'] ?? '9876543210',
            'return_url'     => BASE_URL . '/payment-success.php',
            'notify_url'     => BASE_URL . '/api/payment-webhook.php'
        ];

        // Call active gateway
        $res = $this->gateway->createOrder($orderPayload);

        if ($res['success']) {
            // Upsert payment record
            $pstmt = $this->db->prepare("
                INSERT INTO payments (order_id, gateway_name, gateway_order_id, amount, payment_status)
                VALUES (:oid, :gw_name, :gw_oid, :amt, 'created')
                ON DUPLICATE KEY UPDATE 
                    gateway_name = :gw_name2,
                    gateway_order_id = :gw_oid2,
                    amount = :amt2,
                    payment_status = 'created',
                    updated_at = NOW()
            ");
            $pstmt->execute([
                'oid'      => $orderId,
                'gw_name'  => $this->gateway->getName(),
                'gw_oid'   => $res['gateway_order_id'],
                'amt'      => $order['total_amount'],
                'gw_name2' => $this->gateway->getName(),
                'gw_oid2'  => $res['gateway_order_id'],
                'amt2'     => $order['total_amount']
            ]);
        }

        return $res;
    }

    /**
     * Process verified payment and record platform commission & provider settlement
     *
     * @param string $gatewayOrderId
     * @param array $callbackPayload
     * @param string $signature
     * @return array
     */
    public function processVerifiedPayment(string $gatewayOrderId, array $callbackPayload = [], string $signature = ''): array {
        // 1. Verify with the active gateway
        $verifyResult = $this->gateway->verifyPayment($gatewayOrderId, $callbackPayload, $signature);

        if (!$verifyResult['verified']) {
            return ['success' => false, 'error' => 'Payment verification failed with gateway.'];
        }

        try {
            $this->db->beginTransaction();

            // 2. Fetch payment record linked to this gateway_order_id
            $stmt = $this->db->prepare("SELECT * FROM payments WHERE gateway_order_id = :goid LIMIT 1");
            $stmt->execute(['goid' => $gatewayOrderId]);
            $payment = $stmt->fetch();

            if (!$payment) {
                // Try looking up via order_number if gateway_order_id matches order number
                $stmt = $this->db->prepare("
                    SELECT p.* FROM payments p 
                    JOIN orders o ON p.order_id = o.id 
                    WHERE o.order_number = :goid LIMIT 1
                ");
                $stmt->execute(['goid' => $gatewayOrderId]);
                $payment = $stmt->fetch();
            }

            if (!$payment) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Matching payment transaction not found in database.'];
            }

            $paymentId = (int)$payment['id'];
            $orderId = (int)$payment['order_id'];

            // 3. Fetch order details to know provider and gross amount
            $oStmt = $this->db->prepare("SELECT * FROM orders WHERE id = :oid LIMIT 1");
            $oStmt->execute(['oid' => $orderId]);
            $order = $oStmt->fetch();

            if (!$order) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Order not found for this payment.'];
            }

            // 4. Update payment record to completed
            $upPay = $this->db->prepare("
                UPDATE payments 
                SET payment_status = 'completed',
                    gateway_payment_id = :gpid,
                    payment_method = :pmeth,
                    payment_response_raw = :raw,
                    paid_at = NOW(),
                    updated_at = NOW()
                WHERE id = :pid
            ");
            $upPay->execute([
                'gpid'  => $verifyResult['gateway_payment_id'],
                'pmeth' => $verifyResult['payment_method'],
                'raw'   => $verifyResult['raw_data'],
                'pid'   => $paymentId
            ]);

            // 5. Update order status to confirmed
            $upOrder = $this->db->prepare("UPDATE orders SET order_status = 'confirmed', updated_at = NOW() WHERE id = :oid");
            $upOrder->execute(['oid' => $orderId]);

            // 6. CALCULATE PLATFORM COMMISSION AND PROVIDER SETTLEMENT
            // Retrieve current configured platform commission rate (e.g., 10%)
            $commissionRate = (float)get_platform_setting('platform_commission_percent', DEFAULT_PLATFORM_COMMISSION_PERCENT);
            $grossAmount = (float)$order['total_amount'];
            
            // Formula: Commission = Gross * (Rate / 100)
            $platformCommission = round(($grossAmount * ($commissionRate / 100.0)), 2);
            $gatewayFee = 0.00; // Can be configured if charged to provider
            $providerShare = round($grossAmount - $platformCommission - $gatewayFee, 2);

            // 7. Store snapshot in commissions table
            $cStmt = $this->db->prepare("
                INSERT INTO commissions 
                (payment_id, order_id, provider_id, gross_amount, commission_rate_percent, platform_commission_amount, gateway_fee_amount, provider_payable_amount, settlement_status)
                VALUES 
                (:pay_id, :ord_id, :prov_id, :gross, :rate, :comm, :gw_fee, :prov_share, 'pending')
                ON DUPLICATE KEY UPDATE
                    gross_amount = :gross2,
                    commission_rate_percent = :rate2,
                    platform_commission_amount = :comm2,
                    provider_payable_amount = :prov_share2
            ");
            $cStmt->execute([
                'pay_id'      => $paymentId,
                'ord_id'      => $orderId,
                'prov_id'     => $order['provider_id'],
                'gross'       => $grossAmount,
                'rate'        => $commissionRate,
                'comm'        => $platformCommission,
                'gw_fee'      => $gatewayFee,
                'prov_share'  => $providerShare,
                'gross2'      => $grossAmount,
                'rate2'       => $commissionRate,
                'comm2'       => $platformCommission,
                'prov_share2' => $providerShare
            ]);

            $this->db->commit();

            return [
                'success'             => true,
                'order_id'            => $orderId,
                'order_number'        => $order['order_number'],
                'gross_amount'        => $grossAmount,
                'platform_commission' => $platformCommission,
                'provider_payable'    => $providerShare
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Transaction failure: ' . $e->getMessage()];
        }
    }
}

