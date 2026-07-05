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
require_once __DIR__ . '/../../backend/milestone_helper.php';

$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'status' => $_GET['status'] ?? '',
    'from'   => $_GET['from'] ?? '',
    'to'     => $_GET['to'] ?? '',
];

if (!in_array($filters['status'], ['', 'confirmed', 'awaiting'], true)) {
    $filters['status'] = '';
}

$perPage = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$allRecords = loadDisbursementRecords($pdo, $filters);
$total = count($allRecords);
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$records = array_slice($allRecords, $offset, $perPage);

$totalDisbursed = array_sum(array_map(fn($r) => (float) $r['disbursed_amount'], $allRecords));
$confirmedCount = count(array_filter($allRecords, fn($r) => !empty($r['student_confirmed'])));
$hasFilters = $filters['search'] !== '' || $filters['status'] !== '' || $filters['from'] !== '' || $filters['to'] !== '';

function disbInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $initials = strtoupper(substr($parts[0] ?? 'S', 0, 1));
    if (count($parts) > 1) {
        $initials .= strtoupper(substr($parts[count($parts) - 1], 0, 1));
    }
    return $initials;
}

function disbFilterQuery(array $filters, ?int $page = null): string
{
    $params = [];
    if ($filters['search'] !== '') {
        $params['search'] = $filters['search'];
    }
    if ($filters['status'] !== '') {
        $params['status'] = $filters['status'];
    }
    if ($filters['from'] !== '') {
        $params['from'] = $filters['from'];
    }
    if ($filters['to'] !== '') {
        $params['to'] = $filters['to'];
    }
    if ($page !== null && $page > 1) {
        $params['page'] = $page;
    }
    return $params === [] ? 'disbursement_records.php' : 'disbursement_records.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php require_once __DIR__ . '/../../components/favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disbursement Records - JengaFund</title>
    <link rel="stylesheet" href="../../assets/css/app.css">
    <style>
        .disb-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }
        .disb-stat {
            background: var(--jf-surface);
            border: 1px solid var(--jf-border);
            border-radius: var(--jf-radius);
            padding: 16px 18px;
            box-shadow: var(--jf-shadow-sm);
        }
        .disb-stat-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--jf-text-light);
        }
        .disb-stat-value {
            margin-top: 6px;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--jf-text);
        }

        .disb-shell {
            background: var(--jf-surface);
            border: 1px solid var(--jf-border);
            border-radius: var(--jf-radius);
            box-shadow: var(--jf-shadow-sm);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .disb-filters {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 10px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--jf-border-soft);
            background: #fafbfc;
        }
        .disb-field { display: flex; flex-direction: column; gap: 4px; }
        .disb-field-grow { flex: 1; min-width: 200px; }
        .disb-field-sm { width: 140px; }
        .disb-field label {
            font-size: 0.68rem;
            font-weight: 600;
            color: var(--jf-text-light);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .disb-input-wrap { position: relative; }
        .disb-input-wrap i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--jf-text-light);
            font-size: 0.8rem;
            pointer-events: none;
        }
        .disb-input {
            width: 100%;
            padding: 8px 12px;
            font-size: 0.88rem;
            border: 1px solid var(--jf-border);
            border-radius: 8px;
            background: #fff;
            color: var(--jf-text);
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .disb-input-wrap .disb-input { padding-left: 32px; }
        .disb-input:focus {
            outline: none;
            border-color: var(--jf-brand);
            box-shadow: 0 0 0 3px rgba(230, 0, 0, 0.08);
        }

        .disb-table-scroll { overflow: auto; max-height: min(62vh, 640px); }
        .disb-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        .disb-table thead {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #fff;
        }
        .disb-table th {
            padding: 10px 16px;
            text-align: left;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--jf-text-light);
            border-bottom: 1px solid var(--jf-border-soft);
            white-space: nowrap;
        }
        .disb-table th:last-child { text-align: right; }
        .disb-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f4f5f7;
            color: var(--jf-text-muted);
            vertical-align: middle;
        }
        .disb-table tbody tr:hover td { background: rgba(250, 251, 252, 0.9); }
        .disb-table tbody tr:last-child td { border-bottom: none; }

        .disb-student-cell { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .disb-avatar {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: rgba(230, 0, 0, 0.08);
            color: var(--jf-brand);
            font-weight: 800;
            font-size: 0.72rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .disb-student-name {
            display: block;
            font-weight: 600;
            color: var(--jf-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 160px;
        }
        .disb-student-email {
            display: block;
            font-size: 0.76rem;
            color: var(--jf-text-light);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 160px;
        }
        .disb-campaign {
            display: block;
            font-weight: 600;
            color: var(--jf-text);
            max-width: 180px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .disb-milestone {
            display: block;
            font-size: 0.76rem;
            color: var(--jf-text-light);
            max-width: 180px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .disb-date { white-space: nowrap; font-size: 0.84rem; }
        .disb-date time { display: block; font-size: 0.72rem; color: var(--jf-text-light); }
        .disb-mpesa { font-family: ui-monospace, Consolas, monospace; font-size: 0.82rem; }
        .disb-amount {
            font-weight: 800;
            color: var(--jf-success);
            white-space: nowrap;
        }

        .disb-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }
        .disb-status.is-confirmed {
            border-color: #a7f3d0;
            background: #ecfdf5;
            color: #047857;
        }
        .disb-status.is-awaiting {
            border-color: #fde68a;
            background: #fffbeb;
            color: #b45309;
        }
        .disb-status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .disb-status.is-confirmed .disb-status-dot { background: #10b981; }
        .disb-status.is-awaiting .disb-status-dot { background: #f59e0b; }

        .disb-actions { text-align: right; white-space: nowrap; }
        .disb-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--jf-border);
            background: #fff;
            color: var(--jf-text-muted);
            text-decoration: none;
            transition: border-color 0.15s, color 0.15s, background 0.15s;
        }
        .disb-action-btn:hover {
            border-color: var(--jf-brand);
            color: var(--jf-brand);
            background: rgba(230, 0, 0, 0.04);
        }

        .disb-foot {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 16px;
            border-top: 1px solid var(--jf-border-soft);
            background: #fafbfc;
        }
        .disb-foot-meta {
            font-size: 0.72rem;
            color: var(--jf-text-light);
        }
        .disb-pagination {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            align-items: center;
        }
        .disb-page-link {
            min-width: 2rem;
            padding: 4px 8px;
            text-align: center;
            font-size: 0.78rem;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            color: var(--jf-text-muted);
            border: 1px solid transparent;
        }
        .disb-page-link:hover {
            background: #fff;
            border-color: var(--jf-border);
            color: var(--jf-text);
        }
        .disb-page-link.is-active {
            background: var(--jf-brand);
            color: #fff;
            border-color: var(--jf-brand);
        }
        .disb-page-link.is-disabled {
            color: #ccc;
            pointer-events: none;
        }

        .disb-empty {
            padding: 48px 24px;
            text-align: center;
        }
        .disb-empty-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 16px;
            border-radius: 50%;
            background: rgba(230, 0, 0, 0.08);
            color: var(--jf-brand);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }

        .jf-print-header { display: none; }
        @media print {
            .no-print { display: none !important; }
            body.jf-app { background: #fff; }
            .disb-table-scroll { max-height: none; overflow: visible; }
            .disb-filters, .disb-foot, .disb-actions { display: none !important; }
        }
        @media (max-width: 900px) {
            .disb-stats { grid-template-columns: 1fr; }
            .disb-field-sm { width: 100%; }
        }
    </style>
</head>
<body class="jf-app">
<?php
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/notification.php';
?>

<div class="jf-page jf-page-wide">
    <div class="jf-page-header-row jf-page-header no-print">
        <div>
            <h1>Disbursement Records</h1>
            <p>Escrow release audit trail for milestone and full payouts marked as paid.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="button" class="jf-btn jf-btn-brand" onclick="window.print()">
                <i class="fa-solid fa-file-pdf"></i> Export PDF
            </button>
            <a href="dashboard.php" class="jf-btn jf-btn-outline"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
        </div>
    </div>

    <div class="jf-print-header">
        <h1>JengaFund — Disbursement Records</h1>
        <p>Generated <?= date('M j, Y g:i A') ?> · <?= $total ?> record(s) · Total KES <?= number_format($totalDisbursed, 2) ?></p>
    </div>

    <div class="disb-stats no-print">
        <div class="disb-stat">
            <div class="disb-stat-label">Records<?= $hasFilters ? ' (filtered)' : '' ?></div>
            <div class="disb-stat-value"><?= number_format($total) ?></div>
        </div>
        <div class="disb-stat">
            <div class="disb-stat-label">Total disbursed</div>
            <div class="disb-stat-value">KES <?= number_format($totalDisbursed, 2) ?></div>
        </div>
        <div class="disb-stat">
            <div class="disb-stat-label">Confirmed</div>
            <div class="disb-stat-value"><?= $confirmedCount ?><span style="font-size:0.9rem;color:var(--jf-text-muted);"> / <?= $total ?></span></div>
        </div>
    </div>

    <div class="disb-shell">
        <form method="get" action="disbursement_records.php" class="disb-filters no-print" id="disbFilterForm">
            <div class="disb-field disb-field-grow">
                <label for="disb-search">Search</label>
                <div class="disb-input-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" id="disb-search" name="search" class="disb-input"
                           value="<?= htmlspecialchars($filters['search']) ?>"
                           placeholder="Student, campaign, milestone, M-PESA…">
                </div>
            </div>
            <div class="disb-field disb-field-sm">
                <label for="disb-status">Status</label>
                <select id="disb-status" name="status" class="disb-input">
                    <option value="" <?= $filters['status'] === '' ? 'selected' : '' ?>>All</option>
                    <option value="confirmed" <?= $filters['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="awaiting" <?= $filters['status'] === 'awaiting' ? 'selected' : '' ?>>Awaiting</option>
                </select>
            </div>
            <div class="disb-field disb-field-sm">
                <label for="disb-from">From</label>
                <input type="date" id="disb-from" name="from" class="disb-input"
                       value="<?= htmlspecialchars($filters['from']) ?>">
            </div>
            <div class="disb-field disb-field-sm">
                <label for="disb-to">To</label>
                <input type="date" id="disb-to" name="to" class="disb-input"
                       value="<?= htmlspecialchars($filters['to']) ?>">
            </div>
            <?php if ($hasFilters): ?>
                <a href="disbursement_records.php" class="jf-btn jf-btn-outline" style="padding:8px 12px;font-size:0.82rem;">
                    <i class="fa-solid fa-rotate-left"></i> Clear
                </a>
            <?php endif; ?>
        </form>

        <?php if ($total === 0): ?>
            <div class="disb-empty">
                <div class="disb-empty-icon"><i class="fa-solid fa-file-circle-xmark"></i></div>
                <h3 style="margin:0 0 8px;font-size:1rem;">No records found</h3>
                <p class="jf-hint" style="max-width:360px;margin:0 auto;">
                    <?= $hasFilters
                        ? 'Try changing your filters or clear them to see all disbursement records.'
                        : 'Records appear when an admin marks a milestone as paid.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="disb-table-scroll">
                <table class="disb-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Campaign</th>
                            <th>Date paid</th>
                            <th>M-PESA</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $row): ?>
                        <tr>
                            <td>
                                <div class="disb-student-cell">
                                    <span class="disb-avatar"><?= htmlspecialchars(disbInitials($row['student_name'])) ?></span>
                                    <span>
                                        <span class="disb-student-name" title="<?= htmlspecialchars($row['student_name']) ?>">
                                            <?= htmlspecialchars($row['student_name']) ?>
                                        </span>
                                        <span class="disb-student-email" title="<?= htmlspecialchars($row['student_email']) ?>">
                                            <?= htmlspecialchars($row['student_email']) ?>
                                        </span>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="disb-campaign" title="<?= htmlspecialchars($row['campaign_title']) ?>">
                                    <?= htmlspecialchars($row['campaign_title']) ?>
                                </span>
                                <span class="disb-milestone" title="<?= htmlspecialchars($row['milestone_title']) ?>">
                                    <?= htmlspecialchars($row['milestone_title']) ?>
                                </span>
                            </td>
                            <td class="disb-date">
                                <?= date('M j, Y', strtotime($row['disbursed_at'])) ?>
                                <time><?= date('g:i A', strtotime($row['disbursed_at'])) ?> · <?= htmlspecialchars($row['admin_name'] ?? 'Admin') ?></time>
                            </td>
                            <td class="disb-mpesa"><?= htmlspecialchars($row['mpesa_number'] ?? '—') ?></td>
                            <td class="disb-amount">KES <?= number_format((float) $row['disbursed_amount'], 2) ?></td>
                            <td>
                                <?php if (!empty($row['student_confirmed'])): ?>
                                    <span class="disb-status is-confirmed" title="Confirmed <?= date('M j, Y g:i A', strtotime($row['student_confirmed_at'])) ?>">
                                        <span class="disb-status-dot"></span> Confirmed
                                    </span>
                                <?php else: ?>
                                    <span class="disb-status is-awaiting">
                                        <span class="disb-status-dot"></span> Awaiting
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="disb-actions no-print">
                                <a href="disbursement_record.php?milestone_id=<?= (int) $row['milestone_id'] ?>"
                                   class="disb-action-btn" target="_blank" rel="noopener" title="View record">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="disb-foot no-print">
                <span class="disb-foot-meta">
                    Showing <?= $total === 0 ? 0 : ($offset + 1) ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?> records
                </span>
                <?php if ($totalPages > 1): ?>
                <nav class="disb-pagination" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a class="disb-page-link" href="<?= htmlspecialchars(disbFilterQuery($filters, $page - 1)) ?>">&lsaquo;</a>
                    <?php else: ?>
                        <span class="disb-page-link is-disabled">&lsaquo;</span>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a class="disb-page-link <?= $p === $page ? 'is-active' : '' ?>"
                           href="<?= htmlspecialchars(disbFilterQuery($filters, $p)) ?>"><?= $p ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a class="disb-page-link" href="<?= htmlspecialchars(disbFilterQuery($filters, $page + 1)) ?>">&rsaquo;</a>
                    <?php else: ?>
                        <span class="disb-page-link is-disabled">&rsaquo;</span>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('disbFilterForm');
    if (!form) return;

    const search = document.getElementById('disb-search');
    const status = document.getElementById('disb-status');
    const from = document.getElementById('disb-from');
    const to = document.getElementById('disb-to');
    let debounceTimer = null;

    function submitFilters() {
        form.requestSubmit ? form.requestSubmit() : form.submit();
    }

    if (search) {
        search.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(submitFilters, 350);
        });
    }

    [status, from, to].forEach(function (el) {
        if (el) el.addEventListener('change', submitFilters);
    });
})();
</script>
</body>
</html>
