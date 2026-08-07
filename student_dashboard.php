<?php
// 1. Include database configuration file
require_once 'config.php';

// 2. Check if user is logged in as Student
if (!isLoggedIn() || strtolower($_SESSION['user_role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit();
}

// 3. Get student session details
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$success = '';
$error = '';

// 4. File upload function
function uploadFile($file, $target_dir = "uploads/") {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return false;
    }
    
    $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return $new_filename;
    }
    
    return false;
}

// 5. Handle award application form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['apply_award'])) {
        $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
        $gpa = filter_var($_POST['gpa'] ?? 0, FILTER_VALIDATE_FLOAT);
        $org_name = sanitizeInput($_POST['org_name'] ?? '');
        $org_role = sanitizeInput($_POST['org_role'] ?? '');

        if ($category_id == 0) {
            $error = "Please select a valid award category.";
        } elseif ($gpa <= 0 || $gpa > 4.0) {
            $error = "Please enter a valid GPA (0.00 - 4.00).";
        } elseif (empty($org_name)) {
            $error = "Please fill in your Club/Organization details in Section B.";
        } elseif (empty($org_role)) {
            $error = "Please select your role in the organization.";
        } else {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $student_record = $stmt->fetch();
                
                if (!$student_record) {
                    $error = "Student record not found.";
                } else {
                    $stmt = $pdo->prepare("
                        SELECT application_id FROM award_applications 
                        WHERE student_id = ? AND status IN ('pending', 'verified')
                    ");
                    $stmt->execute([$student_record['student_id']]);
                    
                    if ($stmt->fetch()) {
                        $error = "You already have a pending application.";
                    } else {
                        $result_doc = null;
                        if (isset($_FILES['result_doc']) && $_FILES['result_doc']['error'] === UPLOAD_ERR_OK) {
                            $result_doc = uploadFile($_FILES['result_doc'], 'uploads/results/');
                        }
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO award_applications (student_id, category_id, status) 
                            VALUES (?, ?, 'pending')
                        ");
                        $stmt->execute([$student_record['student_id'], $category_id]);
                        $application_id = $pdo->lastInsertId();
                        
                        $org_doc = null;
                        if (isset($_FILES['org_doc']) && $_FILES['org_doc']['error'] === UPLOAD_ERR_OK) {
                            $org_doc = uploadFile($_FILES['org_doc'], 'uploads/organizations/');
                        }
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO curriculum_org (application_id, org_name, org_role, org_doc) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$application_id, $org_name, $org_role, $org_doc]);
                        
                        for ($i = 1; $i <= 2; $i++) {
                            if (!empty($_POST["prog{$i}_name"])) {
                                $prog_doc = null;
                                if (isset($_FILES["prog{$i}_doc"]) && $_FILES["prog{$i}_doc"]['error'] === UPLOAD_ERR_OK) {
                                    $prog_doc = uploadFile($_FILES["prog{$i}_doc"], 'uploads/programs/');
                                }
                                
                                $stmt = $pdo->prepare("
                                    INSERT INTO curriculum_programs (application_id, program_name, program_role, program_level, program_doc) 
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $application_id,
                                    sanitizeInput($_POST["prog{$i}_name"]),
                                    sanitizeInput($_POST["prog{$i}_role"] ?? ''),
                                    sanitizeInput($_POST["prog{$i}_level"] ?? ''),
                                    $prog_doc
                                ]);
                            }
                        }
                        
                        for ($i = 1; $i <= 3; $i++) {
                            if (!empty($_POST["part{$i}_name"])) {
                                $part_doc = null;
                                if (isset($_FILES["part{$i}_doc"]) && $_FILES["part{$i}_doc"]['error'] === UPLOAD_ERR_OK) {
                                    $part_doc = uploadFile($_FILES["part{$i}_doc"], 'uploads/participations/');
                                }
                                
                                $stmt = $pdo->prepare("
                                    INSERT INTO curriculum_participations (application_id, participation_name, participation_level, participation_doc) 
                                    VALUES (?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $application_id,
                                    sanitizeInput($_POST["part{$i}_name"]),
                                    sanitizeInput($_POST["part{$i}_level"] ?? ''),
                                    $part_doc
                                ]);
                            }
                        }
                        
                        $bonus_prof = null;
                        $bonus_ext = null;
                        $bonus_intl = null;
                        
                        if (isset($_FILES['bonus_prof']) && $_FILES['bonus_prof']['error'] === UPLOAD_ERR_OK) {
                            $bonus_prof = uploadFile($_FILES['bonus_prof'], 'uploads/bonus/');
                        }
                        if (isset($_FILES['bonus_ext']) && $_FILES['bonus_ext']['error'] === UPLOAD_ERR_OK) {
                            $bonus_ext = uploadFile($_FILES['bonus_ext'], 'uploads/bonus/');
                        }
                        if (isset($_FILES['bonus_intl']) && $_FILES['bonus_intl']['error'] === UPLOAD_ERR_OK) {
                            $bonus_intl = uploadFile($_FILES['bonus_intl'], 'uploads/bonus/');
                        }
                        
                        if ($bonus_prof || $bonus_ext || $bonus_intl) {
                            $stmt = $pdo->prepare("
                                INSERT INTO bonus_recognition (application_id, professional_cert, external_recognition, international_participation) 
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([$application_id, $bonus_prof, $bonus_ext, $bonus_intl]);
                        }
                        
                        $pdo->commit();
                        $success = "Application submitted successfully!";
                    }
                }
            } catch(Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "System error: " . $e->getMessage();
            }
        }
    }
}

