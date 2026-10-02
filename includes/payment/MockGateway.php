<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Mock / Event Demonstration Gateway
 * Location: /includes/payment/MockGateway.php
 */

require_once __DIR__ . '/PaymentGatewayInterface.php';

class MockGateway implements PaymentGatewayInterface {

    public function getName(): string {
        return 'mock';
    }

    public function createOrder(array $orderData): array {
        $mockGatewayOrderId = 'MOCK_ORD_' . strtoupper(bin2hex(random_bytes(6)));
        $mockSessionId = 'session_' . bin2hex(random_bytes(12));

        // Generate redirect checkout URL to our built-in simulator screen
        $checkoutUrl = BASE_URL . '/checkout-simulate.php?' . http_build_query([
            'order_id'       => $orderData['order_id'],
            'gateway_order'  => $mockGatewayOrderId,
            'amount'         => $orderData['order_amount'],
            'customer_name'  => $orderData['customer_name'] ?? 'Customer',
            'return_url'     => $orderData['return_url']
        ]);

        return [
            'success'            => true,
            'gateway_order_id'   => $mockGatewayOrderId,
            'payment_session_id' => $mockSessionId,
            'checkout_url'       => $checkoutUrl,
            'error'              => null
        ];
    }

    public function verifyPayment(string $gatewayOrderId, array $payload = [], string $signature = ''): array {
        // The mock simulator supplies simulated signature validation
        $status = $payload['status'] ?? 'SUCCESS';
        $isSuccess = ($status === 'SUCCESS' || $status === 'completed');

        return [
            'verified'           => $isSuccess,
            'payment_status'     => $isSuccess ? 'completed' : 'failed',
            'gateway_payment_id' => $payload['payment_id'] ?? ('MOCK_PAY_' . rand(10000000, 99999999)),
            'payment_method'     => $payload['payment_method'] ?? 'UPI / GPay Simulator',
            'amount'             => (float)($payload['amount'] ?? 0.0),
            'raw_data'           => json_encode($payload)
        ];
    }
}

