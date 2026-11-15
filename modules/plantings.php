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
$totalRecords = 0;
$totalPages = 1;
$plantings = [];
$farmsList = [];
$cropsList = [];

// Fetch dropdown lists
try {
    $farmsList = $db->query("SELECT f.farm_id, f.farm_name, fr.first_name, fr.last_name FROM farm f LEFT JOIN farmer fr ON f.farmer_id = fr.farmer_id ORDER BY f.farm_id ASC")->fetchAll();
} catch (PDOException $e) {
    $farmsList = [];
}

try {
    $cropsList = $db->query("SELECT crop_id, crop_name, variety FROM crop ORDER BY crop_name ASC")->fetchAll();
} catch (PDOException $e) {
    $cropsList = [];
}

// Handle Form Submissions (Add / Update / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $farmId = trim($_POST['farm_id'] ?? '');
        $cropId = trim($_POST['crop_id'] ?? '');
        $plantingDate = trim($_POST['planting_date'] ?? '');
        $areaPlanted = trim($_POST['area_planted'] ?? '');
        $seedQuantity = trim($_POST['seed_quantity'] ?? '');
        $expectedHarvestDate = trim($_POST['expected_harvest_date'] ?? '');
        $status = trim($_POST['status'] ?? 'Growing');

        if (empty($farmId) || empty($cropId) || empty($plantingDate)) {
            $error = "Farm, crop, and planting date are required.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO planting (farm_id, crop_id, planting_date, area_planted, seed_quantity, expected_harvest_date, status) VALUES (:farm_id, :crop_id, :planting_date, :area_planted, :seed_quantity, :expected_harvest_date, :status)");
                $stmt->execute([
                    'farm_id' => $farmId,
                    'crop_id' => $cropId,
                    'planting_date' => $plantingDate,
                    'area_planted' => $areaPlanted !== '' ? (float)$areaPlanted : null,
                    'seed_quantity' => $seedQuantity !== '' ? (float)$seedQuantity : null,
                    'expected_harvest_date' => !empty($expectedHarvestDate) ? $expectedHarvestDate : null,
                    'status' => $status
                ]);

                $db->query("SELECT setval('planting_planting_id_seq', COALESCE((SELECT MAX(planting_id) FROM planting), 0) + 1, false);");

                $message = "Planting record added successfully!";
            } catch (PDOException $e) {
                $error = "Error adding planting record: " . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $plantingId = $_POST['planting_id'] ?? '';
        $farmId = trim($_POST['farm_id'] ?? '');
        $cropId = trim($_POST['crop_id'] ?? '');
        $plantingDate = trim($_POST['planting_date'] ?? '');
        $areaPlanted = trim($_POST['area_planted'] ?? '');
        $seedQuantity = trim($_POST['seed_quantity'] ?? '');
        $expectedHarvestDate = trim($_POST['expected_harvest_date'] ?? '');
        $status = trim($_POST['status'] ?? 'Growing');

        if (empty($plantingId) || empty($farmId) || empty($cropId) || empty($plantingDate)) {
            $error = "Invalid data for update.";
        } else {
            try {
                $stmt = $db->prepare("UPDATE planting SET farm_id = :farm_id, crop_id = :crop_id, planting_date = :planting_date, area_planted = :area_planted, seed_quantity = :seed_quantity, expected_harvest_date = :expected_harvest_date, status = :status WHERE planting_id = :planting_id");
                $stmt->execute([
                    'planting_id' => $plantingId,
                    'farm_id' => $farmId,
                    'crop_id' => $cropId,
                    'planting_date' => $plantingDate,
                    'area_planted' => $areaPlanted !== '' ? (float)$areaPlanted : null,
                    'seed_quantity' => $seedQuantity !== '' ? (float)$seedQuantity : null,
                    'expected_harvest_date' => !empty($expectedHarvestDate) ? $expectedHarvestDate : null,
                    'status' => $status
                ]);
                $message = "Planting record updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating planting record: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $plantingId = $_POST['planting_id'] ?? '';
        if (!empty($plantingId)) {
            try {
                $stmt = $db->prepare("DELETE FROM planting WHERE planting_id = :planting_id");
                $stmt->execute(['planting_id' => $plantingId]);

                $db->query("SELECT setval('planting_planting_id_seq', COALESCE((SELECT MAX(planting_id) FROM planting), 0) + 1, false);");

                $message = "Planting record deleted successfully!";
            } catch (PDOException $e) {
                $error = "Cannot delete planting record because it has linked harvest records.";
            }
        }
    }
}

// Search, Sort, and Pagination Parameters
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'planting_id';
$order = $_GET['order'] ?? 'ASC';

$allowedSorts = ['planting_id', 'planting_date', 'area_planted', 'seed_quantity', 'expected_harvest_date', 'status'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'planting_id';
}
$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    $whereClause = "";
    $params = [];

    if (!empty($search)) {
        $whereClause = "WHERE p.status ILIKE :search OR c.crop_name ILIKE :search OR c.variety ILIKE :search OR f.farm_name ILIKE :search OR fr.first_name ILIKE :search OR fr.last_name ILIKE :search";
        $params['search'] = "%$search%";
    }

    // Count query with comprehensive joins matching search fields
    $countQuery = "SELECT COUNT(*) FROM planting p 
                   LEFT JOIN crop c ON p.crop_id = c.crop_id 
                   LEFT JOIN farm f ON p.farm_id = f.farm_id 
                   LEFT JOIN farmer fr ON f.farmer_id = fr.farmer_id 
                   $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);
    if ($totalPages < 1) $totalPages = 1;

    $query = "SELECT p.*, c.crop_name, c.variety, f.farm_name, fr.first_name, fr.last_name 
              FROM planting p 
              LEFT JOIN crop c ON p.crop_id = c.crop_id 
              LEFT JOIN farm f ON p.farm_id = f.farm_id 
              LEFT JOIN farmer fr ON f.farmer_id = fr.farmer_id 
              $whereClause 
              ORDER BY p.$sort $order 
              LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);

    foreach ($params as $key => $val) {
        $stmt->bindValue(":$key", $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $plantings = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error fetching plantings: " . $e->getMessage();
    $plantings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plantings Management - AgriInfo</title>
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
                <li class="nav-item"><a class="nav-link active fw-semibold" href="plantings.php">Plantings</a></li>
                <li class="nav-item"><a class="nav-link" href="harvests.php">Harvests</a></li>
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
        <h2 class="fw-bold text-custom-title mb-0">Plantings Directory</h2>
        <button class="btn btn-custom px-4 shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#addPlantingModal">+ Add New Planting</button>
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
            <div class="col-md-8">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search by crop, farm, farmer, or status..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-custom px-4" type="submit">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="plantings.php" class="btn btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4 text-md-end text-muted small">
                Showing <?= count($plantings) ?> of <?= $totalRecords ?> plantings
            </div>
        </form>
    </div>

    <!-- Plantings Table Card -->
    <div class="card shadow-sm">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">
                            <a href="?search=<?= urlencode($search) ?>&sort=planting_id&order=<?= ($sort === 'planting_id' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                ID <?= $sort === 'planting_id' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>Farm & Farmer</th>
                        <th>Crop & Variety</th>
                        <th>
                            <a href="?search=<?= urlencode($search) ?>&sort=planting_date&order=<?= ($sort === 'planting_date' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Planting Date <?= $sort === 'planting_date' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th class="text-center">
                            <a href="?search=<?= urlencode($search) ?>&sort=area_planted&order=<?= ($sort === 'area_planted' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Area (ha) <?= $sort === 'area_planted' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th class="text-center">
                            <a href="?search=<?= urlencode($search) ?>&sort=seed_quantity&order=<?= ($sort === 'seed_quantity' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Seed Qty <?= $sort === 'seed_quantity' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="?search=<?= urlencode($search) ?>&sort=expected_harvest_date&order=<?= ($sort === 'expected_harvest_date' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Exp. Harvest <?= $sort === 'expected_harvest_date' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th class="text-center">
                            <a href="?search=<?= urlencode($search) ?>&sort=status&order=<?= ($sort === 'status' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Status <?= $sort === 'status' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($plantings)): ?>
                        <?php foreach ($plantings as $row): ?>
                            <tr>
                                <td class="ps-4 fw-semibold text-muted">#<?= $row['planting_id'] ?? 1 ?></td>
                                <td>
                                    <div class="fw-medium"><?= htmlspecialchars($row['farm_name'] ?? 'Farm #' . ($row['farm_id'] ?? '')) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></div>
                                </td>
                                <td class="fw-medium"><?= htmlspecialchars(($row['crop_name'] ?? 'Crop #' . ($row['crop_id'] ?? '')) . (isset($row['variety']) && $row['variety'] ? ' (' . $row['variety'] . ')' : '')) ?></td>
                                <td><?= htmlspecialchars($row['planting_date'] ?? 'N/A') ?></td>
                                <td class="text-center"><?= htmlspecialchars($row['area_planted'] ?? 'N/A') ?></td>
                                <td class="text-center"><?= htmlspecialchars($row['seed_quantity'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['expected_harvest_date'] ?? 'N/A') ?></td>
                                <td class="text-center">
                                    <?php $st = $row['status'] ?? 'Growing'; ?>
                                    <span class="badge bg-<?= ($st === 'Harvested') ? 'success' : (($st === 'Failed') ? 'danger' : 'warning text-dark') ?>">
                                        <?= htmlspecialchars($st) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <button class="btn btn-sm btn-outline-primary px-3 rounded-pill" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editPlantingModal<?= $row['planting_id'] ?? 1 ?>">Edit</button>
                                        <button class="btn btn-sm btn-outline-danger px-3 rounded-pill" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deletePlantingModal<?= $row['planting_id'] ?? 1 ?>">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No registered planting records found.</td></tr>
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
                                <a class="page-link" href="?search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Render Modals OUTSIDE the table loop -->
<?php if (!empty($plantings)): ?>
    <?php foreach ($plantings as $row): ?>
        <?php $pId = $row['planting_id'] ?? 1; ?>

        <!-- Edit Modal -->
        <div class="modal fade" id="editPlantingModal<?= $pId ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content border-0 shadow">
                    <form method="POST">
                        <div class="modal-header" style="background-color: #fff0f3;">
                            <h5 class="modal-title fw-bold text-custom-title">Edit Planting Record</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-start">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="planting_id" value="<?= $pId ?>">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Farm</label>
                                <select name="farm_id" class="form-select" required>
                                    <option value="">Select Farm</option>
                                    <?php foreach ($farmsList as $f): ?>
                                        <option value="<?= $f['farm_id'] ?? '' ?>" <?= (isset($row['farm_id']) && $f['farm_id'] == $row['farm_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(($f['farm_name'] ?? 'Farm #' . $f['farm_id']) . (isset($f['first_name']) ? ' (' . $f['first_name'] . ' ' . $f['last_name'] . ')' : '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Crop</label>
                                <select name="crop_id" class="form-select" required>
                                    <option value="">Select Crop</option>
                                    <?php foreach ($cropsList as $c): ?>
                                        <option value="<?= $c['crop_id'] ?? '' ?>" <?= (isset($row['crop_id']) && $c['crop_id'] == $row['crop_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(($c['crop_name'] ?? '') . (isset($c['variety']) && $c['variety'] ? ' (' . $c['variety'] . ')' : '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Planting Date</label>
                                <input type="date" name="planting_date" class="form-control" value="<?= htmlspecialchars($row['planting_date'] ?? '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Area Planted (hectares)</label>
                                <input type="number" step="0.01" name="area_planted" class="form-control" value="<?= htmlspecialchars($row['area_planted'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Seed Quantity</label>
                                <input type="number" step="0.01" name="seed_quantity" class="form-control" value="<?= htmlspecialchars($row['seed_quantity'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Expected Harvest Date</label>
                                <input type="date" name="expected_harvest_date" class="form-control" value="<?= htmlspecialchars($row['expected_harvest_date'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="Growing" <?= (($row['status'] ?? '') === 'Growing') ? 'selected' : '' ?>>Growing</option>
                                    <option value="Harvested" <?= (($row['status'] ?? '') === 'Harvested') ? 'selected' : '' ?>>Harvested</option>
                                    <option value="Failed" <?= (($row['status'] ?? '') === 'Failed') ? 'selected' : '' ?>>Failed</option>
                                </select>
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
        <div class="modal fade" id="deletePlantingModal<?= $pId ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form method="POST">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title fw-bold">Confirm Delete</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-start py-4">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="planting_id" value="<?= $pId ?>">
                            <p class="mb-0">Are you sure you want to delete planting record <strong>#<?= $pId ?></strong>?</p>
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

<!-- Add Planting Modal -->
<div class="modal fade" id="addPlantingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <div class="modal-header" style="background-color: #fff0f3;">
                    <h5 class="modal-title fw-bold text-custom-title">Add New Planting Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Farm</label>
                        <select name="farm_id" class="form-select" required>
                            <option value="">Select Farm</option>
                            <?php foreach ($farmsList as $f): ?>
                                <option value="<?= $f['farm_id'] ?? '' ?>">
                                    <?= htmlspecialchars(($f['farm_name'] ?? 'Farm #' . $f['farm_id']) . (isset($f['first_name']) ? ' (' . $f['first_name'] . ' ' . $f['last_name'] . ')' : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Crop</label>
                        <select name="crop_id" class="form-select" required>
                            <option value="">Select Crop</option>
                            <?php foreach ($cropsList as $c): ?>
                                <option value="<?= $c['crop_id'] ?? '' ?>">
                                    <?= htmlspecialchars(($c['crop_name'] ?? '') . (isset($c['variety']) && $c['variety'] ? ' (' . $c['variety'] . ')' : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Planting Date</label>
                        <input type="date" name="planting_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Area Planted (hectares)</label>
                        <input type="number" step="0.01" name="area_planted" class="form-control" placeholder="e.g., 1.5">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Seed Quantity</label>
                        <input type="number" step="0.01" name="seed_quantity" class="form-control" placeholder="e.g., 25.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Expected Harvest Date</label>
                        <input type="date" name="expected_harvest_date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="Growing" selected>Growing</option>
                            <option value="Harvested">Harvested</option>
                            <option value="Failed">Failed</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-custom rounded-pill px-4">Add Planting</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/main.js"></script>
</body>
</html>