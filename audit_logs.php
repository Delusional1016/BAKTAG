<?php
session_start();
require_once 'header.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Super Admin')) {
    header("Location: login.php");
    exit();
}
require_once 'includes/db.php';

// Pagination
$per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM audit_logs");
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $per_page, $offset);
    $stmt->execute();
    $logs = $stmt->get_result();
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching audit logs: " . htmlspecialchars($e->getMessage());
}
?>

<div class="container-fluid">
    <h2 class="mb-4">Audit Logs</h2>
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
    <div class="card">
        <div class="card-header bg-primary-green text-white">
            <i class="bi bi-journal-text"></i> Audit Log Entries
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($logs && $logs->num_rows > 0) {
                        while ($log = $logs->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($log['created_at']) . "</td>";
                            echo "<td>" . htmlspecialchars($log['employee_id']) . "</td>";
                            echo "<td>" . htmlspecialchars($log['action']) . "</td>";
                            echo "<td>" . htmlspecialchars($log['details']) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>No logs found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <?php
            $total_pages = ceil($total / $per_page);
            if ($total_pages > 1) {
                echo "<nav><ul class='pagination'>";
                for ($i = 1; $i <= $total_pages; $i++) {
                    $active = $i == $page ? "active" : "";
                    echo "<li class='page-item $active'><a class='page-link' href='?page=$i'>$i</a></li>";
                }
                echo "</ul></nav>";
            }
            ?>
        </div>
    </div>
    <a href="superadmin_dashboard.php" class="btn btn-primary mt-3"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/scripts.js"></script>
</body>
</html>
<?php $conn->close(); ?>