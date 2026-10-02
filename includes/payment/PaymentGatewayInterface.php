<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Payment Gateway Contract (Adapter Pattern)
 * Location: /includes/payment/PaymentGatewayInterface.php
 */

interface PaymentGatewayInterface {
    /**
     * Create an order session with the gateway
     *
     * @param array $orderData [
     *     'order_id'       => string,
     *     'order_amount'   => float,
     *     'order_currency' => string,
     *     'customer_id'    => string,
     *     'customer_name'  => string,
     *     'customer_email' => string,
     *     'customer_phone' => string,
     *     'return_url'     => string,
     *     'notify_url'     => string
     * ]
     * @return array [
     *     'success'            => bool,
     *     'gateway_order_id'   => string|null,
     *     'payment_session_id' => string|null,
     *     'checkout_url'       => string|null,
     *     'error'              => string|null
     * ]
     */
    public function createOrder(array $orderData): array;

    /**
     * Verify payment status directly with the gateway or via webhook signature
     *
     * @param string $gatewayOrderId
     * @param array $payload Webhook / callback payload
     * @param string $signature Cryptographic signature
     * @return array [
     *     'verified'           => bool,
     *     'payment_status'     => 'completed' | 'failed' | 'processing',
     *     'gateway_payment_id' => string|null,
     *     'payment_method'     => string|null,
     *     'amount'             => float,
     *     'raw_data'           => string
     * ]
     */
    public function verifyPayment(string $gatewayOrderId, array $payload = [], string $signature = ''): array;

    /**
     * Retrieve gateway name identifier
     * @return string
     */
    public function getName(): string;
}

