<?php
session_start();
require_once 'includes/db.php';

if ($_SESSION['role'] != 'Super Admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['report_type'])) {
    $report_type = $_POST['report_type'];
    $filename = "report_" . $report_type . "_" . date('Ymd_His') . ".txt";
    $content = "Franklin Baker Report - Generated on " . date('l, F j, Y g:i A T') . "\n\n";

    try {
        if ($report_type == 'user_activity') {
            $stmt = $conn->prepare("SELECT employee_id, first_name, last_name, status, created_at FROM users ORDER BY created_at DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            $content .= "User Activity Report\n-------------------\n";
            while ($row = $result->fetch_assoc()) {
                $content .= "Employee ID: " . htmlspecialchars($row['employee_id']) . "\n";
                $content .= "Name: " . htmlspecialchars($row['first_name'] . " " . $row['last_name']) . "\n";
                $content .= "Status: " . htmlspecialchars($row['status']) . "\n";
                $content .= "Joined: " . htmlspecialchars($row['created_at']) . "\n\n";
            }
        } elseif ($report_type == 'system_usage') {
            $content .= "System Usage Report\n-------------------\n";
            $content .= "Note: Detailed usage data to be implemented. Current timestamp: " . date('Y-m-d H:i:s T') . "\n";
        }

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $content;
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error generating report: " . htmlspecialchars($e->getMessage());
        header("Location: superadmin_dashboard.php");
        exit();
    }
} else {
    header("Location: superadmin_dashboard.php");
    exit();
}
?>