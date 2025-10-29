<?php
session_start();

// Check for confirmation
if (!isset($_POST['confirmed']) || $_POST['confirmed'] !== 'true') {
    $_SESSION['error'] = "Please confirm the logout action.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit();
}

// Clear all session data
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redirect to login
header("Location: login.php");
exit();
?>