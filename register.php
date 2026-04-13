<?php
session_start();
include 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: homepage.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!preg_match('/^[A-Za-z\s]+$/', $full_name)) {
        $error = "Name can only contain letters and spaces!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        $check = "SELECT id FROM users WHERE username = '$username'";
        $result = mysqli_query($conn, $check);
        if (mysqli_num_rows($result) > 0) {
            $error = "Username already taken!";
        } else {
            $check_email = "SELECT id FROM users WHERE email = '$email'";
            $result = mysqli_query($conn, $check_email);
            if (mysqli_num_rows($result) > 0) {
                $error = "Email already registered!";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $query = "INSERT INTO users (username, email, password_hash, full_name, email_verified) 
                          VALUES ('$username', '$email', '$password_hash', '$full_name', 1)";
                
                if (mysqli_query($conn, $query)) {
                    $user_id = mysqli_insert_id($conn);
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['profile_pic'] = 'default.jpg';
                    
                    $success = "Account created successfully! Redirecting...";
                    header("refresh:2;url=homepage.php");
                    exit();
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TravelMate - Sign Up</title>
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

        .register-container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 32px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid #e8e8e8;
        }

        .register-header {
            background: linear-gradient(135deg, #fafaf8 0%, #f5f5f0 100%);
            padding: 32px;
            text-align: center;
            border-bottom: 1px solid #e8e8e8;
        }

        .register-header i {
            font-size: 48px;
            color: #f39c12;
            margin-bottom: 16px;
        }

        .register-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .register-header p {
            font-size: 14px;
            color: #8a8a8a;
        }

        .register-card {
            padding: 32px;
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
        }

        .input-group input:focus {
            outline: none;
            border-color: #f39c12;
            background: white;
            box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.1);
        }

        .password-strength {
            font-size: 11px;
            margin-top: 8px;
        }

        .weak { color: #e74c3c; }
        .medium { color: #f39c12; }
        .strong { color: #27ae60; }

        .register-btn {
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

        .register-btn:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }

        .divider {
            position: relative;
            text-align: center;
            margin: 24px 0;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e8e8e8;
        }

        .divider span {
            position: relative;
            background: white;
            padding: 0 16px;
            font-size: 12px;
            color: #8a8a8a;
        }

        .login-link {
            text-align: center;
        }

        .login-link a {
            color: #f39c12;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .login-link a:hover {
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
    <div class="register-container">
        <div class="register-header">
            <i class="fas fa-compass"></i>
            <h1>Create Account</h1>
            <p>Join our travel community today</p>
        </div>
        
        <div class="register-card">
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
            
            <form method="POST">
                <div class="input-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="Enter your full name" required>
                </div>
                
                <div class="input-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Choose a username" required>
                </div>
                
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="Enter your email" required>
                </div>
                
                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" id="password" placeholder="Create a password" required onkeyup="checkStrength()">
                    <div class="password-strength" id="strengthText"></div>
                </div>
                
                <div class="input-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm your password" required>
                </div>
                
                <button type="submit" class="register-btn">
                    <i class="fas fa-user-plus" style="margin-right: 8px;"></i> Sign Up
                </button>
            </form>
            
            <div class="divider">
                <span>Already have an account?</span>
            </div>
            
            <div class="login-link">
                <a href="index.php">Sign In</a>
            </div>
        </div>
    </div>

    <script>
        function checkStrength() {
            var password = document.getElementById('password').value;
            var strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;
            
            var text = document.getElementById('strengthText');
            if (password.length === 0) {
                text.innerHTML = '';
                text.className = 'password-strength';
            } else if (strength <= 2) {
                text.innerHTML = 'Weak password';
                text.className = 'password-strength weak';
            } else if (strength <= 3) {
                text.innerHTML = 'Medium password';
                text.className = 'password-strength medium';
            } else {
                text.innerHTML = 'Strong password';
                text.className = 'password-strength strong';
            }
        }
    </script>
</body>
</html>