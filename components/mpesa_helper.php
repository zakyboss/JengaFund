<?php
/**
 * Shared helpers for M-PESA donations (phone format, callback processing, logging).
 */
require_once __DIR__ . '/notification_helper.php';
require_once __DIR__ . '/milestone_helper.php';

function mpesaNormalizePhone(string $phone): ?string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === '') {
        return null;
    }

    if (str_starts_with($digits, '0')) {
        $digits = '254' . substr($digits, 1);
    } elseif (strlen($digits) === 9 && $digits[0] === '7') {
        $digits = '254' . $digits;
    } elseif (strlen($digits) === 10 && $digits[0] === '7') {
        $digits = '254' . $digits;
    } elseif (!str_starts_with($digits, '254')) {
        return null;
    }

    return strlen($digits) === 12 ? $digits : null;
}

function mpesaLog(string $message): void
{
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(
        $dir . '/mpesa_callbacks.log',
        date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function mpesaParseCallbackMetadata(?array $items): array
{
    $parsed = [];
    if (!$items) {
        return $parsed;
    }
    foreach ($items as $item) {
        if (isset($item->Name)) {
            $parsed[$item->Name] = $item->Value ?? null;
        }
    }
    return $parsed;
}

function mpesaGetDonationByCheckoutId(PDO $pdo, string $checkoutRequestId): ?array
{
    $stmt = $pdo->prepare("
        SELECT d.*, c.title AS campaign_title, c.student_id, c.status AS campaign_status,
               c.goal_amount, c.funds_raised
        FROM donations d
        JOIN campaigns c ON c.id = d.campaign_id
        WHERE d.checkout_request_id = ?
        LIMIT 1
    ");
    $stmt->execute([$checkoutRequestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mpesaPaymentTimeoutSeconds(): int
{
    return (int) (getenv('MPESA_PAYMENT_TIMEOUT') ?: 40);
}

function mpesaDonationHasTimedOut(array $donation): bool
{
    if (($donation['status'] ?? '') !== 'pending') {
        return false;
    }

    $createdAt = strtotime($donation['created_at'] ?? '');
    if ($createdAt === false) {
        return false;
    }

    return (time() - $createdAt) >= mpesaPaymentTimeoutSeconds();
}

function mpesaTimeoutDonation(PDO $pdo, int $donationId): bool
{
    $donation = mpesaGetDonationById($pdo, $donationId);
    if (!$donation || !mpesaDonationHasTimedOut($donation)) {
        return false;
    }

    mpesaFailDonation($pdo, $donationId);
    return true;
}

function mpesaGetDonationById(PDO $pdo, int $donationId): ?array
{
    $stmt = $pdo->prepare("
        SELECT d.*, c.title AS campaign_title, c.student_id, c.status AS campaign_status,
               c.goal_amount, c.funds_raised
        FROM donations d
        JOIN campaigns c ON c.id = d.campaign_id
        WHERE d.id = ?
        LIMIT 1
    ");
    $stmt->execute([$donationId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mpesaCompleteDonation(PDO $pdo, array $donation): bool
{
    if ($donation['status'] === 'success') {
        return true;
    }
    if (!in_array($donation['status'], ['pending', 'failed'], true)) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            UPDATE donations
            SET status = 'success', updated_at = NOW()
            WHERE id = ? AND status IN ('pending', 'failed')
        ");
        $stmt->execute([(int) $donation['id']]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            return false;
        }

        $amount = (float) $donation['amount'];
        $campaignId = (int) $donation['campaign_id'];

        $stmtCampaign = $pdo->prepare("
            SELECT funds_raised, goal_amount, status, student_id, title, disbursement_type
            FROM campaigns
            WHERE id = ?
            FOR UPDATE
        ");
        $stmtCampaign->execute([$campaignId]);
        $campaign = $stmtCampaign->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) {
            $pdo->rollBack();
            return false;
        }

        $fundsBefore = (float) $campaign['funds_raised'];

        $pdo->prepare("
            UPDATE campaigns
            SET funds_raised = funds_raised + ?
            WHERE id = ?
        ")->execute([$amount, $campaignId]);

        $campaign['funds_raised'] = $fundsBefore + $amount;

        if (
            $campaign
            && in_array($campaign['status'], ['active', 'approved'], true)
            && (float) $campaign['funds_raised'] >= (float) $campaign['goal_amount']
        ) {
            $pdo->prepare("
                UPDATE campaigns
                SET status = 'awaiting_disbursement'
                WHERE id = ? AND status IN ('active', 'approved')
            ")->execute([$campaignId]);
        }

        $donorName = 'A donor';
        $stmtDonor = $pdo->prepare('SELECT full_name FROM users WHERE id = ?');
        $stmtDonor->execute([(int) $donation['donor_id']]);
        if ($name = $stmtDonor->fetchColumn()) {
            $donorName = $name;
        }

        $campaignTitle = $campaign['title'] ?? $donation['campaign_title'] ?? 'your campaign';
        $formattedAmount = number_format($amount, 2);

        notifyUser(
            $pdo,
            (int) $campaign['student_id'],
            'donation_received',
            "{$donorName} donated KES {$formattedAmount} to \"{$campaignTitle}\".",
            $campaignId
        );

        notifyUser(
            $pdo,
            (int) $donation['donor_id'],
            'donation_receipt',
            "Your donation of KES {$formattedAmount} to \"{$campaignTitle}\" was successful. Thank you!",
            (int) $donation['id']
        );

        $pdo->commit();

        try {
            alertStudentForNewlyUnlockedMilestones($pdo, $campaignId, $fundsBefore);
        } catch (Throwable $unlockError) {
            error_log('Milestone unlock alert failed: ' . $unlockError->getMessage());
        }

        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('mpesaCompleteDonation failed: ' . $e->getMessage());
        throw $e;
    }
}

function mpesaFailDonation(PDO $pdo, int $donationId): void
{
    $pdo->prepare("
        UPDATE donations
        SET status = 'failed', updated_at = NOW()
        WHERE id = ? AND status = 'pending'
    ")->execute([$donationId]);
}

function mpesaProcessCallback(PDO $pdo, string $rawBody): void
{
    mpesaLog($rawBody);

    $data = json_decode($rawBody);
    if (!$data || !isset($data->Body->stkCallback)) {
        mpesaLog('Invalid callback payload');
        return;
    }

    $callback = $data->Body->stkCallback;
    $checkoutRequestId = $callback->CheckoutRequestID ?? '';
    $resultCode = (int) ($callback->ResultCode ?? -1);
    $resultDesc = $callback->ResultDesc ?? 'Unknown';

    if ($checkoutRequestId === '') {
        mpesaLog('Missing CheckoutRequestID');
        return;
    }

    $donation = mpesaGetDonationByCheckoutId($pdo, $checkoutRequestId);
    if (!$donation) {
        mpesaLog('No donation found for CheckoutRequestID: ' . $checkoutRequestId);
        return;
    }

    if ($resultCode === 0) {
        $meta = mpesaParseCallbackMetadata($callback->CallbackMetadata->Item ?? null);
        $paidAmount = isset($meta['Amount']) ? (float) $meta['Amount'] : (float) $donation['amount'];

        if (abs($paidAmount - (float) $donation['amount']) > 0.01) {
            mpesaLog("Amount mismatch for donation {$donation['id']}: expected {$donation['amount']}, got {$paidAmount}");
        }

        mpesaCompleteDonation($pdo, $donation);
        mpesaLog("Donation {$donation['id']} completed. Receipt: " . ($meta['MpesaReceiptNumber'] ?? 'n/a'));
        return;
    }

    mpesaFailDonation($pdo, (int) $donation['id']);
    mpesaLog("Donation {$donation['id']} failed: [{$resultCode}] {$resultDesc}");
}

function mpesaQueryStatusMessage(int|string|null $resultCode): string
{
    $code = (string) $resultCode;
    return match ($code) {
        '0'    => 'Payment completed successfully.',
        '1032' => 'Payment cancelled on phone.',
        '1037' => 'Payment timed out. Please try again.',
        '1'    => 'Insufficient M-PESA balance.',
        default => 'Payment is still processing or could not be confirmed.',
    };
}
