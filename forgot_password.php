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

// Set timezone to UTC
date_default_timezone_set('UTC');

// Set MySQL connection timezone to UTC
$conn->query("SET time_zone = '+00:00'");

$step = isset($_GET['step']) ? $_GET['step'] : 'request';
$employee_id = isset($_GET['employee_id']) ? trim($_GET['employee_id']) : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($step == 'request') {
        $email = trim($_POST['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
            error_log("Invalid email format: $email");
        } else {
            $stmt = $conn->prepare("SELECT employee_id FROM users WHERE email = ? AND status = 'active'");
            $stmt->bind_param("s", $email);
            if (!$stmt->execute()) {
                $error = "Database error during email lookup.";
                error_log("Email lookup failed for $email: " . $conn->error);
            } else {
                $result = $stmt->get_result();
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    $employee_id = trim($user['employee_id']);
                    $token = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Delete old tokens
                    $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE employee_id = ?");
                    $stmt->bind_param("s", $employee_id);
                    $stmt->execute();

                    // Store new token
                    $stmt = $conn->prepare("INSERT INTO password_reset_tokens (employee_id, token, expires_at) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $employee_id, $token, $expires_at);
                    if ($stmt->execute()) {
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
                            $mail->addAddress($email);
                            $mail->isHTML(true);
                            $mail->Subject = 'Password Reset Code';
                            $mail->Body = "Your password reset code is: <b>$token</b><br>This code expires in 1 hour.";
                            $mail->AltBody = "Your password reset code is: $token\nThis code expires in 1 hour.";
                            $mail->send();
                            error_log("Email sent successfully to $email for employee_id: $employee_id with token: $token, expires at: $expires_at");
                            header("Location: forgot_password.php?step=verify&employee_id=" . urlencode($employee_id));
                            exit();
                        } catch (Exception $e) {
                            $error = "Failed to send email. For testing, use this code: $token";
                            error_log("PHPMailer error for $email: {$mail->ErrorInfo}");
                        }
                    } else {
                        $error = "Failed to generate reset code.";
                        error_log("Failed to insert token for employee_id: $employee_id: " . $conn->error);
                    }
                } else {
                    $error = "Email not found or account not active.";
                    error_log("Email not found or not active: $email");
                }
            }
            $stmt->close();
        }
    } elseif ($step == 'verify') {
        $token = trim($_POST['token']);
        $stmt = $conn->prepare("SELECT * FROM password_reset_tokens WHERE employee_id = ? AND token = ? AND expires_at > NOW()");
        $stmt->bind_param("ss", $employee_id, $token);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                error_log("Token verified for employee_id: $employee_id, token: $token, expires_at: {$row['expires_at']}");
                header("Location: forgot_password.php?step=reset&employee_id=" . urlencode($employee_id));
                exit();
            } else {
                $error = "Invalid or expired code.";
                error_log("Invalid or expired token for employee_id: $employee_id, token: $token, current time: " . date('Y-m-d H:i:s'));
                // Debug: Check all tokens for this employee_id
                $stmt = $conn->prepare("SELECT token, expires_at FROM password_reset_tokens WHERE employee_id = ?");
                $stmt->bind_param("s", $employee_id);
                $stmt->execute();
                $debug_result = $stmt->get_result();
                while ($debug_row = $debug_result->fetch_assoc()) {
                    error_log("Debug: Token: {$debug_row['token']}, Expires: {$debug_row['expires_at']}");
                }
                $stmt->close();
            }
        } else {
            $error = "Database error during code verification.";
            error_log("Token verification failed for employee_id: $employee_id: " . $conn->error);
        }
        $stmt->close();
    } elseif ($step == 'reset') {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE employee_id = ?");
            $stmt->bind_param("ss", $hashed_password, $employee_id);
            if ($stmt->execute()) {
                $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE employee_id = ?");
                $stmt->bind_param("s", $employee_id);
                $stmt->execute();
                $_SESSION['success'] = "Password reset successfully.";
                header("Location: login.php");
                exit();
            } else {
                $error = "Failed to reset password.";
                error_log("Password reset failed for employee_id: $employee_id: " . $conn->error);
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
    <title>Franklin Baker - Forgot Password</title>
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
                        <h4 class="mb-0"><?php echo $step == 'request' ? 'Forgot Password' : ($step == 'verify' ? 'Verify Code' : 'Reset Password'); ?></h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="POST" action="forgot_password.php?step=<?php echo htmlspecialchars($step); ?>&employee_id=<?php echo urlencode($employee_id); ?>" onsubmit="<?php echo $step == 'reset' ? 'return confirm(\'Are you sure you want to reset your password?\');' : ''; ?>">
                            <?php if ($step == 'request'): ?>
                                <div class="mb-3">
                                    <label for="email" class="form-label"><i class="bi bi-envelope-fill"></i> Email Address</label>
                                    <input type="email" class="form-control rounded" id="email" name="email" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Send Reset Code</button>
                            <?php elseif ($step == 'verify'): ?>
                                <div class="mb-3">
                                    <label for="token" class="form-label"><i class="bi bi-key-fill"></i> Reset Code</label>
                                    <input type="text" class="form-control rounded" id="token" name="token" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Verify Code</button>
                            <?php else: ?>
                                <div class="mb-3 position-relative">
                                    <label for="password" class="form-label"><i class="bi bi-lock-fill"></i> New Password</label>
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
                                <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <small>Back to <a href="login.php">Login</a></small>
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