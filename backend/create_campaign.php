<?php
session_start();

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    $_SESSION['error_message'] = "Unauthorized access. Please log in as a student.";
    header("Location: ../pages/login.php");
    exit();
}

// Check for session timeout (30 minutes)
if (time() - $_SESSION['last_activity'] > 1800) {
    session_unset();
    session_destroy();
    header("Location: ../pages/login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time(); // Update activity timestamp

/** @var PDO $pdo */
$pdo = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/notification_helper.php';
require_once __DIR__ . '/milestone_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitization & Validation
    $title = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $category = htmlspecialchars(trim($_POST['category'] ?? ''), ENT_QUOTES, 'UTF-8');
    $goal_amount = floatval($_POST['goal_amount'] ?? 0);
    $starts_at = trim($_POST['starts_at'] ?? '');
    $ends_at = trim($_POST['ends_at'] ?? '');
    $description = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
    $student_id = $_SESSION['user_id'];

    $errors = [];

    if (empty($title)) $errors[] = "Project title is required.";
    if (empty($category)) $errors[] = "Category is required.";
    if ($goal_amount < 1) $errors[] = "Funding goal must be at least KES 1.";
    if (empty($starts_at)) $errors[] = "Campaign start date is required.";
    if (empty($ends_at)) $errors[] = "Campaign end date is required.";
    if (empty($description)) $errors[] = "Campaign description is required.";

    $start_timestamp = strtotime($starts_at);
    $end_timestamp = strtotime($ends_at);

    if ($start_timestamp === false) {
        $errors[] = "Invalid start date format.";
    }
    if ($end_timestamp === false) {
        $errors[] = "Invalid end date format.";
    }
    if ($start_timestamp && $end_timestamp) {
        if ($end_timestamp <= $start_timestamp) {
            $errors[] = "Campaign end date must be after the start date.";
        }
        if ($end_timestamp < time()) {
            $errors[] = "Campaign end date cannot be in the past.";
        }
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode(" ", $errors);
        header("Location: ../pages/Student/create_campaign.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert Campaign
        $disbursement_type = campaignUsesMilestones($goal_amount) ? 'milestone' : 'full';
        $stmtCampaign = $pdo->prepare("
            INSERT INTO campaigns (student_id, title, description, category, goal_amount, disbursement_type, starts_at, ends_at, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval')
        ");
        $stmtCampaign->execute([
            $student_id,
            $title,
            $description,
            $category,
            $goal_amount,
            $disbursement_type,
            $starts_at,
            $ends_at
        ]);
        $campaign_id = $pdo->lastInsertId();

        // 2. Automatically generate default milestones
        if ($disbursement_type === 'full') {
            $stmtMilestone = $pdo->prepare("
                INSERT INTO milestones (campaign_id, title, description, sequence_order, disbursement_percent, status)
                VALUES (?, ?, ?, 1, 100.00, 'pending')
            ");
            $stmtMilestone->execute([
                $campaign_id,
                "Full Campaign Payout",
                "100% of the funds raised will be disbursed upon successful campaign completion."
            ]);
        } else {
            $stmtMilestone = $pdo->prepare("
                INSERT INTO milestones (campaign_id, title, description, sequence_order, disbursement_percent, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");

            // Milestone 1: 30%
            $stmtMilestone->execute([
                $campaign_id,
                "Project Initiation",
                "Initial release of 30% of the funds raised to kickstart project implementation.",
                1,
                30.00
            ]);

            // Milestone 2: 30%
            $stmtMilestone->execute([
                $campaign_id,
                "Mid-term Progress",
                "Release of 30% of the funds raised upon submitting evidence of key prototype milestones.",
                2,
                30.00
            ]);

            // Milestone 3: 40%
            $stmtMilestone->execute([
                $campaign_id,
                "Final Delivery & Handover",
                "Remaining release of 40% of the funds raised upon final project demonstration and verification.",
                3,
                40.00
            ]);
        }

        $notificationMsg = 'Your campaign "' . $title . '" has been submitted and is pending admin approval.';
        notifyUser($pdo, (int) $student_id, 'project_update', $notificationMsg, (int) $campaign_id);

        notifyAllAdmins(
            $pdo,
            'project_update',
            'New campaign pending approval: "' . $title . '"',
            (int) $campaign_id
        );

        $pdo->commit();

        $_SESSION['success_message'] = "Campaign submitted successfully! It is currently pending admin review.";
        header("Location: ../pages/Student/dashboard.php");
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Campaign Insertion Error: " . $e->getMessage());
        $_SESSION['error_message'] = "A database error occurred. Please try again.";
        header("Location: ../pages/Student/create_campaign.php");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Campaign Creation General Error: " . $e->getMessage());
        $_SESSION['error_message'] = "A system error occurred. Please try again.";
        header("Location: ../pages/Student/create_campaign.php");
        exit();
    }
} else {
    header("Location: ../pages/Student/create_campaign.php");
    exit();
}
