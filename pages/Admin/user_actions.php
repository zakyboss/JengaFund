<?php
session_start();
require_once '../../config/database.php';
require_once '../../backend/notification_helper.php';

// Ensure admin session is valid (mock check)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];
    $admin_id = $_SESSION['user_id'] ?? 1; // Fallback for demo

    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE users SET 
            approval_status = 'approved', 
            reviewed_by = ?, 
            reviewed_at = CURRENT_TIMESTAMP 
            WHERE id = ? AND approval_status = 'pending'");
        $stmt->execute([$admin_id, $user_id]);

        notifyUser($pdo, (int) $user_id, 'account_approved', 'Your JengaFund account has been approved. You can now use the platform.', (int) $user_id);
        
        $_SESSION['success_message'] = "Account approved successfully.";
        header("Location: user_view.php?id=$user_id");
        exit;
    } 
    
    if ($action === 'reject') {
        $reason = trim($_POST['rejection_reason'] ?? '');
        if (empty($reason)) {
            header("Location: user_view.php?id=$user_id&error=reason_required");
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE users SET 
            approval_status = 'rejected', 
            reviewed_by = ?, 
            reviewed_at = CURRENT_TIMESTAMP 
            WHERE id = ? AND approval_status = 'pending'");
        $stmt->execute([$admin_id, $user_id]);

        $stmtNotify = $pdo->prepare("INSERT INTO notifications (user_id, type, message, related_entity_id) VALUES (?, 'account_rejected', ?, ?)");
        $stmtNotify->execute([$user_id, "Your account has been rejected. Reason: " . $reason, $user_id]);
        
        $_SESSION['success_message'] = "Account rejected.";
        header("Location: user_view.php?id=$user_id");
        exit;
    }

    if ($action === 'delete') {
        // Soft delete: Update is_active and deleted_at
        $stmt = $pdo->prepare("UPDATE users SET 
            is_active = 0, 
            deleted_at = CURRENT_TIMESTAMP 
            WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $_SESSION['success_message'] = "User deleted successfully.";
        header("Location: users.php");
        exit;
    }
}

header("Location: users.php");
exit;