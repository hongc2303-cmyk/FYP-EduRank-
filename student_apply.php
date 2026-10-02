<?php
require_once 'config.php';

// ==========================================
// 1. Session & Access Control
// ==========================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Restrict access strictly to students
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = strtoupper($_SESSION['user_name'] ?? '');
$success_apply = '';
$error_apply   = '';
$redirect_anchor = '';

// ==========================================
// 2. Fetch Student Data & Application Status
// ==========================================
$pending = 0;
if (isset($pdo)) {
    // Fetch student profile details
    $stmtS = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
    $stmtS->execute([$user_id]);
    $student = $stmtS->fetch(PDO::FETCH_ASSOC);

    // Check if the student already has a pending application (used for locking the form on load)
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM award_applications WHERE student_id = ? AND status = 'pending'");
    $stmtCheck->execute([$student['student_id']]);
    $pending = $stmtCheck->fetchColumn();
}

// ==========================================
// 3. Fetch Award Categories & Criteria
// ==========================================
$categories = [];
$categoryCriteria = [];
if (isset($pdo)) {
    $stmtCat = $pdo->query("SELECT category_id, category_name, description FROM award_categories ORDER BY category_id ASC");
    $categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
    
    // Assign dynamic requirement hints if descriptions are empty
    foreach ($categories as $cat) {
        $criteriaText = trim($cat['description'] ?? '');
        if ($criteriaText === '') {
            $catNameLower = strtolower($cat['category_name'] ?? '');
            if (strpos($catNameLower, 'kecemerlangan') !== false) $criteriaText = 'Syarat: HPNM 3.60 ke atas diperlukan.';
            elseif (strpos($catNameLower, 'khas') !== false) $criteriaText = 'Syarat: HPNM 3.00 ke atas diperlukan.';
            else $criteriaText = 'Syarat: Sila rujuk panduan rasmi anugerah APCP.';
        }
        $categoryCriteria[$cat['category_id']] = $criteriaText;
    }
}

// ==========================================
// 4. Global System Configuration Fetch
// ==========================================
$current_session = 'Default Session';
$system_status = 'open'; // Default state

if (isset($pdo)) {
    $stmtSes = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('current_session', 'system_status')");
    while ($row = $stmtSes->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'current_session') {
            $current_session = $row['setting_value'];
        }
        if ($row['setting_key'] === 'system_status') {
            $system_status = $row['setting_value'];
        }
    }
}

// Form Locking Logic: Lock if system is closed globally OR if user has a pending review
$is_locked = ($system_status === 'closed' || $pending > 0);


// ==========================================
// 5. Process Application Submission
// ==========================================

