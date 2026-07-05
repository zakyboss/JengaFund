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

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: campaign_approvals.php');
    exit();
}

/** @var PDO $pdo */
$pdo = require_once '../../config/database.php';
require_once '../../backend/milestone_helper.php';

$base_url = str_replace(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '', str_replace('\\', '/', dirname(__DIR__, 2)));

try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name AS student_name, u.email AS student_email, u.phone_number AS student_phone,
               sp.mpesa_number
        FROM campaigns c
        JOIN users u ON c.student_id = u.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Campaign view fetch error: ' . $e->getMessage());
    $campaign = false;
}

if (!$campaign) {
    $_SESSION['error_message'] = 'Campaign not found.';
    header('Location: campaign_approvals.php');
    exit();
}

$stmtMs = $pdo->prepare('SELECT * FROM milestones WHERE campaign_id = ? ORDER BY sequence_order ASC');
$stmtMs->execute([$id]);
$milestones = $stmtMs->fetchAll(PDO::FETCH_ASSOC);

$stmtDon = $pdo->prepare("
    SELECT d.*, u.full_name AS donor_name, u.email AS donor_email, u.phone_number AS donor_phone
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    WHERE d.campaign_id = ?
    ORDER BY d.created_at DESC
");
$stmtDon->execute([$id]);
$donations = $stmtDon->fetchAll(PDO::FETCH_ASSOC);

$goal = (float) $campaign['goal_amount'];
$raised = (float) $campaign['funds_raised'];
$goalMet = $goal > 0 && $raised >= $goal;
$isFullPayout = $campaign['disbursement_type'] === 'full';

if ($goalMet && in_array($campaign['status'], ['active', 'approved'], true)) {
    $pdo->prepare("UPDATE campaigns SET status = 'awaiting_disbursement' WHERE id = ?")
        ->execute([$id]);
    $campaign['status'] = 'awaiting_disbursement';
}

$progress = $goal > 0 ? min(100, round(($raised / $goal) * 100, 1)) : 0;

$totalOwed = 0;
$totalPaid = 0;
foreach ($milestones as &$ms) {
    $ms['owed_amount'] = round($raised * ((float) $ms['disbursement_percent'] / 100), 2);
    if ($ms['status'] === 'disbursed') {
        $totalPaid += (float) ($ms['disbursed_amount'] ?? $ms['owed_amount']);
    } elseif (in_array($ms['status'], ['approved', 'evidence_submitted'], true)) {
        $totalOwed += $ms['owed_amount'];
    } elseif ($ms['status'] === 'pending' && $isFullPayout && $goalMet) {
        $totalOwed += $ms['owed_amount'];
    }
}
unset($ms);

$paymentConfirmations = loadPaymentConfirmationsForMilestones($pdo, $milestones);

$successfulDonations = array_filter($donations, fn($d) => $d['status'] === 'success');
$donationTotal = array_sum(array_map(fn($d) => (float) $d['amount'], $successfulDonations));

$firstActiveId = null;
foreach ($milestones as $m) {
    if ($m['status'] !== 'disbursed') {
        $firstActiveId = (int) $m['id'];
        break;
    }
}

function donationStatusBadge(string $status): array
{
    return match ($status) {
        'success' => ['jf-badge-approved', 'Successful'],
        'failed' => ['jf-badge-rejected', 'Failed'],
        'cancelled' => ['jf-badge-rejected', 'Cancelled'],
        default => ['jf-badge-pending', 'Pending'],
    };
}

$campaignBadge = match ($campaign['status']) {
    'active' => 'jf-badge-active',
    'approved' => 'jf-badge-approved',
    'awaiting_disbursement' => 'jf-badge-pending',
    'rejected', 'cancelled' => 'jf-badge-rejected',
    'pending_approval' => 'jf-badge-pending',
    'completed' => 'jf-badge-approved',
    default => 'jf-badge-neutral',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php require_once __DIR__ . '/../../components/favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($campaign['title']) ?> — Campaign</title>
    <link rel="stylesheet" href="../../assets/css/app.css">
</head>
<body class="jf-app">
<?php
require_once '../../components/sidebar.php';
require_once '../../components/notification.php';
?>

<div class="jf-page jf-page-wide">
    <a href="campaign_approvals.php" class="jf-back"><i class="fa-solid fa-arrow-left"></i> Back to Approvals</a>

    <div class="jf-page-header-row jf-page-header">
        <div>
            <h1><?= htmlspecialchars($campaign['title']) ?></h1>
            <p><?= htmlspecialchars($campaign['category']) ?> · <?= ucfirst(str_replace('_', ' ', $campaign['status'])) ?>
                · <?= ucfirst($campaign['disbursement_type']) ?> payout</p>
        </div>
        <span class="jf-badge <?= $campaignBadge ?>"><?= ucfirst(str_replace('_', ' ', $campaign['status'])) ?></span>
    </div>

    <div class="jf-campaign-grid">
        <!-- Funding overview -->
        <section class="jf-panel jf-campaign-main">
            <h2>Funding Progress</h2>
            <div class="jf-funding-stats">
                <div>
                    <span class="jf-funding-label">Raised</span>
                    <strong class="jf-funding-value">KES <?= number_format($raised, 2) ?></strong>
                </div>
                <div>
                    <span class="jf-funding-label">Goal</span>
                    <strong class="jf-funding-value">KES <?= number_format($goal, 2) ?></strong>
                </div>
                <div>
                    <span class="jf-funding-label">Progress</span>
                    <strong class="jf-funding-value"><?= $progress ?>%</strong>
                </div>
            </div>
            <div class="jf-progress-wrap">
                <div class="jf-progress-bar" style="width: <?= $progress ?>%;"></div>
            </div>
            <p class="jf-hint" style="margin-top: 12px;">
                <?= count($successfulDonations) ?> successful donation(s) totalling KES <?= number_format($donationTotal, 2) ?>
                <?php if ($goalMet): ?>
                    · <strong style="color: var(--jf-success);">Funding goal reached</strong>
                <?php endif; ?>
            </p>

            <hr class="jf-divider">

            <h2>Campaign Details</h2>
            <div class="jf-meta" style="margin-bottom: 16px;">
                <div><strong>Student:</strong> <?= htmlspecialchars($campaign['student_name']) ?> (<?= htmlspecialchars($campaign['student_email']) ?>)</div>
                <div><strong>Phone:</strong> <?= htmlspecialchars($campaign['student_phone'] ?? 'N/A') ?></div>
                <div><strong>M-Pesa (disbursement):</strong> <?= htmlspecialchars($campaign['mpesa_number'] ?? 'Not provided') ?></div>
                <div><strong>Duration:</strong> <?= date('M j, Y g:i A', strtotime($campaign['starts_at'])) ?> → <?= date('M j, Y g:i A', strtotime($campaign['ends_at'])) ?></div>
                <?php if ($campaign['reviewed_at']): ?>
                    <div><strong>Reviewed:</strong> <?= date('M j, Y g:i A', strtotime($campaign['reviewed_at'])) ?></div>
                <?php endif; ?>
                <?php if ($campaign['rejection_reason']): ?>
                    <div style="color: var(--jf-danger);"><strong>Rejection reason:</strong> <?= htmlspecialchars($campaign['rejection_reason']) ?></div>
                <?php endif; ?>
            </div>
            <div class="jf-description"><?= htmlspecialchars($campaign['description']) ?></div>
        </section>

        <!-- Admin actions sidebar -->
        <aside class="jf-panel jf-campaign-side">
            <h2>Admin Controls</h2>
            <div class="jf-side-stat">
                <span>Total disbursed</span>
                <strong>KES <?= number_format($totalPaid, 2) ?></strong>
            </div>
            <div class="jf-side-stat">
                <span>Outstanding owed</span>
                <strong>KES <?= number_format($totalOwed, 2) ?></strong>
            </div>

            <?php if ($campaign['status'] === 'cancelled'): ?>
                <form method="POST" action="campaign_admin_actions.php" class="jf-side-form">
                    <input type="hidden" name="campaign_id" value="<?= $id ?>">
                    <button type="submit" name="action" value="reactivate" class="jf-btn jf-btn-approve" style="width:100%;">Reactivate Campaign</button>
                </form>
            <?php elseif ($campaign['status'] !== 'rejected'): ?>
                <form method="POST" action="campaign_admin_actions.php" class="jf-side-form" onsubmit="return confirm('Ban this campaign? It will be deactivated immediately.');">
                    <input type="hidden" name="campaign_id" value="<?= $id ?>">
                    <button type="submit" name="action" value="ban" class="jf-btn jf-btn-reject" style="width:100%;">
                        <i class="fa-solid fa-ban"></i> Ban / Deactivate
                    </button>
                </form>
            <?php endif; ?>
        </aside>
    </div>

    <!-- Milestones -->
    <?php if (!empty($milestones)): ?>
    <section class="jf-panel" style="margin-top: 24px;">
        <h2>Milestones & Disbursement</h2>
        <p class="jf-hint" style="margin: -8px 0 20px;">
            <?= $campaign['disbursement_type'] === 'full' ? 'Single payout campaign — 100% on completion.' : 'Funds released in stages as milestones are verified and paid.' ?>
        </p>

        <div class="jf-milestone-list">
            <?php foreach ($milestones as $ms):
                [$badgeClass, $badgeLabel] = milestoneStatusBadge($ms['status']);
                $isDone = $ms['status'] === 'disbursed';
                $isCurrent = ((int) $ms['id'] === $firstActiveId);
            ?>
            <article class="jf-milestone-item <?= $isDone ? 'is-done' : ($isCurrent ? 'is-active' : '') ?>">
                <div class="jf-milestone-head">
                    <div class="jf-milestone-step"><?= (int) $ms['sequence_order'] ?></div>
                    <div class="jf-milestone-info">
                        <h3><?= htmlspecialchars($ms['title']) ?></h3>
                        <p><?= htmlspecialchars($ms['description']) ?></p>
                        <div class="jf-milestone-meta">
                            <span class="jf-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                            <span><?= number_format((float) $ms['disbursement_percent'], 0) ?>% · <strong>KES <?= number_format($ms['owed_amount'], 2) ?></strong> owed</span>
                        </div>
                    </div>
                </div>

                <?php if ($ms['evidence_submitted_at'] || $ms['evidence_file_path'] || $ms['evidence_notes']): ?>
                <div class="jf-evidence-box">
                    <h4>Evidence submitted <?= $ms['evidence_submitted_at'] ? date('M j, Y g:i A', strtotime($ms['evidence_submitted_at'])) : '' ?></h4>
                    <?php if ($ms['evidence_notes']): ?>
                        <p><?= nl2br(htmlspecialchars($ms['evidence_notes'])) ?></p>
                    <?php endif; ?>
                    <?php if ($ms['evidence_file_path']): ?>
                        <a href="<?= $base_url . '/' . htmlspecialchars($ms['evidence_file_path']) ?>" target="_blank" class="jf-btn jf-btn-outline" style="display:inline-flex;margin-top:8px;">
                            <i class="fa-solid fa-file-arrow-down"></i> View evidence file
                        </a>
                    <?php endif; ?>
                    <?php if ($ms['evaluation_notes']): ?>
                        <p class="jf-hint" style="margin-top:8px;"><strong>Admin notes:</strong> <?= htmlspecialchars($ms['evaluation_notes']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($ms['disbursed_at']): ?>
                <div class="jf-paid-box">
                    <i class="fa-solid fa-circle-check"></i>
                    Paid KES <?= number_format((float) ($ms['disbursed_amount'] ?? $ms['owed_amount']), 2) ?>
                    on <?= date('M j, Y g:i A', strtotime($ms['disbursed_at'])) ?>
                </div>
                <?php
                $studentConfirmedAt = $paymentConfirmations[(int) $ms['id']] ?? null;
                if ($studentConfirmedAt): ?>
                <div class="jf-confirm-box is-done">
                    <i class="fa-solid fa-shield-check"></i>
                    Student confirmed receipt on <?= date('M j, Y g:i A', strtotime($studentConfirmedAt)) ?>
                </div>
                <?php else: ?>
                <div class="jf-hint" style="margin-top:12px;">
                    <i class="fa-regular fa-clock"></i> Awaiting student payment confirmation
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if (!in_array($campaign['status'], ['rejected', 'cancelled', 'pending_approval'], true)): ?>
                <div class="jf-milestone-actions">
                    <?php if ($ms['status'] === 'evidence_submitted'): ?>
                        <form method="POST" action="campaign_admin_actions.php" style="display:inline;">
                            <input type="hidden" name="campaign_id" value="<?= $id ?>">
                            <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                            <button type="submit" name="action" value="approve_evidence" class="jf-btn jf-btn-approve">Approve Evidence</button>
                        </form>
                        <form method="POST" action="campaign_admin_actions.php" style="display:inline-flex;gap:8px;align-items:center;">
                            <input type="hidden" name="campaign_id" value="<?= $id ?>">
                            <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                            <input type="text" name="evaluation_notes" class="jf-input" style="max-width:220px;padding:8px 12px;" placeholder="Rejection reason">
                            <button type="submit" name="action" value="reject_evidence" class="jf-btn jf-btn-reject">Reject</button>
                        </form>
                    <?php elseif ($ms['status'] === 'approved'): ?>
                        <form method="POST" action="campaign_admin_actions.php" style="display:inline;" onsubmit="return confirm('Confirm KES <?= number_format($ms['owed_amount'], 2) ?> has been sent via M-Pesa?');">
                            <input type="hidden" name="campaign_id" value="<?= $id ?>">
                            <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                            <button type="submit" name="action" value="mark_paid" class="jf-btn jf-btn-approve"><i class="fa-solid fa-money-bill-transfer"></i> Mark as Paid</button>
                        </form>
                    <?php elseif ($ms['status'] === 'disbursed'): ?>
                        <a href="disbursement_record.php?milestone_id=<?= (int) $ms['id'] ?>" class="jf-btn jf-btn-outline" target="_blank" rel="noopener">
                            <i class="fa-solid fa-file-lines"></i> Disbursement record
                        </a>
                        <form method="POST" action="campaign_admin_actions.php" style="display:inline;" onsubmit="return confirm('Revert this milestone to unpaid?');">
                            <input type="hidden" name="campaign_id" value="<?= $id ?>">
                            <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                            <button type="submit" name="action" value="mark_unpaid" class="jf-btn jf-btn-outline">Mark as Unpaid</button>
                        </form>
                    <?php elseif ($ms['status'] === 'pending'): ?>
                        <?php if ($isFullPayout && $goalMet): ?>
                            <span class="jf-hint" style="margin-right: 12px;">
                                <i class="fa-solid fa-circle-check" style="color: var(--jf-success);"></i>
                                Goal reached — ready to disburse (no evidence required for full payout)
                            </span>
                            <form method="POST" action="campaign_admin_actions.php" style="display:inline;" onsubmit="return confirm('Confirm KES <?= number_format($ms['owed_amount'], 2) ?> has been sent via M-Pesa?');">
                                <input type="hidden" name="campaign_id" value="<?= $id ?>">
                                <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                                <button type="submit" name="action" value="mark_paid" class="jf-btn jf-btn-approve"><i class="fa-solid fa-money-bill-transfer"></i> Mark as Paid</button>
                            </form>
                        <?php else: ?>
                            <span class="jf-hint"><i class="fa-regular fa-clock"></i> Waiting for student to submit evidence</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Donations -->
    <section class="jf-panel" style="margin-top: 24px;">
        <h2>Donor Transactions</h2>
        <?php if (empty($donations)): ?>
            <div class="jf-empty" style="box-shadow:none;border:none;padding:32px;">
                <i class="fa-solid fa-hand-holding-dollar" style="color:var(--jf-text-light);"></i>
                <p>No donations recorded yet.</p>
            </div>
        <?php else: ?>
            <div class="jf-table-wrap" style="border:none;box-shadow:none;">
                <table class="jf-table">
                    <thead>
                        <tr>
                            <th>Donor</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Transaction ID</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donations as $don):
                            [$dBadge, $dLabel] = donationStatusBadge($don['status']);
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($don['donor_name']) ?></strong>
                                <span class="jf-sub"><?= htmlspecialchars($don['donor_email']) ?></span>
                                <?php if ($don['donor_phone']): ?>
                                    <span class="jf-sub"><?= htmlspecialchars($don['donor_phone']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><strong>KES <?= number_format((float) $don['amount'], 2) ?></strong></td>
                            <td><span class="jf-badge <?= $dBadge ?>"><?= $dLabel ?></span></td>
                            <td class="jf-sub"><?= htmlspecialchars($don['checkout_request_id'] ?? '—') ?></td>
                            <td class="jf-sub"><?= date('M j, Y g:i A', strtotime($don['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
