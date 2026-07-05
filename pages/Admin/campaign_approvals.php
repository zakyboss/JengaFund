<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "Access denied. Admin login required.";
    header("Location: ../login.php");
    exit();
}

if (time() - $_SESSION['last_activity'] > 1800) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

/** @var PDO $pdo */
$pdo = require_once '../../config/database.php';

try {
    $sql = "SELECT c.*, u.full_name as student_name, u.email as student_email 
            FROM campaigns c 
            JOIN users u ON c.student_id = u.id 
            ORDER BY 
              CASE WHEN c.status = 'pending_approval' THEN 1 ELSE 2 END,
              c.created_at DESC";
    $stmt = $pdo->query($sql);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to fetch campaigns: " . $e->getMessage());
    $campaigns = [];
}

$pendingCampaigns = array_filter($campaigns, fn($c) => $c['status'] === 'pending_approval');
$processedCampaigns = array_filter($campaigns, fn($c) => $c['status'] !== 'pending_approval');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php require_once __DIR__ . '/../../components/favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Approvals - JengaFund</title>
    <link rel="stylesheet" href="../../assets/css/app.css">
</head>
<body class="jf-app">
    <?php
    require_once '../../components/sidebar.php';
    require_once '../../components/notification.php';
    ?>

    <div class="jf-page jf-page-wide">
        <div class="jf-page-header-row jf-page-header">
            <div>
                <h1>Campaign Approvals</h1>
                <p>Review student proposals and help bring their ideas to life.</p>
            </div>
            <a href="dashboard.php" class="jf-btn jf-btn-outline"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
        </div>

        <div class="jf-tabs" role="tablist">
            <button type="button" class="jf-tab active" data-tab="pending" role="tab">Pending Review</button>
            <button type="button" class="jf-tab" data-tab="processed" role="tab">Processed</button>
        </div>

        <!-- Pending -->
        <div class="jf-tab-panel active" id="tab-pending" role="tabpanel">
            <?php if (empty($pendingCampaigns)): ?>
                <div class="jf-empty">
                    <i class="fa-solid fa-circle-check"></i>
                    <p>You're all caught up — no campaigns waiting for review.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pendingCampaigns as $camp): ?>
                    <article class="jf-card jf-card-accent-pending">
                        <div class="jf-card-head">
                            <div>
                                <span class="jf-badge jf-badge-pending">Pending Approval</span>
                                <span class="jf-meta" style="margin-left: 10px;">Submitted <?= date('M j, Y · g:i A', strtotime($camp['created_at'])) ?></span>
                            </div>
                            <span class="jf-goal">KES <?= number_format($camp['goal_amount'], 2) ?></span>
                        </div>
                        <div class="jf-card-body">
                            <h2 class="jf-card-title"><?= htmlspecialchars($camp['title']) ?></h2>
                            <div class="jf-meta">
                                <div>Submitted by <strong><?= htmlspecialchars($camp['student_name']) ?></strong> · <?= htmlspecialchars($camp['student_email']) ?></div>
                                <div>Category: <strong><?= htmlspecialchars($camp['category']) ?></strong> · <?= ucfirst($camp['disbursement_type']) ?> payout</div>
                                <div><?= date('M j, Y', strtotime($camp['starts_at'])) ?> → <?= date('M j, Y', strtotime($camp['ends_at'])) ?></div>
                            </div>
                            <div class="jf-description"><?= htmlspecialchars($camp['description']) ?></div>

                            <div class="jf-actions" style="border-top:none;margin-top:0;padding-top:0;">
                                <a href="campaign_view.php?id=<?= $camp['id'] ?>" class="jf-btn jf-btn-outline"><i class="fa-solid fa-eye"></i> View Details</a>
                            </div>

                            <form action="campaign_actions.php" method="POST" class="jf-actions">
                                <input type="hidden" name="campaign_id" value="<?= $camp['id'] ?>">
                                <input type="text" name="rejection_reason" class="jf-input" placeholder="Rejection reason (required if rejecting)">
                                <button type="submit" name="action" value="reject" class="jf-btn jf-btn-reject">Reject</button>
                                <button type="submit" name="action" value="approve" class="jf-btn jf-btn-approve">Approve</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Processed -->
        <div class="jf-tab-panel" id="tab-processed" role="tabpanel">
            <?php if (empty($processedCampaigns)): ?>
                <div class="jf-empty">
                    <i class="fa-regular fa-folder-open" style="color: var(--jf-text-light);"></i>
                    <p>No processed campaigns yet.</p>
                </div>
            <?php else: ?>
                <div class="jf-table-wrap">
                    <table class="jf-table">
                        <thead>
                            <tr>
                                <th>Campaign</th>
                                <th>Student</th>
                                <th>Goal</th>
                                <th>Timeline</th>
                                <th>Status</th>
                                <th>Reviewed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($processedCampaigns as $camp):
                                $badgeClass = 'jf-badge-neutral';
                                if ($camp['status'] === 'approved') $badgeClass = 'jf-badge-approved';
                                elseif ($camp['status'] === 'rejected') $badgeClass = 'jf-badge-rejected';
                                elseif ($camp['status'] === 'active') $badgeClass = 'jf-badge-active';
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($camp['title']) ?></strong>
                                        <span class="jf-sub"><?= htmlspecialchars($camp['category']) ?></span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($camp['student_name']) ?></strong>
                                        <span class="jf-sub"><?= htmlspecialchars($camp['student_email']) ?></span>
                                    </td>
                                    <td><strong>KES <?= number_format($camp['goal_amount'], 2) ?></strong></td>
                                    <td class="jf-sub">
                                        <?= date('M j', strtotime($camp['starts_at'])) ?> – <?= date('M j, Y', strtotime($camp['ends_at'])) ?>
                                    </td>
                                    <td>
                                        <span class="jf-badge <?= $badgeClass ?>"><?= ucfirst(str_replace('_', ' ', $camp['status'])) ?></span>
                                        <?php if ($camp['status'] === 'rejected' && !empty($camp['rejection_reason'])): ?>
                                            <div class="jf-sub" style="color: var(--jf-danger); margin-top: 6px; max-width: 220px;">
                                                <?= htmlspecialchars($camp['rejection_reason']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="jf-sub">
                                        <?= $camp['reviewed_at'] ? date('M j, Y · g:i A', strtotime($camp['reviewed_at'])) : '—' ?>
                                    </td>
                                    <td>
                                        <a href="campaign_view.php?id=<?= $camp['id'] ?>" class="jf-btn jf-btn-outline jf-btn-sm"><i class="fa-solid fa-eye"></i> View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.querySelectorAll('.jf-tab').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.jf-tab').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.jf-tab-panel').forEach(function(p) { p.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
        });
    });
    </script>
</body>
</html>
