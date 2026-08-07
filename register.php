<?php
// 1. Include database configuration
require_once 'config.php';

$error = '';
$form_data = [];

// Fetch Academic Advisors from users table
try {
    $stmt = $pdo->prepare("
        SELECT user_id, full_name 
        FROM users 
        WHERE role = 'Academic Advisor'
        ORDER BY full_name
    ");
    $stmt->execute();
    $pa_list = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "System error. Please try again later.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = sanitizeInput($_POST['full_name'] ?? '');
    $email            = sanitizeInput($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $matric_no        = sanitizeInput($_POST['matric_no'] ?? '');
    $semester         = 5; // Fixed to Semester 5
    $phone            = sanitizeInput($_POST['phone'] ?? '');
    $advisor_id       = filter_var($_POST['pa_id'] ?? 0, FILTER_VALIDATE_INT); // Maps to advisor_id
    
    $errors = [];
    
    // Validation
    if (empty($full_name) || strlen($full_name) < 3) {
        $errors[] = "Full name must be at least 3 characters.";
    }
    // Capitalize full name
    $full_name = ucwords(strtolower($full_name));
    
    if (empty($email) || !validateEmail($email)) {
        $errors[] = "Please enter a valid email address.";
    }
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    if (empty($matric_no) || strlen($matric_no) < 4) {
        $errors[] = "Please enter a valid matric number.";
    }
    if ($advisor_id == 0) {
        $errors[] = "Please select your PA.";
    }
    
    if (empty($errors)) {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Email already registered.";
            } else {
                // Check if matric number already exists
                $stmt = $pdo->prepare("SELECT student_id FROM students WHERE matric_no = ?");
                $stmt->execute([$matric_no]);
                if ($stmt->fetch()) {
                    $errors[] = "Matric number already registered.";
                } else {
                    // Hash password
                    $hashed_password = hashPassword($password);
                    
                    $pdo->beginTransaction();
                    
                    // 1. Insert into users table
                    $stmt = $pdo->prepare("
                        INSERT INTO users (full_name, email, password, role, is_active) 
                        VALUES (?, ?, ?, 'Student', 1)
                    ");
                    $stmt->execute([$full_name, $email, $hashed_password]);
                    $user_id = $pdo->lastInsertId();
                    
                    // 2. Insert into students table (FIXED: using advisor_id column)
                    $stmt = $pdo->prepare("
                        INSERT INTO students (user_id, matric_no, programme, semester, phone, advisor_id) 
                        VALUES (?, ?, 'DIT', ?, ?, ?)
                    ");
                    $stmt->execute([$user_id, $matric_no, $semester, $phone, $advisor_id]);
                    
                    $pdo->commit();
                    
                    // Redirect back to login with success message
                    header("Location: login.php?msg=" . urlencode("Registration successful! Please log in with your credentials."));
                    exit();
                }
            }
        } catch(PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
    
    $error = implode("<br>", $errors);
    $form_data = [
        'full_name' => $full_name, 
        'email' => $email, 
        'matric_no' => $matric_no, 
        'phone' => $phone, 
        'semester' => $semester, 
        'pa_id' => $advisor_id
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank AI - Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { 
            background: linear-gradient(135deg, #0f172a 0%, #1a2332 100%);
            min-height: 100vh; 
            padding: 40px 20px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .register-card { 
            background-color: #1e293b; 
            border: none; 
            max-width: 520px; 
            width: 100%;
            margin: 0 auto;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            padding: 32px 28px;
        }
        .form-control, .form-select { 
            background-color: #334155; 
            border: 1px solid #475569; 
            color: white; 
            border-radius: 10px;
            padding: 12px 14px;
        }
        .form-control:focus, .form-select:focus { 
            background-color: #334155; 
            color: white; 
            border-color: #2563eb; 
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }
        .form-control::placeholder {
            color: #94a3b8;
        }
        .form-control option, .form-select option {
            background-color: #1e293b;
            color: white;
        }
        .form-label {
            color: #94a3b8;
            font-size: 13px;
            font-weight: 500;
        }
        .text-secondary-custom {
            color: #94a3b8;
        }
        .required-star {
            color: #ef4444;
        }
        .password-hint {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        .alert {
            font-size: 13px;
            padding: 10px 14px;
            border-radius: 10px;
        }
        .btn-primary {
            background-color: #2563eb;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37,99,235,0.3);
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 576px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .register-card {
                padding: 20px 16px !important;
            }
        }
        .divider {
            border-top: 1px solid #334155;
            margin: 20px 0;
        }
        .field-description {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }
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
        .logo-text {
            font-size: 28px;
            font-weight: 700;
            color: white;
        }
        .logo-text i {
            color: #fbbf24;
        }
        .password-strength {
            height: 4px;
            margin-top: 6px;
            border-radius: 4px;
            background-color: #334155;
            transition: all 0.3s;
        }
        .semester-badge {
            background: #22c55e20;
            color: #22c55e;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid #22c55e40;
            display: inline-block;
            width: 100%;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="card register-card">
    <div class="text-center mb-3">
        <div class="logo-text">
            <i class="bi bi-trophy-fill me-2"></i>EduRank AI
        </div>
        <div class="d-flex align-items-center justify-content-center gap-2 mt-1">
            <p class="text-secondary-custom text-sm mb-0">Student Registration</p>
            <span class="department-badge">
                <i class="bi bi-building me-1"></i>JTMK
            </span>
        </div>
    </div>

    <?php if(!empty($error)): ?>
        <div class="alert alert-danger py-2 text-center">
            <i class="bi bi-exclamation-circle me-1"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php" novalidate>
        <div class="form-group">
            <label class="form-label">Full Name <span class="required-star">*</span></label>
            <input type="text" name="full_name" class="form-control" 
                   value="<?php echo htmlspecialchars($form_data['full_name'] ?? ''); ?>" 
                   placeholder="e.g., Ahmad Bin Abdullah"
                   required>
            <div class="field-description">Your name will be automatically capitalized</div>
        </div>

        <div class="form-group">
            <label class="form-label">Email Address <span class="required-star">*</span></label>
            <input type="email" name="email" class="form-control" 
                   value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" 
                   placeholder="your.email@example.com"
                   required>
            <div class="field-description">Your institutional or personal email</div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Password <span class="required-star">*</span></label>
                <input type="password" name="password" id="password" class="form-control" required>
                <div class="password-hint">Min 8 chars with uppercase, lowercase & number</div>
                <div class="password-strength" id="passwordStrength"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm Password <span class="required-star">*</span></label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Matric No. <span class="required-star">*</span></label>
                <input type="text" name="matric_no" class="form-control" 
                       value="<?php echo htmlspecialchars($form_data['matric_no'] ?? ''); ?>" 
                       placeholder="e.g., DIT123456"
                       required>
                <div class="field-description">Your student identification number</div>
            </div>
            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" class="form-control" 
                       placeholder="e.g., 0113992232" 
                       value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Semester <span class="required-star">*</span></label>
                <div class="semester-badge">
                    <i class="bi bi-book me-1"></i>
                    Semester 5 (Fixed)
                </div>
                <input type="hidden" name="semester" value="5">
                <div class="field-description">All students are registered for Semester 5</div>
            </div>
            <div class="form-group">
                <label class="form-label">PA (Penasihat Akademik) <span class="required-star">*</span></label>
                <select name="pa_id" class="form-select" required>
                    <option value="">-- Select your PA --</option>
                    <?php foreach($pa_list as $pa): ?>
                        <option value="<?php echo $pa['user_id']; ?>" <?php echo (($form_data['pa_id'] ?? '') == $pa['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pa['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="field-description">Select your academic advisor</div>
            </div>
        </div>

        <div class="divider"></div>
        
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-person-plus me-2"></i>Create Account
        </button>

        <div class="mt-3 text-center">
            <small class="text-secondary-custom">
                Already have an account? 
                <a href="login.php" class="text-primary text-decoration-none fw-medium">Sign in here</a>
            </small>
        </div>
    </form>
</div>

<script>
    // Name capitalization on input
    document.querySelector('input[name="full_name"]').addEventListener('input', function() {
        // This will auto-capitalize as user types
        const words = this.value.split(' ');
        const capitalized = words.map(word => {
            if (word.length > 0) {
                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            }
            return word;
        });
        this.value = capitalized.join(' ');
    });

    document.getElementById('password').addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.getElementById('passwordStrength');
        let strength = 0;
        
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        
        const colors = ['#ef4444', '#ef4444', '#f59e0b', '#f59e0b', '#22c55e', '#22c55e'];
        const widths = ['0%', '20%', '40%', '60%', '80%', '100%'];
        
        strengthBar.style.width = widths[strength];
        strengthBar.style.backgroundColor = colors[strength];
    });

    // Prevent form submission if semester is manually changed
    document.querySelector('form').addEventListener('submit', function(e) {
        // Ensure semester is always 5
        const hiddenInput = document.querySelector('input[name="semester"]');
        if (hiddenInput) {
            hiddenInput.value = 5;
        }
    });
</script>

</body>
</html>