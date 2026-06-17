<?php
session_start();
/** @var PDO $pdo */
$pdo = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/code_generator.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitization
    $full_name = htmlspecialchars(trim($_POST['full_name']), ENT_QUOTES, 'UTF-8');
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars(trim($_POST['phone_number']), ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = in_array($_POST['role'], ['student', 'donor']) ? $_POST['role'] : 'student';

    // Validation
    $errors = [];
    if ($role === 'student' && !str_ends_with(strtolower($email), '.edu')) {
        $errors[] = "Students must use a valid .edu email address.";
    }
    
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    // Check if email already exists
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) $errors[] = "This email is already registered.";

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode(" ", $errors);
        header("Location: ../pages/signup.php");
        exit(); // Ensure script stops here
    }

    try {
        $pdo->beginTransaction();

        // 1. Create User
        $password_hash = password_hash($password, PASSWORD_DEFAULT); // Hash the password
        // Set is_active to 0 initially, will be set to 1 upon email verification
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role, full_name, phone_number, is_active) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute([$email, $password_hash, $role, $full_name, $phone]);
        $user_id = $pdo->lastInsertId();

        // 1.5 Generate and Save Verification Code
        $verification_code = generateVerificationCode();
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmtCode = $pdo->prepare("INSERT INTO email_verifications (user_id, code, expires_at) VALUES (?, ?, ?)");
        $stmtCode->execute([$user_id, $verification_code, $expires_at]);
        sendVerificationEmail($email, $verification_code);

        // 2. Initial Approval Entry
        $stmtApp = $pdo->prepare("INSERT INTO account_approvals (user_id, approval_status) VALUES (?, 'pending')");
        $stmtApp->execute([$user_id]);

        // 3. Handle Student-specific data
        if ($role === 'student') {
            $mpesa = htmlspecialchars(trim($_POST['mpesa_number'] ?? ''), ENT_QUOTES, 'UTF-8');
            
            // Secure File Upload Handling
            $upload_base = __DIR__ . '/../uploads/';
            $cert_path = null;
            $id_path = null;

            if (isset($_FILES['kcse_cert']) && $_FILES['kcse_cert']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['kcse_cert']['name'], PATHINFO_EXTENSION);
                $filename = 'cert_' . $user_id . '_' . time() . '.' . $ext;
                $target = $upload_base . 'certificates/' . $filename;
                if (move_uploaded_file($_FILES['kcse_cert']['tmp_name'], $target)) {
                    $cert_path = 'uploads/certificates/' . $filename;
                }
            }

            if (isset($_FILES['id_photo']) && $_FILES['id_photo']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['id_photo']['name'], PATHINFO_EXTENSION);
                $filename = 'id_' . $user_id . '_' . time() . '.' . $ext;
                $target = $upload_base . 'id_photos/' . $filename;
                if (move_uploaded_file($_FILES['id_photo']['tmp_name'], $target)) {
                    $id_path = 'uploads/id_photos/' . $filename;
                }
            }

            $stmtStu = $pdo->prepare("INSERT INTO students (user_id, mpesa_number, kcse_certificate_path, id_photo_path) VALUES (?, ?, ?, ?)");
            $stmtStu->execute([$user_id, $mpesa, $cert_path, $id_path]);
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Registration successful! Please check your email for a verification code.";
        header("Location: ../backend/verification.php?email=" . urlencode($email));
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Signup DB Error: " . $e->getMessage());
        // While developing, it's better to see the actual error
        exit("Database Error: " . $e->getMessage());
    } catch (Exception $e) {
        error_log("Signup General Error: " . $e->getMessage());
        exit("System Error: " . $e->getMessage());
    }
}