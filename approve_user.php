<?php
session_start();
require_once 'includes/db.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Restrict to Admin/Super Admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    header("Location: login.php");
    exit();
}

// Must be POST + confirmed
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['confirmed']) || $_POST['confirmed'] !== 'true') {
    $_SESSION['error'] = "Invalid request.";
    header("Location: admin_manage_users.php");
    exit();
}

$user_id = intval($_POST['id'] ?? 0);
if ($user_id <= 0) {
    $_SESSION['error'] = "Invalid user ID.";
    header("Location: admin_manage_users.php");
    exit();
}

try {
    // Fetch user
    $stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $_SESSION['error'] = "User not found or already processed.";
        $stmt->close();
        header("Location: admin_manage_users.php");
        exit();
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Approve user
    $update = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
    $update->bind_param("i", $user_id);
    $success = $update->execute();
    $update->close();

    if ($success) {
        // Send email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'mailblazer123@gmail.com';
            $mail->Password = 'lzfp jhyv wrom hjdy';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('no-reply@franklinbaker.com', 'Franklin Baker System');
            $mail->addAddress($user['email']);
            $mail->isHTML(true);
            $mail->Subject = 'Account Approved';
            $mail->Body = "
                <p>Dear <strong>{$user['first_name']} {$user['last_name']}</strong>,</p>
                <p>Your account has been <strong>approved</strong>. You can now log in.</p>
                <p><a href='https://yourdomain.com/login.php'>Click here to log in</a></p>
                <hr><small>Franklin Baker HR System</small>
            ";

            $mail->send();
        } catch (Exception $e) {
            error_log("Approval email failed: " . $mail->ErrorInfo);
        }

        $_SESSION['success'] = "User approved and notified.";
    } else {
        $_SESSION['error'] = "Failed to approve user.";
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Database error.";
}

header("Location: admin_manage_users.php");
exit();
?>