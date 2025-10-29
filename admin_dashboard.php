<?php
session_start();
require_once 'header.php';

// Ensure user is Admin or Super Admin
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Super Admin')) {
    header("Location: login.php");
    exit();
}

// Ensure database connection is available
if (!isset($conn)) {
    die("Database connection failed. Please check includes/db.php.");
}

$page_title = "Admin Dashboard";
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
    <h2 class="mb-4">Admin Dashboard</h2>
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary-green text-white">
                    <i class="bi bi-people-fill"></i> Pending User Approvals
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $conn->prepare("SELECT id, employee_id, first_name, last_name FROM users WHERE status = 'pending'");
                        if (!$stmt) {
                            throw new Exception("Query preparation failed: " . $conn->error);
                        }
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            echo '<table class="table table-striped">';
                            echo '<thead><tr><th>Employee ID</th><th>Name</th><th>Action</th></tr></thead><tbody>';
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr><td>" . htmlspecialchars($row['employee_id']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
                                echo "<td><form method='POST' action='approve_user.php?id=" . $row['id'] . "' style='display:inline;'>";
                                echo "<input type='hidden' name='confirmed' value='true'>";
                                echo "<a href='#' class='btn btn-sm btn-success' onclick='if(confirm(\"Are you sure you want to approve this user?\")) this.closest(\"form\").submit();'><i class='bi bi-check-circle'></i> Approve</a>";
                                echo "</form></td></tr>";
                            }
                            echo '</tbody></table>';
                        } else {
                            echo '<p>No pending approvals.</p>';
                        }
                        $stmt->close();
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary-green text-white">
                    <i class="bi bi-tags-fill"></i> Recent Labels
                </div>
                <div class="card-body">
                    <p>View all labels created by employees.</p>
                    <a href="label_oversight.php" class="btn btn-primary"><i class="bi bi-eye"></i> View Labels</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/scripts.js"></script>
</body>
</html>
<?php $conn->close(); ?>