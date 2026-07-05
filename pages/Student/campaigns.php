<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    $_SESSION['error_message'] = 'Unauthorized access. Please log in as a student.';
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

$userId = (int) $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT id, title, category, description, goal_amount, funds_raised, status, disbursement_type, created_at, ends_at
        FROM campaigns
        WHERE student_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Student campaigns list error: ' . $e->getMessage());
    $campaigns = [];
}

function studentCampaignBadge(string $status): string
{
    return match ($status) {
        'active' => 'jf-badge-active',
        'approved', 'completed' => 'jf-badge-approved',
        'awaiting_disbursement' => 'jf-badge-pending',
        'rejected', 'cancelled' => 'jf-badge-rejected',
        'pending_approval' => 'jf-badge-pending',
        default => 'jf-badge-neutral',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php require_once __DIR__ . '/../../components/favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Campaigns - JengaFund</title>
    <link rel="stylesheet" href="../../assets/css/app.css">
    <style>
        .my-campaign-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 22px;
            margin-top: 8px;
        }

        .my-campaign-card {
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

        .my-campaign-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--jf-shadow);
        }

        .my-campaign-card.is-cancelled,
        .my-campaign-card.is-rejected {
            opacity: 0.82;
        }

        .my-campaign-card.is-funded {
            border-color: #b2f2bb;
        }

        .my-campaign-card-top {
            padding: 18px 20px 14px;
            background: linear-gradient(135deg, #fff 0%, #fff5f5 100%);
            border-bottom: 1px solid var(--jf-border);
        }

        .my-campaign-card.is-funded .my-campaign-card-top {
            background: linear-gradient(135deg, #fff 0%, #e6fcf5 100%);
        }

        .my-campaign-card-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }

        .my-campaign-card h3 {
            margin: 0 0 6px;
            font-size: 1.15rem;
            color: var(--jf-text);
            line-height: 1.35;
        }

        .my-campaign-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--jf-text-muted);
        }

        .my-campaign-card-body {
            padding: 18px 20px 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .my-campaign-desc {
            font-size: 0.88rem;
            color: var(--jf-text-muted);
            line-height: 1.55;
            margin: 0 0 18px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
        }

        .my-campaign-progress-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.82rem;
            color: var(--jf-text-muted);
            margin-bottom: 8px;
        }

        .my-campaign-progress-head strong {
            color: var(--jf-brand);
            font-size: 0.95rem;
        }

        .my-campaign-card.is-funded .my-campaign-progress-head strong {
            color: var(--jf-success);
        }

        .my-campaign-progress-wrap {
            height: 10px;
            background: var(--jf-bg);
            border-radius: 999px;
            overflow: hidden;
            margin-bottom: 14px;
        }

        .my-campaign-progress-bar {
            height: 100%;
            background: var(--jf-brand);
            border-radius: 999px;
            transition: width 0.4s ease;
        }

        .my-campaign-card.is-funded .my-campaign-progress-bar {
            background: var(--jf-success);
        }

        .my-campaign-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 16px;
        }

        .my-campaign-stat {
            background: var(--jf-bg);
            border-radius: 10px;
            padding: 10px 12px;
        }

        .my-campaign-stat span {
            display: block;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--jf-text-light);
            margin-bottom: 4px;
        }

        .my-campaign-stat strong {
            font-size: 0.92rem;
            color: var(--jf-text);
        }

        .my-campaign-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-top: 4px;
            border-top: 1px dashed var(--jf-border);
            margin-top: auto;
        }

        .my-campaign-date {
            font-size: 0.78rem;
            color: var(--jf-text-light);
        }

        body.dark-mode .my-campaign-card-top {
            background: linear-gradient(135deg, #1e1e1e 0%, #2a1a1a 100%);
        }

        body.dark-mode .my-campaign-card.is-funded .my-campaign-card-top {
            background: linear-gradient(135deg, #1e1e1e 0%, #1a2e24 100%);
        }

        body.dark-mode .my-campaign-stat {
            background: #252525;
        }
    </style>
</head>
<body class="jf-app">
    <div class="jf-page jf-page-wide">
        <a href="dashboard.php" class="jf-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

        <div class="jf-page-header-row jf-page-header">
            <div>
                <h1>My Campaigns</h1>
                <p>All your projects — view details, edit, and submit milestone evidence.</p>
            </div>
            <a href="create_campaign.php" class="jf-btn jf-btn-brand"><i class="fa-solid fa-plus"></i> New Campaign</a>
        </div>

        <?php if (empty($campaigns)): ?>
            <div class="jf-empty">
                <i class="fa-solid fa-folder-open" style="color: var(--jf-text-light);"></i>
                <p>You haven't launched any campaigns yet.</p>
                <a href="create_campaign.php" class="jf-btn jf-btn-brand" style="margin-top: 16px; display: inline-flex;">Start your first project</a>
            </div>
        <?php else: ?>
            <div class="my-campaign-grid">
                <?php foreach ($campaigns as $camp):
                    $goal = (float) $camp['goal_amount'];
                    $raised = (float) $camp['funds_raised'];
                    $pct = $goal > 0 ? min(100, round(($raised / $goal) * 100)) : 0;
                    $badge = studentCampaignBadge($camp['status']);
                    $statusLabel = ucfirst(str_replace('_', ' ', $camp['status']));
                    $isMilestone = $camp['disbursement_type'] === 'milestone';
                    $goalMet = $goal > 0 && $raised >= $goal;
                    $cardClass = 'my-campaign-card';
                    if (in_array($camp['status'], ['cancelled', 'rejected'], true)) {
                        $cardClass .= ' is-cancelled';
                    }
                    if ($goalMet || in_array($camp['status'], ['awaiting_disbursement', 'completed'], true)) {
                        $cardClass .= ' is-funded';
                    }
                ?>
                <article class="<?= $cardClass ?>">
                    <div class="my-campaign-card-top">
                        <div class="my-campaign-card-badges">
                            <span class="jf-badge jf-badge-neutral"><?= htmlspecialchars($camp['category']) ?></span>
                            <span class="jf-badge <?= $badge ?>"><?= $statusLabel ?></span>
                        </div>
                        <h3><?= htmlspecialchars($camp['title']) ?></h3>
                        <span class="my-campaign-type">
                            <i class="fa-solid <?= $isMilestone ? 'fa-layer-group' : 'fa-bolt' ?>"></i>
                            <?= $isMilestone ? 'Milestone payout' : 'Full payout' ?>
                        </span>
                    </div>
                    <div class="my-campaign-card-body">
                        <p class="my-campaign-desc"><?= htmlspecialchars($camp['description']) ?></p>

                        <div class="my-campaign-progress-head">
                            <span>Funding progress</span>
                            <strong><?= $pct ?>%</strong>
                        </div>
                        <div class="my-campaign-progress-wrap">
                            <div class="my-campaign-progress-bar" style="width: <?= $pct ?>%;"></div>
                        </div>

                        <div class="my-campaign-stats">
                            <div class="my-campaign-stat">
                                <span>Raised</span>
                                <strong>KES <?= number_format($raised, 0) ?></strong>
                            </div>
                            <div class="my-campaign-stat">
                                <span>Goal</span>
                                <strong>KES <?= number_format($goal, 0) ?></strong>
                            </div>
                        </div>

                        <div class="my-campaign-card-footer">
                            <span class="my-campaign-date">
                                <i class="fa-regular fa-calendar"></i>
                                <?= date('M j, Y', strtotime($camp['created_at'])) ?>
                            </span>
                            <a href="campaign_view.php?id=<?= (int) $camp['id'] ?>" class="jf-btn jf-btn-brand jf-btn-sm">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open
                            </a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
