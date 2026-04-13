<?php
session_start();
include 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: homepage.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = mysqli_real_escape_string($conn, $_POST['login']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);
    
    $query = "SELECT * FROM users WHERE username = '$login' OR email = '$login'";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['profile_pic'] = $user['profile_pic'] ?? 'default.jpg';
            
            if ($remember_me) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                mysqli_query($conn, "INSERT INTO user_sessions (user_id, session_token, expires_at) 
                                    VALUES ({$user['id']}, '$token', '$expires')");
                setcookie('remember_token', $token, time() + (86400 * 30), "/");
            }
            
            header("Location: homepage.php");
            exit();
        } else {
            $error = "Invalid username/email or password!";
        }
    } else {
        $error = "Invalid username/email or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TravelMate - Login</title>
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

        .login-container {
            max-width: 1100px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
            background: white;
            border-radius: 32px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid #e8e8e8;
        }

        /* Brand Section */
        .brand {
            padding: 48px;
            background: linear-gradient(135deg, #fafaf8 0%, #f5f5f0 100%);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .brand-icon {
            font-size: 56px;
            color: #f39c12;
            margin-bottom: 24px;
        }

        .brand h1 {
            font-size: 36px;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: -0.5px;
            margin-bottom: 12px;
        }

        .brand p {
            font-size: 16px;
            color: #6b6b6b;
            line-height: 1.5;
            margin-bottom: 32px;
        }

        .features {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #4a4a4a;
            font-size: 14px;
        }

        .feature i {
            width: 24px;
            color: #f39c12;
            font-size: 16px;
        }

        /* Login Card */
        .login-card {
            padding: 48px;
        }

        .login-header {
            margin-bottom: 32px;
        }

        .login-header h2 {
            font-size: 28px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .login-header p {
            font-size: 14px;
            color: #8a8a8a;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .input-group label {
            font-size: 13px;
            font-weight: 500;
            color: #4a4a4a;
        }

        .input-group input {
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

        .remember {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 4px;
        }

        .remember input {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #f39c12;
        }

        .remember label {
            font-size: 13px;
            color: #6b6b6b;
            cursor: pointer;
        }

        .login-btn {
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

        .login-btn:hover {
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

        .register-btn {
            width: 100%;
            background: none;
            border: 1px solid #f39c12;
            color: #f39c12;
            padding: 14px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .register-btn:hover {
            background: #f39c12;
            color: white;
            transform: translateY(-2px);
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

        @media (max-width: 900px) {
            .login-container {
                grid-template-columns: 1fr;
            }
            
            .brand {
                padding: 32px;
                text-align: center;
            }
            
            .features {
                align-items: center;
            }
            
            .login-card {
                padding: 32px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="brand">
            <div class="brand-icon">
                <i class="fas fa-compass"></i>
            </div>
            <h1>TravelMate</h1>
            <p>Your journey begins here. Connect with fellow travelers, share experiences, and explore the world together.</p>
            <div class="features">
                <div class="feature">
                    <i class="fas fa-map-marked-alt"></i>
                    <span>Discover amazing places</span>
                </div>
                <div class="feature">
                    <i class="fas fa-user-friends"></i>
                    <span>Connect with travelers</span>
                </div>
                <div class="feature">
                    <i class="fas fa-camera"></i>
                    <span>Share your memories</span>
                </div>
            </div>
        </div>
        
        <div class="login-card">
            <div class="login-header">
                <h2>Welcome back</h2>
                <p>Sign in to continue your journey</p>
            </div>

            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="input-group">
                    <label>Username or Email</label>
                    <input type="text" name="login" placeholder="Enter your username or email" required>
                </div>
                
                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
                
                <div class="remember">
                    <input type="checkbox" name="remember_me" id="remember">
                    <label for="remember">Remember me</label>
                </div>
                
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i> Sign In
                </button>
            </form>
            
            <div class="divider">
                <span>New to TravelMate?</span>
            </div>
            
            <a href="register.php" style="text-decoration: none;">
                <button class="register-btn">
                    <i class="fas fa-user-plus" style="margin-right: 8px;"></i> Create Account
                </button>
            </a>
        </div>
    </div>
</body>
</html>