<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'includes/db.php';

// Fetch custom menus for the current user
$custom_menus = '';
try {
    $stmt = $conn->prepare("SELECT custom_menus FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $custom_menus = $row['custom_menus'] ?? '';
    }
    $stmt->close();
} catch (Exception $e) {
    // Handle error silently or log it
}
$_SESSION['custom_menus'] = $custom_menus;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Franklin Baker - <?php echo $page_title ?? 'Dashboard'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand fixed-top" style="background-color: var(--primary-color);">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="assets/images/logo.png" alt="Franklin Baker Logo" class="logo" style="max-height: 40px;">
            </a>
            <button class="hamburger navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>
            <div class="ms-auto">
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link text-white profile-icon" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Profile">
                            <i class="bi bi-person-circle"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php?section=credentials">Edit Credentials</a></li>
                            <?php if ($_SESSION['role'] == 'Super Admin'): ?>
                                <li><a class="dropdown-item" href="profile.php?section=logo">Change Logo</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="profile.php?section=report">Report a Problem</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <form method="POST" action="logout.php" style="display:inline;" id="logout-form">
                            <input type="hidden" name="confirmed" value="true">
                            <a class="nav-link text-white logout-icon" href="#" onclick="if(confirm('Are you sure you want to log out?')) document.getElementById('logout-form').submit();" title="Logout">
                                <i class="bi bi-box-arrow-right"></i>
                            </a>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Layout -->
    <div class="main-layout d-flex">
        <div class="sidebar collapse show" id="sidebarMenu">
            <h5 class="text-center p-3 text-white">Welcome, <?php echo htmlspecialchars($_SESSION['role']); ?></h5>
            <hr class="text-white">
            <ul class="nav flex-column p-3">
                <?php
                $custom_menus = !empty($_SESSION['custom_menus']) ? explode(',', $_SESSION['custom_menus']) : [];
                $all_menu_items = [];

                if ($_SESSION['role'] == 'Employee') {
                    $default_menus = [
                        'create_label' => ['url' => 'create_label.php', 'icon' => 'bi-tag', 'label' => 'Create Label'],
                        'label_history' => ['url' => 'label_history.php', 'icon' => 'bi-clock-history', 'label' => 'Label History']
                    ];
                    $custom_menu_items = [
                        'manage_products' => ['url' => 'manage_products.php', 'icon' => 'bi-tags', 'label' => 'Manage Products'],
                        'reports' => ['url' => 'reports.php', 'icon' => 'bi-bar-chart', 'label' => 'Reports'],
                        'audit_logs' => ['url' => 'audit_logs.php', 'icon' => 'bi-journal-text', 'label' => 'Audit Logs']
                    ];
                } elseif ($_SESSION['role'] == 'Admin') {
                    $default_menus = [
                        'manage_users' => ['url' => 'admin_manage_users.php', 'icon' => 'bi-people', 'label' => 'Manage Users'],
                        'manage_products' => ['url' => 'manage_products.php', 'icon' => 'bi-tags', 'label' => 'Manage Products'],
                        'reports' => ['url' => 'reports.php', 'icon' => 'bi-bar-chart', 'label' => 'Reports']
                    ];
                    $custom_menu_items = [
                        'audit_logs' => ['url' => 'audit_logs.php', 'icon' => 'bi-journal-text', 'label' => 'Audit Logs'],
                        'create_label' => ['url' => 'create_label.php', 'icon' => 'bi-tag', 'label' => 'Create Label'],
                        'label_history' => ['url' => 'label_history.php', 'icon' => 'bi-clock-history', 'label' => 'Label History']
                    ];
                } elseif ($_SESSION['role'] == 'Super Admin') {
                    $default_menus = [
                        'superadmin_dashboard' => ['url' => 'superadmin_dashboard.php', 'icon' => 'bi-house', 'label' => 'Dashboard'],
                        'manage_users' => ['url' => 'manage_users.php', 'icon' => 'bi-people', 'label' => 'Manage Users'],
                        'manage_products' => ['url' => 'manage_products.php', 'icon' => 'bi-tags', 'label' => 'Manage Products'],
                        'reports' => ['url' => 'reports.php', 'icon' => 'bi-bar-chart', 'label' => 'Reports'],
                        'audit_logs' => ['url' => 'audit_logs.php', 'icon' => 'bi-journal-text', 'label' => 'Audit Logs']
                    ];
                    $custom_menu_items = [
                        'create_label' => ['url' => 'create_label.php', 'icon' => 'bi-tag', 'label' => 'Create Label'],
                        'label_history' => ['url' => 'label_history.php', 'icon' => 'bi-clock-history', 'label' => 'Label History']
                    ];
                }

                $all_menu_items = $default_menus + array_intersect_key($custom_menu_items, array_flip($custom_menus));
                foreach ($all_menu_items as $menu_key => $menu_data) {
                    echo '<li class="nav-item"><a class="nav-link text-white" href="' . htmlspecialchars($menu_data['url']) . '"><i class="' . htmlspecialchars($menu_data['icon']) . '"></i> <span>' . htmlspecialchars($menu_data['label']) . '</span></a></li>';
                }
                ?>
            </ul>
        </div>
        <div class="content w-100 p-4">
<?php
// No closing PHP tag to prevent trailing newline
?>