<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized access.');
}

/** @var PDO $pdo */
$pdo = require_once '../../config/database.php';
require_once '../../backend/notification_helper.php';
require_once '../../backend/milestone_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: campaign_approvals.php');
    exit;
}

$campaignId = (int) ($_POST['campaign_id'] ?? 0);
$action = $_POST['action'] ?? '';
$adminId = (int) $_SESSION['user_id'];
$milestoneId = (int) ($_POST['milestone_id'] ?? 0);
$redirect = 'campaign_view.php?id=' . $campaignId;

if ($campaignId <= 0) {
    $_SESSION['error_message'] = 'Invalid campaign.';
    header('Location: campaign_approvals.php');
    exit;
}

function fetchCampaign(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT c.*, u.full_name AS student_name FROM campaigns c JOIN users u ON c.student_id = u.id WHERE c.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function checkAllMilestonesDisbursed(PDO $pdo, int $campaignId): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM milestones WHERE campaign_id = ? AND status != 'disbursed'");
    $stmt->execute([$campaignId]);
    return (int) $stmt->fetchColumn() === 0;
}

function maybeCompleteCampaign(PDO $pdo, int $campaignId): void
{
    if (checkAllMilestonesDisbursed($pdo, $campaignId)) {
        $pdo->prepare("UPDATE campaigns SET status = 'completed' WHERE id = ?")->execute([$campaignId]);
    }
}

try {
    $campaign = fetchCampaign($pdo, $campaignId);
    if (!$campaign) {
        $_SESSION['error_message'] = 'Campaign not found.';
        header('Location: campaign_approvals.php');
        exit;
    }

    $pdo->beginTransaction();

    if ($action === 'ban') {
        $pdo->prepare("UPDATE campaigns SET status = 'cancelled' WHERE id = ?")->execute([$campaignId]);
        notifyUser(
            $pdo,
            (int) $campaign['student_id'],
            'project_update',
            'Your campaign "' . $campaign['title'] . '" has been deactivated by an administrator.',
            $campaignId
        );
        $pdo->commit();
        $_SESSION['success_message'] = 'Campaign has been banned (deactivated).';
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'reactivate') {
        $newStatus = (strtotime($campaign['starts_at']) <= time()) ? 'active' : 'approved';
        $pdo->prepare('UPDATE campaigns SET status = ? WHERE id = ?')->execute([$newStatus, $campaignId]);
        $pdo->commit();
        $_SESSION['success_message'] = 'Campaign reactivated.';
        header('Location: ' . $redirect);
        exit;
    }

    if ($milestoneId <= 0) {
        throw new RuntimeException('Milestone required for this action.');
    }

    $stmtMs = $pdo->prepare('SELECT * FROM milestones WHERE id = ? AND campaign_id = ?');
    $stmtMs->execute([$milestoneId, $campaignId]);
    $milestone = $stmtMs->fetch(PDO::FETCH_ASSOC);
    if (!$milestone) {
        throw new RuntimeException('Milestone not found.');
    }

    $fundsRaised = (float) $campaign['funds_raised'];
    $owedAmount = round($fundsRaised * ((float) $milestone['disbursement_percent'] / 100), 2);

    if ($action === 'approve_evidence') {
        $pdo->prepare("UPDATE milestones SET status = 'approved', evaluated_by = ?, evaluated_at = NOW() WHERE id = ?")
            ->execute([$adminId, $milestoneId]);
        notifyUser(
            $pdo,
            (int) $campaign['student_id'],
            'milestone_evaluated',
            'Milestone "' . $milestone['title'] . '" evidence was approved. KES ' . number_format($owedAmount, 2) . ' is ready for disbursement.',
            $milestoneId
        );
        $pdo->commit();
        $_SESSION['success_message'] = 'Milestone evidence approved.';
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'reject_evidence') {
        $notes = trim($_POST['evaluation_notes'] ?? 'Evidence rejected. Please resubmit.');
        $pdo->prepare("UPDATE milestones SET status = 'rejected', evaluated_by = ?, evaluated_at = NOW(), evaluation_notes = ? WHERE id = ?")
            ->execute([$adminId, $notes, $milestoneId]);
        notifyUser(
            $pdo,
            (int) $campaign['student_id'],
            'milestone_evaluated',
            'Milestone "' . $milestone['title'] . '" evidence was rejected. ' . $notes,
            $milestoneId
        );
        $pdo->commit();
        $_SESSION['success_message'] = 'Milestone evidence rejected.';
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'mark_paid') {
        $goalMet = $fundsRaised >= (float) $campaign['goal_amount'];
        $isFullPayout = $campaign['disbursement_type'] === 'full';
        $canPayFromPending = $isFullPayout && $goalMet && $milestone['status'] === 'pending';

        if (!$canPayFromPending && !in_array($milestone['status'], ['approved', 'disbursed'], true)) {
            throw new RuntimeException('Milestone must be approved before marking as paid.');
        }

        if ($canPayFromPending) {
            $pdo->prepare("UPDATE milestones SET status = 'approved', evaluated_by = ?, evaluated_at = NOW() WHERE id = ?")
                ->execute([$adminId, $milestoneId]);
        }

        $pdo->prepare("UPDATE milestones SET status = 'disbursed', disbursed_amount = ?, disbursed_at = NOW(), evaluated_by = COALESCE(evaluated_by, ?), evaluated_at = COALESCE(evaluated_at, NOW()) WHERE id = ?")
            ->execute([$owedAmount, $adminId, $milestoneId]);
        notifyUser(
            $pdo,
            (int) $campaign['student_id'],
            'disbursement_completed',
            'KES ' . number_format($owedAmount, 2) . ' has been disbursed for milestone "' . $milestone['title'] . '".',
            $milestoneId
        );
        maybeCompleteCampaign($pdo, $campaignId);
        $pdo->commit();
        try {
            alertStudentAfterMilestoneDisbursed($pdo, $campaignId, $milestoneId);
        } catch (Throwable $unlockError) {
            error_log('Milestone unlock alert after disbursement failed: ' . $unlockError->getMessage());
        }
        $_SESSION['success_message'] = 'Milestone marked as paid (KES ' . number_format($owedAmount, 2) . ').';
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'mark_unpaid') {
        $pdo->prepare("UPDATE milestones SET status = 'approved', disbursed_amount = NULL, disbursed_at = NULL WHERE id = ?")
            ->execute([$milestoneId]);
        $pdo->prepare("UPDATE campaigns SET status = 'awaiting_disbursement' WHERE id = ? AND status = 'completed'")
            ->execute([$campaignId]);
        $pdo->commit();
        $_SESSION['success_message'] = 'Milestone marked as unpaid.';
        header('Location: ' . $redirect);
        exit;
    }

    throw new RuntimeException('Unknown action.');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Campaign admin action error: ' . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: ' . $redirect);
    exit;
}