// Catch post_max_size exceeded error gracefully
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $error_apply = "Ralat: Saiz fail terlalu besar! Keseluruhan fail yang dimuat naik melebihi had pelayan (Server Limit). Sila kecilkan saiz PDF atau gambar anda.";
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_award'])) {
    
    // STRICT DOUBLE-CLICK PREVENTION: Re-check pending status right inside the POST request
    $stmtStrictCheck = $pdo->prepare("SELECT COUNT(*) FROM award_applications WHERE student_id = ? AND status = 'pending'");
    $stmtStrictCheck->execute([$student['student_id']]);
    $strictPending = $stmtStrictCheck->fetchColumn();

    // Server-side security check against closed systems or duplicate submissions
    if ($system_status === 'closed') {
        $error_apply = "The application submission window is currently CLOSED by the Administrator.";
    } elseif ($strictPending > 0) {
        $error_apply = "You already have a pending application under review. Please wait for the advisor's decision.";
    } else {
        $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
        if (!$category_id) {
            $error_apply = "Please select a valid award category.";
            $redirect_anchor = 'section-cat';
        } else {
            try {
                $gpa = filter_var($_POST['gpa'] ?? null, FILTER_VALIDATE_FLOAT);
                if ($gpa === false || $gpa < 0 || $gpa > 4.00) throw new Exception("CGPA maximum limit is 4.00.|section-a");

                $stmtCatName = $pdo->prepare("SELECT category_name FROM award_categories WHERE category_id = ?");
                $stmtCatName->execute([$category_id]);
                $cat_name_lower = strtolower($stmtCatName->fetchColumn() ?: '');

                // Minimum CGPA requirements validation
                if (strpos($cat_name_lower, 'kecemerlangan') !== false && $gpa < 3.60) throw new Exception("Minimum HPNM/CGPA of 3.60 is required for this category.|section-a");
                if (strpos($cat_name_lower, 'khas') !== false && $gpa < 3.00) throw new Exception("Minimum HPNM/CGPA of 3.00 is required for this category.|section-a");

                // Initialize Database Transaction for secure multi-table insertion
                $pdo->beginTransaction();

                // Insert main application record
                $stmtApp = $pdo->prepare("INSERT INTO award_applications (student_id, category_id, session_name, status) VALUES (?, ?, ?, 'pending')");
                $stmtApp->execute([$student['student_id'], $category_id, $current_session]);
                $application_id = $pdo->lastInsertId();

                // Setup centralized upload directory
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                $stmtCert = $pdo->prepare("INSERT INTO certificates (application_id, file_name, certificate_type, extra_data) VALUES (?, ?, ?, ?)");

                // Helper function: Strictly validates, names, and moves uploaded files
                $uploadFileStrict = function($fileKey, $sectionLabel, $anchor, $compPrefix) use ($upload_dir, $student) {
                    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) throw new Exception("Please upload the required supporting document for {$sectionLabel}.|{$anchor}");
                    
                    // File Size Limitation in PHP (Max 5MB per file as safety net)
                    if ($_FILES[$fileKey]['size'] > 5 * 1024 * 1024) throw new Exception("Fail untuk {$sectionLabel} terlalu besar. Maksimum 5MB sahaja.|{$anchor}");

                    $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) throw new Exception("Invalid file format for {$sectionLabel}. Only PDF, JPG, PNG allowed.|{$anchor}");
                    
                    $matric = preg_replace('/[^a-zA-Z0-9]/', '', $student['matric_no']);
                    // Generate a unique, safe filename using matric number, prefix, and timestamp
                    $saved_filename = $matric . '_' . preg_replace('/[^a-zA-Z0-9_]/', '', $compPrefix) . '_' . time() . '_' . rand(10,99) . '.' . $ext;
                    
                    if (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $upload_dir . $saved_filename)) throw new Exception("Failed to save the uploaded file to the server.|{$anchor}");
                    return $saved_filename;
                };

                // VITAL FIX: Use 'a', 'b', 'c', 'd', 'e', 'p' for certificate_type parameter to match ENUM
                
                // Section A: Transcript Upload
                $file_name = $uploadFileStrict('result_doc', 'Section (a) Result Transcript', 'section-a', 'Akademik_Transcript');
                $stmtCert->execute([$application_id, $file_name, 'a', number_format($gpa, 2)]);

                // Dynamic Section Processing based on Category Type
                if ($category_id == 2) {
                    // --- CATEGORY 2: PROJECT AWARDS ---
                    $project_title = trim($_POST['project_title'] ?? '');
                    $member_names = $_POST['member_name'] ?? [];
                    $member_ics   = $_POST['member_ic'] ?? [];
                    $member_regs  = $_POST['member_reg'] ?? [];

                    $formatted_members = [];
                    foreach ($member_names as $idx => $m_name) {
                        $m_name = trim($m_name);
                        if (!empty($m_name)) {
                            $formatted_members[] = ($idx + 1) . ". " . $m_name . "\n   (No. K/P: " . trim($member_ics[$idx] ?? '-') . ", No. Pend: " . trim($member_regs[$idx] ?? '-') . ")";
                        }
                    }
                    if (!empty($project_title)) {
                        $stmtProj = $pdo->prepare("INSERT INTO project_award_details (application_id, project_title, group_members) VALUES (?, ?, ?)");
                        $stmtProj->execute([$application_id, $project_title, implode("\n\n", $formatted_members)]);
                    }
                    
                    // 1. Mandatory File Upload
                    $file_name = $uploadFileStrict('project_doc', 'Bukti Pencapaian FYP', 'project-fields-container', 'Projek_Bukti');
                    $stmtCert->execute([$application_id, $file_name, 'p', json_encode(['type' => 'Bukti_Pencapaian_FYP'])]);
                    
                    // 2. Optional File Upload
                    if (isset($_FILES['project_industry_doc']) && $_FILES['project_industry_doc']['error'] === UPLOAD_ERR_OK) {
                        $industry_file = $uploadFileStrict('project_industry_doc', 'Sijil Penghargaan Industri', 'project-fields-container', 'Projek_Industri');
                        $stmtCert->execute([$application_id, $industry_file, 'p', json_encode(['type' => 'Sijil_Penghargaan_Industri'])]);
                    }

                } else {
                    // --- CATEGORIES 1 & 3: CO-CURRICULAR & SPECIAL AWARDS ---
                    
                    // Section B1: JPP
                    $jpp_name = trim($_POST["jpp_name"] ?? '');
                    $jpp_role = trim($_POST["jpp_role"] ?? '');
                    if (!empty($jpp_name) || !empty($jpp_role) || (!empty($_FILES["jpp_doc"]['name']) && $_FILES["jpp_doc"]['error'] === UPLOAD_ERR_OK)) {
                        if (empty($jpp_name) || empty($jpp_role)) throw new Exception("Sila lengkapkan semua medan untuk JPP atau biarkan kosong.|section-b");
                        $file_name = $uploadFileStrict("jpp_doc", "JPP Proof", 'section-b', "Organisasi_JPP");
                        $stmtCert->execute([$application_id, $file_name, 'b', json_encode(['org_name' => $jpp_name, 'position' => $jpp_role, 'type' => 'JPP'], JSON_UNESCAPED_UNICODE)]);
                    }

                    // Section B2: Kelab
                    $kelab_name = trim($_POST["kelab_name"] ?? '');
                    $kelab_role = trim($_POST["kelab_role"] ?? '');
                    if (!empty($kelab_name) || !empty($kelab_role) || (!empty($_FILES["kelab_doc"]['name']) && $_FILES["kelab_doc"]['error'] === UPLOAD_ERR_OK)) {
                        if (empty($kelab_name) || empty($kelab_role)) throw new Exception("Sila lengkapkan semua medan untuk Kelab/Persatuan atau biarkan kosong.|section-b");
                        $file_name = $uploadFileStrict("kelab_doc", "Kelab Proof", 'section-b', "Organisasi_Kelab");
                        $stmtCert->execute([$application_id, $file_name, 'b', json_encode(['org_name' => $kelab_name, 'position' => $kelab_role, 'type' => 'Kelab'], JSON_UNESCAPED_UNICODE)]);
                    }
                    
                    // Section C: Program Organization (Max 2)
                    for ($i = 1; $i <= 2; $i++) {
                        $prog_name = trim($_POST["prog{$i}_name"] ?? '');
                        if (!empty($prog_name) || (isset($_FILES["prog{$i}_doc"]) && $_FILES["prog{$i}_doc"]["error"] === UPLOAD_ERR_OK)) {
                            if (empty($prog_name)) throw new Exception("Sila masukkan nama bagi Penganjuran Program {$i}.|section-c");
                            $file_name = $uploadFileStrict("prog{$i}_doc", "Program {$i} Proof", 'section-c', "Penganjuran_Prog{$i}");
                            $stmtCert->execute([$application_id, $file_name, 'c', json_encode(['name' => $prog_name, 'role' => $_POST["prog{$i}_role"] ?? '', 'level' => $_POST["prog{$i}_level"] ?? ''], JSON_UNESCAPED_UNICODE)]);
                        }
                    }
                    
                    // Section D: Event Participation (Max 3)
                    for ($i = 1; $i <= 3; $i++) {
                        $part_name = trim($_POST["part{$i}_name"] ?? '');
                        if (!empty($part_name) || (isset($_FILES["part{$i}_doc"]) && $_FILES["part{$i}_doc"]["error"] === UPLOAD_ERR_OK)) {
                            if (empty($part_name)) throw new Exception("Sila masukkan nama bagi Penyertaan Acara {$i}.|section-d");
                            $file_name = $uploadFileStrict("part{$i}_doc", "Participation {$i} Proof", 'section-d', "Penyertaan_Part{$i}");
                            $stmtCert->execute([$application_id, $file_name, 'd', json_encode(['name' => $part_name, 'level' => $_POST["part{$i}_level"] ?? ''], JSON_UNESCAPED_UNICODE)]);
                        }
                    }
                    
                    // Section E: Bonus Marks
                    $bonuses = [
                        'has_bonus_prof' => ['key'=>'bonus_prof', 'label'=>'Sijil Profesional', 'prefix'=>'Bonus_Profesional', 'type'=>'Bonus_Profesional'], 
                        'has_bonus_ext'  => ['key'=>'bonus_ext',  'label'=>'Pengiktirafan Luar', 'prefix'=>'Bonus_Luar', 'type'=>'Bonus_Luar'], 
                        'has_bonus_intl' => ['key'=>'bonus_intl', 'label'=>'Penglibatan Antarabangsa', 'prefix'=>'Bonus_Antarabangsa', 'type'=>'Bonus_Antarabangsa']
                    ];
                    foreach ($bonuses as $checkbox => $meta) {
                        if (!empty($_POST[$checkbox])) {
                            $file_name = $uploadFileStrict($meta['key'], $meta['label'], 'section-e', $meta['prefix']);
                            $stmtCert->execute([$application_id, $file_name, 'e', json_encode(['type' => $meta['type']], JSON_UNESCAPED_UNICODE)]);
                        }
                    }
                }
                
                // Finalize Transaction
                $pdo->commit();
                $success_apply = "Application successfully submitted to JKP!";
                $pending = 1; // Immediately lock the form upon success
                $is_locked = true; 
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $parts = explode('|', $e->getMessage());
                $error_apply = "Submission failed: " . $parts[0];
                $redirect_anchor = $parts[1] ?? '';
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
    <title>EduRank - Submit Application</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

