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
    die('Error: PHPMailer not found. Please install PHPMailer via Composer or manually place it in vendor/PHPMailer/src/.');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('UTC');

// Ensure user is Admin or Super Admin
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Super Admin')) {
    header("Location: login.php");
    exit();
}

$is_super_admin = ($_SESSION['role'] == 'Super Admin');

// Handle actions: update role/status, delete, and custom menu access
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_super_admin) {
    if (!isset($_POST['confirmed']) || $_POST['confirmed'] !== 'true') {
        $_SESSION['error'] = "Please confirm the action.";
        header("Location: manage_users.php");
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] == 'update_user') {
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
            // Log the promotion action
            $details = json_encode(['new_role' => $role, 'new_status' => $status, 'email' => $user['email']]);
            $stmt = $conn->prepare("INSERT INTO user_actions (user_id, action_type, details) VALUES (?, 'promotion', ?)");
            $stmt->bind_param("is", $user_id, $details);
            $stmt->execute();
            $stmt->close();

            // Send promotion email
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
                $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name']);
                $mail->isHTML(true);
                $mail->Subject = 'Account Update Notification';
                $mail->Body = "Dear {$user['first_name']},<br>Your account has been updated.<br>New Role: <b>$role</b><br>New Status: <b>$status</b><br>Thank you!";
                $mail->AltBody = "Dear {$user['first_name']}, your account has been updated. New Role: $role, New Status: $status. Thank you!";
                $mail->send();
                error_log("Promotion email sent to {$user['email']} for user_id: $user_id");
            } catch (Exception $e) {
                error_log("PHPMailer error for {$user['email']}: {$mail->ErrorInfo}");
            }
            
            $_SESSION['success'] = "User updated successfully.";
        } else {
            $_SESSION['error'] = "Failed to update user.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete_user') {
        $user_id = intval($_POST['user_id']);
        $reason = trim($_POST['reason'] ?? '');

        if (empty($reason)) {
            $_SESSION['error'] = "Please provide a reason for deletion.";
            header("Location: manage_users.php");
            exit();
        }

        $stmt = $conn->prepare("SELECT email, first_name, middle_name, last_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Log the deletion action
        $details = json_encode(['email' => $user['email']]);
        $stmt = $conn->prepare("INSERT INTO user_actions (user_id, action_type, details, reason) VALUES (?, 'deletion', ?, ?)");
        $stmt->bind_param("iss", $user_id, $details, $reason);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            // Send termination email
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
                $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name']);
                $mail->isHTML(true);
                $mail->Subject = 'Account Termination Notification';
                $mail->Body = "Dear {$user['first_name']},<br>Your account has been terminated.<br>Reason: <b>$reason</b><br>Thank you for your time with us.";
                $mail->AltBody = "Dear {$user['first_name']}, your account has been terminated. Reason: $reason. Thank you for your time with us.";
                $mail->send();
                error_log("Termination email sent to {$user['email']} for user_id: $user_id with reason: $reason");
            } catch (Exception $e) {
                error_log("PHPMailer error for {$user['email']}: {$mail->ErrorInfo}");
            }
            $stmt->close();
            $_SESSION['success'] = "User deleted successfully.";
        } else {
            $_SESSION['error'] = "Failed to delete user.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'update_access') {
        $role = $_POST['role'];
        $current_user_id = $_SESSION['user_id'] ?? 1; // Use Super Admin's ID or default to 1 if not set

        if (isset($_POST['menu_permissions'][$role])) {
            $menu_permissions = implode(',', $_POST['menu_permissions'][$role]);
            $stmt = $conn->prepare("UPDATE users SET custom_menus = ? WHERE role = ? AND role != 'Super Admin'");
            $stmt->bind_param("ss", $menu_permissions, $role);
            if ($stmt->execute()) {
                // Log the menu update action with the current user's ID
                $details = json_encode(['role' => $role, 'new_menus' => $menu_permissions]);
                $stmt = $conn->prepare("INSERT INTO user_actions (user_id, action_type, details) VALUES (?, 'menu_update', ?)");
                $stmt->bind_param("is", $current_user_id, $details);
                $stmt->execute();
                $stmt->close();

                // Fetch all users of the affected role to send emails
                $stmt = $conn->prepare("SELECT id, email, first_name, middle_name, last_name FROM users WHERE role = ? AND role != 'Super Admin'");
                $stmt->bind_param("s", $role);
                $stmt->execute();
                $users = $stmt->get_result();
                $menu_list = implode(', ', $_POST['menu_permissions'][$role]);
                while ($user = $users->fetch_assoc()) {
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
                        $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name']);
                        $mail->isHTML(true);
                        $mail->Subject = 'Menu Access Update';
                        $mail->Body = "Dear {$user['first_name']},<br>Your menu access has been updated.<br>New Menus: <b>$menu_list</b><br>Thank you!";
                        $mail->AltBody = "Dear {$user['first_name']}, your menu access has been updated. New Menus: $menu_list. Thank you!";
                        $mail->send();
                        error_log("Menu access email sent to {$user['email']} for role: $role");
                    } catch (Exception $e) {
                        error_log("PHPMailer error for {$user['email']}: {$mail->ErrorInfo}");
                    }
                }
                $stmt->close();
                $_SESSION['success'] = "Access updated for all $role users.";
            } else {
                $_SESSION['error'] = "Failed to update access. Please try again.";
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET custom_menus = NULL WHERE role = ? AND role != 'Super Admin'");
            $stmt->bind_param("s", $role);
            if ($stmt->execute()) {
                // Log the menu clear action with the current user's ID
                $details = json_encode(['role' => $role, 'new_menus' => 'cleared']);
                $stmt = $conn->prepare("INSERT INTO user_actions (user_id, action_type, details) VALUES (?, 'menu_update', ?)");
                $stmt->bind_param("is", $current_user_id, $details);
                $stmt->execute();
                $stmt->close();

                // Fetch all users of the affected role to send emails
                $stmt = $conn->prepare("SELECT id, email, first_name, middle_name, last_name FROM users WHERE role = ? AND role != 'Super Admin'");
                $stmt->bind_param("s", $role);
                $stmt->execute();
                $users = $stmt->get_result();
                while ($user = $users->fetch_assoc()) {
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
                        $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name']);
                        $mail->isHTML(true);
                        $mail->Subject = 'Menu Access Update';
                        $mail->Body = "Dear {$user['first_name']},<br>Your menu access has been cleared.<br>Thank you!";
                        $mail->AltBody = "Dear {$user['first_name']}, your menu access has been cleared. Thank you!";
                        $mail->send();
                        error_log("Menu access cleared email sent to {$user['email']} for role: $role");
                    } catch (Exception $e) {
                        error_log("PHPMailer error for {$user['email']}: {$mail->ErrorInfo}");
                    }
                }
                $stmt->close();
                $_SESSION['success'] = "Access cleared for all $role users.";
            } else {
                $_SESSION['error'] = "Failed to clear access. Please try again.";
            }
        }
    }
    header("Location: manage_users.php");
    exit();
}

