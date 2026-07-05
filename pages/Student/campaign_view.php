<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
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
    header('Location: dashboard.php');
    exit();
}

/** @var PDO $pdo */
$pdo = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../backend/milestone_helper.php';

$studentId = (int) $_SESSION['user_id'];
$base_url = str_replace(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '', str_replace('\\', '/', dirname(__DIR__, 2)));

$stmt = $pdo->prepare('SELECT * FROM campaigns WHERE id = ? AND student_id = ?');
$stmt->execute([$id, $studentId]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    $_SESSION['error_message'] = 'Campaign not found.';
    header('Location: dashboard.php');
    exit();
}

$stmtMs = $pdo->prepare('SELECT * FROM milestones WHERE campaign_id = ? ORDER BY sequence_order ASC');
$stmtMs->execute([$id]);
$milestones = $stmtMs->fetchAll(PDO::FETCH_ASSOC);

$goal = (float) $campaign['goal_amount'];
$raised = (float) $campaign['funds_raised'];
$goalMet = $goal > 0 && $raised >= $goal;
$progress = $goal > 0 ? min(100, round(($raised / $goal) * 100, 1)) : 0;
$canEdit = canStudentEditCampaign($campaign);
$isMilestoneCampaign = $campaign['disbursement_type'] === 'milestone';
$paymentConfirmations = loadPaymentConfirmationsForMilestones($pdo, $milestones, $studentId);

require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/notification.php';

