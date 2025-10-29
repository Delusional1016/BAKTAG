<?php
session_start();
require_once 'includes/db.php';

ob_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Employee') {
    header("Location: login.php");
    exit();
}

$page_title = "Create Label";
require_once 'header.php';

$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);
$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

// Fetch products
$products = [];
try {
    $stmt = $conn->prepare("SELECT id, ean_code, name, net_weight_lbs, net_weight_kg, code_date FROM products ORDER BY name ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $error = "Error fetching products: " . $e->getMessage();
}

$label_data = [
    'product_name' => 'Product Name',
    'ean_code' => '0123456789012',
    'code_date' => 'MM/DD/YYYY',
    'net_weight' => '0 lbs / 0 kg',
    'manufacturer' => 'Franklin Baker, 123 Coconut St, Manila, Philippines',
    'ingredients' => 'Coconut, Sugar',
    'country_of_origin' => 'Philippines',
    'bag_no' => '0001'
];

// Paths
$TEMPLATE_PATH = __DIR__ . '/templates/label_template.odt';
$OUTPUT_DIR = __DIR__ . '/output/';
if (!is_dir($OUTPUT_DIR)) mkdir($OUTPUT_DIR, 0755, true);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'select_product') {
        $product_id = intval($_POST['product_id']);
        if ($product_id > 0) {
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($product) {
                $label_data = [
                    'product_name' => $product['name'],
                    'ean_code' => $product['ean_code'],
                    'code_date' => $product['code_date'],
                    'net_weight' => $product['net_weight_lbs'] . ' lbs / ' . $product['net_weight_kg'] . ' kg',
                    'manufacturer' => 'Franklin Baker, 123 Coconut St, Manila, Philippines',
                    'ingredients' => 'Coconut, Sugar',
                    'country_of_origin' => 'Philippines',
                    'bag_no' => '0001'
                ];
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'search_ean') {
        $ean_code = preg_replace('/\D/', '', trim($_POST['ean_code']));
        if (strlen($ean_code) == 13) {
            $stmt = $conn->prepare("SELECT * FROM products WHERE ean_code = ?");
            $stmt->bind_param("s", $ean_code);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($product) {
                $label_data = [
                    'product_name' => $product['name'],
                    'ean_code' => $product['ean_code'],
                    'code_date' => $product['code_date'],
                    'net_weight' => $product['net_weight_lbs'] . ' lbs / ' . $product['net_weight_kg'] . ' kg',
                    'manufacturer' => 'Franklin Baker, 123 Coconut St, Manila, Philippines',
                    'ingredients' => 'Coconut, Sugar',
                    'country_of_origin' => 'Philippines',
                    'bag_no' => '0001'
                ];
            } else {
                $error = "No product found for EAN: $ean_code";
            }
        } else {
            $error = "EAN must be 13 digits.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'generate_labels') {
        $from_bag = str_pad($_POST['from_bag_no'], 4, '0', STR_PAD_LEFT);
        $to_bag = str_pad($_POST['to_bag_no'], 4, '0', STR_PAD_LEFT);

        if ($from_bag > $to_bag) {
            $error = "From Bag # must be less than or equal to To Bag #.";
        } else {
            $generated_files = [];
            for ($bag = (int)$from_bag; $bag <= (int)$to_bag; $bag++) {
                $current_bag_no = str_pad($bag, 4, '0', STR_PAD_LEFT);
                $current_data = $label_data;
                $current_data['bag_no'] = $current_bag_no;

                $filename = "label_bag_{$current_bag_no}_" . time() . ".odt";
                $output_path = $OUTPUT_DIR . $filename;

                if (generateODTLabel($TEMPLATE_PATH, $output_path, $current_data)) {
                    $generated_files[] = $output_path;
                    saveToDatabase($current_data['ean_code'], $current_data['code_date'], $current_data['net_weight'], $current_bag_no);
                }
            }

            if (!empty($generated_files)) {
                $merged_file = mergeODTLabels($generated_files);
                $success = "Labels generated: <a href='download.php?file=" . basename($merged_file) . "' target='_blank'>Download Merged Label (ODT)</a>";
                if (count($generated_files) === 1) {
                    $success .= " | <a href='download.php?file=" . basename($generated_files[0]) . "' target='_blank'>Download Single</a>";
                }
                // Optional: Auto-open in LibreOffice
                openInLibreOffice($merged_file);
            } else {
                $error = "Failed to generate labels.";
            }
        }
    }
}

// --- HELPER FUNCTIONS ---

function generateODTLabel($template, $output, $data) {
    if (!file_exists($template)) return false;

    $zip = new ZipArchive();
    if ($zip->open($template) !== TRUE) return false;

    $content = $zip->getFromName('content.xml');
    $zip->close();

    // Replace placeholders
    foreach ($data as $key => $value) {
        $placeholder = '{{' . strtoupper($key) . '}}';
        $content = str_replace($placeholder, htmlspecialchars($value), $content);
    }

    // Save new ODT
    $temp_dir = sys_get_temp_dir() . '/label_' . uniqid();
    mkdir($temp_dir, 0777, true);
    copy($template, $temp_dir . '/template.odt');

    $new_zip = new ZipArchive();
    if ($new_zip->open($temp_dir . '/template.odt') === TRUE) {
        $new_zip->addFromString('content.xml', $content);
        $new_zip->close();
        return rename($temp_dir . '/template.odt', $output);
    }
    return false;
}

function mergeODTLabels($files) {
    $merged_content = '<?xml version="1.0" encoding="UTF-8"?>';
    $merged_content .= '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.0">';
    $merged_content .= '<office:body><office:text>';

    foreach ($files as $file) {
        $zip = new ZipArchive();
        if ($zip->open($file) === TRUE) {
            $content = $zip->getFromName('content.xml');
            preg_match('/<office:text>.*<\/office:text>/s', $content, $matches);
            if (!empty($matches[0])) {
                $merged_content .= preg_replace('/<\/?office:text>/', '', $matches[0]);
            }
            $zip->close();
        }
    }

    $merged_content .= '</office:text></office:body></office:document-content>';

    $merged_file = __DIR__ . '/output/merged_labels_' . date('Ymd_His') . '.odt';
    file_put_contents($merged_file, $merged_content);
    return $merged_file;
}

function openInLibreOffice($file) {
    $cmd = escapeshellcmd("libreoffice --headless --norestore --nofirststartwizard " . escapeshellarg($file)) . " > /dev/null 2>&1 &";
    shell_exec($cmd);
}

function saveToDatabase($ean_code, $code_date, $net_weight, $bag_no) {
    global $conn;
    $net_weight_lbs = floatval(explode(' lbs', explode(' / ', $net_weight)[0])[0]);
    $net_weight_kg = floatval(explode(' kg', explode(' / ', $net_weight)[1])[0]);
    $stmt = $conn->prepare("INSERT INTO printed_labels (ean_code, code_date, net_weight_lbs, net_weight_kg, bag_no, created_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE bag_no = VALUES(bag_no)");
    $stmt->bind_param("ssdds", $ean_code, $code_date, $net_weight_lbs, $net_weight_kg, $bag_no);
    $stmt->execute();
    $stmt->close();
}
?>

<div class="container-fluid">
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <h2 class="mb-4">Create Label (LibreOffice)</h2>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary-green text-white">
                    Label Creation
                </div>
                <div class="card-body">
                    <form method="POST" id="labelForm">
                        <input type="hidden" name="action" value="generate_labels">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Select Product</label>
                                <select class="form-control" name="product_id" onchange="this.form.submit(); this.form.action.value='select_product'; this.form.submit();">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Search by EAN</label>
                                <input type="text" class="form-control" name="ean_code" placeholder="13 digits" pattern="\d{13}" onchange="this.form.action.value='search_ean'; this.form.submit();">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Bag # From</label>
                                <input type="number" class="form-control" name="from_bag_no" min="1" value="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Bag # To</label>
                                <input type="number" class="form-control" name="to_bag_no" min="1" value="1" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success">
                            Generate Labels (ODT)
                        </button>
                    </form>

                    <hr>

                    <h5>Current Label Data</h5>
                    <table class="table table-sm">
                        <tr><td><strong>Product:</strong></td><td><?= htmlspecialchars($label_data['product_name']) ?></td></tr>
                        <tr><td><strong>EAN:</strong></td><td><?= htmlspecialchars($label_data['ean_code']) ?></td></tr>
                        <tr><td><strong>Code Date:</strong></td><td><?= htmlspecialchars($label_data['code_date']) ?></td></tr>
                        <tr><td><strong>Net Weight:</strong></td><td><?= htmlspecialchars($label_data['net_weight']) ?></td></tr>
                        <tr><td><strong>Bag No:</strong></td><td><?= htmlspecialchars($label_data['bag_no']) ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
ob_end_flush();
$conn->close();
?>