$page_title = "Manage Users";
require_once 'header.php';

// Fetch all users with custom menus
$users = [];
try {
    $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, employee_id, email, role, status, custom_menus FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching users: " . htmlspecialchars($e->getMessage());
}

// Fetch role-specific sample menus
$sample_employee_menus = [];
$sample_admin_menus = [];
foreach ($users as $user) {
    if ($user['role'] == 'Employee' && empty($sample_employee_menus)) {
        $sample_employee_menus = !empty($user['custom_menus']) ? explode(',', $user['custom_menus']) : [];
    } elseif ($user['role'] == 'Admin' && empty($sample_admin_menus)) {
        $sample_admin_menus = !empty($user['custom_menus']) ? explode(',', $user['custom_menus']) : [];
    }
    if (!empty($sample_employee_menus) && !empty($sample_admin_menus)) break;
}
?>

<div class="container-fluid">
    <?php
    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }
    ?>
    <h2 class="mb-4">Manage Users</h2>
    <div class="card">
        <div class="card-header bg-primary-green text-white">
            <i class="bi bi-people-fill"></i> User List
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped w-100" style="min-width: 1400px;">
                    <thead>
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
                        <?php if (empty($users)): ?>
                            <tr><td colspan="7">No users found.</td></tr>
                        <?php else: ?>
                            <?php $row_number = 1; ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $row_number++; ?></td>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['employee_id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td><?php echo htmlspecialchars($user['status']); ?></td>
                                    <td class="p-1">
                                        <div class="d-flex flex-nowrap align-items-center gap-3">
                                            <?php if ($is_super_admin): ?>
                                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to update this user?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="update_user">
                                                    <input type="hidden" name="confirmed" value="true">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <select name="role" class="form-select form-select-sm w-auto" style="max-width: 120px;">
                                                            <option <?php echo $user['role'] == 'Employee' ? 'selected' : ''; ?>>Employee</option>
                                                            <option <?php echo $user['role'] == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                                            <option <?php echo $user['role'] == 'Super Admin' ? 'selected' : ''; ?>>Super Admin</option>
                                                        </select>
                                                        <select name="status" class="form-select form-select-sm w-auto" style="max-width: 120px;">
                                                            <option <?php echo $user['status'] == 'pending' ? 'selected' : ''; ?>>pending</option>
                                                            <option <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>active</option>
                                                            <option <?php echo $user['status'] == 'inactive' ? 'selected' : ''; ?>>inactive</option>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Update</button>
                                                        <?php if ($user['status'] == 'pending'): ?>
                                                            <form method="POST" action="approve_user.php?id=<?php echo $user['id']; ?>" style="margin: 0; display: inline;">
                                                                <input type="hidden" name="confirmed" value="true">
                                                                <a href="#" class="btn btn-sm btn-success" onclick="if(confirm('Are you sure you want to approve this user?')) this.closest('form').submit();"><i class="bi bi-check-circle"></i> Approve</a>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </form>
                                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $user['id']; ?>">Delete</button>

                                                <!-- Delete Modal -->
                                                <div class="modal fade" id="deleteModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $user['id']; ?>" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteModalLabel<?php echo $user['id']; ?>">Confirm Deletion</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <form method="POST" action="" id="deleteForm<?php echo $user['id']; ?>" onsubmit="handleDelete(event, <?php echo $user['id']; ?>); return false;">
                                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                    <input type="hidden" name="action" value="delete_user">
                                                                    <input type="hidden" name="confirmed" value="true">
                                                                    <div class="mb-3">
                                                                        <label for="reason_<?php echo $user['id']; ?>" class="form-label">Reason for Termination</label>
                                                                        <textarea class="form-control" id="reason_<?php echo $user['id']; ?>" name="reason" required></textarea>
                                                                    </div>
                                                                    <button type="submit" class="btn btn-danger">Delete User</button>
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($is_super_admin): ?>
    <div class="card mt-4">
        <div class="card-header bg-primary-green text-white">
            <i class="bi bi-shield-lock"></i> User Access Management
        </div>
        <div class="card-body">
            <h4 class="mb-3">Manage Menu Access for All Users</h4>
            <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to update menu access for all users of the selected role?');">
                <input type="hidden" name="action" value="update_access">
                <input type="hidden" name="confirmed" value="true">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Employee Menu Access</h5>
                        <div class="d-flex flex-column gap-2">
                            <?php
                            $employee_menus = ['create_label' => 'Create Label', 'label_history' => 'Label History'];
                            $available_menus = [
                                'manage_products' => 'Manage Products',
                                'reports' => 'Reports',
                                'audit_logs' => 'Audit Logs'
                            ];
                            $all_menus = $employee_menus + $available_menus;
                            foreach ($all_menus as $menu_key => $menu_name) {
                                $checked = in_array($menu_key, $sample_employee_menus) || array_key_exists($menu_key, $employee_menus) ? 'checked' : '';
                                echo '<div class="form-check">';
                                echo '<input type="checkbox" class="form-check-input" name="menu_permissions[Employee][]" value="' . htmlspecialchars($menu_key) . '" ' . $checked . '>';
                                echo '<label class="form-check-label">' . htmlspecialchars($menu_name) . '</label>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <button type="submit" name="role" value="Employee" class="btn btn-primary mt-3">Update Employee Access</button>
                    </div>
                    <div class="col-md-6">
                        <h5>Admin Menu Access</h5>
                        <div class="d-flex flex-column gap-2">
                            <?php
                            $admin_menus = ['manage_users' => 'Manage Users', 'manage_products' => 'Manage Products', 'reports' => 'Reports'];
                            $available_menus = [
                                'audit_logs' => 'Audit Logs',
                                'create_label' => 'Create Label',
                                'label_history' => 'Label History'
                            ];
                            $all_menus = $admin_menus + $available_menus;
                            foreach ($all_menus as $menu_key => $menu_name) {
                                $checked = in_array($menu_key, $sample_admin_menus) || array_key_exists($menu_key, $admin_menus) ? 'checked' : '';
                                echo '<div class="form-check">';
                                echo '<input type="checkbox" class="form-check-input" name="menu_permissions[Admin][]" value="' . htmlspecialchars($menu_key) . '" ' . $checked . '>';
                                echo '<label class="form-check-label">' . htmlspecialchars($menu_name) . '</label>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <button type="submit" name="role" value="Admin" class="btn btn-primary mt-3">Update Admin Access</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    .modal {
        z-index: 1060 !important; /* Ensure modal is above backdrop */
    }
    .modal-backdrop {
        z-index: 1050 !important; /* Default backdrop z-index */
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/scripts.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalElements = document.querySelectorAll('.modal');
    modalElements.forEach(function(modal) {
        var modalId = modal.id;
        var modalInstance = new bootstrap.Modal(modal, {
            backdrop: true,
            keyboard: true
        });

        modal.addEventListener('hidden.bs.modal', function () {
            document.body.classList.remove('modal-open');
            var backdrops = document.getElementsByClassName('modal-backdrop');
            while (backdrops.length) {
                backdrops[0].parentNode.removeChild(backdrops[0]);
            }
            document.body.style.overflow = 'auto';
            document.body.style.paddingRight = '0px';
        });

        modal.addEventListener('show.bs.modal', function () {
            var form = modal.querySelector('form');
            if (form) form.querySelector('textarea').focus();
        });
    });

    window.handleDelete = function(event, userId) {
        event.preventDefault();
        if (confirm('Are you sure you want to delete this user?')) {
            var form = document.getElementById('deleteForm' + userId);
            var formData = new FormData(form);
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error('Network error: ' + response.statusText);
                return response.text();
            })
            .then(() => {
                var modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal' + userId));
                if (modal) modal.hide();
                setTimeout(() => location.reload(), 500); // Delay to ensure modal closes
            })
            .catch(error => {
                console.error('Error deleting user: ', error);
                alert('An error occurred while deleting the user. Please try again.');
            });
        }
    };

    window.closeModal = function(userId) {
        var modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal' + userId));
        if (modal) modal.hide();
    };
});
</script>
</body>
</html>
<?php $conn->close(); ?>