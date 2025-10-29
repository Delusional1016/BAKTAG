<?php
session_start();
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $employee_id = trim($_POST['employee_id']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE employee_id = ? OR email = ?");
        $stmt->bind_param("ss", $employee_id, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Employee ID or email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (first_name, middle_name, last_name, employee_id, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?, 'Employee', 'pending')");
            $stmt->bind_param("ssssss", $first_name, $middle_name, $last_name, $employee_id, $email, $hashed_password);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Registration successful. Please wait for admin approval.";
                header("Location: login.php?registered=1");
                exit();
            } else {
                $error = "Registration failed. Try again.";
            }
        }
        $stmt->close();
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
    <title>Franklin Baker - Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
            <div class="col-md-6">
                <div class="card form-card shadow-sm">
                    <div class="card-header bg-primary-green text-white text-center">
                        <img src="assets/images/franklinbakerlogo.png" alt="Franklin Baker Logo" class="logo d-block mx-auto mb-3" style="max-height: 40px;">
                        <h4 class="mb-0">Register</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="POST" action="register.php">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="first_name" class="form-label"><i class="bi bi-person"></i> First Name</label>
                                    <input type="text" class="form-control rounded" id="first_name" name="first_name" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="middle_name" class="form-label"><i class="bi bi-person"></i> Middle Name</label>
                                    <input type="text" class="form-control rounded" id="middle_name" name="middle_name">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="last_name" class="form-label"><i class="bi bi-person"></i> Last Name</label>
                                    <input type="text" class="form-control rounded" id="last_name" name="last_name" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="employee_id" class="form-label"><i class="bi bi-person-fill"></i> Employee ID</label>
                                <input type="text" class="form-control rounded" id="employee_id" name="employee_id" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label"><i class="bi bi-envelope-fill"></i> Email Address</label>
                                <input type="email" class="form-control rounded" id="email" name="email" required>
                            </div>
                            <div class="mb-3 position-relative">
                                <label for="password" class="form-label"><i class="bi bi-lock-fill"></i> Password</label>
                                <input type="password" class="form-control rounded" id="password" name="password" required>
                                <span class="password-toggle-icon" onclick="togglePassword('password')">
                                    <i class="bi bi-eye-slash" id="togglePasswordIcon_password"></i>
                                </span>
                            </div>
                            <div class="mb-3 position-relative">
                                <label for="confirm_password" class="form-label"><i class="bi bi-lock-fill"></i> Confirm Password</label>
                                <input type="password" class="form-control rounded" id="confirm_password" name="confirm_password" required>
                                <span class="password-toggle-icon" onclick="togglePassword('confirm_password')">
                                    <i class="bi bi-eye-slash" id="togglePasswordIcon_confirm_password"></i>
                                </span>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Register</button>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <small>Already have an account? <a href="login.php">Login</a></small>
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