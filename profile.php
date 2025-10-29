<?php
session_start();
$page_title = "Profile";
require_once 'header.php';
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$section = $_GET['section'] ?? 'credentials';
$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);
$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($section == 'credentials') {
        $first_name = trim($_POST['first_name']);
        $middle_name = trim($_POST['middle_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        $errors = [];
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $errors[] = "First name, last name, and email are required.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        if ($password && strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }

        if (empty($errors)) {
            try {
                $conn->begin_transaction();
                $password_hash = $password ? password_hash($password, PASSWORD_DEFAULT) : null;
                $stmt = $conn->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ? WHERE id = ?");
                if ($password_hash) {
                    $stmt = $conn->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ?, password = ? WHERE id = ?");
                    $stmt->bind_param("sssssi", $first_name, $middle_name, $last_name, $email, $password_hash, $user_id);
                } else {
                    $stmt->bind_param("ssssi", $first_name, $middle_name, $last_name, $email, $user_id);
                }
                if ($stmt->execute()) {
                    $conn->commit();
                    $_SESSION['success'] = "Profile updated successfully.";
                } else {
                    $conn->rollback();
                    $_SESSION['error'] = "Failed to update profile.";
                }
                $stmt->close();
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Error updating profile: " . htmlspecialchars($e->getMessage());
            }
        } else {
            $_SESSION['error'] = implode(" ", $errors);
        }
        header("Location: profile.php?section=credentials");
        exit();
    } elseif ($section == 'report' && $_SESSION['role'] != 'Super Admin') {
        $problem = trim($_POST['problem']);
        if (!empty($problem)) {
            try {
                $stmt = $conn->prepare("INSERT INTO reports (employee_id, description) VALUES (?, ?)");
                $employee_id = $_SESSION['employee_id'] ?? 'Unknown'; // Assuming employee_id is in session
                $stmt->bind_param("ss", $employee_id, $problem);
                if ($stmt->execute()) {
                    $report_id = $conn->insert_id;
                    $_SESSION['success'] = "Problem reported successfully (ID: $report_id).";

                    // Attempt email notification, but handle missing system_config gracefully
                    $send_email = true;
                    $stmt_config = $conn->prepare("SELECT smtp_host, smtp_port, notifications FROM system_config LIMIT 1");
                    if ($stmt_config) {
                        $stmt_config->execute();
                        $config = $stmt_config->get_result()->fetch_assoc();
                        $stmt_config->close();
                        $send_email = $config['notifications'] ?? false;
                    } else {
                        // Table doesn't exist or query failed, skip email
                        $send_email = false;
                    }

                    if ($send_email) {
                        $to = "3mjhay0416@gmail.com"; // Replace with actual admin email
                        $subject = "New Problem Report (ID: $report_id)";
                        $message = "A new problem has been reported by $employee_id on " . date('l, F j, Y g:i A T') . ":\n\n$problem";
                        $headers = "From: noreply@franklinbaker.com\r\n";
                        if (!mail($to, $subject, $message, $headers, "-f" . ($config['smtp_host'] ?? 'smtp.gmail.com') . ":" . ($config['smtp_port'] ?? 587))) {
                            $_SESSION['error'] = "Report saved, but email notification failed.";
                        }
                    }
                } else {
                    $_SESSION['error'] = "Failed to submit report.";
                }
                $stmt->close();
            } catch (Exception $e) {
                $_SESSION['error'] = "Error submitting report: " . htmlspecialchars($e->getMessage());
            }
        } else {
            $_SESSION['error'] = "Problem description is required.";
        }
        header("Location: profile.php?section=report");
        exit();
    } elseif ($section == 'logo' && $_SESSION['role'] == 'Super Admin') {
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['logo'];
            $allowed_types = ['image/png', 'image/jpeg', 'image/gif'];
            if (in_array($file['type'], $allowed_types) && $file['size'] < 2000000) { // 2MB limit
                $target = 'assets/images/logo.png';
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $_SESSION['success'] = "Logo updated successfully.";
                } else {
                    $_SESSION['error'] = "Failed to upload logo.";
                }
            } else {
                $_SESSION['error'] = "Invalid file type or size exceeds 2MB.";
            }
        } else {
            $_SESSION['error'] = "No file uploaded or upload error.";
        }
        header("Location: profile.php?section=logo");
        exit();
    }
}
?>

<div class="container-fluid">
    <h2 class="mb-4">User Profile</h2>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="card-header bg-primary-green text-white">
            <i class="bi bi-person-fill"></i> Profile Details
        </div>
        <div class="card-body">
            <?php if ($section == 'credentials'): ?>
                <form method="POST" onsubmit="return confirm('Are you sure you want to update your credentials?');">
                    <div class="mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="middle_name" class="form-label">Middle Name</label>
                        <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" id="password" name="password">
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>
                    <button type="submit" class="btn btn-primary">Update Credentials</button>
                </form>
            <?php elseif ($section == 'report' && $_SESSION['role'] != 'Super Admin'): ?>
                <form method="POST">
                    <div class="mb-3">
                        <label for="problem" class="form-label">Describe the Problem</label>
                        <textarea class="form-control" id="problem" name="problem" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Report</button>
                </form>
                <p class="mt-3">Reports are sent to admins for review via the Reports section.</p>
            <?php elseif ($section == 'logo' && $_SESSION['role'] == 'Super Admin'): ?>
                <form method="POST" enctype="multipart/form-data" id="logoForm">
                    <div class="mb-3">
                        <label for="logo" class="form-label">Upload New Logo (PNG, JPEG, GIF, max 2MB)</label>
                        <input type="file" class="form-control" id="logo" name="logo" accept=".png,.jpg,.jpeg,.gif" onchange="previewLogo()">
                    </div>
                    <div class="mb-3" id="logoPreview" style="display: none;">
                        <label>Preview:</label><br>
                        <img id="logoImage" src="#" alt="Logo Preview" style="max-width: 200px; max-height: 200px;">
                    </div>
                    <button type="submit" class="btn btn-primary">Upload Logo</button>
                </form>
                <p class="mt-3">Current logo will be replaced with the uploaded image.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/scripts.js"></script>
<script>
function previewLogo() {
    const fileInput = document.getElementById('logo');
    const preview = document.getElementById('logoPreview');
    const img = document.getElementById('logoImage');
    const file = fileInput.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
}
</script>
<?php $conn->close(); ?>