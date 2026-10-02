<?php
// 1. Include database configuration
require_once 'config.php';

// 2. Start session and verify authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (function_exists('requireLogin')) {
    requireLogin();
} elseif (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 3. CRITICAL SECURITY PATCH: Strict Admin Role Verification
$user_role = strtolower($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');
if ($user_role !== 'admin') {
    // Kick out unauthorized users (Students, PAs, Committees)
    header("Location: login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// --- Process Add New User Form ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? 'Student');
    $matric_no = trim($_POST['matric_no'] ?? ''); 
    
    // Set default passwords based on user role
    if (strtolower($role) === 'admin') {
        $password = 'admin123';
    } else {
        $password = ($role === 'Student') ? 'student123' : 'staff123'; 
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT); 
    // Force staff to change password on first login
    $must_change = ($role === 'Student' || strtolower($role) === 'admin') ? 0 : 1; 

    if (isset($pdo)) {
        try {
            $pdo->beginTransaction();

            // Check for duplicate emails
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $check_stmt->execute([$email]);
            
            if ($check_stmt->fetchColumn() > 0) {
                $error_msg = "Error: The email address '{$email}' is already registered.";
                $pdo->rollBack();
            } else {
                // Insert into main users table (Category assignment logic removed)
                $insert_stmt = $pdo->prepare("INSERT INTO users (full_name, email, role, password, must_change_password) VALUES (?, ?, ?, ?, ?)");
                $insert_stmt->execute([$full_name, $email, $role, $hashed_password, $must_change]);
                
                // If role is Student, link record to the students table
                if ($role === 'Student') {
                    $new_user_id = $pdo->lastInsertId();
                    $stmt_student = $pdo->prepare("INSERT INTO students (user_id, matric_no) VALUES (?, ?)");
                    $stmt_student->execute([$new_user_id, $matric_no]);
                }
                
                $pdo->commit();
                $success_msg = "Success! New user '{$full_name}' has been added.";
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

// --- Process Edit User Form ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $edit_user_id = $_POST['user_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? 'Student');
    $matric_no = trim($_POST['matric_no'] ?? ''); 
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    $new_password = trim($_POST['new_password'] ?? ''); 

    if (isset($pdo)) {
        try {
            $pdo->beginTransaction();

            // Update user details (Category assignment logic removed)
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $must_change = ($role === 'Student' || strtolower($role) === 'admin') ? 0 : 1; 
    
                $update_stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, is_active = ?, password = ?, must_change_password = ? WHERE user_id = ?");
                $update_stmt->execute([$full_name, $email, $role, $is_active, $hashed_password, $must_change, $edit_user_id]);
            } else {
                $update_stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, is_active = ? WHERE user_id = ?");
                $update_stmt->execute([$full_name, $email, $role, $is_active, $edit_user_id]);
            }

            // Sync matric number for students
            if ($role === 'Student') {
                $check_stu = $pdo->prepare("SELECT COUNT(*) FROM students WHERE user_id = ?");
                $check_stu->execute([$edit_user_id]);
                
                if ($check_stu->fetchColumn() > 0) {
                    $upd_stu = $pdo->prepare("UPDATE students SET matric_no = ? WHERE user_id = ?");
                    $upd_stu->execute([$matric_no, $edit_user_id]);
                } else {
                    $ins_stu = $pdo->prepare("INSERT INTO students (user_id, matric_no) VALUES (?, ?)");
                    $ins_stu->execute([$edit_user_id, $matric_no]);
                }
            }

            $pdo->commit();
            $success_msg = "Success! User profile updated.";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_msg = "Update Error: " . $e->getMessage();
        }
    }
}

// --- Process Bulk Actions (Activate, Suspend, Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action']; 
    $selected_ids = $_POST['selected_ids'] ?? [];
    
    if (!empty($selected_ids)) {
        if (isset($pdo)) {
            try {
                $pdo->beginTransaction();
                $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                
                if ($action === 'delete') {
                    // Delete from students table first to prevent foreign key constraint issues
                    $del_stu = $pdo->prepare("DELETE FROM students WHERE user_id IN ($placeholders)");
                    $del_stu->execute($selected_ids);

                    // Delete from users table (ensure Admin cannot delete themselves)
                    $query = "DELETE FROM users WHERE user_id IN ($placeholders) AND user_id != ?";
                    $params = array_merge($selected_ids, [$_SESSION['user_id']]); 
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $count = $stmt->rowCount();
                    $success_msg = "Success! {$count} user(s) have been permanently deleted.";
                } 
                elseif ($action === 'activate') {
                    $query = "UPDATE users SET is_active = 1 WHERE user_id IN ($placeholders)";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($selected_ids);
                    $count = $stmt->rowCount();
                    $success_msg = "Success! {$count} user(s) have been successfully activated.";
                }
                elseif ($action === 'suspend') {
                    // Prevent Admin from suspending themselves
                    $query = "UPDATE users SET is_active = 0 WHERE user_id IN ($placeholders) AND user_id != ?";
                    $params = array_merge($selected_ids, [$_SESSION['user_id']]); 
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $count = $stmt->rowCount();
                    $success_msg = "Success! {$count} user(s) have been successfully suspended.";
                }
                
                $pdo->commit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error_msg = "Bulk Action Error: " . $e->getMessage();
            }
        }
    }
}

// --- Retrieve User List ---
$users = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("
            SELECT u.*, s.matric_no
            FROM users u 
            LEFT JOIN students s ON u.user_id = s.user_id 
            ORDER BY 
                CASE LOWER(u.role)
                    WHEN 'admin' THEN 1
                    WHEN 'evaluation committee' THEN 2
                    WHEN 'academic advisor' THEN 3
                    WHEN 'student' THEN 4
                    ELSE 5
                END ASC,
                u.user_id DESC
        ");
        if ($stmt) {
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error_msg = "Database query failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - Manage Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

    <!-- Sidebar Navigation -->
    <div class="sidebar d-flex flex-column justify-content-between p-4" id="sidebar">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-mortarboard-fill text-warning fs-3"></i>
                <span class="fs-4 fw-bold text-white">EduRank</span>
            </div>
            <div class="text-uppercase text-secondary mb-3" style="font-size: 0.75rem; font-weight: 600; padding-left: 12px;">Admin Menu</div>
            <a href="admin_dashboard.php" class="nav-link"><i class="bi bi-grid-fill me-2"></i> Dashboard</a>
            <a href="manage_users.php" class="nav-link active"><i class="bi bi-people-fill me-2"></i> Manage Users</a>
            <a href="manage_awards.php" class="nav-link"><i class="bi bi-award-fill me-2"></i> Award Settings</a>
            <a href="manage_classes.php" class="nav-link"><i class="bi bi-building me-2"></i> Manage Classes</a>
            <a href="global_reports.php" class="nav-link"><i class="bi bi-bar-chart-fill me-2"></i> Global Reports</a>
            <a href="audit_logs.php" class="nav-link"><i class="bi bi-journal-text me-2"></i> Audit Logs</a>
            <a href="system_setting.php" class="nav-link"><i class="bi bi-gear-fill me-2"></i> System Settings</a>
        </div>
        <div class="border-top border-secondary pt-3 mt-auto">
            <form method="POST" action="logout.php">
                <button type="submit" class="btn text-danger p-0 border-0 text-sm fw-semibold d-flex align-items-center gap-2 ms-1 bg-transparent">
                    <i class="bi bi-box-arrow-right fs-5"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <header class="top-header shadow-sm d-flex justify-content-between align-items-center pe-4">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <div class="text-white small fw-bold">
                <i class="bi bi-person-circle me-1"></i> Admin Portal
            </div>
        </header>

        <div class="p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-dark mb-0">User Role & Management</h4>
            </div>

            <!-- Status Messages -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <!-- Toolbar: Filters, Search, and Actions -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex gap-2 w-50">
                    <select id="roleFilter" class="form-select shadow-sm border-0" style="width: 180px;">
                        <option value="all">All Roles</option>
                        <option value="student">Student</option>
                        <option value="academic advisor">P.A. (Advisor)</option>
                        <option value="evaluation committee">Committee</option>
                        <option value="admin">System Admin</option>
                    </select>
                    
                    <div class="input-group shadow-sm rounded flex-grow-1">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-0 ps-0" placeholder="Search by name, email, matric no...">
                    </div>
                </div>
                
                <div class="d-flex align-items-center">
                    <div id="bulkActionGroup" style="display:none;" class="me-2 p-1 bg-white rounded shadow-sm border">
                        <button type="button" class="btn btn-sm btn-success fw-bold me-1" onclick="submitBulkAction('activate')" title="Unfreeze Accounts">
                            <i class="bi bi-unlock-fill me-1"></i> Activate
                        </button>
                        <button type="button" class="btn btn-sm btn-warning fw-bold text-dark me-1" onclick="submitBulkAction('suspend')" title="Freeze Accounts">
                            <i class="bi bi-lock-fill me-1"></i> Suspend
                        </button>
                        <button type="button" class="btn btn-sm btn-danger fw-bold" onclick="submitBulkAction('delete')" title="Permanently Delete">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </div>
                    
                    <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-person-plus-fill me-1"></i> Add New User
                    </button>
                </div>
            </div>

            <!-- User Data Table -->
            <form id="bulkActionForm" method="POST" action="">
                <input type="hidden" name="bulk_action" id="bulkActionType" value="">
                
                <div class="custom-card p-0 shadow-sm bg-white overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="userTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-3 px-4" style="width: 50px;">
                                        <input class="form-check-input border-secondary" type="checkbox" id="selectAll">
                                    </th>
                                    <th class="py-3 px-2 text-secondary small fw-semibold text-center">NO.</th>
                                    <th class="py-3 px-2 text-secondary small fw-semibold">FULL NAME</th>
                                    <th class="py-3 px-2 text-secondary small fw-semibold matric-col" style="display:none; color:#2563eb !important;">MATRIC NO</th>
                                    <th class="py-3 px-2 text-secondary small fw-semibold">EMAIL / ID</th>
                                    <th class="py-3 px-2 text-secondary small fw-semibold">ROLE</th>
                                    <th class="py-3 px-2 text-secondary small fw-semibold text-center">STATUS</th>
                                    <th class="py-3 px-4 text-secondary small fw-semibold text-end">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($users)): ?>
                                    <tr><td colspan="8" class="text-center py-4 text-muted">No users found in database.</td></tr>
                                <?php else: ?>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr data-role="<?php echo strtolower($user['role']); ?>">
                                            <td class="px-4">
                                                <?php if($user['user_id'] != $_SESSION['user_id']): ?>
                                                    <input class="form-check-input border-secondary user-checkbox" type="checkbox" name="selected_ids[]" value="<?php echo $user['user_id']; ?>">
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="px-2 text-muted fw-medium text-center user-index">
                                                <?php echo $counter++; ?>
                                            </td>
                                            
                                            <td class="px-2 fw-bold text-dark"><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            
                                            <td class="px-2 fw-bold text-primary matric-col" style="display:none;">
                                                <?php echo !empty($user['matric_no']) ? htmlspecialchars($user['matric_no']) : '<span class="text-muted">-</span>'; ?>
                                            </td>

                                            <td class="px-2 text-secondary small"><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td class="px-2">
                                                <?php 
                                                    $role = $user['role'];
                                                    $role_class = 'bg-secondary';
                                                    if (strtolower($role) === 'admin') $role_class = 'bg-danger';
                                                    if ($role === 'Evaluation Committee') $role_class = 'bg-warning text-dark';
                                                    if ($role === 'Academic Advisor') $role_class = 'bg-success';
                                                    if ($role === 'Student') $role_class = 'bg-primary';
                                                ?>
                                                <span class="badge <?php echo $role_class; ?> text-uppercase" style="font-size: 0.7rem;">
                                                    <?php echo htmlspecialchars($role); ?>
                                                </span>
                                            </td>

                                            <td class="px-2 text-center">
                                                <?php if(isset($user['is_active']) && $user['is_active'] == 1): ?>
                                                    <span class="text-success small fw-bold"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Active</span>
                                                <?php else: ?>
                                                    <span class="text-danger small fw-bold"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 text-end">
                                                <?php if($user['user_id'] == $_SESSION['user_id']): ?>
                                                    <button type="button" class="btn btn-sm btn-secondary disabled" title="You cannot edit your own profile">
                                                        <i class="bi bi-person-bounding-box me-1"></i> It's You
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Passing data to the edit modal -->
                                                    <button type="button" class="btn btn-sm btn-outline-primary" title="Edit Profile & Password"
                                                            onclick="openEditModal(<?php echo $user['user_id']; ?>, '<?php echo addslashes(htmlspecialchars($user['full_name'])); ?>', '<?php echo addslashes(htmlspecialchars($user['email'])); ?>', '<?php echo addslashes(htmlspecialchars($user['role'])); ?>', <?php echo $user['is_active'] ?? 1; ?>, '<?php echo addslashes(htmlspecialchars($user['matric_no'] ?? '')); ?>')">
                                                        <i class="bi bi-pencil-square"></i> Edit
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Register New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form action="" method="POST">
                        <!-- Role Selection -->
                        <div class="btn-group w-100 mb-4 shadow-sm" role="group">
                            <input type="radio" class="btn-check" name="role" id="role_student" value="Student" checked onchange="updateFormUI('student')">
                            <label class="btn btn-outline-primary fw-semibold" for="role_student">Student</label>

                            <input type="radio" class="btn-check" name="role" id="role_pa" value="Academic Advisor" onchange="updateFormUI('pa')">
                            <label class="btn btn-outline-success fw-semibold" for="role_pa">P.A.</label>

                            <input type="radio" class="btn-check" name="role" id="role_committee" value="Evaluation Committee" onchange="updateFormUI('committee')">
                            <label class="btn btn-outline-warning fw-semibold" for="role_committee">Committee</label>

                            <input type="radio" class="btn-check" name="role" id="role_admin" value="admin" onchange="updateFormUI('admin')">
                            <label class="btn btn-outline-danger fw-semibold" for="role_admin">Admin</label>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-semibold">Full Name</label>
                            <input type="text" class="form-control" name="full_name" placeholder="Enter full name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-semibold">Email Address</label>
                            <input type="email" class="form-control" name="email" placeholder="Enter email address" required>
                        </div>
                        
                        <!-- Matric Number (Only for Students) -->
                        <div class="mb-3" id="add_matric_div">
                            <label class="form-label text-secondary small fw-semibold">Matric Number</label>
                            <input type="text" class="form-control border-primary" name="matric_no" placeholder="e.g. 13DIT24F1178">
                        </div>

                        <!-- Default Password Indicator -->
                        <div class="mb-4 p-3 bg-light rounded border" id="passwordHintBox">
                            <label class="form-label text-secondary small fw-semibold mb-1">Default Password Setup</label>
                            <div class="d-flex align-items-center mb-1">
                                <i class="bi bi-key-fill text-primary me-2"></i>
                                <span class="fw-bold font-monospace text-dark" id="defaultPwText">student123</span>
                            </div>
                            <div class="text-muted" style="font-size: 0.75rem;" id="defaultPwDesc">User can log in with this default password.</div>
                        </div>
                        
                        <button type="submit" name="add_user" class="btn btn-primary w-100 fw-bold">Confirm Registration</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-primary"><i class="bi bi-pencil-square me-2"></i>Edit User Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form action="" method="POST">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-semibold">Full Name</label>
                            <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-semibold">Email Address</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        
                        <!-- Matric Number -->
                        <div class="mb-3" id="edit_matric_div" style="display:none;">
                            <label class="form-label text-secondary small fw-semibold">Matric Number</label>
                            <input type="text" class="form-control border-primary" name="matric_no" id="edit_matric_no" placeholder="e.g. 13DIT24F1178">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-secondary small fw-semibold">User Role</label>
                                <select class="form-select" name="role" id="edit_role" required>
                                    <option value="Student">Student</option>
                                    <option value="Academic Advisor">P.A.</option>
                                    <option value="Evaluation Committee">Committee</option>
                                    <option value="admin">System Admin</option>
                                </select>
                                <input type="hidden" name="role" id="hidden_edit_role" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-secondary small fw-semibold">Account Status</label>
                                <select class="form-select border-primary" name="is_active" id="edit_status" required>
                                    <option value="1" class="text-success fw-bold">Active</option>
                                    <option value="0" class="text-danger fw-bold">Inactive (Suspended)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Password Reset -->
                        <div class="mb-4 p-3 rounded" style="background-color: #fff3cd; border: 1px solid #ffe69c;">
                            <label class="form-label text-warning-emphasis small fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Reset Password (Optional)</label>
                            <input type="text" class="form-control border-warning" name="new_password" placeholder="Leave blank to keep current password">
                            <div class="form-text text-muted" style="font-size: 0.75rem;">If you enter a new password, the user's password will be overridden.</div>
                        </div>

                        <button type="submit" name="edit_user" class="btn btn-primary w-100 fw-bold">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- EduRank Footer -->
    <footer class="text-center p-4 mt-5 text-muted">
        <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
    </footer>

    <!-- JavaScript Interactions -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // DataTables-like filtering logic for the Search & Role dropdowns
        const searchInput = document.getElementById('searchInput');
        const roleFilter = document.getElementById('roleFilter');

        function filterTable() {
            let filterText = searchInput.value.toLowerCase();
            let filterRole = roleFilter.value.toLowerCase();
            let rows = document.querySelectorAll('#userTable tbody tr');
            
            let visibleCounter = 1; 

            rows.forEach(row => {
                if (row.cells.length === 1) return; // Skip empty state row
                
                let text = row.innerText.toLowerCase();
                let rowRole = row.getAttribute('data-role');
                let matchesSearch = text.includes(filterText);
                let matchesRole = (filterRole === 'all') || (rowRole === filterRole);
                
                if (matchesSearch && matchesRole) {
                    row.style.display = '';
                    // Re-calculate visible index numbering
                    let indexCell = row.querySelector('.user-index');
                    if (indexCell) {
                        indexCell.innerText = visibleCounter++;
                    }
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Toggle Matric column visibility based on role selection
            const matricCols = document.querySelectorAll('.matric-col');
            if (filterRole === 'student') {
                matricCols.forEach(col => col.style.display = '');
            } else {
                matricCols.forEach(col => col.style.display = 'none');
            }

            toggleBulkActions();
        }

        // Attach event listeners for real-time filtering
        searchInput.addEventListener('keyup', filterTable);
        roleFilter.addEventListener('change', filterTable);

        // Initialize table filtering on load
        document.addEventListener("DOMContentLoaded", function() {
            filterTable();
        });

        // Bulk Action UI Handlers
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.user-checkbox');
        const bulkActionGroup = document.getElementById('bulkActionGroup');

        function toggleBulkActions() {
            const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;
            bulkActionGroup.style.display = checkedCount > 0 ? 'inline-flex' : 'none';
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => {
                    if (cb.closest('tr').style.display !== 'none') {
                        cb.checked = this.checked;
                    }
                });
                toggleBulkActions();
            });
        }
        checkboxes.forEach(cb => cb.addEventListener('change', toggleBulkActions));

        // Submit Bulk Actions logic with confirmations
        function submitBulkAction(actionType) {
            const count = document.querySelectorAll('.user-checkbox:checked').length;
            let confirmMsg = "";
            
            if (actionType === 'delete') {
                confirmMsg = `⚠️ Critical Warning!\n\nAre you sure you want to PERMANENTLY DELETE these ${count} user(s)?`;
            } else if (actionType === 'suspend') {
                confirmMsg = `Are you sure you want to FREEZE / SUSPEND these ${count} user(s)?\nThey will no longer be able to log in.`;
            } else if (actionType === 'activate') {
                confirmMsg = `Are you sure you want to REACTIVATE these ${count} user(s)?`;
            }

            if (confirm(confirmMsg)) {
                document.getElementById('bulkActionType').value = actionType;
                document.getElementById('bulkActionForm').submit();
            }
        }

        // Update Add User Form UI dynamically based on Role Selection
        function updateFormUI(type) {
            const pwText = document.getElementById('defaultPwText');
            const pwDesc = document.getElementById('defaultPwDesc');
            const icon = pwText.previousElementSibling;
            const addMatricDiv = document.getElementById('add_matric_div');

            // Show Matric Number only for students
            addMatricDiv.style.display = (type === 'student') ? 'block' : 'none';

            // Configure visual cues for different account types
            if (type === 'student') {
                pwText.innerText = 'student123';
                icon.className = 'bi bi-key-fill text-primary me-2';
                pwDesc.innerHTML = 'Student can log in with this default password.';
                pwDesc.className = 'text-muted';
            } else {
                if (type === 'admin') {
                    pwText.innerText = 'admin123';
                    icon.className = 'bi bi-shield-fill-check text-danger me-2';
                    pwDesc.innerHTML = '<span class="text-danger fw-semibold">Super User:</span> New Admin will use this default password.';
                    pwDesc.className = 'text-muted';
                } else {
                    pwText.innerText = 'staff123';
                    icon.className = 'bi bi-shield-lock-fill text-warning me-2';
                    pwDesc.innerHTML = '<span class="text-danger fw-semibold">Security Alert:</span> Staff will be forced to change this password upon their first login.';
                }
            }
        }

        // Populate and launch the Edit Modal
        function openEditModal(userId, fullName, email, role, isActive, matricNo) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_matric_no').value = matricNo || ''; 
            
            const roleSelect = document.getElementById('edit_role');
            const hiddenRole = document.getElementById('hidden_edit_role');
            const editMatricDiv = document.getElementById('edit_matric_div');

            // Reset role options
            for(let i=0; i < roleSelect.options.length; i++) {
                roleSelect.options[i].style.display = '';
                roleSelect.options[i].disabled = false;
            }

            // Logic to prevent role changes that might break relations
            if (role.toLowerCase() === 'student') {
                roleSelect.value = 'Student';
                roleSelect.disabled = true; 
                hiddenRole.value = 'Student';
                hiddenRole.disabled = false;
                editMatricDiv.style.display = 'block'; 
            } else {
                roleSelect.disabled = false;
                hiddenRole.disabled = true; 
                editMatricDiv.style.display = 'none'; 
                
                // Disallow changing a staff member to a student to avoid missing required data
                for(let i=0; i < roleSelect.options.length; i++) {
                    if (roleSelect.options[i].value.toLowerCase() === 'student') {
                        roleSelect.options[i].style.display = 'none';
                        roleSelect.options[i].disabled = true;
                    }
                }
                
                // Select matching role
                for(let i=0; i < roleSelect.options.length; i++) {
                    if(roleSelect.options[i].value.toLowerCase() === role.toLowerCase()) {
                        roleSelect.selectedIndex = i;
                        break;
                    }
                }
            }

            // Set User Status
            document.getElementById('edit_status').value = isActive;
            
            // Show the modal
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
    </script>
</body>
</html>