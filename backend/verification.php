<?php
session_start();
/** @var PDO $pdo */
$pdo = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../components/notification.php';

$email_val = $_GET['email'] ?? ($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $code  = trim($_POST['code']);

    try {
        // Fetch the most recent unverified code for this email
        $stmt = $pdo->prepare("
            SELECT ev.*, u.id as user_id
            FROM email_verifications ev
            JOIN users u ON ev.user_id = u.id
            WHERE u.email = ? AND ev.verified_at IS NULL
            ORDER BY ev.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $verification = $stmt->fetch();

        if (!$verification) {
            $_SESSION['error_message'] = "No pending verification found for this email.";

        } elseif ($verification['code'] !== $code) {
            $_SESSION['error_message'] = "The code you entered is incorrect.";

        } elseif (strtotime($verification['expires_at']) < time()) {
            $_SESSION['error_message'] = "This verification code has expired. Please sign up again.";

        } else {
            $pdo->beginTransaction();

            // Mark email as verified and activate account
            $pdo->prepare("UPDATE users SET email_verified_at = NOW(), is_active = 1 WHERE id = ?")
                ->execute([$verification['user_id']]);

            // Mark verification code as used
            $pdo->prepare("UPDATE email_verifications SET verified_at = NOW() WHERE id = ?")
                ->execute([$verification['id']]);

            $pdo->commit();
            $_SESSION['success_message'] = "Email verified successfully! You can now login.";
            header("Location: ../pages/login.php");
            exit();
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        $_SESSION['error_message'] = "A system error occurred.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Account - JengaFund</title>
    <style>
        body {
            background: #f8f9fa;
            font-family: Arial;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .verify-box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1rem;
        }
        .btn-verify {
            width: 100%;
            padding: 12px;
            background: red;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-verify:hover { background: #cc0000; }
        .error   { background: #ffe5e5; color: #cc0000; padding: 10px; border-radius: 6px; margin-bottom: 12px; font-size: 0.9rem; }
        .success { background: #e5ffe5; color: #006600; padding: 10px; border-radius: 6px; margin-bottom: 12px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="verify-box">
        <h2>Verify Your Email</h2>
        <p style="color: #666; font-size: 0.9rem;">Enter the 6-digit code sent to your email.</p>

        <?php if (!empty($_SESSION['error_message'])): ?>
            <div class="error"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['success_message'])): ?>
            <div class="success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <form method="POST" action="verification.php">
            <input type="email" name="email" value="<?= htmlspecialchars($email_val) ?>" required placeholder="Email Address">
            <input type="text" name="code" required placeholder="6-digit Code" maxlength="6" pattern="\d{6}">
            <button type="submit" class="btn-verify">Verify Account</button>
        </form>

        <p style="margin-top: 20px; font-size: 0.85rem;">
            <a href="../pages/login.php" style="color: #999; text-decoration: none;">Back to Login</a>
        </p>
    </div>
</body>
</html>