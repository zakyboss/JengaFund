<?php
session_start();

// Redirect if not logged in or not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "Access denied. Admin login required.";
    header("Location: ../login.php");
    exit();
}

require_once '../../config/database.php';

$role_filter = $_GET['role'] ?? '';
$params = [];
$sql = "SELECT u.id, u.full_name, u.email, u.role, u.created_at, u.approval_status 
        FROM users u 
        WHERE u.deleted_at IS NULL";

if (in_array($role_filter, ['student', 'donor'])) {
    $sql .= " AND u.role = ?";
    $params[] = $role_filter;
}

$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../../components/favicon.php'; ?>
    <title>User Management - JengaFund</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <?php 
    require_once '../../components/sidebar.php';
    require_once '../../components/notification.php'; 
    ?>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>User Management</h2>
            <form method="GET" class="d-flex gap-2">
                <select name="role" class="form-select" onchange="this.form.submit()">
                    <option value="">All Roles</option>
                    <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Students</option>
                    <option value="donor" <?= $role_filter === 'donor' ? 'selected' : '' ?>>Donors</option>
                </select>
            </form>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Approval Status</th>
                            <th>Joined At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= ucfirst($user['role']) ?></td>
                            <td>
                                <span class="badge bg-<?= ($user['approval_status'] === 'approved') ? 'success' : (($user['approval_status'] === 'rejected') ? 'danger' : 'warning') ?>"><?= ucfirst($user['approval_status'] ?? 'pending') ?></span>
                            </td>
                            <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                            <td>
                                <a href="user_view.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                <form action="user_actions.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="6" class="text-center text-muted">No users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>