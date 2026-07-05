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

try {
    $stmtOpen = $pdo->query("
        SELECT c.*, u.full_name AS student_name
        FROM campaigns c
        JOIN users u ON c.student_id = u.id
        WHERE c.status IN ('active', 'approved')
          AND c.ends_at > NOW()
          AND c.funds_raised < c.goal_amount
        ORDER BY c.created_at DESC
    ");
    $openCampaigns = $stmtOpen->fetchAll(PDO::FETCH_ASSOC);

    $stmtFunded = $pdo->query("
        SELECT c.*, u.full_name AS student_name
        FROM campaigns c
        JOIN users u ON c.student_id = u.id
        WHERE c.status NOT IN ('draft', 'pending_approval', 'rejected', 'cancelled')
          AND (
            c.funds_raised >= c.goal_amount
            OR c.status IN ('awaiting_disbursement', 'completed')
          )
        ORDER BY c.updated_at DESC
        LIMIT 24
    ");
    $fundedCampaigns = $stmtFunded->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Donor campaigns fetch error: ' . $e->getMessage());
    $openCampaigns = [];
    $fundedCampaigns = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Campaigns - JengaFund</title>
    <link rel="stylesheet" href="../../assets/css/app.css">
    <style>
        .campaign-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }
        .campaign-card {
            background: var(--jf-surface);
            border: 1px solid var(--jf-border);
            border-radius: var(--jf-radius);
            padding: 24px;
            box-shadow: var(--jf-shadow-sm);
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
            transition: box-shadow var(--jf-transition), transform var(--jf-transition);
        }
        .campaign-card:hover {
            box-shadow: var(--jf-shadow);
            transform: translateY(-3px);
        }
        .campaign-card h3 {
            margin: 0 0 8px;
            font-size: 1.1rem;
            color: var(--jf-text);
        }
        .campaign-card .student {
            font-size: 0.85rem;
            color: var(--jf-text-muted);
            margin-bottom: 12px;
        }
        .campaign-card .desc {
            font-size: 0.88rem;
            color: var(--jf-text-muted);
            line-height: 1.55;
            flex: 1;
            margin-bottom: 16px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .campaign-card .progress-wrap {
            height: 8px;
            background: var(--jf-bg);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        .campaign-card .progress-bar {
            height: 100%;
            background: var(--jf-brand);
            border-radius: 10px;
        }
        .campaign-card .funding-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
            color: var(--jf-text-muted);
            margin-bottom: 16px;
        }
        .campaign-card .funding-row strong { color: var(--jf-text); }
        .campaign-card.is-funded {
            opacity: 0.92;
            border-color: var(--jf-success);
        }
        .campaign-card.is-funded .progress-bar { background: var(--jf-success); }
        .campaign-section-title {
            font-size: 1.15rem;
            margin: 32px 0 8px;
            color: var(--jf-text);
        }
        .campaign-section-desc {
            color: var(--jf-text-muted);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
    </style>
</head>
<body class="jf-app">
    <div class="jf-page jf-page-wide">
        <a href="dashboard.php" class="jf-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

        <div class="jf-page-header">
            <h1>Checkout Campaigns</h1>
            <p>Discover student innovation projects and support the ones that inspire you.</p>
        </div>

        <?php if (empty($openCampaigns) && empty($fundedCampaigns)): ?>
            <div class="jf-empty">
                <i class="fa-regular fa-folder-open" style="color: var(--jf-text-light);"></i>
                <p>No campaigns are open for donations at the moment. Check back soon!</p>
                <a href="dashboard.php" class="jf-btn jf-btn-outline" style="margin-top:16px;display:inline-flex;">Return to Dashboard</a>
            </div>
        <?php else: ?>
            <?php if (!empty($openCampaigns)): ?>
                <h2 class="campaign-section-title">Open for donations</h2>
                <p class="campaign-section-desc">These projects still need your support.</p>
                <div class="campaign-grid">
                    <?php foreach ($openCampaigns as $camp):
                        $goal = (float) $camp['goal_amount'];
                        $raised = (float) $camp['funds_raised'];
                        $pct = $goal > 0 ? min(100, round(($raised / $goal) * 100)) : 0;
                    ?>
                    <article class="campaign-card">
                        <span class="jf-badge jf-badge-active" style="align-self:flex-start;margin-bottom:10px;">
                            <?= ucfirst(htmlspecialchars($camp['category'])) ?>
                        </span>
                        <h3><?= htmlspecialchars($camp['title']) ?></h3>
                        <p class="student">By <?= htmlspecialchars($camp['student_name']) ?></p>
                        <p class="desc"><?= htmlspecialchars($camp['description']) ?></p>
                        <div class="progress-wrap">
                            <div class="progress-bar" style="width: <?= $pct ?>%;"></div>
                        </div>
                        <div class="funding-row">
                            <span><strong>KES <?= number_format($raised, 0) ?></strong> raised</span>
                            <span>Goal: <strong>KES <?= number_format($goal, 0) ?></strong></span>
                        </div>
                        <a href="campaign_detail.php?id=<?= (int) $camp['id'] ?>" class="jf-btn jf-btn-brand jf-btn-block">
                            <i class="fa-solid fa-heart"></i> Support This Project
                        </a>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="jf-empty" style="margin-top: 16px;">
                    <p>No campaigns are currently accepting donations. See fully funded projects below.</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($fundedCampaigns)): ?>
                <h2 class="campaign-section-title">Fully funded</h2>
                <p class="campaign-section-desc">These campaigns have reached their goal and are closed to new donations.</p>
                <div class="campaign-grid">
                    <?php foreach ($fundedCampaigns as $camp):
                        $goal = (float) $camp['goal_amount'];
                        $raised = (float) $camp['funds_raised'];
                        $pct = 100;
                        $statusLabel = $camp['status'] === 'completed' ? 'Completed' : 'Fully funded';
                    ?>
                    <article class="campaign-card is-funded">
                        <span class="jf-badge jf-badge-approved" style="align-self:flex-start;margin-bottom:10px;">
                            <i class="fa-solid fa-circle-check"></i> <?= $statusLabel ?>
                        </span>
                        <h3><?= htmlspecialchars($camp['title']) ?></h3>
                        <p class="student">By <?= htmlspecialchars($camp['student_name']) ?></p>
                        <p class="desc"><?= htmlspecialchars($camp['description']) ?></p>
                        <div class="progress-wrap">
                            <div class="progress-bar" style="width: 100%;"></div>
                        </div>
                        <div class="funding-row">
                            <span><strong>KES <?= number_format($raised, 0) ?></strong> raised</span>
                            <span>Goal met</span>
                        </div>
                        <a href="campaign_detail.php?id=<?= (int) $camp['id'] ?>" class="jf-btn jf-btn-outline jf-btn-block">
                            <i class="fa-solid fa-eye"></i> View Project
                        </a>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
