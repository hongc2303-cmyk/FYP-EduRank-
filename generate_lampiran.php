<?php
// 1. Include DB and verify session
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) { 
    die("Unauthorized Access. Please login."); 
}

$cat_id = filter_var($_GET['cat_id'] ?? 1, FILTER_VALIDATE_INT);
$lampiran_title = "";
$lampiran_type = "";

// --- FIX: Fetch Current Session to ensure we print the CURRENT winner ---
$current_session = '';
if (isset($pdo)) {
    $stmtSes = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'current_session'");
    $current_session = $stmtSes->fetchColumn() ?: 'Default Session';
}
// ----------------------------------------------------------------------

// 2. Determine Official Lampiran Format based on Category
if ($cat_id == 1) {
    $lampiran_title = "ANUGERAH KECEMERLANGAN AKADEMIK & KOKURIKULUM";
    $lampiran_type = "03"; // Official Lampiran APCP 03 format
} elseif ($cat_id == 2) {
    $lampiran_title = "ANUGERAH PROJEK TERBAIK (PENYELIDIKAN & INOVASI)";
    $lampiran_type = "06"; // Official Lampiran APCP 06 format (Requires group members)
} else {
    $lampiran_title = "ANUGERAH KHAS";
    $lampiran_type = "05"; // Official Lampiran APCP 05 format
}

// 3. Fetch the Nominated Student (Top 1 winner locked by JKP Chairman)
$winner = null;
if (isset($pdo)) {
    // FIX: Added 'session_name' constraint
    $stmt = $pdo->prepare("
        SELECT u.full_name, s.matric_no, s.programme, c.category_name, v.calculated_score, a.application_id
        FROM award_applications a
        JOIN students s ON a.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        JOIN award_categories c ON a.category_id = c.category_id
        LEFT JOIN application_verifications v ON a.application_id = v.application_id
        WHERE a.category_id = ? AND a.status = 'nominated' AND a.session_name = ?
        LIMIT 1
    ");
    $stmt->execute([$cat_id, $current_session]);
    $winner = $stmt->fetch(PDO::FETCH_ASSOC);

    // If it's Category 2 (Projek Terbaik), fetch the project details and group members
    if ($cat_id == 2 && $winner) {
        $stmtProj = $pdo->prepare("SELECT project_title, group_members FROM project_award_details WHERE application_id = ?");
        $stmtProj->execute([$winner['application_id']]);
        $proj_details = $stmtProj->fetch(PDO::FETCH_ASSOC);
        
        $winner['project_title'] = $proj_details['project_title'] ?? 'N/A';
        $winner['group_members'] = $proj_details['group_members'] ?? 'N/A';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lampiran APCP <?php echo htmlspecialchars($lampiran_type); ?></title>
    <style>
        /* Print-friendly styles tailored for official documents */
        body { font-family: Arial, sans-serif; padding: 40px; color: #000; line-height: 1.5; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 30px; margin-bottom: 40px; }
        th, td { border: 1px solid #000; padding: 12px; text-align: center; font-size: 14px; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .signature-box { margin-top: 60px; text-align: left; }
        .print-btn { 
            display: block; margin: 20px auto; padding: 10px 20px; 
            background: #2563eb; color: #fff; text-decoration: none; 
            width: 150px; text-align: center; border-radius: 5px; cursor: pointer; 
            font-weight: bold; font-family: sans-serif; border: none;
        }
        .print-btn:hover { background: #1d4ed8; }
        /* Hide the button when printing the page */
        @media print { .print-btn { display: none !important; } }
    </style>
</head>
<body>

    <div class="text-center">
        <h4 style="margin: 0; text-align: right; color: #555;">Lampiran APCP <?php echo htmlspecialchars($lampiran_type); ?></h4>
        <br>
        <h3 style="text-decoration: underline; margin-bottom: 5px;">RINGKASAN PENCALONAN JABATAN</h3>
        <h3 style="margin-top: 0;"><?php echo htmlspecialchars($lampiran_title); ?></h3>
        <h5 style="margin-top: 5px; color: #444;">Sesi Akademik: <?php echo htmlspecialchars($current_session); ?></h5>
    </div>

    <table>
        <thead>
            <?php if ($cat_id == 2): // Special Headers for Projek Terbaik (Lampiran 06) ?>
                <tr>
                    <th width="5%">BIL</th>
                    <th width="30%">TAJUK PROJEK</th>
                    <th width="15%">NAMA PROGRAM</th>
                    <th width="35%">NAMA AHLI KUMPULAN</th>
                    <th width="15%">MARKAH RATING</th>
                </tr>
            <?php else: // Standard Headers for other awards (Lampiran 03 & 05) ?>
                <tr>
                    <th width="5%">BIL</th>
                    <th width="40%">NAMA PELAJAR</th>
                    <th width="20%">NO PENDAFTARAN</th>
                    <th width="20%">NAMA PROGRAM</th>
                    <th width="15%">MARKAH RATING</th>
                </tr>
            <?php endif; ?>
        </thead>
        <tbody>
            <?php if (!$winner): ?>
                <tr><td colspan="5" style="color: #666; font-style: italic;">Tiada pencalonan disahkan bagi sesi ini (Sila buat pengesahan 'Top 1' di sistem JKP)</td></tr>
            <?php else: ?>
                <tr>
                    <td>1</td>
                    <?php if ($cat_id == 2): // Display Project Information ?>
                        <td style="text-align: left;"><?php echo htmlspecialchars($winner['project_title']); ?></td>
                        <td><?php echo htmlspecialchars($winner['programme']); ?></td>
                        <td style="text-align: left; white-space: pre-wrap; font-size: 13px;"><?php echo htmlspecialchars($winner['group_members']); ?></td>
                    <?php else: // Display Standard Student Information ?>
                        <td style="text-align: left;"><?php echo htmlspecialchars($winner['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($winner['matric_no']); ?></td>
                        <td><?php echo htmlspecialchars($winner['programme']); ?></td>
                    <?php endif; ?>
                    <!-- Display strictly as Percentage -->
                    <td class="fw-bold"><?php echo number_format($winner['calculated_score'], 1); ?>%</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top: 30px;">
        <p>Dengan ini diperakukan bahawa penerima <strong><?php echo htmlspecialchars($lampiran_title); ?></strong> diberikan kepada:</p>
        <p>Nama: <strong><?php echo $winner ? htmlspecialchars($winner['full_name']) : '__________________________________________________'; ?></strong></p>
    </div>

    <!-- Official Signature Block for Ketua Jabatan as required by Buku Panduan APCP -->
    <div class="signature-box">
        <p>______________________________________</p>
        <p><strong>Ketua Jabatan Akademik</strong><br>
        (Pengerusi Jawatankuasa Kecil Pemilihan)<br><br>
        Tarikh: <?php echo date('d M Y'); ?><br><br>
        Cop Jabatan:</p>
    </div>

    <!-- Action Button to Trigger Browser Print Dialog -->
    <button class="print-btn" onclick="window.print()">Cetak Dokumen</button>

</body>
</html>