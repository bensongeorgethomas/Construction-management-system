<?php
require '../conn.php';
require_once __DIR__ . '/../includes/csrf.php';

$message = '';
$message_type = ''; // Will be 'success' or 'error'

// Redirect to the registration page if the user hasn't started the process
if (!isset($_SESSION['otp'])) {
    header('Location: index.php'); // Assuming your registration form is index.php
    exit();
}

// NEW: Set the initial OTP expiry time (1 minute) if it's not already set
if (!isset($_SESSION['otp_expiry'])) {
    $_SESSION['otp_expiry'] = time() + 60;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    requireCSRF();
    $enteredOtp = $_POST['otp'] ?? '';

    if (isset($_SESSION['otp']) && $enteredOtp == $_SESSION['otp']) {
        // OTP matched, proceed to register the user
        $name = $_SESSION['name'];
        $email = $_SESSION['email'];
        $phone = $_SESSION['phone'];
        $password = $_SESSION['password'];
        $role = 'worker';   // Default role for new users
        $status = 'pending'; // New accounts are pending admin approval

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $name, $email, $phone, $hashed_password, $role, $status);

        if ($stmt->execute()) {
            $message = "Registration successful! Your account is pending admin approval.";
            $message_type = 'success';
            // Clean up the session completely after successful registration
            $_SESSION = array();
            session_destroy();
        } else {
            // Handle potential DB errors, like a duplicate entry
            $message = ($conn->errno === 1062) ? "This email or phone is already registered." : "Database error: " . $stmt->error;
            $message_type = 'error';
        }
        $stmt->close();
    } else {
        $message = "Incorrect OTP. Please try again.";
        $message_type = 'error';
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    <style>
        /* Your existing CSS remains the same */
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 450px;
            width: 100%;
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
        input[type="number"] {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        button, .btn {
            background-color: #FFC107; /* Yellow button */
            color: #333;
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s;
            width: 100%;
            text-decoration: none;
            display: inline-block;
            box-sizing: border-box;
        }
        button:hover, .btn:hover {
            background-color: #FFB300; /* Darker yellow on hover */
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-weight: 500;
            text-align: left;
            border-left: 4px solid;
        }
        .message.error {
            background-color: #fee2e2;
            color: #b91c1c;
            border-color: #f44336;
        }
        .message.success {
            background-color: #FFF8E1;
            color: #333;
            border-color: #FFC107;
        }
        /* NEW STYLES for the resend button and timer */
        #resendContainer {
            margin-top: 20px;
            font-size: 14px;
            color: #555;
        }
        #resendBtn {
            background: none;
            border: none;
            color: #007bff;
            cursor: pointer;
            padding: 0;
            font-size: 14px;
            text-decoration: underline;
            width: auto;
        }
        #resendBtn:disabled {
            color: #999;
            cursor: not-allowed;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($message)): ?>
            <p class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <?php if ($message_type !== 'success'): ?>
            <h2>Enter the OTP sent to your email</h2>
            <form method="POST" action="verify.php">
                <?= csrfField() ?>
                <input type="number" name="otp" required autofocus placeholder="6-digit code" inputmode="numeric" pattern="\d{6}">
                <button type="submit">Verify & Register</button>
            </form>

            <div id="resendContainer">
                <p>Didn't receive the code?
                    <button id="resendBtn" disabled>Resend OTP</button>
                    <span id="timerText" style="display: none;">in <span id="timer">60</span>s</span>
                </p>
            </div>

        <?php else: ?>
            <h2>✓ Registration Submitted!</h2>
            <a href="../home.php" class="btn">Go to Homepage</a>
        <?php endif; ?>
    </div>

    <script>
        // Existing script to prevent non-numeric characters
        const otpInput = document.querySelector('input[type="number"]');
        if (otpInput) {
            otpInput.addEventListener('keydown', function(e) {
                if (['-', '+', 'e', 'E', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
                    e.preventDefault();
                }
            });
        }

        // NEW: Timer and Resend OTP Logic
        document.addEventListener('DOMContentLoaded', function() {
            const timerEl = document.getElementById('timer');
            const timerTextEl = document.getElementById('timerText');
            const resendBtn = document.getElementById('resendBtn');

            if (!timerEl) return; // Don't run if the timer element isn't on the page

            // Get expiry time from PHP. Fallback to now + 60s if not set.
            const serverExpiry = <?php echo $_SESSION['otp_expiry'] ?? (time() + 60); ?>;
            let secondsLeft = Math.max(0, serverExpiry - Math.floor(Date.now() / 1000));
            let timerInterval;

            function startTimer() {
                resendBtn.disabled = true;
                timerTextEl.style.display = 'inline';
                
                timerInterval = setInterval(() => {
                    secondsLeft--;
                    timerEl.textContent = secondsLeft;

                    if (secondsLeft <= 0) {
                        clearInterval(timerInterval);
                        resendBtn.disabled = false;
                        timerTextEl.style.display = 'none';
                    }
                }, 1000);
            }

            if (secondsLeft > 0) {
                timerEl.textContent = secondsLeft;
                startTimer();
            } else {
                resendBtn.disabled = false;
                timerTextEl.style.display = 'none';
            }

            resendBtn.addEventListener('click', function(e) {
                e.preventDefault();
                resendBtn.disabled = true;
                resendBtn.textContent = 'Sending...';

                fetch('resend_otp.php', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        // Display feedback message
                        const container = document.querySelector('.container');
                        let oldMsg = container.querySelector('.resend-message');
                        if(oldMsg) oldMsg.remove();

                        const newMsg = document.createElement('p');
                        newMsg.textContent = data.message;
                        newMsg.className = `message resend-message ${data.status}`; // 'success' or 'error'
                        container.insertBefore(newMsg, container.firstChild);
                        
                        resendBtn.textContent = 'Resend OTP';

                        if (data.status === 'success') {
                            secondsLeft = 60;
                            timerEl.textContent = secondsLeft;
                            startTimer();
                        } else {
                            resendBtn.disabled = false; // Allow another try if it failed
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        resendBtn.disabled = false; // Re-enable on network error
                        resendBtn.textContent = 'Resend OTP';
                    });
            });
        });
    </script>
</body>
</html>