<div class="d-flex">
    <!-- Sidebar Navigation -->
    <aside class="sidebar p-4 d-flex flex-column justify-content-between" style="min-height: 100vh;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="fa-solid fa-graduation-cap text-warning me-2 fs-4"></i>
                <span class="fs-5 fw-bold text-white">EduRank</span>
            </div>
            <div class="text-uppercase text-secondary text-xs fw-bold mb-3" style="font-size: 11px; letter-spacing: 1px;">MENU</div>
            <ul class="nav nav-pills flex-column gap-2">
                <li class="nav-item"><a href="student_dashboard.php" class="nav-link d-flex align-items-center gap-3"><i class="bi bi-house-fill"></i> Dashboard</a></li>
                <li class="nav-item"><a href="student_history.php" class="nav-link d-flex align-items-center gap-3"><i class="bi bi-clock-history"></i> Submission History <?php if($pending > 0): ?><span class="badge bg-warning text-dark ms-auto"><?php echo $pending; ?></span><?php endif; ?></a></li>
                <li class="nav-item"><a href="student_apply.php" class="nav-link active d-flex align-items-center gap-3"><i class="bi bi-plus-circle"></i> Submit Application</a></li>
                <li class="nav-item"><a href="student_profile.php" class="nav-link d-flex align-items-center gap-3"><i class="bi bi-person"></i> My Profile</a></li>
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
            <a href="logout.php" class="text-danger text-decoration-none text-sm fw-semibold d-flex align-items-center gap-2 ms-1"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </aside>

    <div class="main-content flex-grow-1 d-flex flex-column" style="min-height: 100vh;">
        <header class="top-header shadow-sm">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <?php if (file_exists('notification.php')) include 'notification.php'; ?>
        </header>

        <div class="p-4 flex-grow-1">
            <div class="mb-4">
                <h4 class="fw-bold text-dark mb-1">APCP Merit Calculator & Award Application</h4>
                <p class="text-secondary text-sm">Submit your information categorized by Academic/Co-curricular, Project Awards, or Special Awards</p>
            </div>

            <!-- Feedback Alerts -->
            <?php if(!empty($success_apply)): ?>
                <div class="alert alert-success py-2 mb-4"><i class="bi bi-check-circle me-1"></i> <?php echo htmlspecialchars($success_apply); ?></div>
            <?php endif; ?>
            
            <?php if(!empty($error_apply)): ?>
                <div class="alert alert-danger py-3 mb-4">
                    <div><i class="bi bi-exclamation-circle me-1"></i> <?php echo htmlspecialchars($error_apply); ?></div>
                </div>
            <?php endif; ?>
            
            <!-- System Status & Form Lock Alerts -->
            <?php if($system_status === 'closed'): ?>
                <div class="alert alert-danger py-3 mb-4 fw-bold shadow-sm border-0" style="background-color: #fee2e2;">
                    <i class="bi bi-door-closed-fill me-2 fs-5"></i>
                    The application submission window is currently CLOSED for this academic session (<?php echo htmlspecialchars($current_session); ?>).
                </div>
            <?php elseif($pending > 0): ?>
                <div class="alert alert-warning py-2 mb-4">
                    <i class="bi bi-info-circle me-1"></i> You have pending applications under review. New submissions are temporarily locked.
                </div>
            <?php else: ?>
                <div class="alert alert-info py-2 mb-4 shadow-sm border-0">
                    <i class="bi bi-calendar-check me-1"></i> Current Academic Session: <strong><?php echo htmlspecialchars($current_session); ?></strong>
                </div>
            <?php endif; ?>

            <div class="card bg-white custom-card shadow-sm p-4" style="max-width: 850px;">
                <form method="POST" action="student_apply.php" enctype="multipart/form-data" id="applicationForm">
                    <input type="hidden" name="apply_award" value="1">
                    
                    <!-- CATEGORY SELECTION (Malay Form Context) -->
                    <div id="section-cat" class="mb-4">
                        <h6 class="fw-bold mb-2">1. Pilih Kategori Anugerah <span class="text-danger">*</span></h6>
                        <div class="border rounded p-3 bg-light">
                            <!-- Utilize $is_locked to dynamically disable the form -->
                            <select name="category_id" id="category_id" class="form-select border-primary" required <?php echo $is_locked ? 'disabled' : ''; ?>>
                                <option value="">-- Sila Pilih Kategori --</option>
                                <optgroup label="Pilihan 1: Pencapaian Akademik & Kokurikulum">
                                    <?php foreach($categories as $cat): if($cat['category_id'] == 1): ?>
                                        <option value="<?php echo $cat['category_id']; ?>" data-description="<?php echo htmlspecialchars($categoryCriteria[$cat['category_id']]); ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                    <?php endif; endforeach; ?>
                                </optgroup>
                                <optgroup label="Pilihan 2: Anugerah Projek">
                                    <?php foreach($categories as $cat): if($cat['category_id'] == 2): ?>
                                        <option value="<?php echo $cat['category_id']; ?>" data-description="<?php echo htmlspecialchars($categoryCriteria[$cat['category_id']]); ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                    <?php endif; endforeach; ?>
                                </optgroup>
                                <optgroup label="Pilihan 3: Anugerah Khas">
                                    <?php foreach($categories as $cat): if($cat['category_id'] >= 3): ?>
                                        <option value="<?php echo $cat['category_id']; ?>" data-description="<?php echo htmlspecialchars($categoryCriteria[$cat['category_id']]); ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                    <?php endif; endforeach; ?>
                                </optgroup>
                            </select>
                            <div id="award-criteria-box" class="alert alert-info py-2 mt-3 mb-0" style="display: none; font-size: 13px;"></div>
                        </div>
                    </div>

                    <!-- COMMON SECTION: ACADEMIC TRANSCRIPT -->
                    <div id="section-a" class="mb-4">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-mortarboard me-2"></i>(a) Pencapaian Akademik / HPNM</h6>
                        <div class="row border rounded p-3 bg-light mx-0">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="form-label text-secondary fw-medium" style="font-size: 13px;">HPNM / CGPA <span class="text-danger">*</span></label>
                                <input type="number" name="gpa" class="form-control" placeholder="cth. 3.85" step="0.01" min="0" max="4.00" value="<?php echo htmlspecialchars($_POST['gpa'] ?? ''); ?>" required <?php echo $is_locked ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary fw-medium" style="font-size: 13px;">Muat Naik Transkrip Keputusan <span class="text-danger">*</span></label>
                                <input type="file" name="result_doc" class="form-control file-upload" accept=".pdf,.png" <?php echo $is_locked ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>

                    <!-- DYNAMIC SECTION: PROJECT DETAILS (Category 2) -->
                    <div class="mb-4" id="project-fields-container" style="display: none;">
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-folder-check me-2"></i>Butiran Projek & Bukti (Bagi Anugerah Projek Terbaik)</h6>
                        <div class="border border-primary-subtle rounded p-3 bg-light">
                            
                            <div class="mb-4">
                                <label class="form-label text-dark fw-bold" style="font-size: 13px;">Tajuk Projek <span class="text-danger">*</span></label>
                                <input type="text" name="project_title" class="form-control border-primary" placeholder="Masukkan tajuk rasmi projek akhir" value="<?php echo htmlspecialchars($_POST['project_title'] ?? ''); ?>" <?php echo $is_locked ? 'disabled' : ''; ?>>
                            </div>
                            
                            <div class="mb-2 d-flex justify-content-between align-items-center border-bottom pb-2">
                                <label class="form-label text-dark fw-bold mb-0" style="font-size: 13px;">Butiran Ahli Kumpulan <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold" id="addMemberBtn" <?php echo $is_locked ? 'disabled' : ''; ?>><i class="bi bi-person-plus-fill me-1"></i> Tambah Ahli</button>
                            </div>
                            
                            <div id="membersContainer">
                                <div class="row g-2 mb-3 member-row align-items-center">
                                    <div class="col-md-5"><input type="text" name="member_name[]" class="form-control form-control-sm" placeholder="Nama Penuh" <?php echo $is_locked ? 'disabled' : ''; ?>></div>
                                    <div class="col-md-4"><input type="text" name="member_ic[]" class="form-control form-control-sm" placeholder="No. K/P" <?php echo $is_locked ? 'disabled' : ''; ?>></div>
                                    <div class="col-md-3"><input type="text" name="member_reg[]" class="form-control form-control-sm" placeholder="No. Pend" <?php echo $is_locked ? 'disabled' : ''; ?>></div>
                                </div>
                            </div>
                            
                            <!-- FYP Specific Uploads (Wajib & Optional) -->
                            <div class="mt-4 border-top pt-3">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-dark fw-bold" style="font-size: 13px;">
                                            <i class="bi bi-file-earmark-check text-success me-1"></i>Bukti Pencapaian FYP <span class="text-danger">*</span>
                                        </label>
                                        <p class="text-muted mb-1" style="font-size: 11px;">Muat Naik Surat Sokongan (Wajib)</p>
                                        <input type="file" id="projectDocInput" name="project_doc" class="form-control form-control-sm border-primary file-upload" accept=".pdf,.jpg,.jpeg,.png" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label text-dark fw-bold" style="font-size: 13px;">
                                            <i class="bi bi-award text-warning me-1"></i>Sijil Penghargaan Industri
                                        </label>
                                        <p class="text-muted mb-1" style="font-size: 11px;">Muat Naik Sijil Kolaborasi (Pilihan)</p>
                                        <input type="file" name="project_industry_doc" class="form-control form-control-sm file-upload" accept=".pdf,.jpg,.jpeg,.png" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </div>

                    <!-- DYNAMIC SECTION: CO-CURRICULAR & SPECIAL AWARDS (Categories 1 & 3) -->
                    <div id="cocurricular-fields-container">
                        
                        <!-- SECTION B: Organization -->
                        <div id="section-b" class="mb-4">
                            <h6 class="fw-bold text-primary mb-2"><i class="bi bi-people me-2"></i>(b) Kokurikulum 1 - Pengurusan Organisasi (Maksimum 10 Markah)</h6>
                            <div class="border rounded p-3 bg-light">
                                
                                <!-- JPP Block -->
                                <div class="mb-4">
                                    <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Jawatankuasa Perwakilan Pelajar (JPP) (Pilihan)</div>
                                    <div class="border border-top-0 rounded-bottom p-3 bg-white">
                                        <div class="row g-3">
                                            <div class="col-12"><input type="text" name="jpp_name" class="form-control" placeholder="Sila nyatakan organisasi JPP" <?php echo $is_locked ? 'disabled' : ''; ?>></div>
                                            <div class="col-md-7">
                                                <select name="jpp_role" class="form-select" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                    <option value="">-- Pilih Jawatan Tertinggi --</option>
                                                    <option value="JPP_YDP">YDP (10 Markah)</option>
                                                    <option value="JPP_NYDP">NYDP (9 Markah)</option>
                                                    <option value="JPP_SU_BEND">Setiausaha/Bendahari (8 Markah)</option>
                                                    <option value="JPP_EXCO">Exco (7 Markah)</option>
                                                </select>
                                            </div>
                                            <div class="col-md-5"><input type="file" name="jpp_doc" class="form-control file-upload" accept=".pdf,.jpg,.jpeg,.png" <?php echo $is_locked ? 'disabled' : ''; ?>></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Kelab/Persatuan Block -->
                                <div>
                                    <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Kelab / Persatuan & Lain-lain Organisasi (Pilihan)</div>
                                    <div class="border border-top-0 rounded-bottom p-3 bg-white">
                                        <div class="row g-3">
                                            <div class="col-12"><input type="text" name="kelab_name" class="form-control" placeholder="Sila nyatakan Nama Kelab / Persatuan" <?php echo $is_locked ? 'disabled' : ''; ?>></div>
                                            <div class="col-md-7">
                                                <select name="kelab_role" class="form-select" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                    <option value="">-- Pilih Jawatan Tertinggi --</option>
                                                    <option value="CLUB_PENGERUSI">Pengerusi (6 Markah)</option>
                                                    <option value="CLUB_NAIB">Naib Pengerusi (5 Markah)</option>
                                                    <option value="CLUB_SU_BEND">Setiausaha/Bendahari (4 Markah)</option>
                                                    <option value="CLUB_AJK">AJK (3 Markah)</option>
                                                    <option value="CLUB_AHLI">Ahli Biasa (1 Markah)</option>
                                                </select>
                                            </div>
                                            <div class="col-md-5"><input type="file" name="kelab_doc" class="form-control file-upload" accept=".pdf,.jpg,.jpeg,.png" <?php echo $is_locked ? 'disabled' : ''; ?>></div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- SECTION C: Penganjuran Program -->
                        <div id="section-c" class="mb-4">
                            <h6 class="fw-bold text-primary mb-2"><i class="bi bi-calendar-event me-2"></i>(c) Kokurikulum 2 - Penganjuran Program (Maksimum 20 Markah)</h6>
                            <div class="border rounded p-3 bg-light">
                                <?php for ($i = 1; $i <= 2; $i++): ?>
                                <div class="<?php echo $i == 1 ? 'mb-4' : ''; ?>">
                                    <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Penganjuran Program <?php echo $i; ?></div>
                                    <div class="border border-top-0 rounded-bottom p-3 bg-white">
                                        <div class="row g-3">
                                            <div class="col-12"><input type="text" name="prog<?php echo $i; ?>_name" class="form-control" placeholder="Nama Program" <?php echo $is_locked ? 'disabled' : ''; ?>></div>
                                            <div class="col-md-6">
                                                <select name="prog<?php echo $i; ?>_role" class="form-select" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                    <option value="">-- Pilih Jawatan --</option>
                                                    <option value="PENGARAH">Pengarah/Pengerusi (5 Markah)</option>
                                                    <option value="TIMBALAN">Timbalan Pengarah (4 Markah)</option>
                                                    <option value="SU_BEND">Setiausaha/Bendahari (3 Markah)</option>
                                                    <option value="AJK">AJK (2 Markah)</option>
                                                    <option value="AHLI">Ahli Biasa (1 Markah)</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <select name="prog<?php echo $i; ?>_level" class="form-select" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                    <option value="">-- Pilih Peringkat --</option>
                                                    <option value="ANTARABANGSA">Antarabangsa / Kebangsaan (5 Markah)</option>
                                                    <option value="NEGERI">Negeri / Komuniti (4 Markah)</option>
                                                    <option value="POLITEKNIK">Politeknik (3 Markah)</option>
                                                    <option value="JABATAN">Jabatan (2 Markah)</option>
                                                </select>
                                            </div>
                                            <div class="col-12"><input type="file" name="prog<?php echo $i; ?>_doc" class="form-control file-upload" accept=".pdf,.jpg,.jpeg,.png" <?php echo $is_locked ? 'disabled' : ''; ?>></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- SECTION D: Penyertaan Program -->
                        <div id="section-d" class="mb-4">
                            <h6 class="fw-bold text-primary mb-2"><i class="bi bi-trophy me-2"></i>(d) Kokurikulum 3 - Penyertaan Program (Maksimum 30 Markah)</h6>
                            <div class="border rounded p-3 bg-light">
                                <?php for($i = 1; $i <= 3; $i++): ?>
                                    <div class="mb-3">
                                        <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Penyertaan <?php echo $i; ?></div>
                                        <div class="border border-top-0 rounded-bottom p-3 bg-white">
                                            <div class="row g-3">
                                                <div class="col-12"><input type="text" name="part<?php echo $i; ?>_name" class="form-control" placeholder="Nama Acara / Program" <?php echo $is_locked ? 'disabled' : ''; ?>></div>
                                                <div class="col-md-7">
                                                    <select name="part<?php echo $i; ?>_level" class="form-select" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                        <option value="">-- Pilih Peringkat --</option>
                                                        <option value="ANTARABANGSA">Antarabangsa / Kebangsaan (10 Markah)</option>
                                                        <option value="NEGERI">Negeri / Komuniti (9 Markah)</option>
                                                        <option value="POLITEKNIK">Politeknik (8 Markah)</option>
                                                        <option value="JABATAN">Jabatan (7 Markah)</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-5"><input type="file" name="part<?php echo $i; ?>_doc" class="form-control file-upload" accept=".pdf,.jpg,.jpeg,.png" <?php echo $is_locked ? 'disabled' : ''; ?>></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <!-- SECTION E: Bonus Marks -->
                        <div id="section-e" class="mb-4">
                            <h6 class="fw-bold text-primary mb-2"><i class="bi bi-star me-2"></i>(e) Lain-lain / Bonus (Maksimum 6 Markah)</h6>
                            <div class="border rounded p-3 bg-light">
                                <div class="row g-3 align-items-center mb-3">
                                    <div class="col-md-5">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="has_bonus_prof" value="1" id="bonusProfCheck" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                            <label class="form-check-label text-secondary fw-medium" style="font-size: 13px;" for="bonusProfCheck">Sijil Profesional (+2 Markah)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-7"><input type="file" name="bonus_prof" class="form-control form-control-sm file-upload" accept=".pdf,.jpg,.jpeg,.png" <?php echo $is_locked ? 'disabled' : ''; ?>></div>
                                </div>
                                <div class="row g-3 align-items-center mb-3">
                                    <div class="col-md-5">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="has_bonus_ext" value="1" id="bonusExtCheck" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                            <label class="form-check-label text-secondary fw-medium" style="font-size: 13px;" for="bonusExtCheck">Pengiktirafan Luar (+2 Markah)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-7"><input type="file" name="bonus_ext" class="form-control form-control-sm file-upload" accept=".pdf,.jpg,.jpeg,.png" <?php echo $is_locked ? 'disabled' : ''; ?>></div>
                                </div>
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-5">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="has_bonus_intl" value="1" id="bonusIntlCheck" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                            <label class="form-check-label text-secondary fw-medium" style="font-size: 13px;" for="bonusIntlCheck">Penglibatan Antarabangsa (+2 Markah)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-7"><input type="file" name="bonus_intl" class="form-control form-control-sm file-upload" accept=".pdf,.jpg,.jpeg,.png" <?php echo $is_locked ? 'disabled' : ''; ?>></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Final Submission Button -->
                    <button type="submit" class="btn btn-primary fw-bold px-4 py-3 w-100 shadow-sm <?php echo $system_status === 'closed' ? 'btn-secondary' : ''; ?>" style="border-radius: 8px;" <?php echo $is_locked ? 'disabled' : ''; ?>>
                        <i class="bi bi-send me-1"></i> Submit Official Application to JKP
                    </button>
                </form>
            </div>
        </div>
        
        <footer class="text-center p-4 text-muted border-top mt-auto" style="background-color: #f8f9fa;">
            <p class="mb-0 small fw-medium">&copy; 2026 EduRank System - Designed By JWC</p>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script for Auto-Scrolling to Erroneous Sections -->
