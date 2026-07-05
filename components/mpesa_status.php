<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Donor login required.']);
    exit;
}

/** @var PDO $pdo */
$pdo = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Mpesa.php';
require_once __DIR__ . '/mpesa_helper.php';

$donationId = (int) ($_GET['donation_id'] ?? 0);
$donorId    = (int) $_SESSION['user_id'];

if ($donationId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid donation ID.']);
    exit;
}

$donation = mpesaGetDonationById($pdo, $donationId);
if (!$donation || (int) $donation['donor_id'] !== $donorId) {
    echo json_encode(['status' => 'error', 'message' => 'Donation not found.']);
    exit;
}

if ($donation['status'] === 'success') {
    echo json_encode([
        'status'       => 'success',
        'message'      => 'Payment completed successfully.',
        'funds_raised' => (float) $donation['funds_raised'],
    ]);
    exit;
}

if ($donation['status'] === 'failed' || $donation['status'] === 'cancelled') {
    echo json_encode(['status' => 'failed', 'message' => 'Payment failed or was cancelled.']);
    exit;
}

$timeoutSeconds = mpesaPaymentTimeoutSeconds();
if (mpesaDonationHasTimedOut($donation) || isset($_GET['timeout'])) {
    mpesaFailDonation($pdo, $donationId);
    echo json_encode([
        'status'  => 'failed',
        'message' => "Payment timed out after {$timeoutSeconds} seconds. Please try again.",
    ]);
    exit;
}

$checkoutRequestId = $donation['checkout_request_id'] ?? '';
if ($checkoutRequestId === '') {
    echo json_encode(['status' => 'pending', 'message' => 'Waiting for M-PESA…']);
    exit;
}

try {
    $mpesa = new Mpesa();
    $response = $mpesa->queryTransaction($checkoutRequestId);
    $data = json_decode($response);
    $resultCode = isset($data->ResultCode) ? (string) $data->ResultCode : null;

    if ($resultCode === '0') {
        mpesaCompleteDonation($pdo, $donation);
        $updated = mpesaGetDonationById($pdo, $donationId);
        echo json_encode([
            'status'       => 'success',
            'message'      => mpesaQueryStatusMessage($resultCode),
            'funds_raised' => (float) ($updated['funds_raised'] ?? $donation['funds_raised']),
        ]);
        exit;
    }

    if (in_array($resultCode, ['1032', '1037', '1'], true)) {
        mpesaFailDonation($pdo, $donationId);
        echo json_encode([
            'status'  => 'failed',
            'message' => mpesaQueryStatusMessage($resultCode),
        ]);
        exit;
    }

    echo json_encode([
        'status'  => 'pending',
        'message' => mpesaQueryStatusMessage($resultCode),
    ]);
} catch (Throwable $e) {
    error_log('mpesa_status error: ' . $e->getMessage());
    echo json_encode(['status' => 'pending', 'message' => 'Still waiting for payment confirmation…']);
}
