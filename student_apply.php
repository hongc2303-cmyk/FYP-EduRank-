<?php
// ============================================================================
// 1. SESSION & CONFIGURATION SETUP
// ============================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php'; 

$user_id = $_SESSION['user_id'] ?? 0;
$success = '';
$error   = '';
$redirect_anchor = '';

// Check pending status
$pending = 0;
if ($user_id > 0 && isset($pdo)) {
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(*) 
        FROM award_applications a 
        JOIN students s ON a.student_id = s.student_id 
        WHERE s.user_id = ? AND a.status = 'pending'
    ");
    $stmtCheck->execute([$user_id]);
    $pending = $stmtCheck->fetchColumn();
}

// Fetch categories
$categories = [];
if (isset($pdo)) {
    $stmtCat = $pdo->query("SELECT category_id, category_name FROM award_categories ORDER BY category_id ASC");
    $categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================================
// 2. PROCESS FORM SUBMISSION
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_award']) && $pending == 0) {
    $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$category_id) {
        $error = "Please select a valid award category.";
        $redirect_anchor = 'section-cat';
    } else {
        try {
            // Check student existence
            $stmtStudent = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
            $stmtStudent->execute([$user_id]);
            $student = $stmtStudent->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                throw new Exception("Student record not found. Please contact administrator.|section-cat");
            }
            $student_id = $student['student_id'];

            // Validate CGPA Maximum Limit (Max 4.0 / 4.00)
            $gpa = filter_var($_POST['gpa'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($gpa === false || $gpa < 0 || $gpa > 4.00) {
                throw new Exception("CGPA maximum limit is 4.00. Please enter a valid CGPA.|section-a");
            }

            $pdo->beginTransaction();

            // Insert into award_applications
            $stmtApp = $pdo->prepare("
                INSERT INTO award_applications (student_id, category_id, status) 
                VALUES (?, ?, 'pending')
            ");
            $stmtApp->execute([$student_id, $category_id]);
            $application_id = $pdo->lastInsertId();

            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $stmtCert = $pdo->prepare("
                INSERT INTO certificates (application_id, file_name, certificate_type, extra_data) 
                VALUES (?, ?, ?, ?)
            ");

            // Strict File Upload Helper Function
            $uploadFileStrict = function($fileKey, $sectionLabel, $anchor) use ($upload_dir) {
                if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Please upload the required file for {$sectionLabel}.|{$anchor}");
                }
                
                $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
                $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
                if (!in_array($ext, $allowed)) {
                    throw new Exception("Invalid file format for {$sectionLabel}. Allowed formats: PDF, JPG, PNG.|{$anchor}");
                }

                $saved_filename = time() . '_' . uniqid() . '.' . $ext;
                $target_path = $upload_dir . $saved_filename;
                
                if (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $target_path)) {
                    throw new Exception("Failed to upload file for {$sectionLabel}.|{$anchor}");
                }
                return $saved_filename;
            };

            // Section (a): CGPA & Result Transcript
            $file_name = $uploadFileStrict('result_doc', 'Section (a) Result Transcript', 'section-a');
            $stmtCert->execute([$application_id, $file_name, 'a', number_format($gpa, 2)]);

            // Section (b): Organisation
            if (!empty($_POST['org_role']) || !empty($_POST['org_name'])) {
                if (empty($_POST['org_name']) || empty($_POST['org_role'])) {
                    throw new Exception("Please complete all Organisation fields in Section (b).|section-b");
                }
                $file_name = $uploadFileStrict('org_doc', 'Section (b) Organisation Proof', 'section-b');
                $extra = json_encode([
                    'org_name' => trim($_POST['org_name']),
                    'position' => $_POST['org_role']
                ], JSON_UNESCAPED_UNICODE);

                $stmtCert->execute([$application_id, $file_name, 'b', $extra]);
            }

            // Section (c): Programs Organized
            if (!empty($_POST['prog1_name'])) {
                $file_name = $uploadFileStrict('prog1_doc', 'Program 1 in Section (c)', 'section-c');
                $extra = json_encode([
                    'name'  => trim($_POST['prog1_name']),
                    'role'  => $_POST['prog1_role'] ?? '',
                    'level' => $_POST['prog1_level'] ?? ''
                ], JSON_UNESCAPED_UNICODE);

                $stmtCert->execute([$application_id, $file_name, 'c', $extra]);
            }

            if (!empty($_POST['prog2_name'])) {
                $file_name = $uploadFileStrict('prog2_doc', 'Program 2 in Section (c)', 'section-c');
                $extra = json_encode([
                    'name'  => trim($_POST['prog2_name']),
                    'role'  => $_POST['prog2_role'] ?? '',
                    'level' => $_POST['prog2_level'] ?? ''
                ], JSON_UNESCAPED_UNICODE);

                $stmtCert->execute([$application_id, $file_name, 'c', $extra]);
            }

            // Section (d): Event Participation
            for ($i = 1; $i <= 3; $i++) {
                if (!empty($_POST["part{$i}_name"])) {
                    $file_name = $uploadFileStrict("part{$i}_doc", "Participation {$i} in Section (d)", 'section-d');
                    $extra = json_encode([
                        'name'  => trim($_POST["part{$i}_name"]),
                        'level' => $_POST["part{$i}_level"] ?? ''
                    ], JSON_UNESCAPED_UNICODE);

                    $stmtCert->execute([$application_id, $file_name, 'd', $extra]);
                }
            }

            // Section (e): Bonus Items
            if (!empty($_POST['has_bonus_prof'])) {
                $file_name = $uploadFileStrict('bonus_prof', 'Section (e) Professional Certificate', 'section-e');
                $extra = json_encode(['type' => 'Professional_Cert'], JSON_UNESCAPED_UNICODE);
                $stmtCert->execute([$application_id, $file_name, 'e', $extra]);
            }

            if (!empty($_POST['has_bonus_ext'])) {
                $file_name = $uploadFileStrict('bonus_ext', 'Section (e) External Recognition', 'section-e');
                $extra = json_encode(['type' => 'External_Recognition'], JSON_UNESCAPED_UNICODE);
                $stmtCert->execute([$application_id, $file_name, 'e', $extra]);
            }

            if (!empty($_POST['has_bonus_intl'])) {
                $file_name = $uploadFileStrict('bonus_intl', 'Section (e) International Involvement', 'section-e');
                $extra = json_encode(['type' => 'International_Involvement'], JSON_UNESCAPED_UNICODE);
                $stmtCert->execute([$application_id, $file_name, 'e', $extra]);
            }

            $pdo->commit();
            // Removed raw #id string reference as requested
            $success = "Application submitted successfully!";
            $pending = 1;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $parts = explode('|', $e->getMessage());
            $error = "Submission failed: " . $parts[0];
            $redirect_anchor = $parts[1] ?? '';
        }
    }
}
?>

