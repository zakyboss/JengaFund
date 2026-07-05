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

/** @var PDO $pdo */
$pdo = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/notification.php';

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

$stats = [
    'students'        => 0,
    'donors'          => 0,
    'pending_users'   => 0,
    'total_campaigns' => 0,
    'pending_campaigns' => 0,
    'active_campaigns'  => 0,
    'total_raised'    => 0.0,
    'donation_count'  => 0,
];
$campaignStatusChart = ['labels' => [], 'values' => []];
$donationTrendChart = ['labels' => [], 'amounts' => []];
$topCampaignsChart = ['labels' => [], 'values' => []];
$recentDonations = [];
$topDonors = [];
$pendingUsers = [];
$pendingCampaigns = [];

try {
    $row = $pdo->query("
        SELECT
            SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) AS students,
            SUM(CASE WHEN role = 'donor' THEN 1 ELSE 0 END) AS donors,
            SUM(CASE WHEN approval_status = 'pending' AND role IN ('student', 'donor') THEN 1 ELSE 0 END) AS pending_users
        FROM users
        WHERE deleted_at IS NULL AND role != 'admin'
    ")->fetch(PDO::FETCH_ASSOC);
    $stats['students'] = (int) ($row['students'] ?? 0);
    $stats['donors'] = (int) ($row['donors'] ?? 0);
    $stats['pending_users'] = (int) ($row['pending_users'] ?? 0);

    $row = $pdo->query("
        SELECT
            COUNT(*) AS total_campaigns,
            SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END) AS pending_campaigns,
            SUM(CASE WHEN status IN ('active', 'approved') THEN 1 ELSE 0 END) AS active_campaigns,
            COALESCE(SUM(funds_raised), 0) AS total_raised
        FROM campaigns
        WHERE status NOT IN ('draft', 'cancelled')
    ")->fetch(PDO::FETCH_ASSOC);
    $stats['total_campaigns'] = (int) ($row['total_campaigns'] ?? 0);
    $stats['pending_campaigns'] = (int) ($row['pending_campaigns'] ?? 0);
    $stats['active_campaigns'] = (int) ($row['active_campaigns'] ?? 0);
    $stats['total_raised'] = (float) ($row['total_raised'] ?? 0);

    $row = $pdo->query("
        SELECT COUNT(*) AS donation_count
        FROM donations
    ")->fetch(PDO::FETCH_ASSOC);
    $stats['donation_count'] = (int) ($row['donation_count'] ?? 0);

    $statusLabels = [
        'pending_approval'      => 'Pending approval',
        'approved'              => 'Approved',
        'active'                => 'Active',
        'awaiting_disbursement' => 'Awaiting payout',
        'completed'             => 'Completed',
        'rejected'              => 'Rejected',
    ];
    $stmtStatus = $pdo->query("
        SELECT status, COUNT(*) AS cnt
        FROM campaigns
        WHERE status NOT IN ('draft', 'cancelled')
        GROUP BY status
        ORDER BY cnt DESC
    ");
    while ($statusRow = $stmtStatus->fetch(PDO::FETCH_ASSOC)) {
        $key = $statusRow['status'];
        $campaignStatusChart['labels'][] = $statusLabels[$key] ?? ucwords(str_replace('_', ' ', $key));
        $campaignStatusChart['values'][] = (int) $statusRow['cnt'];
    }

    $stmtTrend = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%b %Y') AS month_label,
               DATE_FORMAT(created_at, '%Y-%m') AS sort_key,
               COALESCE(SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END), 0) AS amount
        FROM donations
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b %Y')
        ORDER BY sort_key ASC
    ");
    while ($trendRow = $stmtTrend->fetch(PDO::FETCH_ASSOC)) {
        $donationTrendChart['labels'][] = $trendRow['month_label'];
        $donationTrendChart['amounts'][] = (float) $trendRow['amount'];
    }

    $stmtTopCampaigns = $pdo->query("
        SELECT title, funds_raised
        FROM campaigns
        WHERE funds_raised > 0 AND status NOT IN ('draft', 'cancelled', 'rejected')
        ORDER BY funds_raised DESC
        LIMIT 5
    ");
    while ($campRow = $stmtTopCampaigns->fetch(PDO::FETCH_ASSOC)) {
        $label = mb_strlen($campRow['title']) > 28
            ? mb_substr($campRow['title'], 0, 25) . '...'
            : $campRow['title'];
        $topCampaignsChart['labels'][] = $label;
        $topCampaignsChart['values'][] = (float) $campRow['funds_raised'];
    }

    $recentDonations = $pdo->query("
        SELECT d.id, d.amount, d.status, d.created_at,
               u.full_name AS donor_name,
               c.title AS campaign_title, c.id AS campaign_id
        FROM donations d
        JOIN users u ON d.donor_id = u.id
        JOIN campaigns c ON d.campaign_id = c.id
        ORDER BY d.created_at DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    $topDonors = $pdo->query("
        SELECT u.id, u.full_name, u.email,
               COUNT(d.id) AS donation_count,
               COALESCE(SUM(d.amount), 0) AS total_donated
        FROM donations d
        JOIN users u ON d.donor_id = u.id
        WHERE d.status = 'success'
        GROUP BY d.donor_id, u.id, u.full_name, u.email
        ORDER BY total_donated DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $pendingUsers = $pdo->query("
        SELECT id, full_name, role, email, created_at
        FROM users
        WHERE approval_status = 'pending'
          AND role IN ('student', 'donor')
          AND deleted_at IS NULL
        ORDER BY created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $pendingCampaigns = $pdo->query("
        SELECT c.id, c.title, c.goal_amount, c.created_at, u.full_name AS student_name
        FROM campaigns c
        JOIN users u ON c.student_id = u.id
        WHERE c.status = 'pending_approval'
        ORDER BY c.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Admin dashboard error: ' . $e->getMessage());
}

function adminDonationBadge(string $status): array
{
    return match ($status) {
        'success'   => ['jf-badge-approved', 'Success'],
        'failed'    => ['jf-badge-rejected', 'Failed'],
        'cancelled' => ['jf-badge-rejected', 'Cancelled'],
        default     => ['jf-badge-pending', 'Pending'],
    };
}

$chartData = [
    'campaignStatuses' => $campaignStatusChart,
    'donationTrend'    => $donationTrendChart,
    'topCampaigns'     => $topCampaignsChart,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php require_once __DIR__ . '/../../components/favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JengaFund</title>
    <link rel="stylesheet" href="../../assets/css/app.css">
    <style>
        .admin-stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .admin-stat-card {
            background: var(--jf-surface);
            border: 1px solid var(--jf-border);
            border-radius: var(--jf-radius);
            padding: 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            box-shadow: var(--jf-shadow-sm);
            transition: transform var(--jf-transition), box-shadow var(--jf-transition);
        }
        .admin-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--jf-shadow);
        }
        .admin-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .admin-stat-icon.red { background: rgba(230, 0, 0, 0.1); color: var(--jf-brand); }
        .admin-stat-icon.green { background: var(--jf-success-bg); color: var(--jf-success); }
        .admin-stat-icon.blue { background: var(--jf-info-bg); color: var(--jf-info); }
        .admin-stat-icon.orange { background: var(--jf-warning-bg); color: var(--jf-warning); }
        .admin-stat-icon.purple { background: #f3f0ff; color: #7048e8; }
        .admin-stat-body h3 {
            margin: 0;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--jf-text-light);
        }
        .admin-stat-body p {
            margin: 4px 0 0;
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--jf-text);
            line-height: 1.2;
        }
        .admin-stat-body span {
            display: block;
            margin-top: 4px;
            font-size: 0.8rem;
            color: var(--jf-text-muted);
        }
        .admin-chart-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }
        .admin-chart-card {
            background: var(--jf-surface);
            border: 1px solid var(--jf-border);
            border-radius: var(--jf-radius);
            padding: 22px;
            box-shadow: var(--jf-shadow-sm);
        }
        .admin-chart-card h2 {
            margin: 0 0 4px;
            font-size: 1rem;
            font-weight: 700;
        }
        .admin-chart-card p {
            margin: 0 0 16px;
            font-size: 0.85rem;
            color: var(--jf-text-muted);
        }
        .admin-chart-wrap {
            position: relative;
            height: 260px;
        }
        .admin-chart-wrap.tall { height: 300px; }
        .admin-split-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }
        .admin-queue-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--jf-border-soft);
        }
        .admin-queue-item:last-child { border-bottom: none; padding-bottom: 0; }
        .admin-queue-item strong { display: block; font-size: 0.92rem; }
        .admin-queue-item small { color: var(--jf-text-muted); font-size: 0.8rem; }
        @media (max-width: 960px) {
            .admin-chart-grid,
            .admin-split-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="jf-app">
<div class="jf-page jf-page-wide">

    <div class="jf-page-header-row jf-page-header">
        <div>
            <h1>Admin dashboard</h1>
            <p>Welcome back, <?php echo $userName; ?>. Platform overview and reports.</p>
        </div>
        <div class="jf-actions">
            <a href="users.php" class="jf-btn jf-btn-outline"><i class="fa-solid fa-users"></i> Users</a>
            <a href="campaign_approvals.php" class="jf-btn jf-btn-brand"><i class="fa-solid fa-file-circle-check"></i> Approvals</a>
        </div>
    </div>

    <div class="admin-stat-grid">
        <div class="admin-stat-card">
            <div class="admin-stat-icon blue"><i class="fa-solid fa-user-graduate"></i></div>
            <div class="admin-stat-body">
                <h3>Students</h3>
                <p><?php echo number_format($stats['students']); ?></p>
                <span><?php echo number_format($stats['pending_users']); ?> pending approval</span>
            </div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-icon green"><i class="fa-solid fa-hand-holding-heart"></i></div>
            <div class="admin-stat-body">
                <h3>Donors</h3>
                <p><?php echo number_format($stats['donors']); ?></p>
                <span>Registered on platform</span>
            </div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-icon orange"><i class="fa-solid fa-bullhorn"></i></div>
            <div class="admin-stat-body">
                <h3>Campaigns</h3>
                <p><?php echo number_format($stats['total_campaigns']); ?></p>
                <span><?php echo number_format($stats['pending_campaigns']); ?> awaiting review</span>
            </div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-icon purple"><i class="fa-solid fa-chart-line"></i></div>
            <div class="admin-stat-body">
                <h3>Funds raised</h3>
                <p>KES <?php echo number_format($stats['total_raised'], 0); ?></p>
                <span><?php echo number_format($stats['donation_count']); ?> donation attempts · across all campaigns</span>
            </div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-icon green"><i class="fa-solid fa-circle-play"></i></div>
            <div class="admin-stat-body">
                <h3>Live campaigns</h3>
                <p><?php echo number_format($stats['active_campaigns']); ?></p>
                <span>Active or approved</span>
            </div>
        </div>
    </div>

    <div class="admin-chart-grid">
        <div class="admin-chart-card">
            <h2>Campaign status</h2>
            <p>Where projects sit in the pipeline</p>
            <div class="admin-chart-wrap"><canvas id="campaignsChart"></canvas></div>
        </div>
        <div class="admin-chart-card">
            <h2>Donations over time</h2>
            <p>Successful M-PESA totals (last 6 months)</p>
            <div class="admin-chart-wrap tall"><canvas id="trendChart"></canvas></div>
        </div>
        <div class="admin-chart-card">
            <h2>Top funded campaigns</h2>
            <p>Highest amounts raised so far</p>
            <div class="admin-chart-wrap tall"><canvas id="topCampaignsChart"></canvas></div>
        </div>
    </div>

    <div class="admin-split-grid">
        <div class="jf-panel">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px;">
                <h2 style="margin:0;border:none;padding:0;">Pending user approvals</h2>
                <a href="users.php" class="jf-btn jf-btn-outline jf-btn-sm">View all</a>
            </div>
            <?php if (empty($pendingUsers)): ?>
                <p class="jf-hint" style="margin:0;">No users waiting for approval.</p>
            <?php else: ?>
                <?php foreach ($pendingUsers as $pu): ?>
                    <div class="admin-queue-item">
                        <div>
                            <strong><?php echo htmlspecialchars($pu['full_name']); ?></strong>
                            <small><?php echo ucfirst($pu['role']); ?> · <?php echo date('M j, Y', strtotime($pu['created_at'])); ?></small>
                        </div>
                        <a href="user_view.php?id=<?php echo (int) $pu['id']; ?>" class="jf-btn jf-btn-brand jf-btn-sm">Review</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="jf-panel">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px;">
                <h2 style="margin:0;border:none;padding:0;">Pending campaign approvals</h2>
                <a href="campaign_approvals.php" class="jf-btn jf-btn-outline jf-btn-sm">View all</a>
            </div>
            <?php if (empty($pendingCampaigns)): ?>
                <p class="jf-hint" style="margin:0;">No campaigns waiting for review.</p>
            <?php else: ?>
                <?php foreach ($pendingCampaigns as $pc): ?>
                    <div class="admin-queue-item">
                        <div>
                            <strong><?php echo htmlspecialchars($pc['title']); ?></strong>
                            <small><?php echo htmlspecialchars($pc['student_name']); ?> · KES <?php echo number_format((float) $pc['goal_amount'], 0); ?></small>
                        </div>
                        <a href="campaign_view.php?id=<?php echo (int) $pc['id']; ?>" class="jf-btn jf-btn-brand jf-btn-sm">Review</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-split-grid">
        <div class="jf-panel">
            <h2>Recent donations</h2>
            <?php if (empty($recentDonations)): ?>
                <p class="jf-hint">No donations recorded yet.</p>
            <?php else: ?>
                <div class="jf-table-wrap" style="border:none;box-shadow:none;">
                    <table class="jf-table">
                        <thead>
                            <tr>
                                <th>Donor</th>
                                <th>Campaign</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentDonations as $don):
                                [$badgeClass, $badgeLabel] = adminDonationBadge($don['status']);
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($don['donor_name']); ?></strong></td>
                                <td>
                                    <a href="campaign_view.php?id=<?php echo (int) $don['campaign_id']; ?>" style="color:var(--jf-brand);font-weight:600;text-decoration:none;">
                                        <?php echo htmlspecialchars($don['campaign_title']); ?>
                                    </a>
                                </td>
                                <td>KES <?php echo number_format((float) $don['amount'], 2); ?></td>
                                <td><span class="jf-badge <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span></td>
                                <td class="jf-sub"><?php echo date('M j, g:i A', strtotime($don['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="jf-panel">
            <h2>Top donors</h2>
            <?php if (empty($topDonors)): ?>
                <p class="jf-hint">No successful donations yet.</p>
            <?php else: ?>
                <div class="jf-table-wrap" style="border:none;box-shadow:none;">
                    <table class="jf-table">
                        <thead>
                            <tr>
                                <th>Donor</th>
                                <th>Donations</th>
                                <th>Total given</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topDonors as $donor): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($donor['full_name']); ?></strong>
                                    <div class="jf-sub"><?php echo htmlspecialchars($donor['email']); ?></div>
                                </td>
                                <td><?php echo number_format((int) $donor['donation_count']); ?></td>
                                <td><strong>KES <?php echo number_format((float) $donor['total_donated'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    const data = <?php echo json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const brand = '#e60000';
    const palette = ['#e60000', '#0ca678', '#1c7ed6', '#f08c00', '#7048e8', '#868e96', '#c92a2a', '#099268'];

    Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
    Chart.defaults.color = '#666';

    function emptyLabels(fallback) {
        return fallback || ['No data yet'];
    }

    function emptyValues(fallback) {
        return fallback || [1];
    }

    new Chart(document.getElementById('campaignsChart'), {
        type: 'pie',
        data: {
            labels: data.campaignStatuses.labels.length ? data.campaignStatuses.labels : emptyLabels(['No campaigns']),
            datasets: [{
                data: data.campaignStatuses.values.length ? data.campaignStatuses.values : emptyValues([1]),
                backgroundColor: palette,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: {
            labels: data.donationTrend.labels.length ? data.donationTrend.labels : emptyLabels(['No donations yet']),
            datasets: [{
                label: 'KES donated',
                data: data.donationTrend.amounts.length ? data.donationTrend.amounts : emptyValues([0]),
                backgroundColor: brand,
                borderRadius: 8,
                maxBarThickness: 48
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'KES ' + value.toLocaleString(); }
                    }
                }
            }
        }
    });

    new Chart(document.getElementById('topCampaignsChart'), {
        type: 'bar',
        data: {
            labels: data.topCampaigns.labels.length ? data.topCampaigns.labels : emptyLabels(['No funded campaigns']),
            datasets: [{
                label: 'KES raised',
                data: data.topCampaigns.values.length ? data.topCampaigns.values : emptyValues([0]),
                backgroundColor: '#7048e8',
                borderRadius: 8
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'KES ' + value.toLocaleString(); }
                    }
                }
            }
        }
    });
})();
</script>
</body>
</html>
