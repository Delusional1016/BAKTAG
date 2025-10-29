<?php
session_start();
require_once 'includes/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role']!='Admin') { header("Location: login.php"); exit(); }

$page_title = "Admin – Manage Users";
require_once 'header.php';

$stmt = $conn->prepare("SELECT id,employee_id,first_name,last_name,email,status FROM users ORDER BY created_at DESC");
$stmt->execute(); $users = $stmt->get_result(); $stmt->close();
?>
<div class="container-fluid">
    <h2 class="mb-4">Pending Registrations</h2>
    <div class="card">
        <div class="card-header bg-primary-green text-white"><i class="bi bi-people-fill"></i> Users</div>
        <div class="card-body">
            <table class="table table-striped">
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
<?php while($u=$users->fetch_assoc()): ?>
                    <tr>
                        <td><?=htmlspecialchars($u['employee_id'])?></td>
                        <td><?=htmlspecialchars($u['first_name'].' '.$u['last_name'])?></td>
                        <td><?=htmlspecialchars($u['email'])?></td>
                        <td><?=htmlspecialchars($u['status'])?></td>
                        <td>
<?php if($u['status']==='pending'): ?>
                            <form method="POST" action="approve_user.php?id=<?=$u['id']?>" style="display:inline;">
                                <input type="hidden" name="confirmed" value="true">
                                <a href="#" class="btn btn-sm btn-success" onclick="if(confirm('Approve this user?')) this.closest('form').submit();">
                                    <i class="bi bi-check-circle"></i> Approve
                                </a>
                            </form>
                            <form method="POST" action="deny_user.php?id=<?=$u['id']?>" style="display:inline;">
                                <input type="hidden" name="confirmed" value="true">
                                <a href="#" class="btn btn-sm btn-danger" onclick="if(confirm('Deny & delete this user?')) this.closest('form').submit();">
                                    <i class="bi bi-x-circle"></i> Deny
                                </a>
                            </form>
<?php else: ?>
                            <span class="text-muted">—</span>
<?php endif; ?>
                        </td>
                    </tr>
<?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once 'footer.php'; ?>