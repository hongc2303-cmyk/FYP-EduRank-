<?php
// notifications.php - Independent notification module

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// 1. Process notification read status update
if (isset($_GET['mark_read']) && isset($pdo)) {
    $mark_app_id = filter_var($_GET['mark_read'], FILTER_VALIDATE_INT);
    if ($mark_app_id) {
        try {
            $stmtRead = $pdo->prepare("UPDATE award_applications SET is_read = 1 WHERE application_id = ?");
            $stmtRead->execute([$mark_app_id]);
        } catch (PDOException $e) {
            // Silently skip
        }
    }
}

$notifications = [];
$unread_notif_count = 0;
$current_user_role = '';

// 2. Fetch True Count & Latest Notifications
if (isset($_SESSION['user_id']) && isset($pdo)) {
    $current_user_id = $_SESSION['user_id'];
    $current_user_role = strtolower($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');

    try {
        if ($current_user_role === 'student') {
            // 🌟 Student 逻辑：查非 pending 的状态更新
            $countStmt = $pdo->prepare("
                SELECT COUNT(*) FROM award_applications a 
                JOIN students s ON a.student_id = s.student_id 
                WHERE s.user_id = ? AND a.status != 'pending' AND COALESCE(a.is_read, 0) = 0
            ");
            $countStmt->execute([$current_user_id]);
            $unread_notif_count = $countStmt->fetchColumn();

            $stmtNotif = $pdo->prepare("
                SELECT a.application_id, a.status, COALESCE(a.is_read, 0) AS is_read, c.category_name, v.verification_date, v.remarks
                FROM award_applications a
                JOIN students s ON a.student_id = s.student_id
                JOIN award_categories c ON a.category_id = c.category_id
                LEFT JOIN application_verifications v ON a.application_id = v.application_id
                WHERE s.user_id = ? AND a.status != 'pending'
                ORDER BY COALESCE(v.verification_date, a.application_date) DESC
                LIMIT 10
            ");
            $stmtNotif->execute([$current_user_id]);
            $notifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($current_user_role === 'advisor' || $current_user_role === 'academic advisor') {
            // 🌟 PA 逻辑：查 pending 的新申请
            $countStmt = $pdo->prepare("
                SELECT COUNT(*) FROM award_applications a 
                JOIN students s ON a.student_id = s.student_id 
                WHERE s.advisor_id = ? AND a.status = 'pending' AND COALESCE(a.is_read, 0) = 0
            ");
            $countStmt->execute([$current_user_id]);
            $unread_notif_count = $countStmt->fetchColumn();

            $stmtNotif = $pdo->prepare("
                SELECT a.application_id, a.application_date, COALESCE(a.is_read, 0) AS is_read, u.full_name AS student_name, c.category_name
                FROM award_applications a
                JOIN students s ON a.student_id = s.student_id
                JOIN users u ON s.user_id = u.user_id
                JOIN award_categories c ON a.category_id = c.category_id
                WHERE s.advisor_id = ? AND a.status = 'pending'
                ORDER BY a.application_date DESC
                LIMIT 10
            ");
            $stmtNotif->execute([$current_user_id]);
            $notifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($current_user_role === 'evaluation committee') {
            // 🌟 Committee 逻辑：必须先查出他负责哪个奖项，再查 verified 的新审核
            $stmtRole = $pdo->prepare("SELECT assigned_category_id FROM users WHERE user_id = ?");
            $stmtRole->execute([$current_user_id]);
            $assigned_category_id = $stmtRole->fetchColumn();

            if ($assigned_category_id) {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM award_applications WHERE status = 'verified' AND category_id = ? AND COALESCE(is_read, 0) = 0");
                $countStmt->execute([$assigned_category_id]);
                $unread_notif_count = $countStmt->fetchColumn();

                $stmtNotif = $pdo->prepare("
                    SELECT a.application_id, a.application_date, COALESCE(a.is_read, 0) AS is_read, u.full_name AS student_name, c.category_name
                    FROM award_applications a
                    JOIN students s ON a.student_id = s.student_id
                    JOIN users u ON s.user_id = u.user_id
                    JOIN award_categories c ON a.category_id = c.category_id
                    WHERE a.status = 'verified' AND a.category_id = ?
                    ORDER BY a.application_date DESC
                    LIMIT 10
                ");
                $stmtNotif->execute([$assigned_category_id]);
                $notifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {
        $notifications = [];
        $unread_notif_count = 0;
    }
}
?>

<!-- UI Portion (Bootstrap Dropdown) -->
<div class="dropdown">
    <button class="btn btn-link text-white position-relative border-0 p-0 shadow-none" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-bell fs-5"></i>
        <?php if ($unread_notif_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                <span class="visually-hidden">New notifications</span>
            </span>
        <?php endif; ?>
    </button>

    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 my-2 p-0" aria-labelledby="notifDropdown" style="width: 350px; max-height: 400px; overflow-y: auto;">
        <li class="dropdown-header fw-bold border-bottom p-3 text-dark d-flex justify-content-between align-items-center bg-light">
            <span><i class="bi bi-bell-fill me-2 text-primary"></i>Notifications</span>
            <?php if ($unread_notif_count > 0): ?>
                <span class="badge bg-danger rounded-pill shadow-sm"><?php echo $unread_notif_count; ?> New</span>
            <?php endif; ?>
        </li>

        <?php if (empty($notifications)): ?>
            <li class="text-center py-5 text-muted bg-white">
                <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                <small>No new updates</small>
            </li>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <?php 
                    $is_unread = ($notif['is_read'] == 0);
                    $bg_class = $is_unread ? 'bg-primary-subtle' : 'bg-white';
                ?>
                <li>
                    <?php if (($current_user_role ?? '') === 'student'): ?>
                        <a class="dropdown-item p-3 border-bottom text-wrap <?php echo $bg_class; ?>" href="student_dashboard.php?page=history&view_app=<?php echo $notif['application_id']; ?>&mark_read=<?php echo $notif['application_id']; ?>">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="text-dark small d-flex align-items-center gap-1">
                                    <?php if ($is_unread): ?><span class="p-1 bg-danger rounded-circle d-inline-block"></span><?php endif; ?>
                                    Application Status Update
                                </strong>
                                <small class="text-muted" style="font-size: 11px;"><?php echo !empty($notif['verification_date']) ? date('M d', strtotime($notif['verification_date'])) : 'Recent'; ?></small>
                            </div>
                            <p class="mb-1 text-secondary" style="font-size: 12px;">
                                Category: <strong><?php echo htmlspecialchars($notif['category_name']); ?></strong><br>
                                Status: 
                                <?php if ($notif['status'] === 'verified'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Verified & Forwarded</span>
                                <?php elseif ($notif['status'] === 'rejected'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Rejected</span>
                                <?php else: ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle"><?php echo ucfirst($notif['status']); ?></span>
                                <?php endif; ?>
                            </p>
                            <?php if(!empty($notif['remarks'])): ?>
                                <div class="small text-muted text-truncate" style="font-size: 11px;">
                                    <i class="bi bi-chat-left-text me-1"></i>"<?php echo htmlspecialchars($notif['remarks']); ?>"
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <!-- 🌟 统一 PA 和 Committee 的跳转页面逻辑 -->
                        <?php $link_page = ($current_user_role === 'evaluation committee') ? 'evaluate.php' : 'pa_review.php'; ?>
                        <?php $id_param = ($current_user_role === 'evaluation committee') ? 'id' : 'app_id'; ?>
                        
                        <a class="dropdown-item p-3 border-bottom text-wrap <?php echo $bg_class; ?>" href="<?php echo $link_page; ?>?<?php echo $id_param; ?>=<?php echo $notif['application_id']; ?>&mark_read=<?php echo $notif['application_id']; ?>">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="text-dark small d-flex align-items-center gap-1">
                                    <?php if ($is_unread): ?><span class="p-1 bg-danger rounded-circle d-inline-block"></span><?php endif; ?>
                                    Action Required
                                </strong>
                                <small class="text-muted" style="font-size: 11px;"><?php echo date('M d', strtotime($notif['application_date'])); ?></small>
                            </div>
                            <p class="mb-0 text-secondary" style="font-size: 12px;">
                                Student: <strong><?php echo htmlspecialchars($notif['student_name']); ?></strong><br>
                                Category: <?php echo htmlspecialchars($notif['category_name']); ?>
                            </p>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            <!-- View All 按钮 -->
            <li class="text-center bg-light">
                <a class="dropdown-item text-primary fw-bold py-2 small" href="#" style="font-size: 13px;">View All Applications</a>
            </li>
        <?php endif; ?>
    </ul>
</div>