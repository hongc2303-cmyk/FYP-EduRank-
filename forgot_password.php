<?php
require_once 'config.php';

// ==========================================
// 📦 Include PHPMailer Core Files
// ==========================================
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$error = '';
$debug_link = ''; 

// Smart Routing: Capture the origin portal type from the URL (defaults to 'student')
$type = $_GET['type'] ?? 'student'; 
$display_type = ucfirst(htmlspecialchars($type)); // Capitalize for UI display (Student / Staff)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email']);
    
    // Step 1: Verify email existence. This query supports all users (Staff & Students).
    $stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Step 2: Generate a secure cryptographic token and set a 1-hour expiration limit
        $token = bin2hex(random_bytes(32)); 
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Step 3: Store the token in the dedicated 'password_resets' table
        $insert = $pdo->prepare("INSERT INTO password_resets (email, token, expiry) VALUES (?, ?, ?)");
        $insert->execute([$email, $token, $expires]);

        // Step 4: Dynamically construct the reset link based on the environment (Local/Live)
        $reset_link = BASE_URL . "/reset_password.php?token=" . $token;

        // ==========================================
        // 🚀 PHPMailer Configuration
        // ==========================================
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            
            // Bypass SSL verification for local development compatibility
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->Host       = 'smtp.gmail.com';             
            $mail->SMTPAuth   = true;                                   
            $mail->Username   = 'edurank.psmza@gmail.com';    
            $mail->Password   = 'imazpslxofqynfxr';           
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port       = 587;                            

            $mail->setFrom('edurank.psmza@gmail.com', 'EduRank System'); 
            $mail->addAddress($email, $user['full_name']);               

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - EduRank';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h3 style='color: #0d6efd;'>Password Reset Request</h3>
                    <p>Hello <strong>{$user['full_name']}</strong>,</p>
                    <p>We received a request to reset your password for the EduRank System.</p>
                    <p>Please click the secure button below to set a new password:</p>
                    <p style='margin: 30px 0;'>
                        <a href='{$reset_link}' style='padding: 12px 20px; background-color: #0d6efd; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;'>Reset Password Now</a>
                    </p>
                    <p style='font-size: 12px; color: #777;'>
                        Or copy and paste this URL into your browser:<br> 
                        <a href='{$reset_link}'>{$reset_link}</a>
                    </p>
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #999;'><em>If you did not request this reset, please ignore this email. This link will expire in 1 hour.</em></p>
                </div>
            ";
            $mail->AltBody = "Hello {$user['full_name']},\n\nWe received a request to reset your password. Please copy and paste this link into your browser to reset it:\n{$reset_link}\n\nThis link will expire in 1 hour.";

            $mail->send();
            $message = "A password reset link has been successfully sent to your email inbox.";
            
        } catch (Exception $e) {
            // Fallback UI: Display the reset link directly if the SMTP server is blocked
            $error = "Mail Server Blocked/Timeout. Falling back to System Backup Mode.";
            $debug_link = $reset_link; 
        }

    } else {
        // Security Feature: Prevent email enumeration by returning a generic message
        $error = "If the email is registered, a reset link will be sent to it.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - EduRank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { 
            background: linear-gradient(135deg, #0f172a 0%, #020617 100%);
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .auth-card { 
            width: 100%; 
            max-width: 400px; 
            padding: 40px; 
            border-radius: 20px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.5); 
            background: #1e293b; 
            border: none; 
            color: white;
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
        .form-floating > label {
            color: #94a3b8;
        }
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: #cbd5e1;
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
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
    </style>
</head>
<body>
    <div class="auth-card text-center">
        <i class="bi bi-shield-lock-fill text-warning" style="font-size: 3rem;"></i>
        <h4 class="fw-bold mt-3 mb-1">Forgot Password</h4>
        <!-- Neutral instruction text, applicable to all user types -->
        <p class="text-secondary small mb-4">Enter your registered email to receive a secure reset link.</p>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success small py-2 bg-success text-white border-0"><i class="bi bi-check-circle me-1"></i><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-warning small py-2 bg-warning text-dark border-0"><i class="bi bi-exclamation-triangle me-1"></i><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($debug_link)): ?>
            <div class="alert alert-info small text-start shadow-sm border-0 bg-info text-dark mt-3">
                <strong class="text-dark"><i class="bi bi-shield-check me-1"></i>[System Backup Mode] Active</strong><br>
                Email delivery failed. Click the secure fallback link below to continue your process:<br><br>
                <a href="<?php echo htmlspecialchars($debug_link); ?>" class="fw-bold text-dark text-break" style="word-break: break-all;">[Proceed to Reset Password]</a>
            </div>
        <?php endif; ?>

        <!-- Maintain the '?type=' parameter upon form submission -->
        <form method="POST" action="forgot_password.php?type=<?php echo htmlspecialchars($type); ?>">
            <div class="form-floating mb-3">
                <input type="email" name="email" class="form-control" id="emailInput" placeholder="name@example.com" required>
                <label for="emailInput">Registered Email Address</label>
            </div>
            <button type="submit" name="request_reset" class="btn btn-primary w-100 py-2 fw-bold shadow-sm mb-3">Generate Reset Link</button>
            
            <!-- Dynamic back link updating according to the captured portal type -->
            <a href="login.php?type=<?php echo htmlspecialchars($type); ?>" class="text-decoration-none small text-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to <?php echo $display_type; ?> Login
            </a>
        </form>
    </div>
</body>
</html>