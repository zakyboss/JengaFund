<?php
session_start();

// Redirect if not logged in or not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "Access denied. Admin login required.";
    header("Location: ../login.php");
    exit();
}

require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;
$sql = "SELECT u.*, sp.mpesa_number, sp.kcse_certificate_path, sp.id_photo_path 
        FROM users u 
        LEFT JOIN student_profiles sp ON u.id = sp.user_id 
        WHERE u.id = ? AND u.deleted_at IS NULL";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../../components/favicon.php'; ?>
    <title>View User - <?= htmlspecialchars($user['full_name']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <?php 
    require_once '../../components/sidebar.php';
    require_once '../../components/notification.php'; 
    ?>
    <div class="container mt-5">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="users.php">User Management</a></li>
                <li class="breadcrumb-item active">View User</li>
            </ol>
        </nav>

        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Profile Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-4 fw-bold">Full Name:</div>
                            <div class="col-sm-8"><?= htmlspecialchars($user['full_name']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 fw-bold">Email Address:</div>
                            <div class="col-sm-8"><?= htmlspecialchars($user['email']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 fw-bold">Phone Number:</div>
                            <div class="col-sm-8"><?= htmlspecialchars($user['phone_number'] ?? 'N/A') ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 fw-bold">Role:</div>
                            <div class="col-sm-8 text-uppercase small"><?= ucfirst($user['role']) ?></div>
                        </div>

                        <?php if ($user['role'] === 'student'): ?>
                            <hr>
                            <h6 class="text-muted">Student Verification Info</h6>
                            <div class="row mb-3">
                                <div class="col-sm-4 fw-bold">M-Pesa Number:</div>
                                <div class="col-sm-8"><?= htmlspecialchars($user['mpesa_number'] ?? 'N/A') ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-4 fw-bold">Documents:</div>
                                <div class="col-sm-8 d-flex flex-column gap-2">
                                    <?php if ($user['kcse_certificate_path']): ?>
                                        <a href="<?= $base_url . '/' . $user['kcse_certificate_path'] ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center">
                                            <i class="fa-solid fa-file-pdf me-2 text-danger"></i> KCSE Certificate
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($user['id_photo_path']): ?>
                                        <a href="<?= $base_url . '/' . $user['id_photo_path'] ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center">
                                            <i class="fa-solid fa-id-card me-2 text-primary"></i> ID Photo
                                        </a>
                                    <?php endif; ?>
                                    <?php if (empty($user['kcse_certificate_path']) && empty($user['id_photo_path'])): ?>
                                        <span class="text-muted">No documents uploaded.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm border-<?= ($user['approval_status'] === 'approved') ? 'success' : (($user['approval_status'] === 'rejected') ? 'danger' : 'warning') ?>">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">Account Action</h5>
                    </div>
                    <div class="card-body">
                        <p>Current Status: <strong><?= ucfirst($user['approval_status'] ?? 'pending') ?></strong></p>
                        
                        <?php if ($user['approval_status'] === 'pending'): ?>
                            <form action="user_actions.php" method="POST">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <div class="d-grid gap-2 mb-3">
                                    <button type="submit" name="action" value="approve" class="btn btn-success">Approve Account</button>
                                </div>
                                <hr>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Rejection Reason (required for reject)</label>
                                    <textarea name="rejection_reason" class="form-control" rows="3"></textarea>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" name="action" value="reject" class="btn btn-danger">Reject Account</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-light border small">
                                Actions are disabled as this account has already been processed.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>