<?php
/**
 * Safaricom STK Push callback — must be reachable via public HTTPS (ngrok in local dev).
 */
header('Content-Type: application/json');

/** @var PDO $pdo */
$pdo = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/mpesa_helper.php';

$rawBody = file_get_contents('php://input') ?: '';

try {
    mpesaProcessCallback($pdo, $rawBody);
} catch (Throwable $e) {
    error_log('mpesa_callback error: ' . $e->getMessage());
    mpesaLog('ERROR: ' . $e->getMessage());
}

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
