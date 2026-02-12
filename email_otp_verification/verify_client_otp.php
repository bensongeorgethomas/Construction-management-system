<?php
require_once __DIR__ . '/../conn.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/csrf.php';
    requireCSRF();
    // Check if pending_client data exists
    if (!isset($_SESSION['pending_client'])) {
        die("Registration session expired. Please start over.");
    }

    // Validate OTP input
    if (empty($_POST['otp'])) {
        die("Please enter the 6-digit OTP");
    }

    $enteredOtp = trim($_POST['otp']);

    if (!preg_match('/^\d{6}$/', $enteredOtp)) {
        die("OTP must be exactly 6 digits");
    }

    // Get OTP from pending_client session
    $storedOtp = $_SESSION['pending_client']['otp'];

    // Compare OTPS
    if ((string)$storedOtp !== (string)$enteredOtp) {
        die("Invalid OTP. Please try again.");
    }

    // Get registration data from pending_client
    $clientData = $_SESSION['pending_client'];

    // Prepare data for database
    $name = $conn->real_escape_string($clientData['name']);
    $email = filter_var($clientData['email'], FILTER_SANITIZE_EMAIL);
    $phone = $conn->real_escape_string($clientData['mobilenumber']);
    $password = $clientData['password']; // Already hashed in clientregister.php
    $role = 'client';

    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Email already exists
        $checkStmt->close();
        unset($_SESSION['pending_client']); // Clear registration data
        die("<script>
            alert('This email is already registered. Please use a different email or try logging in.');
            window.location.href = '../login.php';
        </script>");
    }
    $checkStmt->close();

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $email, $phone, $password, $role);

    if ($stmt->execute()) {
        // Set user session
        $_SESSION['user_id'] = $stmt->insert_id;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $role;
        $_SESSION['loggedin'] = true;
        
        // Clear registration data
        unset($_SESSION['pending_client']);
        
        // Redirect
        header("Location: ../client_dashboard.php"); 
        exit();
    } else {
        // Handle other database errors
        $errorMsg = "Registration failed. Please try again.";
        if (strpos($stmt->error, 'Duplicate entry') !== false) {
            $errorMsg = "This email is already registered. Please try logging in.";
        }
        die("<script>
            alert('$errorMsg');
            window.location.href = '../login.php';
        </script>");
    }
    $stmt->close();

} else {
    // Display form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Verify OTP - Construct.</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <link rel="stylesheet" href="auth_style.css">
        <style>
            /* Additional styles for OTP input */
            .otp-input-container {
                display: flex;
                gap: 0.75rem;
                justify-content: center;
                margin: 2rem 0;
            }

            .otp-digit {
                width: 55px;
                height: 60px;
                font-size: 1.75rem;
                font-weight: 700;
                text-align: center;
                border-radius: 12px;
                border: 2px solid rgba(209, 213, 219, 0.3);
                background-color: rgba(255, 255, 255, 0.7);
                color: var(--text-dark);
                transition: all 0.3s ease;
            }

            .otp-digit:focus {
                outline: none;
                border-color: var(--primary-color);
                background-color: #ffffff;
                box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1);
                transform: scale(1.05);
            }

            .timer {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                margin-top: 1rem;
                color: var(--text-medium);
                font-size: 0.95rem;
            }

            .timer.expired {
                color: var(--error-text);
            }

            .timer i {
                font-size: 1.1rem;
            }

            .resend-link {
                margin-top: 1rem;
                text-align: center;
            }

            .resend-link button {
                background: none;
                border: none;
                color: var(--primary-color);
                font-weight: 600;
                cursor: pointer;
                text-decoration: underline;
                font-size: 0.95rem;
                transition: color 0.3s ease;
            }

            .resend-link button:hover {
                color: var(--primary-hover-color);
            }

            .resend-link button:disabled {
                color: var(--text-light);
                cursor: not-allowed;
            }

            @media (max-width: 480px) {
                .otp-digit {
                    width: 45px;
                    height: 50px;
                    font-size: 1.5rem;
                }

                .otp-input-container {
                    gap: 0.5rem;
                }
            }
        </style>
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
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1 class="logo">Construct<span>.</span></h1>
                <p class="tagline">Build Your Future With Us</p>
            </div>

            <div class="card-header">
                <h2>Verify Your Email</h2>
                <p class="subtitle">Enter the 6-digit code sent to your email</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="otpForm">
                <?= csrfField() ?>
                <div class="otp-input-container">
                    <input type="text" class="otp-digit" maxlength="1" pattern="\d" data-index="0" autocomplete="off" required>
                    <input type="text" class="otp-digit" maxlength="1" pattern="\d" data-index="1" autocomplete="off" required>
                    <input type="text" class="otp-digit" maxlength="1" pattern="\d" data-index="2" autocomplete="off" required>
                    <input type="text" class="otp-digit" maxlength="1" pattern="\d" data-index="3" autocomplete="off" required>
                    <input type="text" class="otp-digit" maxlength="1" pattern="\d" data-index="4" autocomplete="off" required>
                    <input type="text" class="otp-digit" maxlength="1" pattern="\d" data-index="5" autocomplete="off" required>
                </div>

                <!-- Hidden input to store complete OTP -->
                <input type="hidden" name="otp" id="otpValue">

                <div class="timer" id="timer">
                    <i class="fas fa-clock"></i>
                    <span>Time remaining: <strong id="timeLeft">5:00</strong></span>
                </div>

                <button type="submit" class="btn" id="verifyBtn">
                    <span class="btn-text">Verify OTP</span>
                    <span class="btn-icon"><i class="fas fa-check"></i></span>
                    <div class="btn-ripple"></div>
                </button>
            </form>

            <div class="resend-link">
                <button type="button" id="resendBtn" disabled>
                    <i class="fas fa-redo"></i> Resend OTP
                </button>
            </div>

            <div class="divider">
                <span>or</span>
            </div>

            <p class="auth-link">
                Need help? <a href="../login.php">Back to login <i class="fas fa-arrow-right"></i></a>
            </p>

            <div class="security-badge">
                <i class="fas fa-shield-alt"></i>
                <span>Secure verification process</span>
            </div>
        </div>

        <script>
            const otpDigits = document.querySelectorAll('.otp-digit');
            const otpValue = document.getElementById('otpValue');
            const otpForm = document.getElementById('otpForm');
            const verifyBtn = document.getElementById('verifyBtn');
            const timerDisplay = document.getElementById('timeLeft');
            const timerContainer = document.getElementById('timer');
            const resendBtn = document.getElementById('resendBtn');

            let timeRemaining = 300; // 5 minutes in seconds

            // OTP input handling
            otpDigits.forEach((digit, index) => {
                // Auto-focus next input
                digit.addEventListener('input', function(e) {
                    if (this.value.length === 1) {
                        if (index < otpDigits.length - 1) {
                            otpDigits[index + 1].focus();
                        }
                        updateOTPValue();
                    }
                });

                // Handle backspace
                digit.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && this.value === '') {
                        if (index > 0) {
                            otpDigits[index - 1].focus();
                        }
                    }
                });

                // Only allow digits
                digit.addEventListener('keypress', function(e) {
                    if (!/\d/.test(e.key)) {
                        e.preventDefault();
                    }
                });

                // Handle paste
                digit.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text').replace(/\D/g, '');
                    
                    if (pastedData.length === 6) {
                        otpDigits.forEach((input, i) => {
                            input.value = pastedData[i] || '';
                        });
                        updateOTPValue();
                        otpDigits[5].focus();
                    }
                });
            });

            // Update hidden OTP value
            function updateOTPValue() {
                const otp = Array.from(otpDigits).map(digit => digit.value).join('');
                otpValue.value = otp;
            }

            // Timer countdown
            const timerInterval = setInterval(() => {
                timeRemaining--;

                const minutes = Math.floor(timeRemaining / 60);
                const seconds = timeRemaining % 60;
                timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

                if (timeRemaining <= 0) {
                    clearInterval(timerInterval);
                    timerContainer.classList.add('expired');
                    timerDisplay.innerHTML = '<strong>OTP Expired!</strong>';
                    verifyBtn.disabled = true;
                    resendBtn.disabled = false;
                }
            }, 1000);

            // Form submission
            otpForm.addEventListener('submit', function(e) {
                updateOTPValue();

                if (otpValue.value.length !== 6) {
                    e.preventDefault();
                    alert('Please enter all 6 digits');
                    return;
                }

                verifyBtn.disabled = true;
                verifyBtn.querySelector('.btn-text').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
                verifyBtn.style.opacity = '0.8';
            });

            // Resend OTP (placeholder - implement actual resend logic)
            resendBtn.addEventListener('click', function() {
                // In a real implementation, this would trigger an AJAX request to resend OTP
                alert('Resend OTP functionality - implement backend logic');
            });

            // Ripple effect on button
            verifyBtn.addEventListener('click', function(e) {
                const ripple = this.querySelector('.btn-ripple');
                if (ripple) {
                    ripple.style.left = e.offsetX + 'px';
                    ripple.style.top = e.offsetY + 'px';
                    ripple.classList.add('active');
                    
                    setTimeout(() => ripple.classList.remove('active'), 600);
                }
            });

            // Auto-focus first input
            otpDigits[0].focus();
        </script>
    </body>
    </html>
    <?php
}
?>