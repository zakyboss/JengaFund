<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<?php require_once __DIR__ . '/../components/notification.php'; ?>
<head>
    <meta charset="UTF-8">
    <title>Join JengaFund</title>
    <style>
        body { background-color: #f8f9fa; }
        .signup-container { max-width: 500px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; border: 1px solid #e0e0e0; }
        h2 { text-align: center; color: #333; }
        .role-selector { display: flex; gap: 10px; margin-bottom: 20px; justify-content: center; }
        .role-btn { padding: 10px 20px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; background: #eee; }
        .role-btn.active { background: red; color: white; border-color: red; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-size: 0.85rem; color: #666; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .student-fields { display: none; padding-top: 15px; border-top: 1px dashed #eee; margin-top: 15px; }
        .btn-submit { width: 100%; padding: 12px; background: red; color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="signup-container">
        <h2>Create Account</h2>
        <div class="role-selector">
            <div class="role-btn active" onclick="setRole('student')">Student</div>
            <div class="role-btn" onclick="setRole('donor')">Donor</div>
        </div>

        <form id="signupForm" action="../backend/signup.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="role" id="roleInput" value="student">
            
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" required>
            </div>
            <div class="form-group">
                <label>Email (.edu for students)</label>
                <input type="email" name="email" id="sEmail" required>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone_number" required>
            </div>
            <div class="form-group">
                <label>Password (Min 8 characters)</label>
                <input type="password" name="password" id="sPass" required minlength="8">
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" id="sConfirmPass" required>
            </div>

            <!-- Student Only Fields -->
            <div id="studentFields" class="student-fields" style="display: block;">
                <div class="form-group">
                    <label>M-Pesa Number</label>
                    <input type="text" name="mpesa_number">
                </div>
                <div class="form-group">
                    <label>KCSE Certificate (PDF/Image)</label>
                    <input type="file" name="kcse_cert">
                </div>
                <div class="form-group">
                    <label>ID Photo</label>
                    <input type="file" name="id_photo">
                </div>
            </div>

            <button type="submit" class="btn-submit">Register</button>

            <div style="text-align: center; margin-top: 20px; font-size: 0.9rem;">
                Already have an account? <a href="login.php" style="color: red; text-decoration: none; font-weight: 600;">Login</a><br><br>
                <a href="index.php" style="color: #666; font-size: 0.8rem; text-decoration: none;">Back to Home</a>
            </div>
        </form>
    </div>

    <script>
        function setRole(role) {
            document.getElementById('roleInput').value = role;
            document.querySelectorAll('.role-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById('studentFields').style.display = (role === 'student') ? 'block' : 'none';
        }

        document.getElementById('signupForm').onsubmit = function(e) {
            const role = document.getElementById('roleInput').value;
            const email = document.getElementById('sEmail').value;
            const password = document.getElementById('sPass').value;
            const confirmPassword = document.getElementById('sConfirmPass').value;

            if (role === 'student' && !email.toLowerCase().endsWith('.edu')) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Students must use a valid .edu email address.',
                    icon: 'error',
                    confirmButtonColor: 'red'
                });
                e.preventDefault();
                return;
            }

            if (password !== confirmPassword) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Passwords do not match.',
                    icon: 'error',
                    confirmButtonColor: 'red'
                });
                e.preventDefault();
                return;
            }
        };
    </script>
</body>
</html>