<?php
session_start();

// Check if OTP verification is pending
require_once 'includes/developer_login_auth.php';

if (!DeveloperLoginAuth::isVerificationPending()) {
    header('Location: devlog.php');
    exit();
}

$error = $_SESSION['otp_error'] ?? '';
unset($_SESSION['otp_error']);

$success = $_SESSION['otp_success'] ?? '';
unset($_SESSION['otp_success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Developer Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            min-height: 100vh;
            background: #0f0f0f;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .otp-container {
            background: #1a1a1a;
            padding: 3rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            border: 1px solid #333333;
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 2;
            border-radius: 8px;
        }

        .otp-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .otp-icon {
            width: 80px;
            height: 80px;
            background: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.1);
        }

        .otp-icon i {
            font-size: 2rem;
            color: #000000;
        }

        .otp-title {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .otp-subtitle {
            color: #cccccc;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
        }

        .error-message {
            background: #2d1b1b;
            color: #ff6b6b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid #4a2626;
        }

        .success-message {
            background: #1b2d1b;
            color: #6bff6b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid #264a26;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #ffffff;
            font-size: 0.95rem;
        }

        .otp-input-container {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin: 1.5rem 0;
        }

        .otp-digit {
            width: 50px;
            height: 60px;
            border: 2px solid #333333;
            border-radius: 8px;
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            background: #2a2a2a;
            color: #ffffff;
            transition: all 0.3s ease;
        }

        .otp-digit:focus {
            outline: none;
            border-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        .otp-btn {
            background: #ffffff;
            color: #000000;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 1rem;
        }

        .otp-btn:hover {
            background: #f0f0f0;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }

        .otp-btn:disabled {
            background: #666666;
            color: #999999;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .resend-link {
            text-align: center;
            margin: 1rem 0;
        }

        .resend-link button {
            background: none;
            border: none;
            color: #ffffff;
            text-decoration: underline;
            cursor: pointer;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .resend-link button:hover {
            color: #cccccc;
        }

        .back-link {
            text-align: center;
            margin-top: 1rem;
        }

        .back-link a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: #cccccc;
        }

        .security-notice {
            background: #2a2a2a;
            border: 1px solid #333333;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: #cccccc;
            text-align: center;
        }

        .timer {
            text-align: center;
            color: #cccccc;
            font-size: 0.9rem;
            margin: 1rem 0;
        }

        .timer.warning {
            color: #ff6b6b;
        }
    </style>
</head>
<body>
    <div class="otp-container">
        <div class="otp-header">
            <div class="otp-icon">
                <i class="fas fa-envelope-open"></i>
            </div>
            <h1 class="otp-title">Email Verification</h1>
            <p class="otp-subtitle">
                We've sent a 6-digit verification code to your email address. 
                Please enter it below to complete your login.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form id="otpForm" method="POST" action="api/superadmin_api/verify_dev_otp.php">
            <div class="form-group">
                <label>Enter Verification Code</label>
                <div class="otp-input-container">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                </div>
                <input type="hidden" name="otp_code" id="otpCode">
            </div>

            <div class="timer" id="timer">
                Code expires in: <span id="countdown">10:00</span>
            </div>

            <button type="submit" class="otp-btn" id="verifyBtn">
                <i class="fas fa-check"></i>
                Verify Code
            </button>
        </form>

        <div class="resend-link">
            <button type="button" id="resendBtn" onclick="resendCode()">
                <i class="fas fa-redo"></i>
                Resend Code
            </button>
        </div>

        <div class="security-notice">
            <i class="fas fa-shield-alt"></i>
            For security, this code will expire in 10 minutes. If you don't receive the email, check your spam folder.
        </div>

        <div class="back-link">
            <a href="devlog.php">
                <i class="fas fa-arrow-left"></i>
                Back to Login
            </a>
        </div>
    </div>

    <script>
        // OTP Input handling
        const otpInputs = document.querySelectorAll('.otp-digit');
        const otpCodeInput = document.getElementById('otpCode');
        const verifyBtn = document.getElementById('verifyBtn');

        otpInputs.forEach((input, index) => {
            input.addEventListener('input', function(e) {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');
                
                // Move to next input if current is filled
                if (this.value && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
                
                updateOTPCode();
            });

            input.addEventListener('keydown', function(e) {
                // Handle backspace
                if (e.key === 'Backspace' && !this.value && index > 0) {
                    otpInputs[index - 1].focus();
                }
                
                // Handle paste
                if (e.key === 'v' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    navigator.clipboard.readText().then(text => {
                        const digits = text.replace(/[^0-9]/g, '').slice(0, 6);
                        digits.split('').forEach((digit, i) => {
                            if (otpInputs[i]) {
                                otpInputs[i].value = digit;
                            }
                        });
                        updateOTPCode();
                    });
                }
            });
        });

        function updateOTPCode() {
            const code = Array.from(otpInputs).map(input => input.value).join('');
            otpCodeInput.value = code;
            verifyBtn.disabled = code.length !== 6;
        }

        // Countdown timer
        let timeLeft = 600; // 10 minutes in seconds
        const countdownElement = document.getElementById('countdown');
        const timerElement = document.getElementById('timer');

        function updateCountdown() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 60) {
                timerElement.classList.add('warning');
            }
            
            if (timeLeft <= 0) {
                clearInterval(countdownInterval);
                countdownElement.textContent = 'EXPIRED';
                verifyBtn.disabled = true;
                otpInputs.forEach(input => input.disabled = true);
            }
            
            timeLeft--;
        }

        const countdownInterval = setInterval(updateCountdown, 1000);
        updateCountdown();

        // Resend code function
        async function resendCode() {
            const resendBtn = document.getElementById('resendBtn');
            resendBtn.disabled = true;
            resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            try {
                const response = await fetch('api/superadmin_api/resend_dev_otp.php', {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Reset timer
                    timeLeft = 600;
                    timerElement.classList.remove('warning');
                    
                    // Clear inputs
                    otpInputs.forEach(input => {
                        input.value = '';
                        input.disabled = false;
                    });
                    otpInputs[0].focus();
                    
                    // Show success message
                    alert('New verification code sent to your email!');
                } else {
                    alert('Failed to resend code: ' + result.message);
                }
            } catch (error) {
                alert('Network error. Please try again.');
            } finally {
                resendBtn.disabled = false;
                resendBtn.innerHTML = '<i class="fas fa-redo"></i> Resend Code';
            }
        }

        // Auto-focus first input
        otpInputs[0].focus();

        // Form submission
        document.getElementById('otpForm').addEventListener('submit', function(e) {
            if (otpCodeInput.value.length !== 6) {
                e.preventDefault();
                alert('Please enter the complete 6-digit code.');
            }
        });
    </script>
</body>
</html>
