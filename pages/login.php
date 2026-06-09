<?php 
session_start();

// Check if user is already logged in and session is active (30 minutes = 1800 seconds)
if (isset($_SESSION['user_id'], $_SESSION['last_activity'])) {
    $inactivity_limit = 1800; 
    if ((time() - $_SESSION['last_activity']) <= $inactivity_limit) {
        // Session is still valid, redirect to respective dashboard
        if ($_SESSION['role'] === 'student') {
            header("Location: student/dashboard.php");
        } elseif ($_SESSION['role'] === 'donor') {
            header("Location: donor/dashboard.php");
        }
        exit();
    } else {
        // Session expired
        session_unset();
        session_destroy();
        session_start(); // Restart for fresh flash messages
    }
}
?>
<!DOCTYPE html>
<?php require_once __DIR__ . '/../components/notification.php'; ?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - JengaFund</title>
    <style>
        body { background-color: #f8f9fa; display: flex; flex-direction: column; min-height: 100vh; }
        .login-container { max-width: 400px; margin: 80px auto; background: white; padding: 30px; border-radius: 8px; border: 1px solid #e0e0e0; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { text-align: center; color: #333; margin-bottom: 25px; }
        .form-group { margin-bottom: 15px; position: relative; }
        label { display: block; margin-bottom: 5px; font-size: 0.9rem; color: #666; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
        .btn-submit { width: 100%; padding: 12px; background: red; color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; margin-top: 10px; transition: background 0.3s; }
        .btn-submit:hover { background: #cc0000; }
        .toggle-pass { position: absolute; right: 10px; top: 35px; cursor: pointer; color: #999; font-size: 0.8rem; }
        .error-msg { color: red; font-size: 0.85rem; margin-top: 5px; display: none; }
        .signup-link { text-align: center; margin-top: 20px; font-size: 0.9rem; }
        .signup-link a { color: red; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login to JengaFund</h2>
        <form id="loginForm" action="../backend/login.php" method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" id="email" required placeholder="Enter your email">
                <div id="emailError" class="error-msg">Please enter a valid email address.</div>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" id="password" required minlength="8">
                <span class="toggle-pass" onclick="togglePassword()">SHOW</span>
                <div id="passError" class="error-msg">Password must be at least 8 characters.</div>
            </div>
            <button type="submit" class="btn-submit">Login</button>
        </form>
        <div class="signup-link">
            Don't have an account? <a href="signup.php">Sign Up</a>
            <p style="margin-top: 15px;"><a href="index.php" style="color: #666; font-size: 0.8rem; font-weight: 400;">Back to Home</a></p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-pass');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                toggleBtn.textContent = 'HIDE';
            } else {
                passInput.type = 'password';
                toggleBtn.textContent = 'SHOW';
            }
        }

        document.getElementById('loginForm').onsubmit = function(e) {
            let valid = true;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            if (password.length < 8) {
                document.getElementById('passError').style.display = 'block';
                valid = false;
            } else {
                document.getElementById('passError').style.display = 'none';
            }

            if (!valid) e.preventDefault();
        };
    </script>
</body>
</html>