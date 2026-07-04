<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    $_SESSION['error_message'] = 'Access denied. Donor login required.';
    header('Location: ../login.php');
    exit();
}

if (time() - $_SESSION['last_activity'] > 1800) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

/** @var PDO $pdo */
$pdo = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/notification.php';

$donorId = (int) $_SESSION['user_id'];

function donorDonationBadge(string $status): array
{
    return match ($status) {
        'success' => ['jf-badge-approved', 'Successful'],
        'failed' => ['jf-badge-rejected', 'Failed'],
        'cancelled' => ['jf-badge-rejected', 'Cancelled'],
        default => ['jf-badge-pending', 'Pending'],
    };
}

try {
    $stmtStats = $pdo->prepare("
        SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS success_count,
            COALESCE(SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END), 0) AS total_contributed
        FROM donations
        WHERE donor_id = ?
    ");
    $stmtStats->execute([$donorId]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT d.*, c.title AS campaign_title, c.category, u.full_name AS student_name
        FROM donations d
        JOIN campaigns c ON c.id = d.campaign_id
        JOIN users u ON c.student_id = u.id
        WHERE d.donor_id = ?
        ORDER BY d.created_at DESC
    ");
    $stmt->execute([$donorId]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Donor donations fetch error: ' . $e->getMessage());
    $stats = ['total_count' => 0, 'success_count' => 0, 'total_contributed' => 0];
    $donations = [];
}

$totalContributed = (float) ($stats['total_contributed'] ?? 0);
$successCount = (int) ($stats['success_count'] ?? 0);
$totalCount = (int) ($stats['total_count'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Donations - JengaFund</title>
    <link rel="stylesheet" href="../../assets/css/app.css">
    <style>
        .donor-receipt-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 8px;
        }

        .donor-receipt-card {
            background: var(--jf-surface);
            border: 1px solid var(--jf-border);
            border-radius: 16px;
            overflow: hidden;
            min-width: 0;
            box-shadow: var(--jf-shadow-sm);
            display: flex;
            flex-direction: column;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .donor-receipt-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--jf-shadow);
        }

        .donor-receipt-card.is-success {
            border-color: #b2f2bb;
        }

        .donor-receipt-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            padding: 18px 20px;
            background: linear-gradient(135deg, #fff 0%, #fafafa 100%);
            border-bottom: 1px dashed var(--jf-border);
        }

        .donor-receipt-card.is-success .donor-receipt-head {
            background: linear-gradient(135deg, #fff 0%, #e6fcf5 100%);
        }

        .donor-receipt-id {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--jf-text-light);
            margin-bottom: 4px;
        }

        .donor-receipt-head h3 {
            margin: 0;
            font-size: 1.05rem;
            line-height: 1.35;
        }

        .donor-receipt-head h3 a {
            color: var(--jf-text);
            text-decoration: none;
        }

        .donor-receipt-head h3 a:hover {
            color: var(--jf-brand);
        }

        .donor-receipt-body {
            padding: 18px 20px 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .donor-receipt-amount {
            font-size: 1.65rem;
            font-weight: 800;
            color: var(--jf-brand);
            line-height: 1;
        }

        .donor-receipt-card.is-success .donor-receipt-amount {
            color: var(--jf-success);
        }

        .donor-receipt-meta {
            display: grid;
            gap: 8px;
            font-size: 0.88rem;
            color: var(--jf-text-muted);
        }

        .donor-receipt-meta div {
            display: flex;
            gap: 8px;
            align-items: flex-start;
        }

        .donor-receipt-meta i {
            width: 16px;
            margin-top: 2px;
            color: var(--jf-text-light);
        }

        .donor-receipt-ref {
            font-size: 0.78rem;
            word-break: break-all;
            color: var(--jf-text-light);
            background: var(--jf-bg);
            padding: 10px 12px;
            border-radius: 8px;
        }

        body.dark-mode .donor-receipt-head {
            background: linear-gradient(135deg, #1e1e1e 0%, #252525 100%);
        }

        body.dark-mode .donor-receipt-card.is-success .donor-receipt-head {
            background: linear-gradient(135deg, #1e1e1e 0%, #1a2e24 100%);
        }
    </style>
</head>
<body class="jf-app">
    <div class="jf-page jf-page-wide">
        <a href="dashboard.php" class="jf-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

        <div class="jf-page-header-row jf-page-header">
            <div>
                <h1>My Donations</h1>
                <p>Your giving history, M-PESA receipts, and total impact on student projects.</p>
            </div>
            <a href="campaigns.php" class="jf-btn jf-btn-brand"><i class="fa-solid fa-heart"></i> Donate Again</a>
        </div>

        <div class="jf-stat-grid">
            <div class="jf-stat-card">
                <h3>Total Contributed</h3>
                <p>KES <?= number_format($totalContributed, 2) ?></p>
            </div>
            <div class="jf-stat-card">
                <h3>Successful Donations</h3>
                <p><?= number_format($successCount) ?></p>
            </div>
            <div class="jf-stat-card">
                <h3>All Attempts</h3>
                <p><?= number_format($totalCount) ?></p>
            </div>
        </div>

        <?php if (empty($donations)): ?>
            <div class="jf-empty">
                <i class="fa-solid fa-receipt" style="color: var(--jf-text-light);"></i>
                <p>You haven't made any donations yet.</p>
                <a href="campaigns.php" class="jf-btn jf-btn-brand" style="margin-top: 16px; display: inline-flex;">
                    <i class="fa-solid fa-compass"></i> Browse Campaigns
                </a>
            </div>
        <?php else: ?>
            <div class="donor-receipt-grid">
                <?php foreach ($donations as $don):
                    [$badgeClass, $badgeLabel] = donorDonationBadge($don['status']);
                    $isSuccess = $don['status'] === 'success';
                    $cardClass = 'donor-receipt-card' . ($isSuccess ? ' is-success' : '');
                ?>
                <article class="<?= $cardClass ?>">
                    <div class="donor-receipt-head">
                        <div>
                            <div class="donor-receipt-id">Receipt #<?= (int) $don['id'] ?></div>
                            <h3>
                                <a href="campaign_detail.php?id=<?= (int) $don['campaign_id'] ?>">
                                    <?= htmlspecialchars($don['campaign_title']) ?>
                                </a>
                            </h3>
                        </div>
                        <span class="jf-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                    </div>
                    <div class="donor-receipt-body">
                        <div class="donor-receipt-amount">KES <?= number_format((float) $don['amount'], 2) ?></div>
                        <div class="donor-receipt-meta">
                            <div>
                                <i class="fa-solid fa-user-graduate"></i>
                                <span><?= htmlspecialchars($don['student_name']) ?> · <?= htmlspecialchars($don['category']) ?></span>
                            </div>
                            <div>
                                <i class="fa-regular fa-clock"></i>
                                <span><?= date('F j, Y · g:i A', strtotime($don['created_at'])) ?></span>
                            </div>
                            <?php if ($isSuccess): ?>
                            <div>
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Thank you — your support helps students build real projects.</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($don['checkout_request_id'])): ?>
                            <div class="donor-receipt-ref">
                                <strong>M-PESA ref:</strong><br>
                                <?= htmlspecialchars($don['checkout_request_id']) ?>
                            </div>
                        <?php endif; ?>
                        <a href="campaign_detail.php?id=<?= (int) $don['campaign_id'] ?>" class="jf-btn jf-btn-outline jf-btn-block">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> View Campaign
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
