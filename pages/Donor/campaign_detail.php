<?php

session_start();



if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {

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

    header('Location: campaigns.php');

    exit();

}



/** @var PDO $pdo */

$pdo = require_once __DIR__ . '/../../config/database.php';



$stmt = $pdo->prepare("
    SELECT c.*, u.full_name AS student_name
    FROM campaigns c
    JOIN users u ON c.student_id = u.id
    WHERE c.id = ?
      AND c.status NOT IN ('draft', 'pending_approval', 'rejected', 'cancelled')
");
$stmt->execute([$id]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    $_SESSION['error_message'] = 'Campaign not found.';
    header('Location: campaigns.php');
    exit();
}

$goal = (float) $campaign['goal_amount'];
$raised = (float) $campaign['funds_raised'];
$goalMet = $goal > 0 && $raised >= $goal;

if ($goalMet && in_array($campaign['status'], ['active', 'approved'], true)) {
    $pdo->prepare("UPDATE campaigns SET status = 'awaiting_disbursement' WHERE id = ?")
        ->execute([$id]);
    $campaign['status'] = 'awaiting_disbursement';
}

$acceptsDonations = in_array($campaign['status'], ['active', 'approved'], true)
    && strtotime($campaign['ends_at']) > time()
    && !$goalMet;



$stmtPhone = $pdo->prepare('SELECT phone_number FROM users WHERE id = ?');

$stmtPhone->execute([(int) $_SESSION['user_id']]);

$donorPhone = (string) ($stmtPhone->fetchColumn() ?: '');



require_once __DIR__ . '/../../components/sidebar.php';

require_once __DIR__ . '/../../components/notification.php';

require_once __DIR__ . '/../../backend/milestone_helper.php';

$stmtMs = $pdo->prepare('SELECT * FROM milestones WHERE campaign_id = ? ORDER BY sequence_order ASC');
$stmtMs->execute([$id]);
$milestones = $stmtMs->fetchAll(PDO::FETCH_ASSOC);
$showMilestones = $campaign['disbursement_type'] === 'milestone' && !empty($milestones);

$base_url = str_replace(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '', str_replace('\\', '/', dirname(__DIR__, 2)));

$remaining = max(0, $goal - $raised);
$pct = $goal > 0 ? min(100, round(($raised / $goal) * 100, 1)) : 0;

$closedReason = '';
if ($goalMet) {
    $closedReason = 'This campaign has reached its funding goal and is closed to new donations.';
} elseif (!in_array($campaign['status'], ['active', 'approved'], true)) {
    $closedReason = 'This campaign is no longer accepting donations.';
} elseif (strtotime($campaign['ends_at']) <= time()) {
    $closedReason = 'This campaign has ended and is closed to new donations.';
}
?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($campaign['title']) ?> - JengaFund</title>

    <link rel="stylesheet" href="../../assets/css/app.css">

    <style>

        .donate-panel {

            margin-top: 24px;

            padding-top: 24px;

            border-top: 1px solid var(--jf-border, #e8e8e8);

        }

        .donate-status {

            display: none;

            margin-top: 16px;

            padding: 14px 16px;

            border-radius: 10px;

            font-size: 0.95rem;

        }

        .donate-status.show { display: block; }

        .donate-status.pending { background: #fff8e6; color: #8a6d00; border: 1px solid #f0e0a0; }

        .donate-status.success { background: #e8f8ef; color: #1b6b3a; border: 1px solid #b8e6c8; }

        .donate-status.error { background: #fdecea; color: #b42318; border: 1px solid #f5c2c0; }

        .donate-hint { font-size: 0.85rem; color: #666; margin-top: 8px; }

        .campaign-funded-banner {
            margin-top: 24px;
            padding: 18px 20px;
            border-radius: 12px;
            background: var(--jf-success-bg, #e6fcf5);
            border: 1px solid #b2f2bb;
            color: #1b6b3a;
        }
        .campaign-funded-banner h2 {
            font-size: 1.05rem;
            margin: 0 0 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .campaign-funded-banner p { margin: 0; font-size: 0.92rem; }
    </style>

</head>

<body class="jf-app">

    <div class="jf-page">

        <a href="campaigns.php" class="jf-back"><i class="fa-solid fa-arrow-left"></i> All Campaigns</a>



        <div class="jf-form-card" style="max-width:720px;">
            <?php if ($goalMet): ?>
                <span class="jf-badge jf-badge-approved" style="margin-bottom:10px;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fa-solid fa-circle-check"></i> Fully funded
                </span>
            <?php else: ?>
                <span class="jf-badge jf-badge-active"><?= htmlspecialchars($campaign['category']) ?></span>
            <?php endif; ?>

            <h1 style="font-size:1.5rem;margin:12px 0 8px;"><?= htmlspecialchars($campaign['title']) ?></h1>

            <p class="jf-hint" style="margin-bottom:20px;">By <?= htmlspecialchars($campaign['student_name']) ?></p>



            <div class="jf-funding-stats" style="grid-template-columns:1fr 1fr;margin-bottom:16px;">

                <div>

                    <span class="jf-funding-label">Raised</span>

                    <strong class="jf-funding-value" id="raisedDisplay">KES <?= number_format($raised, 2) ?></strong>

                </div>

                <div>

                    <span class="jf-funding-label">Goal</span>

                    <strong class="jf-funding-value">KES <?= number_format($goal, 2) ?></strong>

                </div>

            </div>

            <div class="jf-progress-wrap">
                <div class="jf-progress-bar" id="progressBar" style="width:<?= $pct ?>%;<?= $goalMet ? 'background:var(--jf-success,#0ca678);' : '' ?>"></div>
            </div>

            <?php if ($goalMet): ?>
                <p class="jf-hint" style="margin-top:12px;color:var(--jf-success);font-weight:600;">
                    <i class="fa-solid fa-bullseye"></i> Goal reached — KES <?= number_format($goal, 2) ?> fully raised
                </p>
            <?php endif; ?>



            <div class="jf-description" style="margin:24px 0;"><?= htmlspecialchars($campaign['description']) ?></div>

            <?php if ($showMilestones): ?>
            <section style="margin: 24px 0;">
                <h2 style="font-size:1.15rem;margin-bottom:12px;"><i class="fa-solid fa-list-check"></i> Project Milestones</h2>
                <p class="jf-hint" style="margin-bottom:16px;">Track progress updates the student shares as they hit each funding stage.</p>
                <div class="jf-milestone-list">
                    <?php foreach ($milestones as $index => $ms):
                        $unlocked = isMilestoneUnlocked($campaign, $milestones, $index);
                        [$badgeClass, $badgeLabel] = milestoneStatusBadge($ms['status']);
                        $hasEvidence = milestoneShowsPublicEvidence($ms);
                        $isDone = $ms['status'] === 'disbursed';
                    ?>
                    <article class="jf-milestone-item <?= $isDone ? 'is-done' : ($hasEvidence ? 'is-active' : '') ?>">
                        <div class="jf-milestone-head">
                            <div class="jf-milestone-step"><?= (int) $ms['sequence_order'] ?></div>
                            <div class="jf-milestone-info">
                                <h3><?= htmlspecialchars($ms['title']) ?></h3>
                                <p><?= htmlspecialchars($ms['description']) ?></p>
                                <div class="jf-milestone-meta">
                                    <span class="jf-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                                    <span><?= number_format((float) $ms['disbursement_percent'], 0) ?>% of funds</span>
                                </div>
                            </div>
                        </div>
                        <?php if ($hasEvidence): ?>
                        <div class="jf-evidence-box">
                            <h4>Progress update <?= $ms['evidence_submitted_at'] ? '· ' . date('M j, Y', strtotime($ms['evidence_submitted_at'])) : '' ?></h4>
                            <?php if ($ms['evidence_notes']): ?>
                                <p><?= nl2br(htmlspecialchars($ms['evidence_notes'])) ?></p>
                            <?php endif; ?>
                            <?php if ($ms['evidence_file_path']): ?>
                                <a href="<?= $base_url . '/' . htmlspecialchars($ms['evidence_file_path']) ?>" target="_blank" class="jf-btn jf-btn-outline" style="display:inline-flex;margin-top:8px;">
                                    <i class="fa-solid fa-image"></i> View evidence
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php elseif (!$unlocked && $index > 0): ?>
                            <p class="jf-hint" style="margin-top:12px;"><i class="fa-solid fa-lock"></i> Unlocks after the previous milestone is completed.</p>
                        <?php elseif ($unlocked && !$hasEvidence): ?>
                            <p class="jf-hint" style="margin-top:12px;">Awaiting student progress update.</p>
                        <?php endif; ?>
                        <?php if ($ms['disbursed_at']): ?>
                        <div class="jf-paid-box">
                            <i class="fa-solid fa-circle-check"></i> Milestone paid out on <?= date('M j, Y', strtotime($ms['disbursed_at'])) ?>
                        </div>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <p class="jf-hint"><i class="fa-regular fa-clock"></i> Ends <?= date('M j, Y', strtotime($campaign['ends_at'])) ?></p>

            <?php if ($acceptsDonations): ?>
            <div class="donate-panel" id="donatePanel">

                <h2 style="font-size:1.15rem;margin-bottom:16px;"><i class="fa-solid fa-mobile-screen"></i> Donate via M-PESA</h2>



                <div class="jf-form-row">

                    <div class="jf-form-group">

                        <label for="donateAmount">Amount (KES)</label>

                        <input type="number" id="donateAmount" min="1" max="<?= $remaining > 0 ? (int) ceil($remaining) : '' ?>" step="1"

                               value="<?= $remaining > 0 ? min(100, (int) ceil($remaining)) : 1 ?>" required>

                        <?php if ($remaining > 0): ?>

                            <p class="donate-hint">Up to KES <?= number_format($remaining, 2) ?> remaining toward the goal.</p>

                        <?php endif; ?>

                    </div>

                    <div class="jf-form-group">

                        <label for="donatePhone">M-PESA phone number</label>

                        <input type="tel" id="donatePhone" placeholder="0712345678"

                               value="<?= htmlspecialchars($donorPhone) ?>" required>

                        <p class="donate-hint">STK prompt is sent to this number.</p>

                    </div>

                </div>



                <button type="button" class="jf-form-submit" id="donateBtn">

                    <i class="fa-solid fa-hand-holding-heart"></i> Send STK Push

                </button>



                <div class="donate-status" id="donateStatus" role="status"></div>
            </div>
            <?php else: ?>
            <div class="campaign-funded-banner">
                <h2><i class="fa-solid fa-lock"></i> Donations closed</h2>
                <p><?= htmlspecialchars($closedReason) ?></p>
            </div>
            <?php endif; ?>

        </div>

    </div>



    <?php if ($acceptsDonations): ?>
    <script>

        const campaignId = <?= (int) $campaign['id'] ?>;

        const goalAmount = <?= json_encode($goal) ?>;

        const donateUrl = '../../backend/mpesa_donate.php';

        const statusUrl = '../../backend/mpesa_status.php';



        const donateBtn = document.getElementById('donateBtn');

        const donateStatus = document.getElementById('donateStatus');

        const donateAmount = document.getElementById('donateAmount');

        const donatePhone = document.getElementById('donatePhone');

        const raisedDisplay = document.getElementById('raisedDisplay');

        const progressBar = document.getElementById('progressBar');



        const PAYMENT_TIMEOUT_MS = 40000;
        let pollTimer = null;
        let timeoutTimer = null;

        function stopPolling() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
            if (timeoutTimer) {
                clearTimeout(timeoutTimer);
                timeoutTimer = null;
            }
        }

        function showStatus(type, message) {

            donateStatus.className = 'donate-status show ' + type;

            donateStatus.textContent = message;

        }



        function setDonating(active) {

            donateBtn.disabled = active;

            donateAmount.disabled = active;

            donatePhone.disabled = active;

            donateBtn.innerHTML = active

                ? '<i class="fa-solid fa-spinner fa-spin"></i> Processing…'

                : '<i class="fa-solid fa-hand-holding-heart"></i> Send STK Push';

        }



        function updateProgress(fundsRaised) {

            raisedDisplay.textContent = 'KES ' + fundsRaised.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const pct = goalAmount > 0 ? Math.min(100, Math.round((fundsRaised / goalAmount) * 1000) / 10) : 0;

            progressBar.style.width = pct + '%';

        }



        async function handlePaymentTimeout(donationId) {
            stopPolling();
            setDonating(false);

            try {
                const res = await fetch(statusUrl + '?donation_id=' + donationId + '&timeout=1', { credentials: 'same-origin' });
                const data = await res.json();
                showStatus('error', data.message || 'Payment timed out after 40 seconds. Please try again.');
            } catch (err) {
                showStatus('error', 'Payment timed out after 40 seconds. Please try again.');
            }
        }

        function pollDonationStatus(donationId) {
            stopPolling();

            pollTimer = setInterval(async () => {
                try {
                    const res = await fetch(statusUrl + '?donation_id=' + donationId, { credentials: 'same-origin' });
                    const data = await res.json();

                    if (data.status === 'success') {
                        stopPolling();
                        setDonating(false);
                        showStatus('success', data.message || 'Thank you! Your donation was successful.');
                        if (typeof data.funds_raised === 'number') {
                            updateProgress(data.funds_raised);
                        }
                        return;
                    }

                    if (data.status === 'failed') {
                        stopPolling();
                        setDonating(false);
                        showStatus('error', data.message || 'Payment failed.');
                        return;
                    }

                    showStatus('pending', data.message || 'Waiting for M-PESA confirmation… Enter your PIN on your phone.');
                } catch (err) {
                    showStatus('pending', 'Still waiting for payment confirmation…');
                }
            }, 3000);

            timeoutTimer = setTimeout(() => handlePaymentTimeout(donationId), PAYMENT_TIMEOUT_MS);
        }



        donateBtn.addEventListener('click', async () => {

            const amount = parseFloat(donateAmount.value);

            const phone = donatePhone.value.trim();



            if (!amount || amount < 1) {

                showStatus('error', 'Enter an amount of at least KES 1.');

                return;

            }

            if (!phone) {

                showStatus('error', 'Enter your M-PESA phone number.');

                return;

            }



            setDonating(true);

            showStatus('pending', 'Sending STK Push to your phone…');



            try {

                const res = await fetch(donateUrl, {

                    method: 'POST',

                    credentials: 'same-origin',

                    headers: { 'Content-Type': 'application/json' },

                    body: JSON.stringify({ campaign_id: campaignId, amount, phone }),

                });

                const data = await res.json();



                if (!data.success) {

                    setDonating(false);

                    showStatus('error', data.errorMessage || 'Could not start payment.');

                    return;

                }



                showStatus('pending', data.message || 'Check your phone and enter your M-PESA PIN.');

                pollDonationStatus(data.donationId);

            } catch (err) {

                setDonating(false);

                showStatus('error', 'Network error. Please try again.');

            }

        });

    </script>
    <?php endif; ?>
</body>

</html>


