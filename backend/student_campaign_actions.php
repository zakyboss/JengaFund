<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    die('Unauthorized access.');
}

/** @var PDO $pdo */
$pdo = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/notification_helper.php';
require_once __DIR__ . '/milestone_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/Student/dashboard.php');
    exit;
}

$action = $_POST['action'] ?? '';
$campaignId = (int) ($_POST['campaign_id'] ?? 0);
$studentId = (int) $_SESSION['user_id'];
$redirect = '../pages/Student/campaign_view.php?id=' . $campaignId;

if ($campaignId <= 0) {
    $_SESSION['error_message'] = 'Invalid campaign.';
    header('Location: ../pages/Student/campaigns.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM campaigns WHERE id = ? AND student_id = ?');
$stmt->execute([$campaignId, $studentId]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    $_SESSION['error_message'] = 'Campaign not found.';
    header('Location: ../pages/Student/campaigns.php');
    exit;
}

try {
    if ($action === 'update_details') {
        if (!canStudentEditCampaign($campaign)) {
            throw new RuntimeException('This campaign can no longer be edited.');
        }

        $title = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $category = htmlspecialchars(trim($_POST['category'] ?? ''), ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');

        if ($title === '' || $category === '' || $description === '') {
            throw new RuntimeException('Title, category, and description are required.');
        }

        $pdo->prepare('
            UPDATE campaigns
            SET title = ?, category = ?, description = ?, updated_at = NOW()
            WHERE id = ? AND student_id = ?
        ')->execute([$title, $category, $description, $campaignId, $studentId]);

        $_SESSION['success_message'] = 'Campaign details updated.';
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'submit_evidence') {
        $milestoneId = (int) ($_POST['milestone_id'] ?? 0);
        $notes = trim($_POST['evidence_notes'] ?? '');

        $stmtMs = $pdo->prepare('SELECT * FROM milestones WHERE id = ? AND campaign_id = ?');
        $stmtMs->execute([$milestoneId, $campaignId]);
        $milestone = $stmtMs->fetch(PDO::FETCH_ASSOC);

        if (!$milestone) {
            throw new RuntimeException('Milestone not found.');
        }

        $stmtAll = $pdo->prepare('SELECT * FROM milestones WHERE campaign_id = ? ORDER BY sequence_order ASC');
        $stmtAll->execute([$campaignId]);
        $milestones = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        $index = null;
        foreach ($milestones as $i => $row) {
            if ((int) $row['id'] === $milestoneId) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            throw new RuntimeException('Milestone not found.');
        }

        $unlocked = isMilestoneUnlocked($campaign, $milestones, $index);
        if (!canStudentSubmitEvidence($campaign, $milestone, $unlocked)) {
            throw new RuntimeException('You cannot submit evidence for this milestone yet.');
        }

        if (!isset($_FILES['evidence_file']) || $_FILES['evidence_file']['error'] === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Please attach a photo or PDF as evidence.');
        }

        $filePath = saveMilestoneEvidenceFile($_FILES['evidence_file'], $campaignId, $milestoneId);

        $pdo->prepare("
            UPDATE milestones
            SET status = 'evidence_submitted',
                evidence_file_path = ?,
                evidence_notes = ?,
                evidence_submitted_at = NOW(),
                evaluation_notes = NULL,
                updated_at = NOW()
            WHERE id = ? AND campaign_id = ?
        ")->execute([$filePath, $notes !== '' ? $notes : null, $milestoneId, $campaignId]);

        notifyAllAdmins(
            $pdo,
            'project_update',
            'New milestone evidence for "' . $campaign['title'] . '" — ' . $milestone['title'],
            $milestoneId
        );

        $_SESSION['success_message'] = 'Evidence submitted. An admin will review it soon.';
        header('Location: ' . $redirect);
        exit;
    }

    throw new RuntimeException('Unknown action.');
} catch (Throwable $e) {
    error_log('student_campaign_actions: ' . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: ' . $redirect);
    exit;
}
