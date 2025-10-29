<?php
session_start();
require_once 'includes/db.php';

// Restrict to Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$page_title = "Admin – Manage Users";
require_once 'header.php';

// Fetch ONLY pending users
$stmt = $conn->prepare("
    SELECT id, employee_id, first_name, last_name, email 
    FROM users 
    WHERE status = 'pending' 
    ORDER BY created_at DESC
");
$stmt->execute();
$users = $stmt->get_result();
$stmt->close();
?>

<div class="container-fluid">
    <h2 class="mb-4">Pending User Registrations</h2>

    <?php if ($users->num_rows === 0): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No pending registrations.
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header bg-primary-green text-white">
                <i class="bi bi-people-fill"></i> Pending Users
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($u = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($u['employee_id']) ?></strong></td>
                                    <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td class="text-nowrap">
                                        <!-- APPROVE -->
                                        <form method="POST" action="approve_user.php" style="display:inline;" class="d-inline">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="confirmed" value="true">
                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this user?')">
                                                <i class="bi bi-check-circle"></i> Approve
                                            </button>
                                        </form>

                                        <!-- DENY -->
                                        <form method="POST" action="deny_user.php" style="display:inline;" class="d-inline">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="confirmed" value="true">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Deny and delete this user?')">
                                                <i class="bi bi-x-circle"></i> Deny
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

