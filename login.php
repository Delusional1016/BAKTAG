<?php
session_start();
require_once 'includes/db.php';

// Set timezone to UTC
date_default_timezone_set('UTC');

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    error_log("User already logged in, user_id: {$_SESSION['user_id']}, role: {$_SESSION['role']}");
    if ($_SESSION['role'] == 'Employee') {
        header("Location: employee_dashboard.php");
    } elseif ($_SESSION['role'] == 'Admin') {
        header("Location: admin_dashboard.php");
    } elseif ($_SESSION['role'] == 'Super Admin') {
        header("Location: superadmin_dashboard.php");
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $employee_id = trim($_POST['employee_id']);
    $password = $_POST['password'];

    // Validate inputs
    if (empty($employee_id) || empty($password)) {
        $error = "All fields are required.";
        error_log("Login failed: Empty fields for employee_id: $employee_id");
    } else {
        // Check if employee_id exists and get user details
        $stmt = $conn->prepare("SELECT id, employee_id, password, role, status FROM users WHERE employee_id = ?");
        $stmt->bind_param("s", $employee_id);
        if (!$stmt->execute()) {
            $error = "Database error during login.";
            error_log("Login query failed for employee_id: $employee_id: " . $conn->error);
            $stmt->close();
        } else {
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Check account status
                    if ($user['status'] === 'active') {
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['employee_id'] = $user['employee_id'];
                        $_SESSION['role'] = $user['role'];
                        error_log("Login successful for employee_id: $employee_id, role: {$user['role']}");
                        // Redirect based on role
                        if ($user['role'] == 'Employee') {
                            header("Location: employee_dashboard.php");
                        } elseif ($user['role'] == 'Admin') {
                            header("Location: admin_dashboard.php");
                        } elseif ($user['role'] == 'Super Admin') {
                            header("Location: superadmin_dashboard.php");
                        }
                        exit();
                    } else {
                        $error = "Account not activated.";
                        error_log("Login failed: Account not activated for employee_id: $employee_id, status: {$user['status']}");
                    }
                } else {
                    $error = "Invalid Employee ID or password.";
                    error_log("Login failed: Invalid password for employee_id: $employee_id");
                }
            } else {
                $error = "Invalid Employee ID or password.";
                error_log("Login failed: Employee ID not found: $employee_id");
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Franklin Baker - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
            <div class="col-md-4">
                <div class="card form-card shadow-sm">
                    <div class="card-header bg-primary-green text-white text-center">
                        <img src="assets/images/logo.png" alt="Franklin Baker Logo" class="logo d-block mx-auto mb-3" style="max-height: 40px;">
                        <h4 class="mb-0">Login</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="POST" action="login.php">
                            <div class="mb-3">
                                <label for="employee_id" class="form-label"><i class="bi bi-person-fill"></i> Employee ID</label>
                                <input type="text" class="form-control rounded" id="employee_id" name="employee_id" required>
                            </div>
                            <div class="mb-3 position-relative">
                                <label for="password" class="form-label"><i class="bi bi-lock-fill"></i> Password</label>
                                <input type="password" class="form-control rounded" id="password" name="password" required>
                                <span class="password-toggle-icon" onclick="togglePassword('password')">
                                    <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
                                </span>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <small>Don't have an account? <a href="register.php">Register</a> | <a href="forgot_password.php">Forgot Password?</a></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/scripts.js"></script>
</body>
</html>
<?php if (isset($conn)) $conn->close(); ?>