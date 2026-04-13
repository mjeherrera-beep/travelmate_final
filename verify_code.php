<?php
session_start();
include 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: homepage.php");
    exit();
}

$error = '';
$success = '';

// If verification email is not set, redirect to register
if (!isset($_SESSION['verification_email'])) {
    header("Location: register.php");
    exit();
}

$email = $_SESSION['verification_email'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entered_code = mysqli_real_escape_string($conn, $_POST['verification_code']);
    
    // Check if code matches
    $query = "SELECT id, full_name, verification_token, token_expires FROM users WHERE email = '$email' AND email_verified = 0";
    $result = mysqli_query($conn, $query);
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        if ($user['verification_token'] == $entered_code) {
            $current_time = date('Y-m-d H:i:s');
            if ($current_time <= $user['token_expires']) {
                // Update user as verified
                $update = "UPDATE users SET email_verified = 1, verification_token = NULL, token_expires = NULL WHERE id = " . $user['id'];
                if (mysqli_query($conn, $update)) {
                    // Auto login
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    
                    // Clear verification session
                    unset($_SESSION['verification_email']);
                    
                    $success = "Email verified successfully! Redirecting...";
                    header("refresh:2;url=homepage.php");
                    exit();
                } else {
                    $error = "Verification failed. Please try again.";
                }
            } else {
                $error = "Verification code has expired. Please register again.";
                // Delete expired user
                mysqli_query($conn, "DELETE FROM users WHERE email = '$email' AND email_verified = 0");
                unset($_SESSION['verification_email']);
            }
        } else {
            $error = "Invalid verification code. Please try again.";
        }
    } else {
        $error = "User not found or already verified.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - TravelMate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f5f0;
            background-image: radial-gradient(circle at 10% 20%, rgba(243, 156, 18, 0.05) 0%, rgba(255, 255, 255, 0) 50%),
                              radial-gradient(circle at 90% 80%, rgba(243, 156, 18, 0.03) 0%, rgba(255, 255, 255, 0) 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .verify-container {
            max-width: 450px;
            width: 100%;
            background: white;
            border-radius: 32px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid #e8e8e8;
        }

        .verify-header {
            background: linear-gradient(135deg, #fafaf8 0%, #f5f5f0 100%);
            padding: 32px;
            text-align: center;
            border-bottom: 1px solid #e8e8e8;
        }

        .verify-header i {
            font-size: 48px;
            color: #f39c12;
            margin-bottom: 16px;
        }

        .verify-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .verify-header p {
            font-size: 14px;
            color: #8a8a8a;
        }

        .verify-card {
            padding: 32px;
        }

        .info-text {
            text-align: center;
            margin-bottom: 24px;
            color: #6b6b6b;
            font-size: 14px;
        }

        .info-text i {
            color: #f39c12;
            margin-right: 6px;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #4a4a4a;
            margin-bottom: 8px;
        }

        .input-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #e8e8e8;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.2s;
            background: #fafaf8;
            text-align: center;
            font-size: 24px;
            letter-spacing: 4px;
        }

        .input-group input:focus {
            outline: none;
            border-color: #f39c12;
            background: white;
            box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.1);
        }

        .verify-btn {
            width: 100%;
            background: #f39c12;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }

        .verify-btn:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }

        .resend-link {
            text-align: center;
            margin-top: 20px;
        }

        .resend-link a {
            color: #f39c12;
            text-decoration: none;
            font-size: 13px;
        }

        .resend-link a:hover {
            text-decoration: underline;
        }

        .error {
            background: #fef5f5;
            border: 1px solid #f5c6cb;
            color: #c0392b;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error i {
            font-size: 16px;
        }

        .success {
            background: #e8f8f5;
            border: 1px solid #a3e4d7;
            color: #1abc9c;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success i {
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-header">
            <i class="fas fa-envelope"></i>
            <h1>Verify Your Email</h1>
            <p>Enter the 6-digit code sent to your email</p>
        </div>
        
        <div class="verify-card">
            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <div class="info-text">
                <i class="fas fa-envelope"></i> We sent a code to <strong><?php echo htmlspecialchars($email); ?></strong>
            </div>
            
            <form method="POST">
                <div class="input-group">
                    <label>Verification Code</label>
                    <input type="text" name="verification_code" placeholder="000000" maxlength="6" required autofocus>
                </div>
                
                <button type="submit" class="verify-btn">
                    <i class="fas fa-check"></i> Verify Account
                </button>
            </form>
            
            <div class="resend-link">
                <a href="resend_code.php">Didn't receive the code? Resend</a>
            </div>
        </div>
    </div>
</body>
</html>