<div class="mb-4">
    <h4 class="fw-bold text-dark mb-1">Student Excellence Award Application Form</h4>
    <p class="text-secondary text-sm">Fill in your information across all sections and upload supporting documents.</p>
</div>

<?php if(!empty($success)): ?>
    <div class="alert alert-success py-2 mb-4"><i class="bi bi-check-circle me-1"></i> <?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if(!empty($error)): ?>
    <div class="alert alert-danger py-2 mb-4"><i class="bi bi-exclamation-circle me-1"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if($pending > 0): ?>
    <div class="alert alert-warning py-2 mb-4"><i class="bi bi-info-circle me-1"></i> You have pending applications. New submissions are temporarily locked.</div>
<?php endif; ?>

<div class="card bg-white custom-card shadow-sm p-4" style="max-width: 850px;">
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="apply_award" value="1">
        
        <div id="section-cat" class="mb-4">
            <h6 class="fw-bold mb-2">1. Select Award Category <span class="text-danger">*</span></h6>
            <div class="border rounded p-3 bg-light">
                <select name="category_id" class="form-select border-primary" required <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                    <option value="">-- Choose Category --</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="section-a" class="mb-4">
            <h6 class="fw-bold text-primary mb-2"><i class="bi bi-mortarboard me-2"></i>(a) Pencapaian Akademik (Max 40 Marks)</h6>
            <div class="row border rounded p-3 bg-light mx-0">
                <div class="col-md-6 mb-3 mb-md-0">
                    <label class="form-label text-secondary fw-medium" style="font-size: 13px;">HPNM / CGPA <span class="text-danger">*</span></label>
                    <input type="number" name="gpa" class="form-control" placeholder="e.g. 3.85" step="0.01" min="0" max="4.00" required <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-secondary fw-medium" style="font-size: 13px;">Upload Result Transcript (PDF/PNG) <span class="text-danger">*</span></label>
                    <input type="file" name="result_doc" class="form-control" accept=".pdf,.png" required <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                </div>
            </div>
        </div>

        <div id="section-b" class="mb-4">
            <h6 class="fw-bold text-primary mb-2"><i class="bi bi-people me-2"></i>(b) Kokurikulum 1 - Pengurusan Organisasi (Top 1 Position)</h6>
            <div class="border rounded p-3 bg-light">
                <div class="row g-3">
                    <div class="col-12">
                        <input type="text" name="org_name" class="form-control" placeholder="Organization / Club Name (e.g. JPP / Kelab IT)" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                    </div>
                    <div class="col-md-7">
                        <select name="org_role" class="form-select" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                            <option value="">-- Select Highest Position --</option>
                            <option value="JPP_YDP">JPP - YDP (10 Marks)</option>
                            <option value="JPP_NYDP">JPP - NYDP (9 Marks)</option>
                            <option value="JPP_SU_BEND">JPP - Setiausaha/Bendahari (8 Marks)</option>
                            <option value="JPP_EXCO">JPP - Exco (7 Marks)</option>
                            <option value="CLUB_PENGERUSI">Kelab - Pengerusi (6 Marks)</option>
                            <option value="CLUB_NAIB">Kelab - Naib Pengerusi (5 Marks)</option>
                            <option value="CLUB_SU_BEND">Kelab - Setiausaha/Bendahari (4 Marks)</option>
                            <option value="CLUB_AJK">Kelab - AJK (3 Marks)</option>
                            <option value="CLUB_AHLI">Kelab - Ahli Biasa (1 Mark)</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="file" name="org_doc" class="form-control" accept=".pdf,.jpg,.jpeg,.png" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                    </div>
                </div>
            </div>
        </div>

        <div id="section-c" class="mb-4">
            <h6 class="fw-bold text-primary mb-2"><i class="bi bi-calendar-event me-2"></i>(c) Kokurikulum 2 - Penganjuran Program (Max 2 Best Programs)</h6>
            <div class="border rounded p-3 bg-light">
                <div class="mb-4">
                    <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Program 1</div>
                    <div class="border border-top-0 rounded-bottom p-3 bg-white">
                        <div class="row g-3">
                            <div class="col-12">
                                <input type="text" name="prog1_name" class="form-control" placeholder="Program Name" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-6">
                                <select name="prog1_role" class="form-select" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                                    <option value="PENGARAH">Pengarah/Pengerusi (5 Marks)</option>
                                    <option value="TIMBALAN">Timbalan Pengarah (4 Marks)</option>
                                    <option value="SU_BEND">Setiausaha/Bendahari (3 Marks)</option>
                                    <option value="AJK">AJK (2 Marks)</option>
                                    <option value="AHLI">Ahli Biasa (1 Mark)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <select name="prog1_level" class="form-select" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                                    <option value="ANTARABANGSA">Antarabangsa / Kebangsaan (5 Marks)</option>
                                    <option value="NEGERI">Negeri / Daerah / Komuniti (4 Marks)</option>
                                    <option value="POLITEKNIK">Politeknik (3 Marks)</option>
                                    <option value="JABATAN">Jabatan (2 Marks)</option>
                                </select>
                            </div> 
                            <div class="col-12">
                                <input type="file" name="prog1_doc" class="form-control" accept=".pdf,.jpg,.jpeg,.png" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Program 2</div>
                    <div class="border border-top-0 rounded-bottom p-3 bg-white">
                        <div class="row g-3">
                            <div class="col-12">
                                <input type="text" name="prog2_name" class="form-control" placeholder="Program Name" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-6">
                                <select name="prog2_role" class="form-select" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                                    <option value="PENGARAH">Pengarah/Pengerusi (5 Marks)</option>
                                    <option value="TIMBALAN">Timbalan Pengarah (4 Marks)</option>
                                    <option value="SU_BEND">Setiausaha/Bendahari (3 Marks)</option>
                                    <option value="AJK">AJK (2 Marks)</option>
                                    <option value="AHLI">Ahli Biasa (1 Mark)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <select name="prog2_level" class="form-select" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                                    <option value="ANTARABANGSA">Antarabangsa / Kebangsaan (5 Marks)</option>
                                    <option value="NEGERI">Negeri / Daerah / Komuniti (4 Marks)</option>
                                    <option value="POLITEKNIK">Politeknik (3 Marks)</option>
                                    <option value="JABATAN">Jabatan (2 Marks)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <input type="file" name="prog2_doc" class="form-control" accept=".pdf,.jpg,.jpeg,.png" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="section-d" class="mb-4">
            <h6 class="fw-bold text-primary mb-2"><i class="bi bi-trophy me-2"></i>(d) Kokurikulum 3 - Penyertaan Program (Max 3 Best Participations)</h6>
            <div class="border rounded p-3 bg-light">
                <div class="mb-3">
                    <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Participation 1</div>
                    <div class="border border-top-0 rounded-bottom p-3 bg-white">
                        <div class="row g-3">
                            <div class="col-12">
                                <input type="text" name="part1_name" class="form-control" placeholder="Event / Competition Name" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-7">
                                <select name="part1_level" class="form-select" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                                    <option value="ANTARABANGSA">Antarabangsa / Kebangsaan (10 Marks)</option>
                                    <option value="NEGERI">Negeri / Daerah / Komuniti (9 Marks)</option>
                                    <option value="POLITEKNIK">Politeknik (8 Marks)</option>
                                    <option value="JABATAN">Jabatan (7 Marks)</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="file" name="part1_doc" class="form-control" accept=".pdf,.jpg,.jpeg,.png" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Participation 2</div>
                    <div class="border border-top-0 rounded-bottom p-3 bg-white">
                        <div class="row g-3">
                            <div class="col-12">
                                <input type="text" name="part2_name" class="form-control" placeholder="Event / Competition Name" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-7">
                                <select name="part2_level" class="form-select" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                                    <option value="ANTARABANGSA">Antarabangsa / Kebangsaan (10 Marks)</option>
                                    <option value="NEGERI">Negeri / Daerah / Komuniti (9 Marks)</option>
                                    <option value="POLITEKNIK">Politeknik (8 Marks)</option>
                                    <option value="JABATAN">Jabatan (7 Marks)</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="file" name="part2_doc" class="form-control" accept=".pdf,.jpg,.jpeg,.png" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Participation 3</div>
                    <div class="border border-top-0 rounded-bottom p-3 bg-white">
                        <div class="row g-3">
                            <div class="col-12">
                                <input type="text" name="part3_name" class="form-control" placeholder="Event / Competition Name" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-7">
                                <select name="part3_level" class="form-select" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                                    <option value="ANTARABANGSA">Antarabangsa / Kebangsaan (10 Marks)</option>
                                    <option value="NEGERI">Negeri / Daerah / Komuniti (9 Marks)</option>
                                    <option value="POLITEKNIK">Politeknik (8 Marks)</option>
                                    <option value="JABATAN">Jabatan (7 Marks)</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="file" name="part3_doc" class="form-control" accept=".pdf,.jpg,.jpeg,.png" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="section-e" class="mb-4">
            <h6 class="fw-bold text-primary mb-2"><i class="bi bi-star me-2"></i>(e) Bonus / Extra Recognition (Optional)</h6>
            <div class="border rounded p-3 bg-light">
                <div class="row g-3 align-items-center mb-3">
                    <div class="col-md-5">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="has_bonus_prof" value="1" id="bonusProfCheck" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                            <label class="form-check-label text-secondary fw-medium" style="font-size: 13px;" for="bonusProfCheck">
                                Sijil Profesional (+2 Marks)
                            </label>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <input type="file" name="bonus_prof" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                    </div>
                </div>

                <div class="row g-3 align-items-center mb-3">
                    <div class="col-md-5">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="has_bonus_ext" value="1" id="bonusExtCheck" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                            <label class="form-check-label text-secondary fw-medium" style="font-size: 13px;" for="bonusExtCheck">
                                Pengiktirafan Luar (+2 Marks)
                            </label>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <input type="file" name="bonus_ext" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                    </div>
                </div>

                <div class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="has_bonus_intl" value="1" id="bonusIntlCheck" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                            <label class="form-check-label text-secondary fw-medium" style="font-size: 13px;" for="bonusIntlCheck">
                                Penglibatan Antarabangsa (+2 Marks)
                            </label>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <input type="file" name="bonus_intl" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary fw-bold px-4 py-3 w-100 shadow-sm" style="border-radius: 8px;" <?php echo ($pending > 0) ? 'disabled' : ''; ?>>
            <i class="bi bi-send me-1"></i> Submit Official Application
        </button>
    </form>
</div>

<?php if(!empty($redirect_anchor)): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const el = document.getElementById("<?php echo $redirect_anchor; ?>");
        if (el) {
            el.scrollIntoView({ behavior: 'smooth' });
        }
    });
</script>
<?php endif; ?>