<?php if(!empty($redirect_anchor)): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const el = document.getElementById("<?php echo $redirect_anchor; ?>");
        if (el) el.scrollIntoView({ behavior: 'smooth' });
    });
</script>
<?php endif; ?>

<!-- Script for Front-End File Size Limitation, Validation, and Double-Click Prevention -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById('applicationForm');
    const fileInputs = document.querySelectorAll('.file-upload');
    const MAX_FILE_SIZE_MB = 5; // Max 5MB per file
    const MAX_TOTAL_SIZE_MB = 35; // Max 35MB total upload size to prevent server crash

    form.addEventListener('submit', function(e) {
        let totalSize = 0;
        let hasError = false;

        fileInputs.forEach(input => {
            if (input.files.length > 0) {
                const fileSizeMB = input.files[0].size / (1024 * 1024);
                totalSize += fileSizeMB;

                if (fileSizeMB > MAX_FILE_SIZE_MB) {
                    alert(`Ralat: Fail "${input.files[0].name}" terlalu besar (${fileSizeMB.toFixed(2)} MB). Maksimum saiz dibenarkan ialah ${MAX_FILE_SIZE_MB}MB setiap fail.`);
                    input.value = ''; // Clear the input
                    hasError = true;
                }
            }
        });

        if (totalSize > MAX_TOTAL_SIZE_MB) {
            alert(`Ralat: Jumlah saiz semua fail adalah ${totalSize.toFixed(2)} MB. Sila pastikan jumlah keseluruhan tidak melebihi ${MAX_TOTAL_SIZE_MB}MB untuk mengelakkan kegagalan sistem.`);
            hasError = true;
        }

        if (hasError) {
            e.preventDefault(); // Stop form submission
        } else {
            // PREVENT DOUBLE CLICK: Disable button and change text
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Submitting... Please Wait';
        }
    });

    const categorySelect = document.getElementById("category_id");
    const projectContainer = document.getElementById("project-fields-container");
    const cocurricularContainer = document.getElementById("cocurricular-fields-container");
    const criteriaBox = document.getElementById("award-criteria-box");
    const projectDocInput = document.getElementById("projectDocInput");

    function updateFormSections() {
        if (!categorySelect || !projectContainer || !cocurricularContainer) return;
        const selectedId = parseInt(categorySelect.value);
        
        if (!selectedId) {
            projectContainer.style.display = "none";
            cocurricularContainer.style.display = "block";
            if (projectDocInput) projectDocInput.required = false;
            return;
        }
        
        if (selectedId === 2) { 
            projectContainer.style.display = "block";
            cocurricularContainer.style.display = "none";
            if (projectDocInput && !projectDocInput.disabled) projectDocInput.required = true; 
        } else {
            projectContainer.style.display = "none";
            cocurricularContainer.style.display = "block";
            if (projectDocInput) projectDocInput.required = false;
        }
    }

    function updateAwardCriteria() {
        if (!categorySelect || !criteriaBox) return;
        const selectedOption = categorySelect.options[categorySelect.selectedIndex];
        const description = selectedOption ? (selectedOption.getAttribute("data-description") || "") : "";
        if (!description) { criteriaBox.style.display = "none"; return; }
        criteriaBox.innerHTML = "<strong>Syarat Kelayakan:</strong> " + description;
        criteriaBox.style.display = "block";
    }

    if (categorySelect) {
        categorySelect.addEventListener("change", function () {
            updateFormSections();
            updateAwardCriteria();
        });
        updateFormSections();
        updateAwardCriteria();
    }

    document.getElementById('addMemberBtn')?.addEventListener('click', function() {
        const container = document.getElementById('membersContainer');
        if (container.getElementsByClassName('member-row').length >= 5) {
            alert('Maksimum 5 orang ahli dibenarkan untuk setiap kumpulan projek.');
            return;
        }
        const newRow = document.createElement('div');
        newRow.className = 'row g-2 mb-3 member-row align-items-center';
        newRow.innerHTML = `
            <div class="col-md-5"><input type="text" name="member_name[]" class="form-control form-control-sm" placeholder="Nama Penuh" required></div>
            <div class="col-md-3"><input type="text" name="member_ic[]" class="form-control form-control-sm" placeholder="No. K/P" required></div>
            <div class="col-md-3"><input type="text" name="member_reg[]" class="form-control form-control-sm" placeholder="No. Pend" required></div>
            <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-danger remove-member-btn" title="Buang Ahli"><i class="bi bi-trash-fill"></i></button></div>
        `;
        container.appendChild(newRow);
        newRow.querySelector('.remove-member-btn').addEventListener('click', function() { newRow.remove(); });
    });
});
</script>
</body>
</html>