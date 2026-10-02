<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Asynchronous Payment Gateway Webhook Receiver
 * Location: /api/payment-webhook.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/payment/PaymentService.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$rawPayload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

if (empty($rawPayload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty webhook payload']);
    exit;
}

$data = json_decode($rawPayload, true);
$gatewayOrderId = $data['data']['order']['order_id'] ?? $data['order_id'] ?? '';

if (empty($gatewayOrderId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing gateway order identifier']);
    exit;
}

$paymentService = new PaymentService();
$result = $paymentService->processVerifiedPayment($gatewayOrderId, $data, $signature);

if ($result['success']) {
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Payment processed and commission calculated']);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'failed', 'error' => $result['error']]);
}

