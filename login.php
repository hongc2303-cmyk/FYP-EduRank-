<?php
// 1. Include database connection and settings
require_once 'config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';
$login_id = ''; 

// 2. Determine which portal the user is trying to access (Student or Staff)
$portal_type = $_GET['type'] ?? $_POST['portal_type'] ?? 'student';
if (!in_array($portal_type, ['student', 'staff'])) {
    $portal_type = 'student'; // Default to student portal
}

// 3. Define where each user role should be redirected after successful login
$role_config = [
    'Student' => ['page' => 'student_dashboard.php'],
    'Academic Advisor' => ['page' => 'pa_dashboard.php'],
    'Evaluation Committee' => ['page' => 'committee_dashboard.php'],
    'admin' => ['page' => 'admin_dashboard.php'],
    'Admin' => ['page' => 'admin_dashboard.php'],
    'ADMIN' => ['page' => 'admin_dashboard.php']
];

// 4. Check if the user is ALREADY logged in. If yes, redirect them immediately to their dashboard.
if (function_exists('isLoggedIn') && isLoggedIn() && isset($_SESSION['user_role'])) {
    // If they need to change password, redirect to change password page
    if (isset($_SESSION['require_password_change']) && $_SESSION['require_password_change'] === true) {
        header("Location: change_pws.php");
        exit();
    }
    // Redirect to normal dashboard
    $user_role = $_SESSION['user_role'];
    if (isset($role_config[$user_role])) {
        header("Location: " . $role_config[$user_role]['page']);
        exit();
    }
}

