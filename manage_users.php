<?php
session_start();
require_once 'includes/db.php';

// Load PHPMailer
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} elseif (file_exists('vendor/PHPMailer/src/PHPMailer.php')) {
    require 'vendor/PHPMailer/src/PHPMailer.php';
    require 'vendor/PHPMailer/src/SMTP.php';
    require 'vendor/PHPMailer/src/Exception.php';
} else {
    die('Error: PHPMailer not found. Please install via Composer.');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('UTC');

// Restrict to Admin & Super Admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    header("Location: login.php");
    exit();
}

$is_super_admin = ($_SESSION['role'] === 'Super Admin');

// === POST HANDLER ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_super_admin) {
    if (!isset($_POST['confirmed']) || $_POST['confirmed'] !== 'true') {
        $_SESSION['error'] = "Action not confirmed.";
        header("Location: manage_users.php");
        exit();
    }

    // === UPDATE USER ROLE/STATUS ===
    if (isset($_POST['action']) && $_POST['action'] === 'update_user') {
        $user_id = intval($_POST['user_id']);
        $role = $_POST['role'];
        $status = $_POST['status'];

        $stmt = $conn->prepare("SELECT email, first_name, middle_name, last_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE users SET role = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssi", $role, $status, $user_id);
        if ($stmt->execute()) {
            $details = json_encode(['new_role' => $role, 'new_status' => $status]);
            $log = $conn->prepare("INSERT INTO user_actions (user_id, action_type, details) VALUES (?, 'promotion', ?)");
            $log->bind_param("is", $user_id, $details);
            $log->execute();
            $log->close();

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'mailblazer123@gmail.com';
                $mail->Password = 'lzfp jhyv wrom hjdy';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->setFrom('mailblazer123@gmail.com', 'Franklin Baker');
                $mail->addAddress($user['email']);
                $mail->isHTML(true);
                $mail->Subject = 'Account Updated';
                $mail->Body = "Dear {$user['first_name']},<br>Your account has been updated.<br>Role: <b>$role</b><br>Status: <b>$status</b>";
                $mail->send();
            } catch (Exception $e) {
                error_log("Update email failed: {$mail->ErrorInfo}");
            }

            $_SESSION['success'] = "User updated.";
        } else {
            $_SESSION['error'] = "Update failed.";
        }
        $stmt->close();

    // === DELETE USER (FIXED + ADMIN NOTIFICATION) ===
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        $user_id = intval($_POST['user_id']);
        $reason  = trim($_POST['reason'] ?? '');

        if (empty($reason)) {
            $_SESSION['error'] = "Reason is required.";
            header("Location: manage_users.php");
            exit();
        }

        // ---- START TRANSACTION ----
        $conn->begin_transaction();

        try {
            // 1. Get user details
            $stmt = $conn->prepare("SELECT email, first_name, middle_name, last_name FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user) {
                throw new Exception("User not found.");
            }

            $full_name = trim($user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name']);
            $user_email = $user['email'];

            // 2. Log deletion
            $details = json_encode(['email' => $user_email, 'reason' => $reason]);
            $log = $conn->prepare(
                "INSERT INTO user_actions (user_id, action_type, details, reason, created_at) 
                 VALUES (?, 'deletion', ?, ?, NOW())"
            );
            $log->bind_param("iss", $user_id, $details, $reason);
            $log->execute();
            $log->close();

            // 3. Delete related user_actions (FK safety)
            $del_act = $conn->prepare("DELETE FROM user_actions WHERE user_id = ?");
            $del_act->bind_param("i", $user_id);
            $del_act->execute();
            $del_act->close();

            // 4. Delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            // ---- COMMIT ----
            $conn->commit();

            // === EMAIL TO TERMINATED USER ===
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'mailblazer123@gmail.com';
                $mail->Password   = 'lzfp jhyv wrom hjdy';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->setFrom('mailblazer123@gmail.com', 'Franklin Baker');
                $mail->addAddress($user_email);
                $mail->isHTML(true);
                $mail->Subject = 'Account Terminated';
                $mail->Body    = "
                    <p>Dear <strong>{$user['first_name']}</strong>,</p>
                    <p>Your account has been <strong>terminated</strong>.</p>
                    <p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>
                    <p>Thank you for your time with us.</p>
                    <hr><small>This is an automated message.</small>
                ";
                $mail->send();
            } catch (Exception $e) {
                error_log("Termination email failed: {$mail->ErrorInfo}");
            }

            // === EMAIL TO GMAIL OWNER (ADMIN) ===
            $admin_mail = new PHPMailer(true);
            try {
                $admin_mail->isSMTP();
                $admin_mail->Host       = 'smtp.gmail.com';
                $admin_mail->SMTPAuth   = true;
                $admin_mail->Username   = 'mailblazer123@gmail.com';
                $admin_mail->Password   = 'lzfp jhyv wrom hjdy';
                $admin_mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $admin_mail->Port       = 587;
                $admin_mail->setFrom('mailblazer123@gmail.com', 'Franklin Baker System');
                $admin_mail->addAddress('mailblazer123@gmail.com'); // Admin Gmail
                $admin_mail->isHTML(true);
                $admin_mail->Subject = 'User Account Terminated';
                $admin_mail->Body = "
                    <h3>User Termination Alert</h3>
                    <p>A user account has been <strong>permanently deleted</strong>:</p>
                    <ul>
                        <li><strong>Name:</strong> {$full_name}</li>
                        <li><strong>Email:</strong> {$user_email}</li>
                        <li><strong>Reason:</strong> " . htmlspecialchars($reason) . "</li>
                        <li><strong>Deleted on:</strong> " . date('Y-m-d H:i:s') . "</li>
                        <li><strong>Deleted by:</strong> {$_SESSION['first_name']} {$_SESSION['last_name']} ({$_SESSION['email']})</li>
                    </ul>
                    <p><em>This action cannot be undone.</em></p>
                    <hr><small>Franklin Baker HR System</small>
                ";
                $admin_mail->send();
            } catch (Exception $e) {
                error_log("Admin notification failed: {$admin_mail->ErrorInfo}");
            }

            $_SESSION['success'] = "User and logs deleted. Admin notified.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Delete failed: " . $e->getMessage();
        }

        header("Location: manage_users.php");
        exit();

    // === APPROVE USER ===
    } elseif (isset($_POST['action']) && $_POST['action'] === 'approve_user') {
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $details = json_encode(['user_id' => $user_id]);
            $log = $conn->prepare("INSERT INTO user_actions (user_id, action_type, details) VALUES (?, 'approval', ?)");
            $log->bind_param("is", $user_id, $details);
            $log->execute();
            $log->close();

            $info = $conn->prepare("SELECT email, first_name FROM users WHERE id = ?");
            $info->bind_param("i", $user_id);
            $info->execute();
            $user = $info->get_result()->fetch_assoc();
            $info->close();

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'mailblazer123@gmail.com';
                $mail->Password = 'lzfp jhyv wrom hjdy';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->setFrom('mailblazer123@gmail.com', 'Franklin Baker');
                $mail->addAddress($user['email']);
                $mail->isHTML(true);
                $mail->Subject = 'Account Approved';
                $mail->Body = "Dear {$user['first_name']},<br><b>Congratulations!</b> Your account is now active.";
                $mail->send();
            } catch (Exception $e) {
                error_log("Approval email failed: {$mail->ErrorInfo}");
            }

            $_SESSION['success'] = "User approved.";
        } else {
            $_SESSION['error'] = "User already approved or not found.";
        }
        $stmt->close();

    // === MENU ACCESS UPDATE ===
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_access') {
        $role = $_POST['role'];
        $current_user_id = $_SESSION['user_id'] ?? 1;

        if (isset($_POST['menu_permissions'][$role])) {
            $menu_permissions = implode(',', $_POST['menu_permissions'][$role]);
            $stmt = $conn->prepare("UPDATE users SET custom_menus = ? WHERE role = ? AND role != 'Super Admin'");
            $stmt->bind_param("ss", $menu_permissions, $role);
            if ($stmt->execute()) {
                $details = json_encode(['role' => $role, 'menus' => $menu_permissions]);
                $log = $conn->prepare("INSERT INTO user_actions (user_id, action_type, details) VALUES (?, 'menu_update', ?)");
                $log->bind_param("is", $current_user_id, $details);
                $log->execute();
                $log->close();
                $_SESSION['success'] = "Access updated for $role users.";
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET custom_menus = NULL WHERE role = ? AND role != 'Super Admin'");
            $stmt->bind_param("s", $role);
            if ($stmt->execute()) {
                $details = json_encode(['role' => $role, 'menus' => 'cleared']);
                $log = $conn->prepare("INSERT INTO user_actions (user_id, action_type, details) VALUES (?, 'menu_update', ?)");
                $log->bind_param("is", $current_user_id, $details);
                $log->execute();
                $log->close();
                $_SESSION['success'] = "Access cleared for $role users.";
            }
        }
    }

    header("Location: manage_users.php");
    exit();
}