// 6. Fetch student profile, applications and stats
try {
    // Fetch student profile along with assigned Academic Advisor (PA) name
$stmt = $pdo->prepare("
    SELECT 
        s.*, 
        u.full_name, 
        u.email,
        pa.full_name AS pa_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN users pa ON s.advisor_id = pa.user_id
    WHERE s.user_id = ?
");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Fetch applications along with category name and CGPA from certificates
$stmt = $pdo->prepare("
    SELECT 
        a.*, 
        c.category_name, 
        cert.extra_data AS student_cgpa, 
        v.remarks AS pa_remarks 
    FROM award_applications a 
    JOIN award_categories c ON a.category_id = c.category_id 
    LEFT JOIN certificates cert ON a.application_id = cert.application_id AND cert.certificate_type = 'a' 
    LEFT JOIN application_verifications v ON a.application_id = v.application_id 
    WHERE a.student_id = ? 
    ORDER BY a.application_date DESC
");
$stmt->execute([$student['student_id']]);
$applications = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT * FROM award_categories ORDER BY category_name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    $total_applications = count($applications);
    $pending = 0; $verified = 0; $rejected = 0; $evaluated = 0;
    
    foreach($applications as $app) {
        switch($app['status']) {
            case 'pending': $pending++; break;
            case 'verified': $verified++; break;
            case 'rejected': $rejected++; break;
            case 'evaluated': $evaluated++; break;
        }
    }
} catch(PDOException $e) {
    $error = "System error. Please try again later.";
}

$page = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank AI - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

<div class="d-flex">
    <aside class="sidebar p-4 d-flex flex-column justify-content-between">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="fa-solid fa-graduation-cap text-warning me-2"></i>
                <span class="fs-5 fw-bold text-white">EduRank AI</span>
            </div>
            <div class="text-uppercase text-secondary text-xs fw-bold mb-3" style="font-size: 11px; letter-spacing: 1px;">MENU</div>
            <ul class="nav nav-pills flex-column gap-2">
                <li class="nav-item">
                    <a href="?page=dashboard" class="nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?> d-flex align-items-center gap-3">
                        <i class="bi bi-house-fill"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=history" class="nav-link <?php echo $page === 'history' ? 'active' : ''; ?> d-flex align-items-center gap-3">
                        <i class="bi bi-clock-history"></i> Submission History
                        <?php if($pending > 0): ?>
                            <span class="badge bg-warning text-dark ms-auto"><?php echo $pending; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=apply" class="nav-link <?php echo $page === 'apply' ? 'active' : ''; ?> d-flex align-items-center gap-3">
                        <i class="bi bi-plus-circle"></i> Submit Application
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=profile" class="nav-link <?php echo $page === 'profile' ? 'active' : ''; ?> d-flex align-items-center gap-3">
                        <i class="bi bi-person"></i> My Profile
                    </a>
                </li>
            </ul>
        </div>
        <div class="border-top border-secondary pt-3">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px;">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <div>
                    <div class="fw-bold text-sm text-white" style="font-size: 14px;"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="text-secondary" style="font-size: 12px;">Student</div>
                </div>
            </div>
            <form method="POST" action="logout.php">
                <button type="submit" class="btn text-danger p-0 border-0 text-sm fw-semibold d-flex align-items-center gap-2 ms-1 bg-transparent">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </button>
            </form>
        </div>
    </aside>

    <div class="main-content flex-grow-1">
        <header class="top-header shadow-sm">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <?php include 'notification.php'; ?>
        </header>

        <div class="p-4">
            <?php if($page === 'dashboard'): ?>
                <div class="mb-4">
                    <h4 class="fw-bold text-dark mb-1">Student Portal</h4>
                    <p class="text-secondary text-sm">Welcome back, <?php echo htmlspecialchars($user_name); ?></p>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card bg-white custom-card shadow-sm p-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="card-icon-bg bg-primary-subtle text-primary fs-4"><i class="bi bi-file-text"></i></div>
                                <div><div class="text-secondary text-sm" style="font-size: 12px;">Total Applications</div><div class="fs-4 fw-bold text-dark"><?php echo $total_applications; ?></div></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white custom-card shadow-sm p-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="card-icon-bg bg-warning-subtle text-warning fs-4"><i class="bi bi-clock"></i></div>
                                <div><div class="text-secondary text-sm" style="font-size: 12px;">Pending PA Review</div><div class="fs-4 fw-bold text-dark"><?php echo $pending; ?></div></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white custom-card shadow-sm p-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="card-icon-bg bg-info-subtle text-info fs-4"><i class="bi bi-check-circle"></i></div>
                                <div><div class="text-secondary text-sm" style="font-size: 12px;">Verified</div><div class="fs-4 fw-bold text-dark"><?php echo $verified; ?></div></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-white custom-card shadow-sm p-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="card-icon-bg bg-success-subtle text-success fs-4"><i class="bi bi-award"></i></div>
                                <div><div class="text-secondary text-sm" style="font-size: 12px;">Evaluated</div><div class="fs-4 fw-bold text-dark"><?php echo $evaluated; ?></div></div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if(!empty($success)): ?><div class="alert alert-success py-2 mb-4"><i class="bi bi-check-circle me-1"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <?php if(!empty($error)): ?><div class="alert alert-danger py-2 mb-4"><i class="bi bi-exclamation-circle me-1"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <div class="card bg-white custom-card shadow-sm p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0 text-dark">Recent Submissions</h6>
                        <a href="?page=history" class="text-primary text-decoration-none fw-semibold" style="font-size: 13px;">View All</a>
                    </div>
                    <?php if(count($applications) > 0): ?>
                        <div class="table-responsive">
                            <table class="table align-middle border-0">
                                <thead class="table-light text-secondary text-uppercase" style="font-size: 11px;">
                                    <tr><th class="border-0">AWARD CATEGORY</th><th class="border-0">SUBMISSION DATE</th><th class="border-0">GPA / CGPA</th><th class="border-0 text-end">STATUS</th></tr>
                                </thead>
                                <tbody style="font-size: 14px;">
                                    <?php foreach(array_slice($applications, 0, 5) as $app): ?>
                                        <tr class="border-bottom">
                                            <td class="fw-semibold text-dark">
                                                <i class="bi bi-award me-1 text-primary"></i><?php echo htmlspecialchars($app['category_name'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="text-secondary">
                                                <i class="bi bi-calendar3 me-1"></i><?php echo !empty($app['application_date']) ? date('M d, Y', strtotime($app['application_date'])) : '-'; ?>
                                            </td>
                                            <td class="text-secondary fw-semibold">
                                                <?php 
                                                    $gpa_value = floatval($app['student_cgpa'] ?? $app['gpa'] ?? $app['extra_data'] ?? 0);
                                                    if ($gpa_value > 0) {
                                                        echo number_format($gpa_value, 2);
                                                    } else {
                                                        echo '<span class="text-muted">-</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if (($app['status'] ?? '') === 'pending'): ?>
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill">Pending PA Review</span>
                                                <?php elseif (($app['status'] ?? '') === 'verified'): ?>
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-pill">Verified</span>
                                                <?php elseif (($app['status'] ?? '') === 'evaluated'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Evaluated</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-secondary">
                            <i class="bi bi-inbox fs-2 mb-2 d-block"></i>
                            <p class="mb-1">No applications submitted yet.</p>
                            <a href="?page=apply" class="text-primary text-decoration-none fw-semibold">Submit your first application</a>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif($page === 'history'): ?>
                <?php require_once 'student_history.php'; ?>

            <?php elseif($page === 'apply'): ?>
                <?php require_once 'student_apply.php'; ?>

            <?php elseif($page === 'profile'): ?>
                <?php require_once 'student_profile.php'; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Clean up URL fragment IDs on page load -->
<script>
    if (window.location.hash) {
        window.history.replaceState(null, null, window.location.pathname);
    }
</script>
</body>
</html>