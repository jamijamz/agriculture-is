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
$farms = [];
$farmersList = [];
$irrigationList = [];

// Fetch farmers list for dropdowns
try {
    $farmersList = $db->query("SELECT farmer_id, first_name, last_name FROM farmer ORDER BY last_name ASC")->fetchAll();
} catch (PDOException $e) {
    $farmersList = [];
}

// Fetch unique irrigation types for the filter dropdown
try {
    $irrigationList = $db->query("SELECT DISTINCT irrigation_type FROM farm WHERE irrigation_type IS NOT NULL AND irrigation_type != '' ORDER BY irrigation_type ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $irrigationList = [];
}

// Handle Form Submissions (Add / Update / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $farmerId = trim($_POST['farmer_id'] ?? '');
        $farmName = trim($_POST['farm_name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $areaHectares = trim($_POST['area_hectares'] ?? '');
        $soilType = trim($_POST['soil_type'] ?? '');
        $irrigationType = trim($_POST['irrigation_type'] ?? '');

        if (empty($farmerId) || empty($farmName)) {
            $error = "Farmer and Farm Name are required.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO farm (farmer_id, farm_name, location, area_hectares, soil_type, irrigation_type) VALUES (:farmer_id, :farm_name, :location, :area_hectares, :soil_type, :irrigation_type)");
                $stmt->execute([
                    'farmer_id' => $farmerId,
                    'farm_name' => $farmName,
                    'location' => $location,
                    'area_hectares' => $areaHectares !== '' ? (float)$areaHectares : null,
                    'soil_type' => $soilType,
                    'irrigation_type' => $irrigationType
                ]);

                // Realign sequence counter
                $db->query("SELECT setval('farm_farm_id_seq', COALESCE((SELECT MAX(farm_id) FROM farm), 0) + 1, false);");

                $message = "Farm added successfully!";
            } catch (PDOException $e) {
                $error = "Error adding farm: " . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $farmId = $_POST['farm_id'] ?? '';
        $farmerId = trim($_POST['farmer_id'] ?? '');
        $farmName = trim($_POST['farm_name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $areaHectares = trim($_POST['area_hectares'] ?? '');
        $soilType = trim($_POST['soil_type'] ?? '');
        $irrigationType = trim($_POST['irrigation_type'] ?? '');

        if (empty($farmId) || empty($farmerId) || empty($farmName)) {
            $error = "Invalid data for update.";
        } else {
            try {
                $stmt = $db->prepare("UPDATE farm SET farmer_id = :farmer_id, farm_name = :farm_name, location = :location, area_hectares = :area_hectares, soil_type = :soil_type, irrigation_type = :irrigation_type WHERE farm_id = :farm_id");
                $stmt->execute([
                    'farm_id' => $farmId,
                    'farmer_id' => $farmerId,
                    'farm_name' => $farmName,
                    'location' => $location,
                    'area_hectares' => $areaHectares !== '' ? (float)$areaHectares : null,
                    'soil_type' => $soilType,
                    'irrigation_type' => $irrigationType
                ]);
                $message = "Farm updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating farm: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $farmId = $_POST['farm_id'] ?? '';
        if (!empty($farmId)) {
            try {
                $stmt = $db->prepare("DELETE FROM farm WHERE farm_id = :farm_id");
                $stmt->execute(['farm_id' => $farmId]);

                $db->query("SELECT setval('farm_farm_id_seq', COALESCE((SELECT MAX(farm_id) FROM farm), 0) + 1, false);");

                $message = "Farm deleted successfully!";
            } catch (PDOException $e) {
                $error = "Cannot delete farm because it has linked planting records.";
            }
        }
    }
}

// Search, Filter, Sort, and Pagination Parameters
$search = trim($_GET['search'] ?? '');
$irrigationFilter = trim($_GET['irrigation_filter'] ?? '');
$sort = $_GET['sort'] ?? 'farm_id';
$order = $_GET['order'] ?? 'ASC';

$allowedSorts = ['farm_id', 'farm_name', 'location', 'area_hectares', 'soil_type', 'irrigation_type'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'farm_id';
}
$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    $conditions = [];
    $params = [];

    if (!empty($search)) {
        $conditions[] = "(f.farm_name ILIKE :search OR f.location ILIKE :search OR f.soil_type ILIKE :search OR f.irrigation_type ILIKE :search OR fr.first_name ILIKE :search OR fr.last_name ILIKE :search)";
        $params['search'] = "%$search%";
    }

    if (!empty($irrigationFilter)) {
        $conditions[] = "f.irrigation_type = :irrigation_filter";
        $params['irrigation_filter'] = $irrigationFilter;
    }

    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // Count query with proper joins for accurate search and filter results
    $countQuery = "SELECT COUNT(*) FROM farm f LEFT JOIN farmer fr ON f.farmer_id = fr.farmer_id $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);
    if ($totalPages < 1) $totalPages = 1;

    // Fetch paginated records with farmer details
    $query = "SELECT f.*, fr.first_name, fr.last_name 
              FROM farm f 
              LEFT JOIN farmer fr ON f.farmer_id = fr.farmer_id 
              $whereClause 
              ORDER BY f.$sort $order 
              LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);

    foreach ($params as $key => $val) {
        $stmt->bindValue(":$key", $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $farms = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error fetching farms: " . $e->getMessage();
    $farms = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farms Management - AgriInfo</title>
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
                <li class="nav-item"><a class="nav-link active fw-semibold" href="farms.php">Farms</a></li>
                <li class="nav-item"><a class="nav-link" href="crops.php">Crops</a></li>
                <li class="nav-item"><a class="nav-link" href="plantings.php">Plantings</a></li>
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
        <h2 class="fw-bold text-custom-title mb-0">Farms Directory</h2>
        <button class="btn btn-custom px-4 shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#addFarmModal">+ Add New Farm</button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success shadow-sm"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Search & Irrigation Filter Bar -->
    <div class="card shadow-sm mb-4 p-3">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, location, soil..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="irrigation_filter" class="form-select">
                    <option value="">All Irrigation Types</option>
                    <?php foreach ($irrigationList as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>" <?= ($irrigationFilter === $type) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-custom px-3 w-100" type="submit">Filter</button>
                <?php if (!empty($search) || !empty($irrigationFilter)): ?>
                    <a href="farms.php" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
        <div class="mt-2 text-muted small px-1">
            Showing <?= count($farms) ?> of <?= $totalRecords ?> farms
        </div>
    </div>

    <!-- Farms Table Card -->
    <div class="card shadow-sm">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">
                            <a href="?search=<?= urlencode($search) ?>&irrigation_filter=<?= urlencode($irrigationFilter) ?>&sort=farm_id&order=<?= ($sort === 'farm_id' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                ID <?= $sort === 'farm_id' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>Farm Name</th>
                        <th>Owner (Farmer)</th>
                        <th>Location</th>
                        <th class="text-center">Area (ha)</th>
                        <th>Soil Type</th>
                        <th>Irrigation</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($farms)): ?>
                        <?php foreach ($farms as $row): ?>
                            <tr>
                                <td class="ps-4 fw-semibold text-muted">#<?= $row['farm_id'] ?></td>
                                <td class="fw-medium"><?= htmlspecialchars($row['farm_name']) ?></td>
                                <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                <td><?= htmlspecialchars($row['location'] ?? 'N/A') ?></td>
                                <td class="text-center"><?= htmlspecialchars($row['area_hectares'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['soil_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['irrigation_type'] ?? 'N/A') ?></td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <button class="btn btn-sm btn-outline-primary px-3 rounded-pill" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editFarmModal<?= $row['farm_id'] ?>">Edit</button>
                                        <button class="btn btn-sm btn-outline-danger px-3 rounded-pill" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteFarmModal<?= $row['farm_id'] ?>">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No registered farms found.</td></tr>
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
                                <a class="page-link" href="?search=<?= urlencode($search) ?>&irrigation_filter=<?= urlencode($irrigationFilter) ?>&sort=<?= $sort ?>&order=<?= $order ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modals placed OUTSIDE the table loop -->
<?php if (!empty($farms)): ?>
    <?php foreach ($farms as $row): ?>
        
        <!-- Edit Farm Modal -->
        <div class="modal fade" id="editFarmModal<?= $row['farm_id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content border-0 shadow">
                    <form method="POST">
                        <div class="modal-header" style="background-color: #fff0f3;">
                            <h5 class="modal-title fw-bold text-custom-title">Edit Farm</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-start">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="farm_id" value="<?= $row['farm_id'] ?>">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Farmer</label>
                                <select name="farmer_id" class="form-select" required>
                                    <?php foreach ($farmersList as $f): ?>
                                        <option value="<?= $f['farmer_id'] ?>" <?= ($f['farmer_id'] == $row['farmer_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Farm Name</label>
                                <input type="text" name="farm_name" class="form-control" value="<?= htmlspecialchars($row['farm_name']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Location</label>
                                <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($row['location'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Area (hectares)</label>
                                <input type="number" step="0.01" name="area_hectares" class="form-control" value="<?= htmlspecialchars($row['area_hectares'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Soil Type</label>
                                <input type="text" name="soil_type" class="form-control" value="<?= htmlspecialchars($row['soil_type'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Irrigation Type</label>
                                <input type="text" name="irrigation_type" class="form-control" value="<?= htmlspecialchars($row['irrigation_type'] ?? '') ?>">
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

        <!-- Delete Farm Modal -->
        <div class="modal fade" id="deleteFarmModal<?= $row['farm_id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form method="POST">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title fw-bold">Confirm Delete</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-start py-4">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="farm_id" value="<?= $row['farm_id'] ?>">
                            <p class="mb-0">Are you sure you want to delete farm <strong><?= htmlspecialchars($row['farm_name']) ?></strong>?</p>
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

<!-- Add Farm Modal -->
<div class="modal fade" id="addFarmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <div class="modal-header" style="background-color: #fff0f3;">
                    <h5 class="modal-title fw-bold text-custom-title">Add New Farm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Farmer</label>
                        <select name="farmer_id" class="form-select" required>
                            <option value="">Select Farmer</option>
                            <?php foreach ($farmersList as $f): ?>
                                <option value="<?= $f['farmer_id'] ?>">
                                    <?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Farm Name</label>
                        <input type="text" name="farm_name" class="form-control" placeholder="e.g., Dela Cruz Farm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Location</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g., Cebu City">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Area (hectares)</label>
                        <input type="number" step="0.01" name="area_hectares" class="form-control" placeholder="e.g., 2.50">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Soil Type</label>
                        <input type="text" name="soil_type" class="form-control" placeholder="e.g., Loam">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Irrigation Type</label>
                        <input type="text" name="irrigation_type" class="form-control" placeholder="e.g., Rainfed">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-custom rounded-pill px-4">Add Farm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/main.js"></script>
</body>
</html>
