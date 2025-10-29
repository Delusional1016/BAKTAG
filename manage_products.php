<?php
session_start();
require_once 'includes/db.php';

// Restrict to Admin & Super Admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    header("Location: login.php");
    exit();
}

$is_super_admin = ($_SESSION['role'] === 'Super Admin');

// === HANDLE CRUD ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['confirmed']) || $_POST['confirmed'] !== 'true') {
        $_SESSION['error'] = "Action not confirmed.";
        header("Location: manage_products.php");
        exit();
    }

    $action = $_POST['action'] ?? '';

    // ADD PRODUCT
    if ($action === 'add') {
        $ean_code = trim($_POST['ean_code']);
        $name = trim($_POST['name']);
        $net_weight_lbs = floatval($_POST['net_weight_lbs']);
        $net_weight_kg = round($net_weight_lbs * 0.453592, 2);
        $code_date = $_POST['code_date'] ?? '';
        $manufacturer = trim($_POST['manufacturer'] ?? 'Franklin Baker');
        $ingredients = trim($_POST['ingredients'] ?? 'Coconut, Sugar');
        $country = trim($_POST['country'] ?? 'Philippines');

        if (!preg_match('/^\d{13}$/', $ean_code)) {
            $_SESSION['error'] = "EAN must be exactly 13 digits.";
        } elseif (empty($name)) {
            $_SESSION['error'] = "Product name is required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO products (ean_code, name, net_weight_lbs, net_weight_kg, code_date, manufacturer, ingredients, country_of_origin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssddssss", $ean_code, $name, $net_weight_lbs, $net_weight_kg, $code_date, $manufacturer, $ingredients, $country);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Product '$name' added.";
            } else {
                $_SESSION['error'] = "EAN already exists.";
            }
            $stmt->close();
        }

    // UPDATE PRODUCT
    } elseif ($action === 'update') {
        $id = intval($_POST['product_id']);
        $ean_code = trim($_POST['ean_code']);
        $name = trim($_POST['name']);
        $net_weight_lbs = floatval($_POST['net_weight_lbs']);
        $net_weight_kg = round($net_weight_lbs * 0.453592, 2);
        $code_date = $_POST['code_date'] ?? '';
        $manufacturer = trim($_POST['manufacturer'] ?? '');
        $ingredients = trim($_POST['ingredients'] ?? '');
        $country = trim($_POST['country'] ?? '');

        if (!preg_match('/^\d{13}$/', $ean_code)) {
            $_SESSION['error'] = "EAN must be 13 digits.";
        } else {
            $stmt = $conn->prepare("UPDATE products SET ean_code = ?, name = ?, net_weight_lbs = ?, net_weight_kg = ?, code_date = ?, manufacturer = ?, ingredients = ?, country_of_origin = ? WHERE id = ?");
            $stmt->bind_param("ssddssssi", $ean_code, $name, $net_weight_lbs, $net_weight_kg, $code_date, $manufacturer, $ingredients, $country, $id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Product updated.";
            } else {
                $_SESSION['error'] = "Failed to update.";
            }
            $stmt->close();
        }

    // DELETE PRODUCT
    } elseif ($action === 'delete') {
        $id = intval($_POST['product_id']);
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Product deleted.";
        } else {
            $_SESSION['error'] = "Cannot delete: used in labels?";
        }
        $stmt->close();
    }

    header("Location: manage_products.php");
    exit();
}

$page_title = "Manage Products";
require_once 'header.php';

