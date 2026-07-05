<?php
session_start();

// Ensure admin session is valid
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

/** @var PDO $pdo */
$pdo = require_once '../../config/database.php';
require_once '../../backend/code_generator.php';
require_once '../../backend/notification_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $admin_id = $_SESSION['user_id'];

    if ($campaign_id <= 0 || !in_array($action, ['approve', 'reject'])) {
        $_SESSION['error_message'] = "Invalid request parameters.";
        header("Location: campaign_approvals.php");
        exit();
    }

    try {
        // Fetch campaign and student details
        $stmtFetch = $pdo->prepare("
            SELECT c.*, u.email as student_email, u.full_name as student_name 
            FROM campaigns c 
            JOIN users u ON c.student_id = u.id 
            WHERE c.id = ? AND c.status = 'pending_approval'
            LIMIT 1
        ");
        $stmtFetch->execute([$campaign_id]);
        $campaign = $stmtFetch->fetch(PDO::FETCH_ASSOC);

        if (!$campaign) {
            $_SESSION['error_message'] = "Campaign not found or has already been reviewed.";
            header("Location: campaign_approvals.php");
            exit();
        }

        $pdo->beginTransaction();

        if ($action === 'approve') {
            // Determine starting status: 'active' if starts_at has already arrived, otherwise 'approved'
            $status = (strtotime($campaign['starts_at']) <= time()) ? 'active' : 'approved';

            $stmtUpdate = $pdo->prepare("
                UPDATE campaigns 
                SET status = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmtUpdate->execute([$status, $admin_id, $campaign_id]);

            // Add notification
            $msg = 'Your campaign "' . $campaign['title'] . '" has been approved by the administrator.';
            notifyUser($pdo, (int) $campaign['student_id'], 'campaign_approved', $msg, (int) $campaign_id);

            $pdo->commit();

            // Send notification email (outside transaction in case SMTP blocks/slows down)
            sendCampaignStatusEmail($campaign['student_email'], $campaign['title'], 'approved');

            $_SESSION['success_message'] = "Campaign approved successfully.";
            header("Location: campaign_approvals.php");
            exit();
        }

        if ($action === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? '');
            if (empty($reason)) {
                $_SESSION['error_message'] = "You must provide a rejection reason.";
                header("Location: campaign_approvals.php");
                exit();
            }

            $stmtUpdate = $pdo->prepare("
                UPDATE campaigns 
                SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmtUpdate->execute([$reason, $admin_id, $campaign_id]);

            // Add notification
            $msg = 'Your campaign "' . $campaign['title'] . '" has been rejected. Reason: ' . $reason;
            notifyUser($pdo, (int) $campaign['student_id'], 'campaign_rejected', $msg, (int) $campaign_id);

            $pdo->commit();

            // Send notification email (outside transaction)
            sendCampaignStatusEmail($campaign['student_email'], $campaign['title'], 'rejected', $reason);

            $_SESSION['success_message'] = "Campaign rejected.";
            header("Location: campaign_approvals.php");
            exit();
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Campaign Action Error: " . $e->getMessage());
        $_SESSION['error_message'] = "Database error processing campaign action.";
        header("Location: campaign_approvals.php");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Campaign Action General Error: " . $e->getMessage());
        $_SESSION['error_message'] = "System error processing campaign action.";
        header("Location: campaign_approvals.php");
        exit();
    }
} else {
    header("Location: campaign_approvals.php");
    exit();
}
