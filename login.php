<?php
require_once 'config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';
$login_id = ''; 

// 1. 读取入口类型：student 或 staff (默认为 student)
$portal_type = $_GET['type'] ?? $_POST['portal_type'] ?? 'student';
if (!in_array($portal_type, ['student', 'staff'])) {
    $portal_type = 'student';
}

$role_config = [
    'Student' => [
        'page' => 'student_dashboard.php'
    ],
    'Academic Advisor' => [
        'page' => 'pa_dashboard.php'
    ],
    'Evaluation Committee' => [
        'page' => 'committee_dashboard.php'
    ]
];

// 2. 如果已经登录，直接重定向到对应的 Dashboard
if (function_exists('isLoggedIn') && isLoggedIn() && isset($_SESSION['user_role'])) {
    if (isset($_SESSION['require_password_change']) && $_SESSION['require_password_change'] === true) {
        header("Location: change_pws.php");
        exit();
    }
    $user_role = $_SESSION['user_role'];
    if (isset($role_config[$user_role])) {
        header("Location: " . $role_config[$user_role]['page']);
        exit();
    }
}

// 3. 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = sanitizeInput($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($login_id) || empty($password)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            $user = false;
            
            if ($portal_type === 'student') {
                // 学生通道：必须使用合法 Email 查询 Student 角色
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
                // 教职工通道 (PA / Committee)：同时匹配 Email、Username 或 Full Name
                $stmt = $pdo->prepare("
                    SELECT * FROM users 
                    WHERE (email = ? OR full_name = ? OR full_name = ?) 
                      AND role IN ('Academic Advisor', 'Evaluation Committee') 
                      AND is_active = 1
                ");
                $stmt->execute([$login_id, $login_id, $login_id]);
                $user = $stmt->fetch();
            }
            
            // 验证密码与 Session 写入
            if (empty($error)) {
                if ($user && verifyPassword($password, $user['password'])) {
                    $_SESSION['user_id']    = $user['user_id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role']  = $user['role'];
                    $_SESSION['user_name']  = $user['full_name'];
                    
                    $db_role = $user['role'];

                    // PA / Committee 账号检查是否需要强制修改密码
                    if ($db_role !== 'Student' && isset($user['must_change_password']) && (int)$user['must_change_password'] === 1) {
                        $_SESSION['require_password_change'] = true;
                        header("Location: change_pws.php");
                        exit();
                    }
                    
                    // 根据数据库匹配出的 role 自动跳转
                    if (isset($role_config[$db_role])) {
                        header("Location: " . $role_config[$db_role]['page']);
                        exit();
                    } else {
                        header("Location: student_dashboard.php");
                        exit();
                    }
                } else {
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
    <title>EduRank - <?php echo ($portal_type === 'staff') ? 'Staff Login' : 'Student Login'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #0f172a 0%, #1a2332 100%);
            min-height: 100vh; 
            display: flex;
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
            border-color: #2563eb; 
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
        <div class="logo-text">
            <a href="main.php" class="text-decoration-none d-inline-flex align-items-center gap-2">
                <i class="fa-solid fa-graduation-cap text-warning fs-3"></i>
                <span class="fs-4 fw-bold text-white">EduRank AI</span>
            </a>
        </div>
        <div class="d-flex align-items-center justify-content-center gap-2 mt-1 mb-2">
            <p class="text-secondary-custom text-sm mb-0">Intelligent Student Award System</p>
            <a href="main.php" class="text-decoration-none">
                <span class="department-badge">
                    <i class="bi bi-building me-1"></i>JTMK
                </span>
            </a>
        </div>

        <!-- 动态显示当前入口标题 -->
        <div>
            <span class="portal-header-badge">
                <i class="bi <?php echo ($portal_type === 'staff') ? 'bi-person-badge' : 'bi-mortarboard-fill'; ?> me-1"></i>
                <?php echo ($portal_type === 'staff') ? 'PA / Committee Staff Portal' : 'Student Portal'; ?>
            </span>
        </div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success py-2 text-center">
            <i class="bi bi-check-circle me-1"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <?php if(!empty($error)): ?>
        <div class="alert alert-danger py-2 text-center">
            <i class="bi bi-exclamation-circle me-1"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <input type="hidden" name="portal_type" value="<?php echo htmlspecialchars($portal_type); ?>">

        <div class="mb-3">
            <label class="form-label">
                <?php echo ($portal_type === 'staff') ? 'Email Address / Username' : 'Email Address'; ?> <span class="required-star">*</span>
            </label>
            <input type="text" 
                   name="login_id" 
                   class="form-control" 
                   placeholder="<?php echo ($portal_type === 'staff') ? 'Enter email or committee username' : 'Enter registered student email'; ?>" 
                   value="<?php echo htmlspecialchars($login_id); ?>" 
                   required>
        </div>

        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label mb-0">Password <span class="required-star">*</span></label>
                <a href="forgot_password.php" class="text-decoration-none text-primary small">Forgot Password?</a>
            </div>
            <div class="input-group">
                <input type="password" name="password" id="passwordInput" class="form-control" 
                       placeholder="Enter your password" required>
                <button type="button" class="password-toggle" id="togglePassword">
                    <i class="bi bi-eye" id="passwordIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>

        <div class="divider"></div>

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
                    Staff accounts (PA & Committee) are created by System Administrator.
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('passwordInput');
        const icon = document.getElementById('passwordIcon');

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