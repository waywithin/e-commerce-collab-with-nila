<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Cashfree Payment Gateway Adapter
 * Location: /includes/payment/CashfreeGateway.php
 */

require_once __DIR__ . '/PaymentGatewayInterface.php';
require_once __DIR__ . '/../../config/config.php';

class CashfreeGateway implements PaymentGatewayInterface {
    private string $appId;
    private string $secretKey;
    private string $baseUrl;
    private string $apiVersion;

    public function __construct() {
        $this->appId = CASHFREE_APP_ID;
        $this->secretKey = CASHFREE_SECRET_KEY;
        $this->apiVersion = CASHFREE_API_VERSION;

        $this->baseUrl = (CASHFREE_ENV === 'PROD')
            ? 'https://api.cashfree.com/pg'
            : 'https://sandbox.cashfree.com/pg';
    }

    public function getName(): string {
        return 'cashfree';
    }

    public function createOrder(array $orderData): array {
        $endpoint = $this->baseUrl . '/orders';

        $payload = [
            'order_id'       => (string)$orderData['order_id'],
            'order_amount'   => (float)$orderData['order_amount'],
            'order_currency' => $orderData['order_currency'] ?? 'INR',
            'customer_details' => [
                'customer_id'    => (string)$orderData['customer_id'],
                'customer_name'  => (string)$orderData['customer_name'],
                'customer_email' => (string)$orderData['customer_email'],
                'customer_phone' => (string)$orderData['customer_phone']
            ],
            'order_meta' => [
                'return_url' => $orderData['return_url'] . '?order_id={order_id}',
                'notify_url' => $orderData['notify_url'] ?? (BASE_URL . '/api/payment-webhook.php')
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-client-id: ' . $this->appId,
            'x-client-secret: ' . $this->secretKey,
            'x-api-version: ' . $this->apiVersion
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode >= 400) {
            return [
                'success' => false,
                'error'   => 'Cashfree API Error (' . $httpCode . '): ' . ($response ?: 'Connection timeout')
            ];
        }

        $resData = json_decode($response, true);

        return [
            'success'            => true,
            'gateway_order_id'   => $resData['cf_order_id'] ?? null,
            'payment_session_id' => $resData['payment_session_id'] ?? null,
            'checkout_url'       => $resData['payments']['url'] ?? null,
            'error'              => null
        ];
    }

    public function verifyPayment(string $gatewayOrderId, array $payload = [], string $signature = ''): array {
        $endpoint = $this->baseUrl . '/orders/' . urlencode($gatewayOrderId);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-client-id: ' . $this->appId,
            'x-client-secret: ' . $this->secretKey,
            'x-api-version: ' . $this->apiVersion
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return ['verified' => false, 'payment_status' => 'failed', 'raw_data' => 'Empty response from Cashfree'];
        }

        $data = json_decode($response, true);
        $orderStatus = $data['order_status'] ?? 'FAILED';
        $isPaid = ($orderStatus === 'PAID');

        return [
            'verified'           => $isPaid,
            'payment_status'     => $isPaid ? 'completed' : 'failed',
            'gateway_payment_id' => $data['cf_order_id'] ?? null,
            'payment_method'     => 'Cashfree Integrated Gateway',
            'amount'             => (float)($data['order_amount'] ?? 0),
            'raw_data'           => $response
        ];
    }
}

