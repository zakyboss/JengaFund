<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    $_SESSION['error_message'] = "Unauthorized access. Please log in as a student.";
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
require_once __DIR__ . '/../../backend/milestone_helper.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/notification.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php require_once __DIR__ . '/../../components/favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Campaign - JengaFund</title>
    <link rel="stylesheet" href="../../assets/css/app.css">
</head>
<body class="jf-app">
    <div class="jf-page">
        <a href="dashboard.php" class="jf-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

        <div class="jf-form-card">
            <div class="jf-form-header">
                <h1><i class="fa-solid fa-rocket" style="color: var(--jf-brand); margin-right: 8px;"></i> Launch Your Innovation</h1>
                <p>Tell your story, set your goal, and connect with donors who believe in student innovation.</p>
            </div>

            <form action="../../backend/create_campaign.php" method="POST">
                <div class="jf-form-group">
                    <label for="title">Project Title</label>
                    <input type="text" id="title" name="title" required placeholder="Give your project a catchy name">
                </div>

                <div class="jf-form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" required>
                        <option value="" disabled selected>Select a category</option>
                        <option value="Technology">Technology</option>
                        <option value="Agriculture">Agriculture</option>
                        <option value="Health">Health</option>
                        <option value="Education">Education</option>
                        <option value="Social Impact">Social Impact</option>
                        <option value="Membership Fees">Membership Fees</option>
                    </select>
                </div>

                <div class="jf-form-group">
                    <label for="goal_amount">Funding Goal (KES)</label>
                    <input type="number" id="goal_amount" name="goal_amount" step="0.01" min="1" required placeholder="e.g. 10">
                    <p class="jf-hint">Goals under KES <?php echo number_format(milestoneFullPayoutThreshold(), 0); ?> are paid out in one lump sum when complete. KES <?php echo number_format(milestoneFullPayoutThreshold(), 0); ?> and above use milestone payouts.</p>
                </div>

                <div class="jf-form-row">
                    <div class="jf-form-group">
                        <label for="starts_at">Campaign Start</label>
                        <input type="datetime-local" id="starts_at" name="starts_at" required>
                    </div>
                    <div class="jf-form-group">
                        <label for="ends_at">Campaign End</label>
                        <input type="datetime-local" id="ends_at" name="ends_at" required>
                    </div>
                </div>

                <div class="jf-form-group">
                    <label for="description">Campaign Description</label>
                    <textarea id="description" name="description" required placeholder="What are you building? Why does it matter?"></textarea>
                </div>

                <button type="submit" class="jf-form-submit">Submit for Approval</button>
            </form>
        </div>
    </div>
</body>
</html>