// === FETCH ALL PRODUCTS (for JS filtering) ===
$products = [];
$stmt = $conn->prepare("SELECT * FROM products ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();
?>

<div class="container-fluid">
    <!-- Alerts -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <h2 class="mb-4">Manage Products</h2>

    <!-- Add Product Form (Clean & Aligned) -->
    <div class="card mb-4">
        <div class="card-header bg-primary-green text-white">
            <i class="bi bi-plus-circle"></i> Add New Product
        </div>
        <div class="card-body">
            <form method="POST" onsubmit="return confirm('Add this product?');">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="confirmed" value="true">

                <!-- EAN & Name -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">EAN-13 Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ean_code" maxlength="13" required pattern="\d{13}" placeholder="1234567890123">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required placeholder="e.g. Sweetened Coconut">
                    </div>
                </div>

                <!-- Weights & Code Date -->
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Net Weight (lbs) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="net_weight_lbs" required min="0" id="lbs" onchange="convertLbsToKg()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Net Weight (kg)</label>
                        <input type="text" class="form-control" id="kg" readonly placeholder="Auto-calculated">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Code Date</label>
                        <input type="date" class="form-control" name="code_date">
                    </div>
                </div>

                <!-- Manufacturer, Ingredients, Country -->
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Manufacturer</label>
                        <input type="text" class="form-control" name="manufacturer" value="Franklin Baker">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ingredients</label>
                        <input type="text" class="form-control" name="ingredients" value="Coconut, Sugar">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Country of Origin</label>
                        <input type="text" class="form-control" name="country" value="Philippines">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Add Product</button>
            </form>
        </div>
    </div>

    <!-- Product List with Real-time Search -->
    <div class="card">
        <div class="card-header bg-primary-green text-white d-flex justify-content-between align-items-center">
            <div><i class="bi bi-tags-fill"></i> Product List</div>
            <input type="text" id="searchInput" class="form-control form-control-sm w-50" placeholder="Type to search by EAN or Name...">
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="productTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>EAN-13</th>
                            <th>Name</th>
                            <th>Weight (lbs)</th>
                            <th>Weight (kg)</th>
                            <th>Code Date</th>
                            <th>Country</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productBody">
                        <?php foreach ($products as $i => $p): ?>
                            <tr data-ean="<?= htmlspecialchars($p['ean_code']) ?>" data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>">
                                <td><?= $i + 1 ?></td>
                                <td><code><?= htmlspecialchars($p['ean_code']) ?></code></td>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><?= number_format($p['net_weight_lbs'], 2) ?></td>
                                <td><?= number_format($p['net_weight_kg'], 2) ?></td>
                                <td><?= $p['code_date'] === '0000-00-00' ? '-' : $p['code_date'] ?></td>
                                <td><?= htmlspecialchars($p['country_of_origin']) ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $p['id'] ?>">
                                        Edit
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="confirmed" value="true">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==================== EDIT MODALS ==================== -->
    <?php foreach ($products as $p): ?>
    <div class="modal fade" id="editModal<?= $p['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" onsubmit="return confirm('Save changes?');">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="confirmed" value="true">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">EAN-13</label>
                                <input type="text" class="form-control" name="ean_code" value="<?= htmlspecialchars($p['ean_code']) ?>" maxlength="13" required pattern="\d{13}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Name</label>
                                <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($p['name']) ?>" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Weight (lbs)</label>
                                <input type="number" step="0.01" class="form-control" name="net_weight_lbs" value="<?= $p['net_weight_lbs'] ?>" required min="0" onchange="convertLbsToKgModal(this)">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Weight (kg)</label>
                                <input type="text" class="form-control" id="kg<?= $p['id'] ?>" value="<?= $p['net_weight_kg'] ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Code Date</label>
                                <input type="date" class="form-control" name="code_date" value="<?= $p['code_date'] === '0000-00-00' ? '' : $p['code_date'] ?>">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Manufacturer</label>
                                <input type="text" class="form-control" name="manufacturer" value="<?= htmlspecialchars($p['manufacturer']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Ingredients</label>
                                <input type="text" class="form-control" name="ingredients" value="<?= htmlspecialchars($p['ingredients']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" value="<?= htmlspecialchars($p['country_of_origin']) ?>">
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Real-time Search
document.getElementById('searchInput').addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#productBody tr');

    rows.forEach(row => {
        const ean = row.getAttribute('data-ean');
        const name = row.getAttribute('data-name');
        const matches = ean.includes(query) || name.includes(query);
        row.style.display = matches ? '' : 'none';
    });
});

// lbs → kg
function convertLbsToKg() {
    const lbs = document.getElementById('lbs').value;
    document.getElementById('kg').value = lbs ? (lbs * 0.453592).toFixed(2) : '';
}
function convertLbsToKgModal(input) {
    const id = input.closest('form').querySelector('input[name="product_id"]').value;
    const lbs = input.value;
    const kgField = document.getElementById('kg' + id);
    kgField.value = lbs ? (lbs * 0.453592).toFixed(2) : '';
}
</script>


<?php $conn->close(); ?>