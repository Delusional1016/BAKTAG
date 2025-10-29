<?php
session_start();
require_once 'includes/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure user is Admin or Super Admin
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// Handle CRUD actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['confirmed']) || $_POST['confirmed'] !== 'true') {
        $_SESSION['error'] = "Please confirm the action.";
        header("Location: manage_products.php");
        exit();
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $name = trim($_POST['name']);
            $code = trim($_POST['code']); // Use the generated barcode
            $description = trim($_POST['description']);
            $category = trim($_POST['category']);
            $created_by = $_SESSION['user_id'];

            if (empty($name) || empty($code)) {
                $_SESSION['error'] = "Name and code are required.";
            } else {
                $stmt = $conn->prepare("INSERT INTO products (name, code, description, category, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssi", $name, $code, $description, $category, $created_by);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Product '$name' added successfully with barcode $code.";
                } else {
                    $_SESSION['error'] = "Failed to add product.";
                }
                $stmt->close();
            }
        } elseif ($_POST['action'] == 'update') {
            $product_id = intval($_POST['product_id']);
            $name = trim($_POST['name']);
            $code = trim($_POST['code']);
            $description = trim($_POST['description']);
            $category = trim($_POST['category']);

            $stmt = $conn->prepare("UPDATE products SET name = ?, code = ?, description = ?, category = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $code, $description, $category, $product_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Product updated successfully.";
            } else {
                $_SESSION['error'] = "Failed to update product.";
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'delete') {
            $product_id = intval($_POST['product_id']);
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->bind_param("i", $product_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Product deleted successfully.";
            } else {
                $_SESSION['error'] = "Failed to delete product.";
            }
            $stmt->close();
        }
        header("Location: manage_products.php");
        exit();
    }
}

$page_title = "Manage Products";
require_once 'header.php';

// Fetch all products
$products = [];
try {
    $stmt = $conn->prepare("SELECT p.id, p.name, p.code, p.description, p.category, u.first_name AS creator FROM products p LEFT JOIN users u ON p.created_by = u.id ORDER BY p.created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching products: " . htmlspecialchars($e->getMessage());
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
    <h2 class="mb-4">Manage Products</h2>
    <div class="card mb-4">
        <div class="card-header bg-primary-green text-white">
            <i class="bi bi-plus-circle"></i> Add New Product
        </div>
        <div class="card-body">
            <form method="POST" onsubmit="return confirm('Are you sure you want to add this product?');">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="confirmed" value="true">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required oninput="generateBarcode()">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="code" class="form-label">Code (Barcode)</label>
                        <input type="text" class="form-control" id="code" name="code" required readonly>
                        <div id="barcodeDisplay" class="mt-2"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description"></textarea>
                </div>
                <div class="mb-3">
                    <label for="category" class="form-label">Category</label>
                    <input type="text" class="form-control" id="category" name="category">
                </div>
                <button type="submit" class="btn btn-primary">Add Product</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header bg-primary-green text-white">
            <i class="bi bi-tags-fill"></i> Product List
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="7">No products found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['id']); ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['code']); ?></td>
                                <td><?php echo htmlspecialchars($product['description']); ?></td>
                                <td><?php echo htmlspecialchars($product['category']); ?></td>
                                <td><?php echo htmlspecialchars($product['creator']); ?></td>
                                <td>
                                    <!-- Update form -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $product['id']; ?>"><i class="bi bi-pencil"></i> Edit</button>
                                    </form>
                                    <!-- Delete form -->
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <input type="hidden" name="confirmed" value="true">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Delete</button>
                                    </form>

                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $product['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editModalLabel">Edit Product</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <form method="POST" onsubmit="return confirm('Are you sure you want to update this product?');">
                                                        <input type="hidden" name="action" value="update">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <input type="hidden" name="confirmed" value="true">
                                                        <div class="mb-3">
                                                            <label for="name" class="form-label">Name</label>
                                                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="code" class="form-label">Code</label>
                                                            <input type="text" class="form-control" name="code" value="<?php echo htmlspecialchars($product['code']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="description" class="form-label">Description</label>
                                                            <textarea class="form-control" name="description"><?php echo htmlspecialchars($product['description']); ?></textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="category" class="form-label">Category</label>
                                                            <input type="text" class="form-control" name="category" value="<?php echo htmlspecialchars($product['category']); ?>">
                                                        </div>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="assets/js/scripts.js"></script>
</body>
</html>
<?php $conn->close(); ?>