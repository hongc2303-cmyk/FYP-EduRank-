<?php
require_once 'config.php';

date_default_timezone_set('Asia/Kuala_Lumpur'); 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            // Check if user exists and is active
            $stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Delete any existing unused tokens for this email
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ? AND used = 0");
                $stmt->execute([$email]);
                
                // Generate new token
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', time() + 3600); // exactly 1 hour from now
                
                // Store token in database
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expiry) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expiry]);
                
                // Create reset link
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . urlencode($token);
                
                // For local development - show the link
                $success = "
                    <div class='alert alert-info'>
                        <strong>Password Reset Link Generated!</strong><br>
                        <small>In production, this would be sent to your email.</small><br>
                        <div class='mt-3 p-3 bg-dark rounded'>
                            <a href='{$reset_link}' class='text-primary' target='_blank'>
                                Click here to reset your password
                            </a>
                        </div>
                        <small class='text-muted mt-2 d-block'>This link expires in 1 hour.</small>
                    </div>
                ";
                
                // Try to send email
                $subject = "EduRank AI - Password Reset";
                $message = "
                    <html>
                    <body style='font-family: Arial, sans-serif;'>
                        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                            <h2 style='color: #2563eb;'>EduRank AI</h2>
                            <h3>Password Reset Request</h3>
                            <p>Hello {$user['full_name']},</p>
                            <p>We received a request to reset your password. Click the button below to create a new password:</p>
                            <p style='text-align: center;'>
                                <a href='{$reset_link}' style='background-color: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; display: inline-block;'>
                                    Reset Password
                                </a>
                            </p>
                            <p>Or copy and paste this link in your browser:</p>
                            <p style='background-color: #f1f5f9; padding: 10px; border-radius: 5px;'>{$reset_link}</p>
                            <p style='color: #64748b; font-size: 14px;'>This link will expire in 1 hour.</p>
                            <p style='color: #64748b; font-size: 14px;'>If you didn't request this, please ignore this email.</p>
                        </div>
                    </body>
                    </html>
                ";
                
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "From: EduRank AI <noreply@edurank.com>\r\n";
                
                @mail($email, $subject, $message, $headers);
                
            } else {
                // Don't reveal if email exists - shows generic message
                $success = "
                    <div class='alert alert-success'>
                        <i class='bi bi-check-circle me-2'></i>
                        If a student account with that email exists, a password reset link will be sent.
                    </div>
                ";
            }
        } catch (PDOException $e) {
            $error = "System error. Please try again later.";
            // For debugging:
            // $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1a2332 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            padding: 20px;
        }
        .card {
            background: #1e293b;
            border-radius: 20px;
            max-width: 440px;
            width: 100%;
            padding: 36px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .form-control {
            background: #334155;
            border: 1px solid #475569;
            color: white;
            border-radius: 10px;
            padding: 12px;
        }
        .form-control:focus {
            background: #334155;
            color: white;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }
        .form-control::placeholder {
            color: #94a3b8;
        }
        .btn-primary {
            background: #2563eb;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .text-secondary {
            color: #94a3b8 !important;
        }
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
<div class="card text-white">
    <div class="text-center mb-3">
        <h4 class="fw-bold">
            <i class="bi bi-lock text-warning me-2"></i>Forgot Password
        </h4>
        <p class="text-secondary small">Enter your student email to receive a reset link.</p>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger py-2">
            <i class="bi bi-exclamation-circle me-1"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if($success): ?>
        <?php echo $success; ?>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label text-secondary">Email Address</label>
            <input type="email" name="email" class="form-control" 
                   placeholder="you@example.com" 
                   value="<?php echo htmlspecialchars($email); ?>" 
                   required>
            <small class="text-muted">Enter your registered student email address.</small>
        </div>
        
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-send me-2"></i>Send Reset Link
        </button>
    </form>

    <div class="text-center mt-3">
        <a href="login.php" class="text-decoration-none small text-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Login
        </a>
    </div>
</div>
</body>
</html>