// 5. Handle the form submission when the user clicks "Sign In"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = sanitizeInput($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Check if input fields are empty
    if (empty($login_id) || empty($password)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            $user = false;
            
            // 6. Search for the user in the database based on portal type
            if ($portal_type === 'student') {
                // Students can ONLY login using their email
                if (!validateEmail($login_id)) {
                    $error = "Students must use a valid email address to log in.";
                } else {
                    $stmt = $pdo->prepare("
                        SELECT * FROM users 
                        WHERE email = ? 
                          AND LOWER(role) = 'student' 
                          AND is_active = 1
                    ");
                    $stmt->execute([$login_id]);
                    $user = $stmt->fetch();
                }
            } else {
                // Staff (PA, Committee, Admin) can login using email OR full name
                $stmt = $pdo->prepare("
                    SELECT * FROM users 
                    WHERE (email = ? OR full_name = ? OR full_name = ?) 
                      AND role IN ('Academic Advisor', 'Evaluation Committee', 'admin') 
                      AND is_active = 1
                ");
                $stmt->execute([$login_id, $login_id, $login_id]);
                $user = $stmt->fetch();
            }
            
            // 7. Verify the password if user exists
            if (empty($error)) {
                if ($user && verifyPassword($password, $user['password'])) {
                    // Password is correct! Save user data into Session
                    $_SESSION['user_id']    = $user['user_id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role']  = $user['role'];
                    $_SESSION['user_name']  = $user['full_name'];
                    
                    $db_role = $user['role'];

                    // 8. Check if staff member needs to change their password upon first login
                    if (in_array($db_role, ['Academic Advisor', 'Evaluation Committee']) && isset($user['must_change_password']) && (int)$user['must_change_password'] === 1) {
                        $_SESSION['require_password_change'] = true;
                        header("Location: change_pws.php");
                        exit();
                    }
                    
                    // 9. Redirect the user to their specific dashboard
                    if (isset($role_config[$db_role])) {
                        header("Location: " . $role_config[$db_role]['page']);
                        exit();
                    } else {
                        header("Location: student_dashboard.php");
                        exit();
                    }
                } else {
                    // Password or Email is wrong
                    $error = "Invalid login credentials or password. Please try again.";
                }
            }
        } catch(PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Dynamic Title: Changes based on Staff or Student Portal -->
    <title>EduRank - <?php echo ($portal_type === 'staff') ? 'Staff Login' : 'Student Login'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- UI Styling: Advanced Dark Theme with Subtle Gradient -->
    <style>
        body { 
            background: linear-gradient(135deg, #0f172a 0%, #020617 100%);
            min-height: 100vh; 
            display: flex;
            flex-direction: column; 
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .login-card { 
            background-color: #1e293b; 
            border: none; 
            max-width: 440px;
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            padding: 36px 32px;
        }
        .form-control { 
            background-color: #334155; 
            border: 1px solid #475569; 
            color: white; 
            border-radius: 10px;
            padding: 12px 14px;
            transition: all 0.2s;
        }
        .form-control:focus { 
            background-color: #334155; 
            color: white; 
            border-color: #001b55; 
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }
        .form-control::placeholder { color: #94a3b8; }
        .form-label { color: #94a3b8; font-size: 13px; font-weight: 500; }
        .text-secondary-custom { color: #94a3b8; }
        .btn-primary {
            background-color: #2563eb;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.2s;
            width: 100%;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37,99,235,0.3);
        }
        .alert { border-radius: 10px; font-size: 13px; padding: 10px 14px; }
        .divider { border-top: 1px solid #334155; margin: 20px 0; }
        .department-badge {
            background: #2563eb20;
            color: #60a5fa;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            border: 1px solid #2563eb40;
            display: inline-block;
        }
        .logo-text { font-size: 24px; font-weight: 700; letter-spacing: -0.5px; color: white; }
        .logo-text i { color: #fbbf24; }
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 4px;
        }
        .password-toggle:hover { color: #60a5fa; }
        .input-group { position: relative; }
        .register-link { text-align: center; padding: 12px 0 4px 0; }
        .required-star { color: #ef4444; }
        
        .portal-header-badge {
            background: #334155;
            color: #38bdf8;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 15px;
            border: 1px solid #475569;
        }
        .info-message {
            background: #2a3446;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 12px;
            color: #94a3b8;
            border: 1px solid #3b4a5f;
            text-align: center;
        }
        .info-message i { color: #60a5fa; margin-right: 6px; }
    </style>
</head>
<body>

<div class="card login-card">
    <div class="text-center mb-3">
        <!-- System Logo and Name -->
        <div class="logo-text">
            <a href="main.php" class="text-decoration-none d-inline-flex align-items-center gap-2">
                <i class="fa-solid fa-graduation-cap text-warning fs-3"></i>
                <span class="fs-4 fw-bold text-white">EduRank</span>
            </a>
        </div>
        
        <!-- Department Badge -->
        <div class="d-flex align-items-center justify-content-center gap-2 mt-1 mb-2">
            <p class="text-secondary-custom text-sm mb-0">Intelligent Student Award System</p>
            <a href="main.php" class="text-decoration-none">
                <span class="department-badge">
                    <i class="bi bi-building me-1"></i>JTMK
                </span>
            </a>
        </div>

        <!-- Portal Type Indicator (Changes based on URL parameter) -->
        <div>
            <span class="portal-header-badge">
                <i class="bi <?php echo ($portal_type === 'staff') ? 'bi-person-badge' : 'bi-mortarboard-fill'; ?> me-1"></i>
                <?php echo ($portal_type === 'staff') ? 'PA / Committee Staff Portal' : 'Student Portal'; ?>
            </span>
        </div>
    </div>

    <!-- Success Message Alert -->
    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success py-2 text-center">
            <i class="bi bi-check-circle me-1"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- Error Message Alert -->
    <?php if(!empty($error)): ?>
        <div class="alert alert-danger py-2 text-center">
            <i class="bi bi-exclamation-circle me-1"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST" action="login.php">
        <input type="hidden" name="portal_type" value="<?php echo htmlspecialchars($portal_type); ?>">

        <!-- Email Input -->
        <div class="mb-3">
            <label class="form-label">
                <?php echo ($portal_type === 'staff') ? 'Email Address ' : 'Email Address'; ?> <span class="required-star">*</span>
            </label>
            <input type="text" 
                   name="login_id" 
                   class="form-control" 
                   placeholder="<?php echo ($portal_type === 'staff') ? 'Enter staff email ' : 'Enter registered student email'; ?>" 
                   value="<?php echo htmlspecialchars($login_id); ?>" 
                   required>
        </div>

        <!-- Password Input with Show/Hide Toggle -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label mb-0">Password <span class="required-star">*</span></label>
                <a href="forgot_password.php?type=<?php echo htmlspecialchars($portal_type); ?>" class="text-decoration-none text-primary small">Forgot Password?</a>
            </div>
            <div class="input-group">
                <input type="password" name="password" id="passwordInput" class="form-control" 
                       placeholder="Enter your password" required>
                <button type="button" class="password-toggle" id="togglePassword">
                    <i class="bi bi-eye" id="passwordIcon"></i>
                </button>
            </div>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>

        <div class="divider"></div>

        <!-- Registration Link for Students / Info for Staff -->
        <div class="register-link">
            <?php if($portal_type === 'student'): ?>
                <small class="text-secondary-custom">
                    Don't have a student account? 
                    <a href="register.php" class="text-primary text-decoration-none fw-medium">
                        Register here
                    </a>
                </small>
            <?php else: ?>
                <div class="info-message">
                    <i class="bi bi-info-circle"></i>
                    Staff accounts are securely managed by the university.
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Footer -->
<footer class="text-center p-4 mt-5 text-muted">
    <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
</footer>

<!-- JavaScript to handle the Show/Hide Password functionality -->
<script>
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('passwordInput');
        const icon = document.getElementById('passwordIcon');

        // Toggle between password (hidden) and text (visible)
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            passwordInput.type = 'password';
            icon.className = 'bi bi-eye';
        }
    });
</script>
</body>
</html>