$page_title = "Manage Users";
require_once 'header.php';

// Fetch users
$stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, employee_id, email, role, status FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->get_result();
$stmt->close();

// Sample menus
$sample_employee_menus = [];
$sample_admin_menus = [];
$menu_stmt = $conn->prepare("SELECT role, custom_menus FROM users WHERE custom_menus IS NOT NULL AND role IN ('Employee', 'Admin')");
$menu_stmt->execute();
$result = $menu_stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if ($row['role'] === 'Employee' && empty($sample_employee_menus)) {
        $sample_employee_menus = explode(',', $row['custom_menus']);
    } elseif ($row['role'] === 'Admin' && empty($sample_admin_menus)) {
        $sample_admin_menus = explode(',', $row['custom_menus']);
    }
}
$menu_stmt->close();
?>

<div class="container-fluid">
    <!-- Alerts -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <h2 class="mb-4">Manage Users</h2>

    <!-- User List -->
    <div class="card">
        <div class="card-header bg-primary-green text-white">
            <i class="bi bi-people-fill"></i> User List
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Employee ID</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($u = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['middle_name'] . ' ' . $u['last_name']) ?></td>
                                <td><?= htmlspecialchars($u['employee_id']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['role']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : ($u['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                        <?= htmlspecialchars($u['status']) ?>
                                    </span>
                                </td>
                                <td class="p-1">
                                    <?php if ($is_super_admin): ?>
                                        <!-- Update Role/Status -->
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Update user?');">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="update_user">
                                            <input type="hidden" name="confirmed" value="true">
                                            <select name="role" class="form-select form-select-sm d-inline w-auto" style="width:110px;">
                                                <option <?= $u['role'] == 'Employee' ? 'selected' : '' ?>>Employee</option>
                                                <option <?= $u['role'] == 'Admin' ? 'selected' : '' ?>>Admin</option>
                                                <option <?= $u['role'] == 'Super Admin' ? 'selected' : '' ?>>Super Admin</option>
                                            </select>
                                            <select name="status" class="form-select form-select-sm d-inline w-auto" style="width:100px;">
                                                <option <?= $u['status'] == 'pending' ? 'selected' : '' ?>>pending</option>
                                                <option <?= $u['status'] == 'active' ? 'selected' : '' ?>>active</option>
                                                <option <?= $u['status'] == 'inactive' ? 'selected' : '' ?>>inactive</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save"></i></button>
                                        </form>

                                        <!-- Approve Button -->
                                        <?php if ($u['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Approve this user?');">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="action" value="approve_user">
                                                <input type="hidden" name="confirmed" value="true">
                                                <button type="submit" class="btn btn-sm btn-success ms-1">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Delete Button (opens modal) -->
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $u['id'] ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==================== MODALS (outside the table) ==================== -->
    <?php 
    // Reset result pointer so we can loop again for modals
    $users->data_seek(0);
    while ($u = $users->fetch_assoc()): 
    ?>
    <div class="modal fade" id="deleteModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to <strong>permanently delete</strong> this user?</p>
                    <p><strong><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></strong> (<?= htmlspecialchars($u['email']) ?>)</p>
                    <form method="POST">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="confirmed" value="true">
                        <div class="mb-3">
                            <label for="reason<?= $u['id'] ?>" class="form-label"><strong>Reason for Termination <span class="text-danger">*</span></strong></label>
                            <textarea class="form-control" id="reason<?= $u['id'] ?>" name="reason" rows="3" required placeholder="Enter reason for deletion..."></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger">Delete User</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>

    <!-- Menu Access Management (unchanged) -->
    <?php if ($is_super_admin): ?>
    <div class="card mt-4">
        <div class="card-header bg-primary-green text-white">
            <i class="bi bi-shield-lock"></i> User Access Management
        </div>
        <div class="card-body">
            <form method="POST" onsubmit="return confirm('Update menu access for all users of selected role?');">
                <input type="hidden" name="action" value="update_access">
                <input type="hidden" name="confirmed" value="true">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Employee Menus</h6>
                        <?php
                        $menus = ['create_label' => 'Create Label', 'label_history' => 'Label History', 'manage_products' => 'Manage Products', 'reports' => 'Reports', 'audit_logs' => 'Audit Logs'];
                        foreach ($menus as $k => $v):
                            $checked = in_array($k, $sample_employee_menus) || in_array($k, ['create_label', 'label_history']) ? 'checked' : '';
                        ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="menu_permissions[Employee][]" value="<?= $k ?>" <?= $checked ?>>
                                <label class="form-check-label"><?= $v ?></label>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" name="role" value="Employee" class="btn btn-primary btn-sm mt-2">Update Employee</button>
                    </div>
                    <div class="col-md-6">
                        <h6>Admin Menus</h6>
                        <?php
                        $menus = ['manage_users' => 'Manage Users', 'manage_products' => 'Manage Products', 'reports' => 'Reports', 'audit_logs' => 'Audit Logs', 'create_label' => 'Create Label', 'label_history' => 'Label History'];
                        foreach ($menus as $k => $v):
                            $checked = in_array($k, $sample_admin_menus) || in_array($k, ['manage_users', 'manage_products', 'reports']) ? 'checked' : '';
                        ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="menu_permissions[Admin][]" value="<?= $k ?>" <?= $checked ?>>
                                <label class="form-check-label"><?= $v ?></label>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" name="role" value="Admin" class="btn btn-primary btn-sm mt-2">Update Admin</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Bootstrap 5 JS (required for modals) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


<?php $conn->close(); ?>