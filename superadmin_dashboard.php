<?php
session_start();
$page_title = "Super Admin Dashboard";
require_once 'header.php';

if ($_SESSION['role'] != 'Super Admin') {
    header("Location: login.php");
    exit();
}
require_once 'includes/db.php';

// Fetch summary data for dashboard
$summary = [];
try {
    $stmt = $conn->prepare("SELECT 
        (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
        (SELECT COUNT(*) FROM users WHERE status = 'pending') as pending_users,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE status = 'inactive') as inactive_users");
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching summary data: " . htmlspecialchars($e->getMessage());
}

// Fetch analytics data
$analytics_data = [];
try {
    $stmt = $conn->prepare("SELECT 
        DATE_FORMAT(created_at, '%b %Y') as month,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count
        FROM users 
        GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
        ORDER BY created_at ASC LIMIT 6");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $analytics_data[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching analytics data: " . htmlspecialchars($e->getMessage());
}

?>

<div class="container-fluid">
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
    <h2 class="mb-4">Super Admin Dashboard</h2>
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-header bg-primary-green text-white">
                    <i class="bi bi-people-fill"></i> User Summary
                </div>
                <div class="card-body text-center">
                    <p>Total Users: <?php echo htmlspecialchars($summary['total_users'] ?? 0); ?></p>
                    <p>Active: <?php echo htmlspecialchars($summary['active_users'] ?? 0); ?></p>
                    <p>Pending: <?php echo htmlspecialchars($summary['pending_users'] ?? 0); ?></p>
                    <p>Inactive: <?php echo htmlspecialchars($summary['inactive_users'] ?? 0); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-9 mb-4">
            <div class="card">
                <div class="card-header bg-primary-green text-white">
                    <i class="bi bi-bar-chart-fill"></i> Analytics
                </div>
                <div class="card-body">
                    <canvas id="userChart" width="600" height="400"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary-green text-white">
                    <i class="bi bi-file-earmark-text"></i> Reports
                </div>
                <div class="card-body">
                    <p>Generate reports on user activity and system usage.</p>
                    <form method="POST" action="generate_report.php" target="_blank">
                        <div class="mb-3">
                            <label for="report_type" class="form-label">Report Type</label>
                            <select class="form-control" id="report_type" name="report_type" required>
                                <option value="user_activity">User Activity</option>
                                <option value="system_usage">System Usage</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Download Report</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary-green text-white">
                    <i class="bi bi-journal-text"></i> Audit Logs
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
                            try {
                                $stmt = $conn->prepare("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 5");
                                $stmt->execute();
                                $logs = $stmt->get_result();
                                while ($log = $logs->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($log['created_at']) . "</td>";
                                    echo "<td>" . htmlspecialchars($log['employee_id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($log['action']) . "</td>";
                                    echo "<td>" . htmlspecialchars($log['details']) . "</td>";
                                    echo "</tr>";
                                }
                                $stmt->close();
                            } catch (Exception $e) {
                                echo "<tr><td colspan='4'>Error loading logs: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <a href="audit_logs.php" class="btn btn-primary">View All Logs</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/scripts.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('userChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($analytics_data, 'month')); ?>,
                datasets: [
                    {
                        label: 'Active Users',
                        data: <?php echo json_encode(array_column($analytics_data, 'active_count')); ?>,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Pending Users',
                        data: <?php echo json_encode(array_column($analytics_data, 'pending_count')); ?>,
                        backgroundColor: 'rgba(255, 193, 7, 0.7)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Inactive Users',
                        data: <?php echo json_encode(array_column($analytics_data, 'inactive_count')); ?>,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0 // Forces whole numbers
                        }
                    }
                },
                animation: { duration: 1000, easing: 'easeInOutQuad' },
                plugins: { legend: { position: 'top' } },
                maintainAspectRatio: false // Allows custom size without distortion
            }
        });
    });
</script>
</body>
</html>
<?php $conn->close(); ?>