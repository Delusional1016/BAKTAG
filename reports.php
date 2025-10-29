<?php
session_start();
require_once 'header.php';

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';

// Pagination
$per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM reports");
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT r.id, r.employee_id, r.description, r.created_at, u.first_name, u.last_name FROM reports r LEFT JOIN users u ON r.employee_id = u.employee_id ORDER BY r.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $per_page, $offset);
    $stmt->execute();
    $reports = $stmt->get_result();
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching reports: " . htmlspecialchars($e->getMessage());
}
?>

<div class="container-fluid">
    <h2 class="mb-4">Reports</h2>
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
            <i class="bi bi-bar-chart"></i> Reported Problems
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee</th>
                        <th>Description</th>
                        <th>Reported On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($reports && $reports->num_rows > 0) {
                        while ($report = $reports->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($report['id']) . "</td>";
                            echo "<td>" . htmlspecialchars($report['first_name'] . " " . $report['last_name'] . " (" . $report['employee_id'] . ")") . "</td>";
                            echo "<td>" . htmlspecialchars($report['description']) . "</td>";
                            echo "<td>" . htmlspecialchars($report['created_at']) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>No reports found.</td></tr>";
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