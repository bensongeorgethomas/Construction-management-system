<?php
// Load configuration & DB connection (handles session start and error reporting)
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/send_client_otp.php';
require_once __DIR__ . '/../includes/csrf.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $mobilenumber = trim($_POST['mobilenumber']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $gender = $_POST['gender'];
    $address = trim($_POST['address']);

    // Generate 6-digit OTP
    $otp = rand(100000, 999999);

    // Store data in session
    $_SESSION['pending_client'] = [
        'name' => $name,
        'email' => $email,
        'mobilenumber' => $mobilenumber,
        'password' => $password,
        'gender' => $gender,
        'address' => $address,
        'otp' => $otp
    ];

    // Send OTP
    if (sendClientOTP($email, $otp)) {
        header("Location: verify_client_otp.php");
        exit();
    } else {
        $error = "❌ Failed to send OTP. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Registration - Construct.</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="auth_style.css">
</head>
<body>
    <!-- Animated Background Particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <audio id="otpAudio" src="../welldone.mp3"></audio>

    <div class="auth-container">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-hard-hat"></i>
            </div>
            <h1 class="logo">Construct<span>.</span></h1>
            <p class="tagline">Build Your Future With Us</p>
        </div>

        <div class="card-header">
            <h2>Client Registration</h2>
            <p class="subtitle">Create your account to get started</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="registrationForm">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="name">
                    <i class="fas fa-user"></i>
                    Full Name
                </label>
                <div class="input-wrapper">
                    <input type="text" id="name" name="name" required placeholder="Enter your full name">
                    <span class="input-icon"><i class="fas fa-user"></i></span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i>
                    Email Address
                </label>
                <div class="input-wrapper">
                    <input type="email" id="email" name="email" required placeholder="your.email@example.com">
                    <span class="input-icon"><i class="fas fa-envelope"></i></span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="mobilenumber">
                    <i class="fas fa-phone"></i>
                    Mobile Number
                </label>
                <div class="input-wrapper">
                    <input type="text" id="mobilenumber" name="mobilenumber" required placeholder="+91 98765 43210">
                    <span class="input-icon"><i class="fas fa-phone"></i></span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i>
                    Password
                </label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" required placeholder="Create a strong password">
                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                    <button type="button" class="toggle-password" onclick="togglePassword('password')">
                        <i class="fas fa-eye" id="password-eye"></i>
                    </button>
                </div>
                <div class="password-strength" id="passwordStrength">
                    <div class="strength-bar"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="gender">
                    <i class="fas fa-venus-mars"></i>
                    Gender
                </label>
                <div class="input-wrapper">
                    <select id="gender" name="gender" required>
                        <option value="" disabled selected>Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                    <span class="input-icon"><i class="fas fa-venus-mars"></i></span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="address">
                    <i class="fas fa-map-marker-alt"></i>
                    Address
                </label>
                <div class="input-wrapper">
                    <input type="text" id="address" name="address" required placeholder="Enter your address">
                    <span class="input-icon"><i class="fas fa-map-marker-alt"></i></span>
                </div>
            </div>
            
            <button type="submit" id="submitBtn" class="btn">
                <span class="btn-text">Register & Send OTP</span>
                <span class="btn-icon"><i class="fas fa-arrow-right"></i></span>
                <div class="btn-ripple"></div>
            </button>
        </form>

        <div class="divider">
            <span>or</span>
        </div>
        
        <p class="auth-link">
            Already have an account? <a href="../login.php">Sign in here <i class="fas fa-arrow-right"></i></a>
        </p>

        <div class="security-badge">
            <i class="fas fa-shield-alt"></i>
            <span>Your data is secure with us</span>
        </div>
    </div>

    <script>
        const registrationForm = document.getElementById('registrationForm');
        const submitBtn = document.getElementById('submitBtn');
        const otpAudio = document.getElementById('otpAudio');
        const passwordInput = document.getElementById('password');
        const strengthBar = document.querySelector('.strength-bar');

        // Password strength indicator
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'strength-bar';
            if (strength === 0) strengthBar.classList.add('strength-none');
            else if (strength <= 2) strengthBar.classList.add('strength-weak');
            else if (strength === 3) strengthBar.classList.add('strength-medium');
            else strengthBar.classList.add('strength-strong');
        });

        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-eye');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Form submission
        registrationForm.addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitBtn.querySelector('.btn-text').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending OTP...';
            submitBtn.style.opacity = '0.8';

            // Play sound
            otpAudio.play().catch(error => {
                console.log("Audio play failed:", error);
            });
        });

        // Add stagger animation to form groups
        document.addEventListener('DOMContentLoaded', function() {
            const formGroups = document.querySelectorAll('.form-group');
            formGroups.forEach((group, index) => {
                group.style.animationDelay = `${index * 0.1}s`;
            });
        });

        // Ripple effect on button
        submitBtn.addEventListener('click', function(e) {
            const ripple = this.querySelector('.btn-ripple');
            ripple.style.left = e.offsetX + 'px';
            ripple.style.top = e.offsetY + 'px';
            ripple.classList.add('active');
            
            setTimeout(() => ripple.classList.remove('active'), 600);
        });

        // Input focus effects
        document.querySelectorAll('input, select').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });
    </script>
</body>
</html>