<?php
session_start();
$page_title = "Employee Dashboard";
require_once 'header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4">Employee Dashboard</h2>
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary-green text-white">
                    <i class="bi bi-tag-fill"></i> Quick Actions
                </div>
                <div class="card-body">
                    <a href="create_label.php" class="btn btn-primary mb-2"><i class="bi bi-plus-circle"></i> Create New Label</a>
                    <a href="label_history.php" class="btn btn-outline-primary"><i class="bi bi-clock-history"></i> View Label History</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary-green text-white">
                    <i class="bi bi-bell-fill"></i> Notifications
                </div>
                <div class="card-body">
                    <div class="alert alert-info">No new notifications.</div>
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