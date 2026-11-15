<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Handle Form Submissions (Add / Update / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $plantingId = trim($_POST['planting_id'] ?? '');
        $harvestDate = trim($_POST['harvest_date'] ?? '');
        $quantityKg = trim($_POST['quantity_kg'] ?? '');
        $sellingPricePerKg = trim($_POST['selling_price_per_kg'] ?? '');
        $qualityGrade = trim($_POST['quality_grade'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if (empty($plantingId) || empty($harvestDate) || empty($quantityKg)) {
            $error = "Planting ID, Harvest Date, and Quantity (kg) are required fields.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO harvest (planting_id, harvest_date, quantity_kg, selling_price_per_kg, quality_grade, remarks) VALUES (:planting_id, :harvest_date, :quantity_kg, :selling_price_per_kg, :quality_grade, :remarks)");
                $stmt->execute([
                    'planting_id' => $plantingId,
                    'harvest_date' => $harvestDate,
                    'quantity_kg' => $quantityKg,
                    'selling_price_per_kg' => $sellingPricePerKg !== '' ? $sellingPricePerKg : null,
                    'quality_grade' => $qualityGrade,
                    'remarks' => $remarks
                ]);

                // Realign sequence counter so IDs increment cleanly without gaps/jumping
                $db->query("SELECT setval('harvest_harvest_id_seq', COALESCE((SELECT MAX(harvest_id) FROM harvest), 0) + 1, false);");

                $message = "Harvest record added successfully!";
            } catch (PDOException $e) {
                $error = "Error adding harvest record: " . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $harvestId = $_POST['harvest_id'] ?? '';
        $plantingId = trim($_POST['planting_id'] ?? '');
        $harvestDate = trim($_POST['harvest_date'] ?? '');
        $quantityKg = trim($_POST['quantity_kg'] ?? '');
        $sellingPricePerKg = trim($_POST['selling_price_per_kg'] ?? '');
        $qualityGrade = trim($_POST['quality_grade'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if (empty($harvestId) || empty($plantingId) || empty($harvestDate) || empty($quantityKg)) {
            $error = "Invalid data for update.";
        } else {
            try {
                $stmt = $db->prepare("UPDATE harvest SET planting_id = :planting_id, harvest_date = :harvest_date, quantity_kg = :quantity_kg, selling_price_per_kg = :selling_price_per_kg, quality_grade = :quality_grade, remarks = :remarks WHERE harvest_id = :harvest_id");
                $stmt->execute([
                    'harvest_id' => $harvestId,
                    'planting_id' => $plantingId,
                    'harvest_date' => $harvestDate,
                    'quantity_kg' => $quantityKg,
                    'selling_price_per_kg' => $sellingPricePerKg !== '' ? $sellingPricePerKg : null,
                    'quality_grade' => $qualityGrade,
                    'remarks' => $remarks
                ]);
                $message = "Harvest record updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating harvest record: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $harvestId = $_POST['harvest_id'] ?? '';
        if (!empty($harvestId)) {
            try {
                $stmt = $db->prepare("DELETE FROM harvest WHERE harvest_id = :harvest_id");
                $stmt->execute(['harvest_id' => $harvestId]);

                // Realign sequence counter after deletion
                $db->query("SELECT setval('harvest_harvest_id_seq', COALESCE((SELECT MAX(harvest_id) FROM harvest), 0) + 1, false);");

                $message = "Harvest record deleted successfully!";
            } catch (PDOException $e) {
                $error = "Cannot delete harvest record: " . $e->getMessage();
            }
        }
    }
}

// Search, Sort, and Pagination Parameters
$search = trim($_GET['search'] ?? '');
$gradeFilter = trim($_GET['grade_filter'] ?? '');
$sort = $_GET['sort'] ?? 'harvest_id';
$order = $_GET['order'] ?? 'ASC';

// Allowed sorting columns to prevent SQL injection
$allowedSorts = ['harvest_id', 'harvest_date', 'quantity_kg', 'selling_price_per_kg', 'quality_grade'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'harvest_id';
}
$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    // Fetch plantings list for dropdown selections with joined farm and crop names
    $plantingsList = $db->query("SELECT p.planting_id, f.farm_name, c.crop_name, p.planting_date FROM planting p LEFT JOIN farm f ON p.farm_id = f.farm_id LEFT JOIN crop c ON p.crop_id = c.crop_id ORDER BY p.planting_id DESC")->fetchAll(PDO::FETCH_ASSOC);

    // Build Base Query with Search and Quality Grade Filters
    $whereClauses = [];
    $params = [];

    if (!empty($search)) {
        $whereClauses[] = "(f.farm_name ILIKE :search OR c.crop_name ILIKE :search OR h.quality_grade ILIKE :search OR h.remarks ILIKE :search)";
        $params['search'] = "%$search%";
    }

    if (!empty($gradeFilter)) {
        $whereClauses[] = "h.quality_grade = :grade_filter";
        $params['grade_filter'] = $gradeFilter;
    }

    $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

    // Get Total Count for Pagination
    $countQuery = "SELECT COUNT(*) FROM harvest h LEFT JOIN planting p ON h.planting_id = p.planting_id LEFT JOIN farm f ON p.farm_id = f.farm_id LEFT JOIN crop c ON p.crop_id = c.crop_id $whereSql";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);
    if ($totalPages < 1) $totalPages = 1;

    // Fetch Paginated Records with JOINs
    $query = "SELECT h.*, p.planting_date, f.farm_name, c.crop_name, c.variety FROM harvest h LEFT JOIN planting p ON h.planting_id = p.planting_id LEFT JOIN farm f ON p.farm_id = f.farm_id LEFT JOIN crop c ON p.crop_id = c.crop_id $whereSql ORDER BY h.$sort $order LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $val) {
        $stmt->bindValue(":$key", $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $harvests = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error fetching harvests: " . $e->getMessage();
    $harvests = [];
    $plantingsList = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Harvest Records - AgriInfo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../css/style.css?v=2.0" rel="stylesheet">
</head>
<body>

<!-- Navbar Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../dashboard.php">🌱 AgriInfo</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="farmers.php">Farmers</a></li>
                <li class="nav-item"><a class="nav-link" href="farms.php">Farms</a></li>
                <li class="nav-item"><a class="nav-link" href="crops.php">Crops</a></li>
                <li class="nav-item"><a class="nav-link" href="plantings.php">Plantings</a></li>
                <li class="nav-item"><a class="nav-link active fw-semibold" href="harvests.php">Harvests</a></li>
                <li class="nav-item"><a class="nav-link" href="../reports.php">Reports</a></li>
            </ul>
            <span class="navbar-text text-white me-3 small">
                👤 <?= htmlspecialchars($_SESSION['username'] ?? '') ?> (<?= htmlspecialchars($_SESSION['role'] ?? '') ?>)
            </span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm px-3 rounded-pill">Logout</a>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-custom-title mb-0">Harvest Records</h2>
        <button class="btn btn-custom px-4 shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#addHarvestModal">+ New Harvest Record</button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success shadow-sm"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Search & Filter Bar -->
    <div class="card shadow-sm mb-4 p-3">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search by farm, crop, quality grade, remarks..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-custom px-4" type="submit">Search</button>
                </div>
            </div>
            <div class="col-md-4">
                <select name="grade_filter" class="form-select" onchange="this.form.submit()">
                    <option value="">Filter by Quality Grade (All)</option>
                    <option value="Grade A" <?= ($gradeFilter === 'Grade A') ? 'selected' : '' ?>>Grade A</option>
                    <option value="Grade B" <?= ($gradeFilter === 'Grade B') ? 'selected' : '' ?>>Grade B</option>
                    <option value="Grade C" <?= ($gradeFilter === 'Grade C') ? 'selected' : '' ?>>Grade C</option>
                </select>
            </div>
            <div class="col-md-2 text-md-end">
                <?php if (!empty($search) || !empty($gradeFilter)): ?>
                    <a href="harvests.php" class="btn btn-outline-secondary w-100">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Harvests Table Card -->
    <div class="card shadow-sm">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">
                            <a href="?search=<?= urlencode($search) ?>&grade_filter=<?= urlencode($gradeFilter) ?>&sort=harvest_id&order=<?= ($sort === 'harvest_id' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                ID <?= $sort === 'harvest_id' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>Farm & Crop</th>
                        <th>
                            <a href="?search=<?= urlencode($search) ?>&grade_filter=<?= urlencode($gradeFilter) ?>&sort=harvest_date&order=<?= ($sort === 'harvest_date' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Harvest Date <?= $sort === 'harvest_date' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="?search=<?= urlencode($search) ?>&grade_filter=<?= urlencode($gradeFilter) ?>&sort=quantity_kg&order=<?= ($sort === 'quantity_kg' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Quantity (kg) <?= $sort === 'quantity_kg' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="?search=<?= urlencode($search) ?>&grade_filter=<?= urlencode($gradeFilter) ?>&sort=selling_price_per_kg&order=<?= ($sort === 'selling_price_per_kg' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Price/kg (₱) <?= $sort === 'selling_price_per_kg' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>Total Value (₱)</th>
                        <th>
                            <a href="?search=<?= urlencode($search) ?>&grade_filter=<?= urlencode($gradeFilter) ?>&sort=quality_grade&order=<?= ($sort === 'quality_grade' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Grade <?= $sort === 'quality_grade' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>Remarks</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($harvests)): ?>
                        <?php foreach ($harvests as $row): ?>
                            <?php 
                                $qty = (float)($row['quantity_kg'] ?? 0);
                                $price = (float)($row['selling_price_per_kg'] ?? 0);
                                $totalVal = $qty * $price;
                            ?>
                            <tr>
                                <td class="ps-4 fw-semibold text-muted">#<?= htmlspecialchars($row['harvest_id'] ?? '') ?></td>
                                <td>
                                    <div class="fw-medium"><?= htmlspecialchars($row['farm_name'] ?? 'Unknown Farm') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars(($row['crop_name'] ?? '') . ' (' . ($row['variety'] ?? 'N/A') . ')') ?></small>
                                </td>
                                <td><?= htmlspecialchars($row['harvest_date'] ?? 'N/A') ?></td>
                                <td class="fw-semibold"><?= number_format($qty, 2) ?> kg</td>
                                <td>₱<?= number_format($price, 2) ?></td>
                                <td class="text-success fw-bold">₱<?= number_format($totalVal, 2) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <?= htmlspecialchars($row['quality_grade'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars($row['remarks'] ?? 'N/A') ?></td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <button class="btn btn-sm btn-outline-primary px-3 rounded-pill" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editHarvestModal<?= $row['harvest_id'] ?>">Edit</button>
                                        <button class="btn btn-sm btn-outline-danger px-3 rounded-pill" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteHarvestModal<?= $row['harvest_id'] ?>">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No harvest records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Footer -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white py-3 d-flex justify-content-center">
                <nav>
                    <ul class="pagination mb-0">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?search=<?= urlencode($search) ?>&grade_filter=<?= urlencode($gradeFilter) ?>&sort=<?= $sort ?>&order=<?= $order ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit and Delete Modals placed OUTSIDE the table loop -->
<?php if (!empty($harvests)): ?>
    <?php foreach ($harvests as $row): ?>
        <!-- Edit Modal -->
        <div class="modal fade" id="editHarvestModal<?= $row['harvest_id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content border-0 shadow">
                    <form method="POST">
                        <div class="modal-header" style="background-color: #fff0f3;">
                            <h5 class="modal-title fw-bold text-custom-title">Edit Harvest Record</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-start">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="harvest_id" value="<?= $row['harvest_id'] ?>">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Planting Record</label>
                                <select name="planting_id" class="form-select" required>
                                    <option value="">Select Planting</option>
                                    <?php foreach ($plantingsList as $p): ?>
                                        <option value="<?= $p['planting_id'] ?>" <?= ($row['planting_id'] == $p['planting_id']) ? 'selected' : '' ?>>
                                            [ID: #<?= $p['planting_id'] ?>] <?= htmlspecialchars($p['farm_name'] . ' - ' . $p['crop_name'] . ' (' . $p['planting_date'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">Harvest Date</label>
                                    <input type="date" name="harvest_date" class="form-control" value="<?= htmlspecialchars($row['harvest_date'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">Quantity (kg)</label>
                                    <input type="number" step="0.01" name="quantity_kg" class="form-control" value="<?= htmlspecialchars($row['quantity_kg'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">Selling Price per kg (₱)</label>
                                    <input type="number" step="0.01" name="selling_price_per_kg" class="form-control" value="<?= htmlspecialchars($row['selling_price_per_kg'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">Quality Grade</label>
                                    <select name="quality_grade" class="form-select">
                                        <option value="">Select Grade</option>
                                        <option value="Grade A" <?= ($row['quality_grade'] === 'Grade A') ? 'selected' : '' ?>>Grade A</option>
                                        <option value="Grade B" <?= ($row['quality_grade'] === 'Grade B') ? 'selected' : '' ?>>Grade B</option>
                                        <option value="Grade C" <?= ($row['quality_grade'] === 'Grade C') ? 'selected' : '' ?>>Grade C</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="2"><?= htmlspecialchars($row['remarks'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-custom rounded-pill px-4">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div class="modal fade" id="deleteHarvestModal<?= $row['harvest_id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form method="POST">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title fw-bold">Confirm Delete</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-start py-4">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="harvest_id" value="<?= $row['harvest_id'] ?>">
                            <p class="mb-0">Are you sure you want to delete harvest record <strong>#<?= $row['harvest_id'] ?></strong>?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger rounded-pill px-4">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Add Harvest Modal -->
<div class="modal fade" id="addHarvestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <div class="modal-header" style="background-color: #fff0f3;">
                    <h5 class="modal-title fw-bold text-custom-title">New Harvest Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Planting Record</label>
                        <select name="planting_id" class="form-select" required>
                            <option value="">Select Planting</option>
                            <?php foreach ($plantingsList as $p): ?>
                                <option value="<?= $p['planting_id'] ?>">
                                    [ID: #<?= $p['planting_id'] ?>] <?= htmlspecialchars($p['farm_name'] . ' - ' . $p['crop_name'] . ' (' . $p['planting_date'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Harvest Date</label>
                            <input type="date" name="harvest_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Quantity (kg)</label>
                            <input type="number" step="0.01" name="quantity_kg" class="form-control" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Selling Price per kg (₱)</label>
                            <input type="number" step="0.01" name="selling_price_per_kg" class="form-control" placeholder="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Quality Grade</label>
                            <select name="quality_grade" class="form-select">
                                <option value="">Select Grade</option>
                                <option value="Grade A">Grade A</option>
                                <option value="Grade B">Grade B</option>
                                <option value="Grade C">Grade C</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="e.g., High quality harvest"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-custom rounded-pill px-4">Add Harvest</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>