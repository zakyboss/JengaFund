<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = 'Access denied. Admin login required.';
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

$milestoneId = (int) ($_GET['milestone_id'] ?? 0);
if ($milestoneId <= 0) {
    $_SESSION['error_message'] = 'Invalid disbursement record.';
    header('Location: disbursement_records.php');
    exit();
}

/** @var PDO $pdo */
$pdo = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../backend/milestone_helper.php';

$record = loadDisbursementRecord($pdo, $milestoneId);
if (!$record) {
    $_SESSION['error_message'] = 'Disbursement record not found.';
    header('Location: disbursement_records.php');
    exit();
}

$recordRef = 'JF-DISB-' . str_pad((string) $milestoneId, 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php require_once __DIR__ . '/../../components/favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disbursement Record <?= htmlspecialchars($recordRef) ?> - JengaFund</title>
    <link rel="stylesheet" href="../../assets/css/app.css">
    <style>
        .jf-record-sheet {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid var(--jf-border-soft, #e0e0e0);
            border-radius: var(--jf-radius, 12px);
            padding: 32px 36px;
        }
        .jf-record-title {
            text-align: center;
            margin-bottom: 28px;
            padding-bottom: 16px;
            border-bottom: 2px solid #1f3864;
        }
        .jf-record-title h1 {
            font-size: 1.35rem;
            margin: 0 0 6px;
            color: #1f3864;
        }
        .jf-record-title p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--jf-text-muted, #666);
        }
        .jf-record-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
        }
        .jf-record-table th,
        .jf-record-table td {
            border: 1px solid #d0d0d0;
            padding: 10px 14px;
            text-align: left;
            vertical-align: top;
        }
        .jf-record-table th {
            width: 34%;
            background: #f4f6f9;
            font-weight: 600;
            color: #1f3864;
        }
        .jf-record-footer {
            margin-top: 28px;
            padding-top: 16px;
            border-top: 1px solid #e0e0e0;
            font-size: 0.82rem;
            color: #666;
        }
        @media print {
            .no-print { display: none !important; }
            body.jf-app { background: #fff; }
            .jf-page { max-width: none; padding: 0; }
            .jf-record-sheet {
                border: none;
                border-radius: 0;
                padding: 0;
                max-width: none;
            }
        }
    </style>
</head>
<body class="jf-app">
<?php
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/notification.php';
?>

<div class="jf-page">
    <div class="jf-page-header-row no-print" style="margin-bottom: 20px;">
        <a href="disbursement_records.php" class="jf-back"><i class="fa-solid fa-arrow-left"></i> All records</a>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-left:auto;">
            <button type="button" class="jf-btn jf-btn-brand" onclick="window.print()">
                <i class="fa-solid fa-file-pdf"></i> Print / Save as PDF
            </button>
            <a href="campaign_view.php?id=<?= (int) $record['campaign_id'] ?>" class="jf-btn jf-btn-outline">
                <i class="fa-solid fa-eye"></i> Campaign
            </a>
        </div>
    </div>

    <div class="jf-record-sheet">
        <div class="jf-record-title">
            <h1>Escrow Disbursement Record</h1>
            <p>Reference: <strong><?= htmlspecialchars($recordRef) ?></strong> · Issued <?= date('M j, Y g:i A') ?></p>
        </div>

        <table class="jf-record-table">
            <tr>
                <th>Campaign</th>
                <td><?= htmlspecialchars($record['campaign_title']) ?> (ID #<?= (int) $record['campaign_id'] ?>)</td>
            </tr>
            <tr>
                <th>Student</th>
                <td>
                    <?= htmlspecialchars($record['student_name']) ?><br>
                    <?= htmlspecialchars($record['student_email']) ?>
                </td>
            </tr>
            <tr>
                <th>M-PESA (disbursement)</th>
                <td><?= htmlspecialchars($record['mpesa_number'] ?? 'Not provided') ?></td>
            </tr>
            <tr>
                <th>Milestone</th>
                <td><?= htmlspecialchars($record['milestone_title']) ?></td>
            </tr>
            <tr>
                <th>Amount disbursed</th>
                <td><strong>KES <?= number_format((float) $record['disbursed_amount'], 2) ?></strong></td>
            </tr>
            <tr>
                <th>Date paid</th>
                <td><?= date('l, M j, Y \a\t g:i A', strtotime($record['disbursed_at'])) ?></td>
            </tr>
            <tr>
                <th>Marked as paid by</th>
                <td><?= htmlspecialchars($record['admin_name'] ?? 'Administrator') ?></td>
            </tr>
            <tr>
                <th>Student confirmation</th>
                <td>
                    <?php if (!empty($record['student_confirmed'])): ?>
                        <strong style="color:var(--jf-success,#0ca678);">Confirmed — Yes</strong><br>
                        Acknowledged on <?= date('M j, Y g:i A', strtotime($record['student_confirmed_at'])) ?>
                    <?php else: ?>
                        <strong style="color:var(--jf-warning,#f59f00);">Not yet confirmed</strong><br>
                        Awaiting student acknowledgment on M-PESA receipt.
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <div class="jf-record-footer">
            This record documents admin release of escrowed campaign funds after milestone conditions were met.
            Outbound payment is sent manually via M-PESA to the student number above; this form is the platform audit trail.
        </div>
    </div>
</div>
</body>
</html>
