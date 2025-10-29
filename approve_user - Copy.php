<?php
session_start();
require_once 'includes/db.php';
require 'vendor/autoload.php'; // Assuming PHPMailer is installed via Composer in the root

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure user is Admin or Super Admin
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Super Admin')) {
    header("Location: login.php");
    exit();
}

// Check for confirmation
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['confirmed']) || $_POST['confirmed'] !== 'true') {
    $_SESSION['error'] = "Please confirm the approval action.";
    header("Location: admin_dashboard.php");
    exit();
}

if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);

    try {
        // Fetch the user to approve
        $stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            // Approve the user
            $update_stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $update_stmt->bind_param("i", $user_id);
            if ($update_stmt->execute()) {
                // Send email notification using PHPMailer
                $mail = new PHPMailer(true);
                try {
                    // Server settings (update with your SMTP details)
                    $mail->isSMTP();
                    $mail->Host = 'smtp.example.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'your-email@example.com';
                    $mail->Password = 'your-password';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    // Recipients
                    $mail->setFrom('no-reply@franklinbaker.com', 'Franklin Baker System');
                    $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Your Account Has Been Approved';
                    $mail->Body = 'Dear ' . htmlspecialchars($user['first_name']) . ',<br><br>Your account has been approved. You can now log in to the Franklin Baker Label System.<br><br>Best regards,<br>Admin Team';

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Email error: {$mail->ErrorInfo}");
                }

                $_SESSION['success'] = "User approved successfully.";
            } else {
                $_SESSION['error'] = "Failed to approve user.";
            }
            $update_stmt->close();
        } else {
            $_SESSION['error'] = "Invalid or already approved user.";
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . htmlspecialchars($e->getMessage());
    }
} else {
    $_SESSION['error'] = "No user ID provided.";
}

header("Location: admin_dashboard.php");
exit();
?>