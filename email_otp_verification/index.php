<?php
// --- START OF PHP LOGIC ---

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

// Database connection & session start (conn.php handles both securely)
require_once __DIR__ . '/../conn.php';

// Initialize variables to hold user input and error messages
$name = '';
$email = '';
$phone = '';
$email_error = ''; // Specific error for email/phone

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Store posted values to repopulate the form if there's an error
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Server-side password match check
    if ($password !== $confirm_password) {
        $email_error = "Passwords do not match.";
    } else {
        // Check if user already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $stmt->bind_param("ss", $email, $phone);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $email_error = "This email or phone number is already registered.";
        } else {
            $stmt->close();

            // Store form data in session
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            $_SESSION['phone'] = $phone;
            $_SESSION['password'] = $password;

            // Generate OTP
            $otp = rand(100000, 999999);
            $_SESSION['otp'] = $otp;

            // --- CHANGE #1: Store the OTP creation time for the 60-second timer ---
            $_SESSION['otp_time'] = time();

            // Send email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = SMTP_PORT;

                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = 'ഇത് പോലൊരു പണിക്കാരനെ കിട്ടാൻ പുണ്യം ചെയ്യണം പുണ്യം .....';
                $mail->Body    = "<h3>കേറി വാ മക്കളേ: <b>$otp</b></h3><p>This code is valid for 60 seconds.</p>";

                $mail->send();

                // --- CHANGE #2: Redirect to verify.php instead of verify.html ---
                header("Location: verify.php"); 
                exit();

            } catch (Exception $e) {
                $email_error = "Error sending OTP: " . $mail->ErrorInfo;
            }
        }
        $stmt->close();
    }
}
// --- END OF PHP LOGIC ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Construct.</title>
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

    <div class="auth-container">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-hard-hat"></i>
            </div>
            <h1 class="logo">Construct<span>.</span></h1>
            <p class="tagline">Build Your Future With Us</p>
        </div>

        <div class="card-header">
            <h2>Create Account</h2>
            <p class="subtitle">Join our construction management platform</p>
        </div>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="registerForm">
            
            <div class="form-group">
                <label for="name">
                    <i class="fas fa-user"></i>
                    Full Name
                </label>
                <div class="input-wrapper">
                    <input type="text" id="name" name="name" required value="<?= htmlspecialchars($name) ?>" placeholder="Enter your full name">
                    <span class="input-icon"><i class="fas fa-user"></i></span>
                </div>
            </div>

            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i>
                    Email Address
                </label>
                <div class="input-wrapper">
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($email) ?>" placeholder="your.email@example.com">
                    <span class="input-icon"><i class="fas fa-envelope"></i></span>
                </div>
                <?php if (!empty($email_error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= $email_error; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="phone">
                    <i class="fas fa-phone"></i>
                    Phone Number
                </label>
                <div class="input-wrapper">
                    <input type="tel" id="phone" name="phone" required value="<?= htmlspecialchars($phone) ?>" placeholder="+91 98765 43210">
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
                <label for="confirm_password">
                    <i class="fas fa-lock"></i>
                    Confirm Password
                </label>
                <div class="input-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                        <i class="fas fa-eye" id="confirm_password-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn" id="sendOtpBtn">
                <span class="btn-text">Create Account & Send OTP</span>
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

    <audio id="clickSound" src="../benjamin3.mp3" preload="auto"></audio>
    
    <script>
    const registerForm = document.getElementById('registerForm');
    const sendOtpBtn = document.getElementById('sendOtpBtn');
    const sound = document.getElementById("clickSound");
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
    registerForm.addEventListener('submit', function(event) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (password !== confirmPassword) {
            event.preventDefault(); 
            showError('Passwords do not match. Please try again.');
            return; 
        }

        // Visual feedback
        sendOtpBtn.disabled = true;
        sendOtpBtn.querySelector('.btn-text').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        sendOtpBtn.style.opacity = '0.8';
        
        // Play sound
        sound.play().catch(e => console.log(e));
    });

    // Show error message
    function showError(message) {
        const existingError = document.querySelector('.error-message');
        if (!existingError) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
            registerForm.insertBefore(errorDiv, sendOtpBtn);
            
            setTimeout(() => errorDiv.remove(), 5000);
        }
    }

    // Add stagger animation to form groups
    document.addEventListener('DOMContentLoaded', function() {
        const formGroups = document.querySelectorAll('.form-group');
        formGroups.forEach((group, index) => {
            group.style.animationDelay = `${index * 0.1}s`;
        });
    });

    // Ripple effect on button
    sendOtpBtn.addEventListener('click', function(e) {
        const ripple = this.querySelector('.btn-ripple');
        ripple.style.left = e.offsetX + 'px';
        ripple.style.top = e.offsetY + 'px';
        ripple.classList.add('active');
        
        setTimeout(() => ripple.classList.remove('active'), 600);
    });

    // Input focus effects
    document.querySelectorAll('input').forEach(input => {
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