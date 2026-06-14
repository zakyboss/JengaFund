<?php
session_start();
/** @var PDO $pdo */
$pdo = require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs and handle "backspaces" (trimming whitespace)
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password']; // Don't trim passwords as spaces might be intentional

    // Backend Validation
    $errors = [];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email address is required.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode(" ", $errors);
        header("Location: ../pages/login.php");
        exit();
    }

    try {
        // Fetch user - check is_active to prevent disabled accounts from logging in
        $stmt = $pdo->prepare("SELECT id, password_hash, full_name, role, email_verified_at FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Check if email is verified
            if ($user['email_verified_at'] === null) {
                $_SESSION['error_message'] = "Please verify your email before logging in.";
                header("Location: ../backend/verification.php?email=" . urlencode($email));
                exit();
            }

            // Regenerate session ID for security (prevents session fixation)
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $email; // Store email for the sidebar profile
            $_SESSION['last_activity'] = time(); // Record login time
            $_SESSION['success_message'] = "Welcome back, " . htmlspecialchars($user['full_name']) . "!";

            // Role-based redirection
            if ($user['role'] === 'student') {
                header("Location: ../pages/student/dashboard.php");
            } elseif ($user['role'] === 'admin') {
                header("Location: ../pages/Admin/dashboard.php");
            } else {
                header("Location: ../pages/donor/dashboard.php");
            }
            exit();
        } else {
            $_SESSION['error_message'] = "Invalid email or password.";
            header("Location: ../pages/login.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Login Error: " . $e->getMessage());
        exit("A system error occurred. Please try again later.");
    }
}