$campaignBadge = match ($campaign['status']) {
    'active' => 'jf-badge-active',
    'approved' => 'jf-badge-approved',
    'awaiting_disbursement' => 'jf-badge-pending',
    'completed' => 'jf-badge-approved',
    'rejected', 'cancelled' => 'jf-badge-rejected',
    'pending_approval' => 'jf-badge-pending',
    default => 'jf-badge-neutral',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php require_once __DIR__ . '/../../components/favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($campaign['title']) ?> — My Campaign</title>
    <link rel="stylesheet" href="../../assets/css/app.css">
    <style>
        .jf-milestone-item.is-locked { opacity: 0.65; background: #fafafa; }
        .jf-lock-msg {
            margin-top: 12px;
            padding: 10px 14px;
            background: #fff8e6;
            border: 1px solid #f0e0a0;
            border-radius: 8px;
            font-size: 0.88rem;
            color: #8a6d00;
        }
        .jf-evidence-form {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px dashed var(--jf-border);
        }
        .jf-confirm-box {
            margin-top: 12px;
            padding: 14px 16px;
            border-radius: var(--jf-radius-sm);
            border: 1px solid var(--jf-border);
            background: #fff;
        }
        .jf-confirm-box.is-done {
            background: var(--jf-success-bg);
            border-color: #b2f2bb;
            color: var(--jf-success);
            font-size: 0.88rem;
            font-weight: 600;
        }
        .jf-confirm-box.is-pending p {
            margin: 0 0 12px;
            font-size: 0.88rem;
            color: var(--jf-text-muted);
        }
    </style>
</head>
<body class="jf-app">
<div class="jf-page jf-page-wide">
    <a href="campaigns.php" class="jf-back"><i class="fa-solid fa-arrow-left"></i> Back to My Campaigns</a>

    <div class="jf-page-header-row jf-page-header">
        <div>
            <h1><?= htmlspecialchars($campaign['title']) ?></h1>
            <p><?= htmlspecialchars($campaign['category']) ?> · <?= ucfirst(str_replace('_', ' ', $campaign['status'])) ?>
                · <?= $isMilestoneCampaign ? 'Milestone payout' : 'Full payout' ?></p>
        </div>
        <span class="jf-badge <?= $campaignBadge ?>"><?= ucfirst(str_replace('_', ' ', $campaign['status'])) ?></span>
    </div>

    <div class="jf-campaign-grid">
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
                <div class="jf-progress-bar" style="width: <?= $progress ?>%;<?= $goalMet ? 'background:var(--jf-success);' : '' ?>"></div>
            </div>
            <?php if ($goalMet): ?>
                <p class="jf-hint" style="margin-top:12px;color:var(--jf-success);font-weight:600;">
                    <i class="fa-solid fa-bullseye"></i> Funding goal reached
                </p>
            <?php endif; ?>

            <hr class="jf-divider">

            <?php if ($canEdit): ?>
            <h2>Edit Campaign Details</h2>
            <p class="jf-hint" style="margin:-8px 0 16px;">Update your story while the campaign is still pending or live.</p>
            <form action="../../backend/student_campaign_actions.php" method="POST" class="jf-form-group">
                <input type="hidden" name="action" value="update_details">
                <input type="hidden" name="campaign_id" value="<?= $id ?>">
                <div class="jf-form-group">
                    <label for="title">Project Title</label>
                    <input type="text" id="title" name="title" required value="<?= htmlspecialchars($campaign['title']) ?>">
                </div>
                <div class="jf-form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" required>
                        <?php
                        $categories = ['Technology', 'Agriculture', 'Health', 'Education', 'Social Impact', 'Membership Fees'];
                        foreach ($categories as $cat):
                        ?>
                            <option value="<?= $cat ?>" <?= $campaign['category'] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="jf-form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="5" required><?= htmlspecialchars($campaign['description']) ?></textarea>
                </div>
                <button type="submit" class="jf-btn jf-btn-brand"><i class="fa-solid fa-save"></i> Save Changes</button>
            </form>
            <?php else: ?>
            <h2>Campaign Description</h2>
            <div class="jf-description"><?= nl2br(htmlspecialchars($campaign['description'])) ?></div>
            <p class="jf-hint" style="margin-top:12px;">This campaign can no longer be edited.</p>
            <?php endif; ?>
        </section>

        <aside class="jf-panel jf-campaign-side">
            <h2>Timeline</h2>
            <div class="jf-meta">
                <div><strong>Starts:</strong> <?= date('M j, Y g:i A', strtotime($campaign['starts_at'])) ?></div>
                <div><strong>Ends:</strong> <?= date('M j, Y g:i A', strtotime($campaign['ends_at'])) ?></div>
                <div><strong>Created:</strong> <?= date('M j, Y', strtotime($campaign['created_at'])) ?></div>
            </div>
            <?php if (!$isMilestoneCampaign && $goalMet): ?>
                <p class="jf-hint" style="margin-top:16px;">
                    <i class="fa-solid fa-circle-info"></i> Full payout — admin will disburse once the campaign is complete. No evidence upload needed.
                </p>
            <?php endif; ?>
        </aside>
    </div>

    <?php if ($isMilestoneCampaign && !empty($milestones)): ?>
    <section class="jf-panel" style="margin-top: 24px;">
        <h2>Milestones</h2>
        <p class="jf-hint" style="margin: -8px 0 20px;">
            Work through milestones one at a time. Submit photos or documents for the current step — the next milestone unlocks after the previous one is paid out.
        </p>

        <div class="jf-milestone-list">
            <?php foreach ($milestones as $index => $ms):
                $unlocked = isMilestoneUnlocked($campaign, $milestones, $index);
                $lockMsg = milestoneUnlockMessage($campaign, $milestones, $index);
                [$badgeClass, $badgeLabel] = milestoneStatusBadge($ms['status']);
                $isDone = $ms['status'] === 'disbursed';
                $canSubmit = canStudentSubmitEvidence($campaign, $ms, $unlocked);
                $itemClass = $isDone ? 'is-done' : ($unlocked && in_array($ms['status'], ['pending', 'rejected', 'evidence_submitted'], true) ? 'is-active' : 'is-locked');
                $owed = round($raised * ((float) $ms['disbursement_percent'] / 100), 2);
            ?>
            <article class="jf-milestone-item <?= $itemClass ?>">
                <div class="jf-milestone-head">
                    <div class="jf-milestone-step"><?= (int) $ms['sequence_order'] ?></div>
                    <div class="jf-milestone-info">
                        <h3><?= htmlspecialchars($ms['title']) ?></h3>
                        <p><?= htmlspecialchars($ms['description']) ?></p>
                        <div class="jf-milestone-meta">
                            <span class="jf-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                            <span><?= number_format((float) $ms['disbursement_percent'], 0) ?>% · up to <strong>KES <?= number_format($owed, 2) ?></strong></span>
                            <?php if (!$unlocked): ?>
                                <span class="jf-badge jf-badge-neutral"><i class="fa-solid fa-lock"></i> Locked</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!$unlocked && $lockMsg !== ''): ?>
                    <div class="jf-lock-msg"><i class="fa-solid fa-lock"></i> <?= htmlspecialchars($lockMsg) ?></div>
                <?php endif; ?>

                <?php if (milestoneShowsPublicEvidence($ms)): ?>
                <div class="jf-evidence-box">
                    <h4>Your submitted evidence <?= $ms['evidence_submitted_at'] ? '· ' . date('M j, Y g:i A', strtotime($ms['evidence_submitted_at'])) : '' ?></h4>
                    <?php if ($ms['evidence_notes']): ?>
                        <p><?= nl2br(htmlspecialchars($ms['evidence_notes'])) ?></p>
                    <?php endif; ?>
                    <?php if ($ms['evidence_file_path']): ?>
                        <a href="<?= $base_url . '/' . htmlspecialchars($ms['evidence_file_path']) ?>" target="_blank" class="jf-btn jf-btn-outline" style="display:inline-flex;margin-top:8px;">
                            <i class="fa-solid fa-file-arrow-down"></i> View file
                        </a>
                    <?php endif; ?>
                    <?php if ($ms['status'] === 'rejected' && $ms['evaluation_notes']): ?>
                        <p class="jf-hint" style="margin-top:10px;color:var(--jf-danger);"><strong>Admin feedback:</strong> <?= htmlspecialchars($ms['evaluation_notes']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($ms['disbursed_at']): ?>
                <div class="jf-paid-box">
                    <i class="fa-solid fa-circle-check"></i>
                    Paid KES <?= number_format((float) ($ms['disbursed_amount'] ?? $owed), 2) ?>
                    on <?= date('M j, Y g:i A', strtotime($ms['disbursed_at'])) ?>
                </div>
                <?php
                $confirmedAt = $paymentConfirmations[(int) $ms['id']] ?? null;
                if ($confirmedAt): ?>
                <div class="jf-confirm-box is-done">
                    <i class="fa-solid fa-shield-check"></i>
                    You confirmed receipt on <?= date('M j, Y g:i A', strtotime($confirmedAt)) ?>
                </div>
                <?php elseif (canStudentConfirmPayment($ms, null)): ?>
                <div class="jf-confirm-box is-pending">
                    <p>Admin marked this as paid. Confirm once the money is on your M-PESA.</p>
                    <form method="POST" action="../../backend/student_campaign_actions.php" onsubmit="return confirm('Confirm that you received KES <?= number_format((float) ($ms['disbursed_amount'] ?? $owed), 2) ?> on M-PESA?');">
                        <input type="hidden" name="action" value="confirm_payment">
                        <input type="hidden" name="campaign_id" value="<?= $id ?>">
                        <input type="hidden" name="milestone_id" value="<?= (int) $ms['id'] ?>">
                        <button type="submit" class="jf-btn jf-btn-brand jf-btn-sm">
                            <i class="fa-solid fa-handshake"></i> Yes, I received this payment
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($canSubmit): ?>
                <form class="jf-evidence-form" action="../../backend/student_campaign_actions.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="submit_evidence">
                    <input type="hidden" name="campaign_id" value="<?= $id ?>">
                    <input type="hidden" name="milestone_id" value="<?= (int) $ms['id'] ?>">
                    <div class="jf-form-group">
                        <label>Notes (what you completed)</label>
                        <textarea name="evidence_notes" rows="3" placeholder="Describe the progress shown in your photos…"></textarea>
                    </div>
                    <div class="jf-form-group">
                        <label>Photo or PDF evidence *</label>
                        <input type="file" name="evidence_file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" required>
                        <p class="jf-hint">Max 5 MB. JPG, PNG, or PDF.</p>
                    </div>
                    <button type="submit" class="jf-btn jf-btn-brand">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Submit Evidence
                    </button>
                </form>
                <?php elseif ($unlocked && $ms['status'] === 'evidence_submitted'): ?>
                    <p class="jf-hint" style="margin-top:12px;"><i class="fa-regular fa-hourglass-half"></i> Evidence is with admin for review.</p>
                <?php elseif ($unlocked && $ms['status'] === 'approved'): ?>
                    <p class="jf-hint" style="margin-top:12px;"><i class="fa-solid fa-check"></i> Approved — awaiting M-Pesa disbursement from admin.</p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php elseif (!$isMilestoneCampaign && !empty($milestones)): ?>
    <section class="jf-panel" style="margin-top: 24px;">
        <h2>Payout</h2>
        <?php $ms = $milestones[0]; [$badgeClass, $badgeLabel] = milestoneStatusBadge($ms['status']); ?>
        <p class="jf-hint">Single full payout when the campaign completes — no milestone evidence required from you.</p>
        <div class="jf-milestone-meta" style="margin-top:12px;">
            <span class="jf-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
            <?php if ($ms['disbursed_at']): ?>
                <span>Paid KES <?= number_format((float) ($ms['disbursed_amount'] ?? $raised), 2) ?> on <?= date('M j, Y', strtotime($ms['disbursed_at'])) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($ms['disbursed_at']): ?>
            <?php
            $confirmedAt = $paymentConfirmations[(int) $ms['id']] ?? null;
            if ($confirmedAt): ?>
            <div class="jf-confirm-box is-done" style="margin-top:16px;">
                <i class="fa-solid fa-shield-check"></i>
                You confirmed receipt on <?= date('M j, Y g:i A', strtotime($confirmedAt)) ?>
            </div>
            <?php elseif (canStudentConfirmPayment($ms, null)): ?>
            <div class="jf-confirm-box is-pending" style="margin-top:16px;">
                <p>Admin marked your full payout as paid. Confirm once the money is on your M-PESA.</p>
                <form method="POST" action="../../backend/student_campaign_actions.php" onsubmit="return confirm('Confirm that you received KES <?= number_format((float) ($ms['disbursed_amount'] ?? $raised), 2) ?> on M-PESA?');">
                    <input type="hidden" name="action" value="confirm_payment">
                    <input type="hidden" name="campaign_id" value="<?= $id ?>">
                    <input type="hidden" name="milestone_id" value="<?= (int) $ms['id'] ?>">
                    <button type="submit" class="jf-btn jf-btn-brand">
                        <i class="fa-solid fa-handshake"></i> Yes, I received this payment
                    </button>
                </form>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
</body>
</html>
