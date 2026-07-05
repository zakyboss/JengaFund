<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'errorMessage' => 'Donor login required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errorMessage' => 'Method not allowed.']);
    exit;
}

/** @var PDO $pdo */
$pdo = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Mpesa.php';
require_once __DIR__ . '/mpesa_helper.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$campaignId = (int) ($payload['campaign_id'] ?? 0);
$amount     = round((float) ($payload['amount'] ?? 0), 2);
$phone      = trim((string) ($payload['phone'] ?? ''));
$donorId    = (int) $_SESSION['user_id'];

if ($campaignId <= 0 || $amount < 1 || $phone === '') {
    echo json_encode(['success' => false, 'errorMessage' => 'Campaign, amount (min KES 1), and phone are required.']);
    exit;
}

if (mpesaNormalizePhone($phone) === null) {
    echo json_encode(['success' => false, 'errorMessage' => 'Enter a valid Kenyan number (e.g. 0712345678).']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, title, goal_amount, funds_raised, status, ends_at
        FROM campaigns
        WHERE id = ?
          AND status IN ('active', 'approved')
          AND ends_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        echo json_encode(['success' => false, 'errorMessage' => 'Campaign not found or no longer accepting donations.']);
        exit;
    }

    $remaining = (float) $campaign['goal_amount'] - (float) $campaign['funds_raised'];
    if ($remaining <= 0) {
        echo json_encode(['success' => false, 'errorMessage' => 'This campaign has already reached its funding goal.']);
        exit;
    }
    if ($amount > $remaining) {
        echo json_encode([
            'success' => false,
            'errorMessage' => 'Maximum donation for this campaign is KES ' . number_format($remaining, 2) . '.',
        ]);
        exit;
    }

    $pdo->beginTransaction();

    $stmtInsert = $pdo->prepare("
        INSERT INTO donations (donor_id, campaign_id, amount, status)
        VALUES (?, ?, ?, 'pending')
    ");
    $stmtInsert->execute([$donorId, $campaignId, $amount]);
    $donationId = (int) $pdo->lastInsertId();

    $accountReference = 'JF' . $donationId;
    $mpesa = new Mpesa();
    $response = $mpesa->initiateStkPush($amount, $phone, $accountReference);
    $data = json_decode($response);

    if (!isset($data->ResponseCode) || (string) $data->ResponseCode !== '0') {
        $pdo->rollBack();
        $error = $data->errorMessage ?? ($data->CustomerMessage ?? $response);
        echo json_encode(['success' => false, 'errorMessage' => 'STK Push failed: ' . $error]);
        exit;
    }

    $checkoutRequestId = $data->CheckoutRequestID ?? '';
    if ($checkoutRequestId === '') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'errorMessage' => 'No CheckoutRequestID returned from M-PESA.']);
        exit;
    }

    $pdo->prepare("
        UPDATE donations
        SET checkout_request_id = ?, updated_at = NOW()
        WHERE id = ?
    ")->execute([$checkoutRequestId, $donationId]);

    $pdo->commit();

    echo json_encode([
        'success'           => true,
        'donationId'        => $donationId,
        'checkoutRequestID' => $checkoutRequestId,
        'message'           => 'STK Push sent. Check your phone and enter your M-PESA PIN.',
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('mpesa_donate error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'errorMessage' => $e->getMessage()]);
}
